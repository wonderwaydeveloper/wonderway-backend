<?php

namespace App\Http\Controllers\Api;

use App\Events\CommentCreated;
use App\Events\PostInteraction;
use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function index(Post $post)
    {
        $comments = $post->comments()
            ->with('user:id,name,username,avatar')
            ->withCount('likes')
            ->latest()
            ->paginate(20);

        return response()->json($comments);
    }

    public function store(Request $request, Post $post)
    {
        $request->validate([
            'content' => 'required|string|max:280',
        ]);

        $comment = $post->comments()->create([
            'user_id' => $request->user()->id,
            'content' => $request->content,
        ]);

        // Process mentions in comment
        $mentionedUsers = $comment->processMentions($comment->content);
        
        // Fire comment created event
        event(new CommentCreated($comment, $request->user()));

        // Broadcast real-time interaction
        broadcast(new PostInteraction($post, 'comment', $request->user(), [
            'comment' => [
                'id' => $comment->id,
                'content' => $comment->content,
                'user' => $comment->user->only(['id', 'name', 'username', 'avatar'])
            ]
        ]));

        $post->increment('comments_count');
        $comment->load('user:id,name,username,avatar');

        return response()->json($comment, 201);
    }

    public function destroy(Comment $comment)
    {
        $this->authorize('delete', $comment);

        if ($comment->post->comments_count > 0) {
            $comment->post->decrement('comments_count');
        }
        $comment->delete();

        return response()->json(['message' => 'Comment deleted successfully']);
    }

    public function like(Comment $comment)
    {
        $user = auth()->user();

        if ($comment->isLikedBy($user->id)) {
            $comment->likes()->where('user_id', $user->id)->delete();
            if ($comment->likes_count > 0) {
                $comment->decrement('likes_count');
            }
            $liked = false;
        } else {
            $comment->likes()->create(['user_id' => $user->id]);
            $comment->increment('likes_count');
            $liked = true;
        }

        return response()->json(['liked' => $liked, 'likes_count' => $comment->likes_count]);
    }
}
