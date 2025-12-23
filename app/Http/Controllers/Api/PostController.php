<?php

namespace App\Http\Controllers\Api;

use App\Actions\Post\{UpdatePostAction, DeletePostAction, LikePostAction};
use App\DTOs\{PostDTO, QuotePostDTO};
use App\Http\Controllers\Controller;
use App\Http\Requests\{StorePostRequest, UpdatePostRequest};
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Services\PostService;
use Illuminate\Http\{JsonResponse, Request};

class PostController extends Controller
{
    public function __construct(
        private PostService $postService,
        private ?UpdatePostAction $updateAction = null,
        private ?DeletePostAction $deleteAction = null,
        private ?LikePostAction $likeAction = null
    ) {}

    public function index(): JsonResponse
    {
        try {
            $posts = $this->postService->getPublicPosts(request('page', 1));
            return response()->json([
                'data' => PostResource::collection($posts->items()),
                'meta' => ['current_page' => $posts->currentPage(), 'total' => $posts->total()]
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch posts'], 500);
        }
    }

    public function store(StorePostRequest $request): JsonResponse
    {
        try {
            $dto = PostDTO::fromRequest($request->validated(), $request->user()->id);
            $post = $this->postService->createPost($dto, $request->file('image'), $request->file('video'));
            return response()->json(new PostResource($post), 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create post'], 500);
        }
    }

    public function show(Post $post): JsonResponse
    {
        try {
            return response()->json(new PostResource($post->load(['user', 'likes', 'comments'])));
        } catch (\Exception $e) {
            return response()->json(['error' => 'Post not found'], 404);
        }
    }

    public function update(UpdatePostRequest $request, Post $post): JsonResponse
    {
        $this->authorize('update', $post);
        if ($this->updateAction) {
            $updatedPost = $this->updateAction->execute($post, $request->validated());
        } else {
            $updatedPost = $this->postService->updatePost($post, $request->validated());
        }
        return response()->json(new PostResource($updatedPost));
    }

    public function destroy(Post $post): JsonResponse
    {
        $this->authorize('delete', $post);
        if ($this->deleteAction) {
            $this->deleteAction->execute($post);
        } else {
            $this->postService->deletePost($post);
        }
        return response()->json(['message' => 'Post deleted successfully']);
    }

    public function like(Post $post): JsonResponse
    {
        if ($this->likeAction) {
            $result = $this->likeAction->execute($post, auth()->user());
        } else {
            $result = $this->postService->toggleLike($post, auth()->user());
        }
        return response()->json($result);
    }

    public function timeline(): JsonResponse
    {
        $timelineData = $this->postService->getUserTimeline(auth()->user());
        return response()->json([
            'data' => PostResource::collection($timelineData['data']),
            'cached' => $timelineData['cached'] ?? false,
            'optimized' => $timelineData['optimized'] ?? false
        ]);
    }

    public function drafts(): JsonResponse
    {
        $drafts = $this->postService->getUserDrafts(auth()->user());
        return response()->json(['data' => PostResource::collection($drafts)]);
    }

    /**
     * Get post edit history
     */
    public function editHistory(Post $post): JsonResponse
    {
        $this->authorize('view', $post);
        
        $history = $this->postService->getEditHistory($post);
        
        return response()->json($history);
    }

    /**
     * Create quote post
     */
    public function quote(Request $request, Post $post): JsonResponse
    {
        $request->validate([
            'content' => 'required|string|max:280'
        ]);
        
        $quoteDTO = QuotePostDTO::fromRequest(
            $request->only(['content']),
            auth()->user()->id,
            $post->id
        );
        
        $quotePost = $this->postService->createQuotePost($quoteDTO);
        
        return response()->json(new PostResource($quotePost), 201);
    }

    /**
     * Get quotes of a post
     */
    public function quotes(Post $post): JsonResponse
    {
        $quotes = $this->postService->getPostQuotes($post);
        return response()->json(['data' => PostResource::collection($quotes)]);
    }
}
