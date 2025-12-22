<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Moment;
use App\Models\Post;
use Illuminate\Http\Request;

class MomentController extends Controller
{
    public function index(Request $request)
    {
        $moments = Moment::public()
            ->with(['user:id,name,username,avatar'])
            ->withCount('posts')
            ->when($request->featured, fn ($q) => $q->featured())
            ->latest()
            ->paginate(20);

        return response()->json($moments);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'privacy' => 'required|in:public,private',
            'cover_image' => 'nullable|image|max:2048',
            'post_ids' => 'required|array|min:2|max:20',
            'post_ids.*' => 'exists:posts,id',
        ]);

        $moment = Moment::create([
            'user_id' => $request->user()->id,
            'title' => $request->title,
            'description' => $request->description,
            'privacy' => $request->privacy,
            'cover_image' => $request->hasFile('cover_image')
                ? $request->file('cover_image')->store('moments', 'public')
                : null,
        ]);

        // Add posts to moment
        foreach ($request->post_ids as $index => $postId) {
            $moment->addPost($postId, $index);
        }

        $moment->load('user:id,name,username,avatar', 'posts.user:id,name,username,avatar');

        return response()->json($moment, 201);
    }

    public function show(Moment $moment)
    {
        if ($moment->privacy === 'private' && $moment->user_id !== auth()->id()) {
            return response()->json(['message' => 'Moment not found'], 404);
        }

        $moment->load([
            'user:id,name,username,avatar',
            'posts.user:id,name,username,avatar',
            'posts.hashtags:id,name,slug',
        ])->loadCount('posts');

        $moment->incrementViews();

        return response()->json($moment);
    }

    public function update(Request $request, Moment $moment)
    {
        $this->authorize('update', $moment);

        $request->validate([
            'title' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'privacy' => 'required|in:public,private',
        ]);

        $moment->update($request->only(['title', 'description', 'privacy']));

        return response()->json($moment);
    }

    public function destroy(Moment $moment)
    {
        $this->authorize('delete', $moment);

        $moment->delete();

        return response()->json(['message' => 'Moment deleted successfully']);
    }

    public function addPost(Request $request, Moment $moment)
    {
        $this->authorize('update', $moment);

        $request->validate([
            'post_id' => 'required|exists:posts,id',
            'position' => 'nullable|integer|min:0',
        ]);

        if ($moment->posts()->where('post_id', $request->post_id)->exists()) {
            return response()->json(['message' => 'Post already in moment'], 409);
        }

        $moment->addPost($request->post_id, $request->position);

        return response()->json(['message' => 'Post added to moment']);
    }

    public function removePost(Request $request, Moment $moment, Post $post)
    {
        $this->authorize('update', $moment);

        if (! $moment->posts()->where('post_id', $post->id)->exists()) {
            return response()->json(['message' => 'Post not in moment'], 404);
        }

        $moment->removePost($post->id);

        return response()->json(['message' => 'Post removed from moment']);
    }

    public function myMoments(Request $request)
    {
        $moments = $request->user()
            ->moments()
            ->withCount('posts')
            ->latest()
            ->paginate(20);

        return response()->json($moments);
    }

    public function featured()
    {
        $moments = Moment::public()
            ->featured()
            ->with(['user:id,name,username,avatar'])
            ->withCount('posts')
            ->latest()
            ->limit(10)
            ->get();

        return response()->json(['data' => $moments]);
    }
}
