<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Request;

class TimelineController extends Controller
{
    public function liveTimeline(Request $request)
    {
        $user = $request->user();
        
        // Get following IDs for timeline
        $followingIds = $user->following()->pluck('users.id')->toArray();
        $followingIds[] = $user->id;

        $posts = Post::published()
            ->with([
                'user:id,name,username,avatar',
                'hashtags:id,name,slug',
                'poll.options',
                'likes' => function($query) use ($user) {
                    $query->where('user_id', $user->id)->select('id', 'likeable_id', 'user_id');
                }
            ])
            ->withCount('likes', 'comments')
            ->whereIn('user_id', $followingIds)
            ->where('created_at', '>', now()->subHours(24)) // Last 24 hours
            ->latest('created_at')
            ->limit(50)
            ->get();

        return response()->json([
            'posts' => $posts,
            'following_ids' => $followingIds,
            'channels' => [
                'timeline' => 'timeline',
                'user_timeline' => 'user.timeline.' . $user->id,
            ]
        ]);
    }

    public function getPostUpdates(Request $request, Post $post)
    {
        $user = $request->user();
        
        $post->load([
            'user:id,name,username,avatar',
            'likes' => function($query) use ($user) {
                $query->where('user_id', $user->id)->select('id', 'likeable_id', 'user_id');
            }
        ])->loadCount('likes', 'comments');

        return response()->json([
            'post' => $post,
            'is_liked' => $post->isLikedBy($user->id),
            'channel' => 'post.' . $post->id,
        ]);
    }
}