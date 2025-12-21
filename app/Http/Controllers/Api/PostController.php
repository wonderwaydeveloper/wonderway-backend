<?php

namespace App\Http\Controllers\Api;

use App\Events\PostInteraction;
use App\Events\PostPublished;
use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PostController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/posts",
     *     summary="Get public posts",
     *     tags={"Posts"},
     *     @OA\Response(
     *         response=200,
     *         description="List of public posts"
     *     )
     * )
     */
    public function index()
    {
        $cacheKey = 'posts:public:page:' . request('page', 1);
        
        $posts = cache()->remember($cacheKey, 600, function () {
            return Post::published()
                ->with([
                    'user:id,name,username,avatar',
                    'hashtags:id,name,slug',
                    'poll.options',
                    'quotedPost.user:id,name,username,avatar',
                    'threadPosts.user:id,name,username,avatar'
                ])
                ->withCount('likes', 'comments', 'quotes')
                ->whereNull('thread_id') // Only show main posts, not thread replies
                ->latest('published_at')
                ->paginate(config('pagination.posts', 20));
        });

        return response()->json($posts);
    }

    /**
     * @OA\Post(
     *     path="/api/posts",
     *     summary="Create a new post",
     *     tags={"Posts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"content"},
     *             @OA\Property(property="content", type="string", maxLength=280, example="سلام! این پست جدید من است #wonderway @testuser"),
     *             @OA\Property(property="image", type="string", format="binary"),
     *             @OA\Property(property="gif_url", type="string", format="url"),
     *             @OA\Property(property="reply_settings", type="string", enum={"everyone","following","mentioned","none"}),
     *             @OA\Property(property="is_draft", type="boolean")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Post created successfully"
     *     )
     * )
     */

    public function store(Request $request)
    {
        $request->validate([
            'content' => 'required|string|max:280',
            'image' => 'nullable|image|mimes:jpeg,jpg,png,gif,webp|max:2048',
            'gif_url' => 'nullable|url',
            'reply_settings' => 'nullable|in:everyone,following,mentioned,none',
            'quoted_post_id' => 'nullable|exists:posts,id',
        ]);

        $data = [
            'user_id' => $request->user()->id,
            'content' => $request->content,
            'reply_settings' => $request->input('reply_settings', 'everyone'),
            'quoted_post_id' => $request->quoted_post_id,
        ];

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('posts', 'public');
        }

        if ($request->gif_url) {
            $data['gif_url'] = $request->gif_url;
        }

        $isDraft = $request->boolean('is_draft', false);
        $data['is_draft'] = $isDraft;
        $data['published_at'] = $isDraft ? null : now();

        $post = Post::create($data);
        
        // Always sync hashtags and mentions immediately
        $post->syncHashtags();
        $mentionedUsers = $post->processMentions($post->content);
        
        // Send mention notifications
        foreach ($mentionedUsers as $mentionedUser) {
            $mentionedUser->notify(new \App\Notifications\MentionNotification(auth()->user(), $post));
        }
        
        // Spam detection for published posts
        if (!$isDraft) {
            $spamDetection = app(\App\Services\SpamDetectionService::class);
            $spamResult = $spamDetection->checkPost($post);
            
            if ($spamResult['is_spam']) {
                // Delete the post if it's spam
                $post->delete();
                
                // Determine error type based on reasons
                $errorType = 'SPAM_DETECTED';
                if (in_array('Too many links detected (3 links)', $spamResult['reasons'])) {
                    $errorType = 'TOO_MANY_LINKS';
                }
                
                return response()->json([
                    'message' => 'پست شما به دلیل مشکوک بودن تأیید نشد',
                    'error' => $errorType
                ], 422);
            }
        }
        
        // Process post asynchronously (skip in testing)
        if (!$isDraft && !app()->environment('testing')) {
            dispatch(new \App\Jobs\ProcessPostJob($post))->onQueue('high');
        }
        
        // Broadcast new post if published
        if (!$isDraft) {
            broadcast(new PostPublished($post->load('user:id,name,username,avatar')));
        }
        
        $post->load('user:id,name,username,avatar', 'hashtags');

        return response()->json($post, 201);
    }

    public function show(Post $post)
    {
        $post->load([
            'user:id,name,username,avatar',
            'comments.user:id,name,username,avatar',
            'hashtags',
            'quotedPost.user:id,name,username,avatar',
            'threadPosts.user:id,name,username,avatar'
        ])->loadCount('likes', 'comments', 'quotes');

        // If this is a thread post, also load thread info
        if ($post->isThread()) {
            $post->thread_info = [
                'is_thread' => true,
                'is_main_thread' => $post->isMainThread(),
                'thread_root_id' => $post->getThreadRoot()->id,
                'total_posts' => $post->isMainThread() ? $post->threadPosts()->count() + 1 : $post->getThreadRoot()->threadPosts()->count() + 1
            ];
        }

        return response()->json($post);
    }

    public function destroy(Post $post)
    {
        $this->authorize('delete', $post);

        if ($post->image) {
            Storage::disk('public')->delete($post->image);
        }

        $post->delete();

        return response()->json(['message' => 'Post deleted successfully']);
    }

    public function like(Post $post)
    {
        $user = auth()->user();

        if ($post->isLikedBy($user->id)) {
            $post->likes()->where('user_id', $user->id)->delete();
            if ($post->likes_count > 0) {
                $post->decrement('likes_count');
            }
            $liked = false;
        } else {
            $post->likes()->create(['user_id' => $user->id]);
            $post->increment('likes_count');
            $liked = true;

            event(new \App\Events\PostLiked($post, $user));
        }

        // Broadcast real-time interaction
        broadcast(new PostInteraction($post, 'like', $user, ['liked' => $liked]));

        return response()->json(['liked' => $liked, 'likes_count' => $post->likes_count]);
    }

    public function timeline()
    {
        $user = auth()->user();
        
        // Use optimized database service
        $dbService = app(\App\Services\DatabaseOptimizationService::class);
        $posts = $dbService->optimizeTimeline($user->id, 20);

        return response()->json([
            'data' => $posts,
            'optimized' => true
        ]);
    }

    public function drafts()
    {
        $drafts = auth()->user()
            ->posts()
            ->drafts()
            ->latest()
            ->paginate(20);

        return response()->json($drafts);
    }

    public function publish(Post $post)
    {
        $this->authorize('delete', $post);

        $post->update([
            'is_draft' => false,
            'published_at' => now(),
        ]);

        return response()->json(['message' => 'پست منتشر شد', 'post' => $post]);
    }

    public function quote(Request $request, Post $post)
    {
        $request->validate([
            'content' => 'required|string|max:280',
        ]);

        $quotePost = Post::create([
            'user_id' => $request->user()->id,
            'content' => $request->content,
            'quoted_post_id' => $post->id,
            'is_draft' => false,
            'published_at' => now(),
        ]);

        $quotePost->syncHashtags();
        $mentionedUsers = $quotePost->processMentions($quotePost->content);
        
        foreach ($mentionedUsers as $mentionedUser) {
            $mentionedUser->notify(new \App\Notifications\MentionNotification(auth()->user(), $quotePost));
        }

        broadcast(new PostPublished($quotePost->load('user:id,name,username,avatar', 'quotedPost.user:id,name,username,avatar')));

        $quotePost->load('user:id,name,username,avatar', 'quotedPost.user:id,name,username,avatar', 'hashtags');

        return response()->json($quotePost, 201);
    }

    public function quotes(Post $post)
    {
        $quotes = $post->quotes()
            ->with('user:id,name,username,avatar')
            ->withCount('likes', 'comments')
            ->latest()
            ->paginate(20);

        return response()->json($quotes);
    }
}
