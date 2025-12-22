<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SearchService;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    private $searchService;

    public function __construct(SearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    /**
     * @OA\Get(
     *     path="/api/search/posts",
     *     summary="Advanced post search",
     *     tags={"Search"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="q", in="query", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="user_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="has_media", in="query", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="date_from", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="date_to", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="min_likes", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="hashtags", in="query", @OA\Schema(type="array", @OA\Items(type="string"))),
     *     @OA\Parameter(name="sort", in="query", @OA\Schema(type="string", enum={"relevance", "latest", "oldest", "popular"})),
     *     @OA\Response(response=200, description="Search results")
     * )
     */
    public function posts(Request $request)
    {
        $request->validate([
            'q' => 'required|string|min:1|max:100',
            'page' => 'nullable|integer|min:1',
            'user_id' => 'nullable|integer|exists:users,id',
            'has_media' => 'nullable|boolean',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'min_likes' => 'nullable|integer|min:0',
            'hashtags' => 'nullable|array',
            'hashtags.*' => 'string|max:50',
            'sort' => 'nullable|in:relevance,latest,oldest,popular',
        ]);

        $filters = $request->only([
            'user_id', 'has_media', 'date_from', 'date_to',
            'min_likes', 'hashtags', 'sort',
        ]);

        $results = $this->searchService->searchPosts(
            $request->q,
            $request->page ?? 1,
            20,
            $filters
        );

        return response()->json($results);
    }

    /**
     * @OA\Get(
     *     path="/api/search/users",
     *     summary="Advanced user search",
     *     tags={"Search"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="q", in="query", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="verified", in="query", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="min_followers", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="location", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="sort", in="query", @OA\Schema(type="string", enum={"relevance", "followers", "newest"})),
     *     @OA\Response(response=200, description="User search results")
     * )
     */
    public function users(Request $request)
    {
        $request->validate([
            'q' => 'required|string|min:1|max:50',
            'page' => 'nullable|integer|min:1',
            'verified' => 'nullable|boolean',
            'min_followers' => 'nullable|integer|min:0',
            'location' => 'nullable|string|max:100',
            'sort' => 'nullable|in:relevance,followers,newest',
        ]);

        $filters = $request->only([
            'verified', 'min_followers', 'location', 'sort',
        ]);

        $results = $this->searchService->searchUsers(
            $request->q,
            $request->page ?? 1,
            20,
            $filters
        );

        return response()->json($results);
    }

    /**
     * @OA\Get(
     *     path="/api/search/hashtags",
     *     summary="Advanced hashtag search",
     *     tags={"Search"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="q", in="query", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="min_posts", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="sort", in="query", @OA\Schema(type="string", enum={"relevance", "popular", "recent"})),
     *     @OA\Response(response=200, description="Hashtag search results")
     * )
     */
    public function hashtags(Request $request)
    {
        $request->validate([
            'q' => 'required|string|min:1|max:50',
            'page' => 'nullable|integer|min:1',
            'min_posts' => 'nullable|integer|min:0',
            'sort' => 'nullable|in:relevance,popular,recent',
        ]);

        $filters = $request->only(['min_posts', 'sort']);

        $results = $this->searchService->searchHashtags(
            $request->q,
            $request->page ?? 1,
            20,
            $filters
        );

        return response()->json($results);
    }

    /**
     * @OA\Get(
     *     path="/api/search/all",
     *     summary="Search across all content types",
     *     tags={"Search"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="q", in="query", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Combined search results")
     * )
     */
    public function all(Request $request)
    {
        $request->validate([
            'q' => 'required|string|min:1|max:100',
        ]);

        $results = $this->searchService->advancedSearch($request->q);

        return response()->json($results);
    }

    /**
     * @OA\Get(
     *     path="/api/search/advanced",
     *     summary="Advanced search with all filters",
     *     tags={"Search"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="q", in="query", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="type", in="query", @OA\Schema(type="string", enum={"posts", "users", "hashtags"})),
     *     @OA\Response(response=200, description="Advanced search results")
     * )
     */
    public function advanced(Request $request)
    {
        $request->validate([
            'q' => 'required|string|min:1|max:100',
            'type' => 'nullable|in:posts,users,hashtags',
            // Post filters
            'user_id' => 'nullable|integer|exists:users,id',
            'has_media' => 'nullable|boolean',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'min_likes' => 'nullable|integer|min:0',
            'hashtags' => 'nullable|array',
            // User filters
            'verified' => 'nullable|boolean',
            'min_followers' => 'nullable|integer|min:0',
            'location' => 'nullable|string|max:100',
            // General
            'sort' => 'nullable|string|max:20',
        ]);

        $filters = $request->except(['q']);

        $results = $this->searchService->advancedSearch($request->q, $filters);

        return response()->json($results);
    }

    /**
     * @OA\Get(
     *     path="/api/search/suggestions",
     *     summary="Get search suggestions",
     *     tags={"Search"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="q", in="query", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="type", in="query", @OA\Schema(type="string", enum={"all", "users", "hashtags"})),
     *     @OA\Response(response=200, description="Search suggestions")
     * )
     */
    public function suggestions(Request $request)
    {
        $request->validate([
            'q' => 'required|string|min:1|max:50',
            'type' => 'nullable|in:all,users,hashtags',
        ]);

        $suggestions = $this->searchService->getSuggestions(
            $request->q,
            $request->type ?? 'all'
        );

        return response()->json($suggestions);
    }
}
