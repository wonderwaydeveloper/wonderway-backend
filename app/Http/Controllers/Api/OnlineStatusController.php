<?php

namespace App\Http\Controllers\Api;

use App\Events\UserOnlineStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class OnlineStatusController extends Controller
{
    public function updateStatus(Request $request)
    {
        $request->validate([
            'status' => 'required|in:online,offline,away',
        ]);

        $user = $request->user();

        // Update user's status
        $isOnline = $request->status === 'online';

        $user->update([
            'last_seen_at' => now(),
            'is_online' => $isOnline,
        ]);

        // Broadcast status change
        broadcast(new UserOnlineStatus($user->id, $request->status));

        return response()->json(['status' => 'updated']);
    }

    public function getOnlineUsers()
    {
        $onlineUsers = \App\Models\User::where('is_online', true)
            ->where('last_seen_at', '>', now()->subMinutes(5))
            ->select('id', 'name', 'username', 'avatar')
            ->get();

        return response()->json($onlineUsers);
    }
}
