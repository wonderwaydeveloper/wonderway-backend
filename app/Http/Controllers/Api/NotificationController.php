<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = $request->user()
            ->notifications()
            ->with('fromUser:id,name,username,avatar')
            ->latest()
            ->paginate(20);

        return response()->json($notifications);
    }

    public function unread(Request $request)
    {
        $notifications = $request->user()
            ->notifications()
            ->unread()
            ->with('fromUser:id,name,username,avatar')
            ->latest()
            ->paginate(20);

        return response()->json($notifications);
    }

    public function markAsRead(Notification $notification)
    {
        if ($notification->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $notification->markAsRead();

        return response()->json(['message' => 'علامت خوانده شد']);
    }

    public function markAllAsRead(Request $request)
    {
        $request->user()
            ->notifications()
            ->unread()
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'همه علامت خوانده شد']);
    }

    public function unreadCount(Request $request)
    {
        $count = $request->user()
            ->notifications()
            ->unread()
            ->count();

        return response()->json(['count' => $count]);
    }
}
