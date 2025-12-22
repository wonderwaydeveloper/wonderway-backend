<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Models\Post;
use App\Services\PostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function __construct(
        private PostService $postService
    ) {
    }

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
    public function index(): JsonResponse
    {
        $posts = $this->postService->getPublicPosts(request('page', 1));

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
    public function store(StorePostRequest $request): JsonResponse
    {
        try {
            $post = $this->postService->createPost(
                $request->validated(),
                $request->user(),
                $request->file('image')
            );

            return response()->json($post, 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error' => 'POST_CREATION_FAILED',
            ], 422);
        }
    }

    public function show(Post $post): JsonResponse
    {
        $post = $this->postService->getPostWithRelations($post);

        return response()->json($post);
    }

    public function destroy(Post $post): JsonResponse
    {
        $this->authorize('delete', $post);

        $this->postService->deletePost($post);

        return response()->json(['message' => 'Post deleted successfully']);
    }

    public function like(Post $post): JsonResponse
    {
        $result = $this->postService->toggleLike($post, auth()->user());

        return response()->json($result);
    }

    public function timeline(): JsonResponse
    {
        $timeline = $this->postService->getUserTimeline(auth()->user());

        return response()->json($timeline);
    }

    public function drafts(): JsonResponse
    {
        $drafts = $this->postService->getUserDrafts(auth()->user());

        return response()->json($drafts);
    }

    public function publish(Post $post): JsonResponse
    {
        $this->authorize('delete', $post);

        $post = $this->postService->publishPost($post);

        return response()->json(['message' => 'پست منتشر شد', 'post' => $post]);
    }

    public function quote(Request $request, Post $post): JsonResponse
    {
        $request->validate(['content' => 'required|string|max:280']);

        $quotePost = $this->postService->createQuotePost(
            $request->only('content'),
            $request->user(),
            $post
        );

        return response()->json($quotePost, 201);
    }

    public function quotes(Post $post): JsonResponse
    {
        $quotes = $this->postService->getPostQuotes($post);

        return response()->json($quotes);
    }

    public function update(UpdatePostRequest $request, Post $post): JsonResponse
    {
        try {
            $post = $this->postService->updatePost($post, $request->validated());

            return response()->json([
                'message' => 'Post updated successfully',
                'post' => $post,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function editHistory(Post $post): JsonResponse
    {
        $this->authorize('view', $post);

        $history = $this->postService->getEditHistory($post);

        return response()->json($history);
    }
}
