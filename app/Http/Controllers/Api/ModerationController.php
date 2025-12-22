<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ModerationController extends Controller
{
    public function reportContent(Request $request)
    {
        $request->validate([
            'reportable_type' => 'required|in:post,comment,user',
            'reportable_id' => 'required|integer',
            'reason' => 'required|in:spam,harassment,inappropriate,copyright,fake_news,violence,hate_speech,other',
            'description' => 'nullable|string|max:500',
        ]);

        try {
            // Check if user already reported this content
            $existingReport = DB::table('reports')
                ->where('reporter_id', auth()->id())
                ->where('reportable_type', $request->reportable_type)
                ->where('reportable_id', $request->reportable_id)
                ->first();

            if ($existingReport) {
                return response()->json(['message' => 'شما قبلاً این محتوا را گزارش کرده‌اید'], 400);
            }

            // Create report
            DB::table('reports')->insert([
                'reporter_id' => auth()->id(),
                'reportable_type' => $request->reportable_type,
                'reportable_id' => $request->reportable_id,
                'reason' => $request->reason,
                'description' => $request->description,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Auto-moderate based on report count
            $this->autoModerate($request->reportable_type, $request->reportable_id);

            return response()->json(['message' => 'گزارش شما ثبت شد و بررسی خواهد شد']);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'خطا در ثبت گزارش',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getReports(Request $request)
    {
        // This should be admin-only in production
        $request->validate([
            'status' => 'nullable|in:pending,reviewed,resolved,dismissed',
            'type' => 'nullable|in:post,comment,user',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = DB::table('reports')
            ->leftJoin('users as reporters', 'reports.reporter_id', '=', 'reporters.id')
            ->select([
                'reports.*',
                'reporters.name as reporter_name',
                'reporters.username as reporter_username',
            ])
            ->orderBy('reports.created_at', 'desc');

        if ($request->status) {
            $query->where('reports.status', $request->status);
        }

        if ($request->type) {
            $query->where('reports.reportable_type', $request->type);
        }

        $reports = $query->paginate($request->per_page ?? 20);

        // Add reportable content details
        foreach ($reports->items() as $report) {
            $report->reportable_content = $this->getReportableContent($report->reportable_type, $report->reportable_id);
        }

        return response()->json($reports);
    }

    public function updateReportStatus(Request $request, $reportId)
    {
        $request->validate([
            'status' => 'required|in:reviewed,resolved,dismissed',
            'admin_notes' => 'nullable|string|max:1000',
            'action_taken' => 'nullable|in:none,warning,content_removed,user_suspended,user_banned',
        ]);

        try {
            $report = DB::table('reports')->where('id', $reportId)->first();

            if (! $report) {
                return response()->json(['message' => 'گزارش یافت نشد'], 404);
            }

            // Update report status
            DB::table('reports')
                ->where('id', $reportId)
                ->update([
                    'status' => $request->status,
                    'admin_notes' => $request->admin_notes,
                    'action_taken' => $request->action_taken,
                    'reviewed_by' => auth()->id(),
                    'reviewed_at' => now(),
                    'updated_at' => now(),
                ]);

            // Take action if specified
            if ($request->action_taken && $request->action_taken !== 'none') {
                $this->takeAction($report, $request->action_taken);
            }

            return response()->json(['message' => 'وضعیت گزارش بروزرسانی شد']);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'خطا در بروزرسانی گزارش',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getContentStats()
    {
        try {
            $stats = [
                'reports' => [
                    'total' => DB::table('reports')->count(),
                    'pending' => DB::table('reports')->where('status', 'pending')->count(),
                    'reviewed' => DB::table('reports')->where('status', 'reviewed')->count(),
                    'resolved' => DB::table('reports')->where('status', 'resolved')->count(),
                ],
                'content' => [
                    'total_posts' => Post::count(),
                    'flagged_posts' => Post::where('is_flagged', true)->count(),
                    'total_users' => User::count(),
                    'suspended_users' => User::where('is_suspended', true)->count(),
                ],
            ];

            return response()->json($stats);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'خطا در دریافت آمار',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function autoModerate($type, $id)
    {
        $reportCount = DB::table('reports')
            ->where('reportable_type', $type)
            ->where('reportable_id', $id)
            ->where('status', 'pending')
            ->count();

        // Auto-flag content if it has 5+ reports
        if ($reportCount >= 5) {
            switch ($type) {
                case 'post':
                    Post::where('id', $id)->update(['is_flagged' => true]);

                    break;
                case 'user':
                    User::where('id', $id)->update(['is_flagged' => true]);

                    break;
            }
        }

        // Auto-hide content if it has 10+ reports
        if ($reportCount >= 10) {
            switch ($type) {
                case 'post':
                    Post::where('id', $id)->update(['is_hidden' => true]);

                    break;
            }
        }
    }

    private function getReportableContent($type, $id)
    {
        switch ($type) {
            case 'post':
                return Post::select('id', 'content', 'user_id', 'created_at')
                    ->with('user:id,name,username')
                    ->find($id);
            case 'comment':
                return Comment::select('id', 'content', 'user_id', 'post_id', 'created_at')
                    ->with('user:id,name,username')
                    ->find($id);
            case 'user':
                return User::select('id', 'name', 'username', 'email', 'created_at')
                    ->find($id);
            default:
                return null;
        }
    }

    private function takeAction($report, $action)
    {
        switch ($action) {
            case 'content_removed':
                if ($report->reportable_type === 'post') {
                    Post::where('id', $report->reportable_id)->update(['is_deleted' => true]);
                } elseif ($report->reportable_type === 'comment') {
                    Comment::where('id', $report->reportable_id)->delete();
                }

                break;

            case 'user_suspended':
                if ($report->reportable_type === 'user') {
                    User::where('id', $report->reportable_id)->update([
                        'is_suspended' => true,
                        'suspended_until' => now()->addDays(7),
                    ]);
                }

                break;

            case 'user_banned':
                if ($report->reportable_type === 'user') {
                    User::where('id', $report->reportable_id)->update([
                        'is_banned' => true,
                        'banned_at' => now(),
                    ]);
                }

                break;
        }
    }
}
