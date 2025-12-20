<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;

class FollowController extends Controller
{
    public function follow(User $user)
    {
        $currentUser = auth()->user();

        if ($currentUser->id === $user->id) {
            return response()->json(['message' => 'You cannot follow yourself'], 400);
        }

        if ($currentUser->isFollowing($user->id)) {
            $currentUser->following()->detach($user->id);
            $following = false;
        } else {
            if ($user->is_private) {
                return response()->json([
                    'message' => 'This account is private. Send a follow request.',
                    'requires_request' => true,
                ], 403);
            }

            $currentUser->following()->attach($user->id);
            $following = true;

            event(new \App\Events\UserFollowed($currentUser, $user));
        }

        return response()->json([
            'following' => $following,
            'followers_count' => $user->followers()->count(),
        ]);
    }

    public function followers(User $user)
    {
        $followers = $user->followers()
            ->select('users.id', 'users.name', 'users.username', 'users.avatar')
            ->paginate(20);

        return response()->json($followers);
    }

    public function following(User $user)
    {
        $following = $user->following()
            ->select('users.id', 'users.name', 'users.username', 'users.avatar')
            ->paginate(20);

        return response()->json($following);
    }
}
