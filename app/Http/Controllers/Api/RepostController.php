<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Request;

class RepostController extends Controller
{
    public function repost(Request $request, Post $post)
    {
        $request->validate([
            'quote' => 'nullable|string|max:280',
        ]);

        $user = $request->user();
        $existing = $user->reposts()->where('post_id', $post->id)->first();

        if ($existing) {
            $existing->delete();

            return response()->json(['message' => 'Repost cancelled', 'reposted' => false]);
        }

        $repost = $user->reposts()->create([
            'post_id' => $post->id,
            'quote' => $request->quote,
        ]);

        $isQuote = ! empty($request->quote);
        event(new \App\Events\PostReposted($post, $user, $repost, $isQuote));

        return response()->json(['message' => 'Reposted successfully', 'reposted' => true, 'repost' => $repost], 201);
    }

    public function myReposts(Request $request)
    {
        $reposts = $request->user()
            ->reposts()
            ->with('post.user:id,name,username,avatar')
            ->latest()
            ->paginate(20);

        return response()->json($reposts);
    }
}
