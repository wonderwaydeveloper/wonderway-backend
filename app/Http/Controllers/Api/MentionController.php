<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Mention;
use App\Models\User;
use Illuminate\Http\Request;

class MentionController extends Controller
{
    /**
     * Search users for mentions
     */
    public function searchUsers(Request $request)
    {
        $query = $request->get('q', '');

        if (strlen($query) < 2) {
            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        }

        $users = User::where('username', 'LIKE', "%{$query}%")
            ->orWhere('name', 'LIKE', "%{$query}%")
            ->select('id', 'username', 'name', 'avatar')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    /**
     * Get user's mentions
     */
    public function getUserMentions(Request $request)
    {
        $mentions = auth()->user()->mentions()
            ->with(['mentionable' => function ($morphTo) {
                $morphTo->morphWith([
                    'App\Models\Post' => ['user:id,username,name,avatar'],
                    'App\Models\Comment' => ['user:id,username,name,avatar', 'post:id,content'],
                ]);
            }])
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $mentions,
        ]);
    }

    /**
     * Get mentions for a specific post or comment
     */
    public function getMentions(Request $request, $type, $id)
    {
        $model = $type === 'post' ? 'App\Models\Post' : 'App\Models\Comment';

        $mentions = Mention::where('mentionable_type', $model)
            ->where('mentionable_id', $id)
            ->with('user:id,username,name,avatar')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $mentions,
        ]);
    }
}
