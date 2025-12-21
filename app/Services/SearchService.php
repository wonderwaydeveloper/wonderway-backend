<?php

namespace App\Services;

use MeiliSearch\Client;
use Illuminate\Support\Facades\Log;
use App\Models\Post;
use App\Models\User;
use App\Models\Hashtag;

class SearchService
{
    private $client;

    public function __construct()
    {
        $this->client = new Client(
            config('scout.meilisearch.host'),
            config('scout.meilisearch.key')
        );
    }

    public function searchPosts($query, $page = 1, $perPage = 20, $filters = [])
    {
        try {
            $searchParams = [
                'limit' => $perPage,
                'offset' => ($page - 1) * $perPage,
                'attributesToHighlight' => ['content'],
            ];

            // Advanced filters
            $filterStrings = [];
            
            if (!empty($filters['user_id'])) {
                $filterStrings[] = "user_id = {$filters['user_id']}";
            }
            
            if (!empty($filters['has_media'])) {
                $filterStrings[] = $filters['has_media'] ? "has_media = true" : "has_media = false";
            }
            
            if (!empty($filters['date_from'])) {
                $timestamp = strtotime($filters['date_from']);
                $filterStrings[] = "created_at >= {$timestamp}";
            }
            
            if (!empty($filters['date_to'])) {
                $timestamp = strtotime($filters['date_to'] . ' 23:59:59');
                $filterStrings[] = "created_at <= {$timestamp}";
            }
            
            if (!empty($filters['min_likes'])) {
                $filterStrings[] = "likes_count >= {$filters['min_likes']}";
            }
            
            if (!empty($filters['hashtags'])) {
                $hashtags = is_array($filters['hashtags']) ? $filters['hashtags'] : [$filters['hashtags']];
                $hashtagFilters = array_map(function($tag) {
                    return "hashtags = '{$tag}'";
                }, $hashtags);
                $filterStrings[] = '(' . implode(' OR ', $hashtagFilters) . ')';
            }

            if (!empty($filterStrings)) {
                $searchParams['filter'] = implode(' AND ', $filterStrings);
            }

            // Sorting
            if (!empty($filters['sort'])) {
                switch ($filters['sort']) {
                    case 'latest':
                        $searchParams['sort'] = ['created_at:desc'];
                        break;
                    case 'oldest':
                        $searchParams['sort'] = ['created_at:asc'];
                        break;
                    case 'popular':
                        $searchParams['sort'] = ['likes_count:desc'];
                        break;
                    case 'relevance':
                    default:
                        // Default MeiliSearch relevance
                        break;
                }
            }

            $results = $this->client->index('posts')->search($query, $searchParams);

            return [
                'data' => $results['hits'],
                'total' => $results['estimatedTotalHits'],
                'page' => $page,
                'filters_applied' => $filters
            ];
        } catch (\Exception $e) {
            Log::error('Post search failed', ['error' => $e->getMessage(), 'filters' => $filters]);
            return ['data' => [], 'total' => 0, 'error' => 'Search failed'];
        }
    }

    public function searchUsers($query, $page = 1, $perPage = 20, $filters = [])
    {
        try {
            $searchParams = [
                'limit' => $perPage,
                'offset' => ($page - 1) * $perPage,
                'attributesToHighlight' => ['name', 'username'],
            ];

            // Advanced filters for users
            $filterStrings = [];
            
            if (!empty($filters['verified'])) {
                $filterStrings[] = "is_verified = true";
            }
            
            if (!empty($filters['min_followers'])) {
                $filterStrings[] = "followers_count >= {$filters['min_followers']}";
            }
            
            if (!empty($filters['location'])) {
                $filterStrings[] = "location = '{$filters['location']}'";
            }

            if (!empty($filterStrings)) {
                $searchParams['filter'] = implode(' AND ', $filterStrings);
            }

            // Sorting for users
            if (!empty($filters['sort'])) {
                switch ($filters['sort']) {
                    case 'followers':
                        $searchParams['sort'] = ['followers_count:desc'];
                        break;
                    case 'newest':
                        $searchParams['sort'] = ['created_at:desc'];
                        break;
                    case 'relevance':
                    default:
                        break;
                }
            }

            $results = $this->client->index('users')->search($query, $searchParams);

            return [
                'data' => $results['hits'],
                'total' => $results['estimatedTotalHits'],
                'page' => $page,
                'filters_applied' => $filters
            ];
        } catch (\Exception $e) {
            Log::error('User search failed', ['error' => $e->getMessage(), 'filters' => $filters]);
            return ['data' => [], 'total' => 0, 'error' => 'Search failed'];
        }
    }

    public function searchHashtags($query, $page = 1, $perPage = 20, $filters = [])
    {
        try {
            $searchParams = [
                'limit' => $perPage,
                'offset' => ($page - 1) * $perPage,
            ];

            // Filters for hashtags
            $filterStrings = [];
            
            if (!empty($filters['min_posts'])) {
                $filterStrings[] = "posts_count >= {$filters['min_posts']}";
            }

            if (!empty($filterStrings)) {
                $searchParams['filter'] = implode(' AND ', $filterStrings);
            }

            // Sorting for hashtags
            if (!empty($filters['sort'])) {
                switch ($filters['sort']) {
                    case 'popular':
                        $searchParams['sort'] = ['posts_count:desc'];
                        break;
                    case 'recent':
                        $searchParams['sort'] = ['updated_at:desc'];
                        break;
                    case 'relevance':
                    default:
                        break;
                }
            }

            $results = $this->client->index('hashtags')->search($query, $searchParams);

            return [
                'data' => $results['hits'],
                'total' => $results['estimatedTotalHits'],
                'page' => $page,
                'filters_applied' => $filters
            ];
        } catch (\Exception $e) {
            Log::error('Hashtag search failed', ['error' => $e->getMessage(), 'filters' => $filters]);
            return ['data' => [], 'total' => 0, 'error' => 'Search failed'];
        }
    }

    public function advancedSearch($query, $filters = [])
    {
        $results = [];
        
        if (empty($filters['type']) || $filters['type'] === 'posts') {
            $results['posts'] = $this->searchPosts($query, 1, 10, $filters);
        }
        
        if (empty($filters['type']) || $filters['type'] === 'users') {
            $results['users'] = $this->searchUsers($query, 1, 5, $filters);
        }
        
        if (empty($filters['type']) || $filters['type'] === 'hashtags') {
            $results['hashtags'] = $this->searchHashtags($query, 1, 5, $filters);
        }

        return $results;
    }

    public function getSuggestions($query, $type = 'all')
    {
        try {
            $suggestions = [];
            
            if ($type === 'all' || $type === 'users') {
                $userResults = $this->client->index('users')->search($query, [
                    'limit' => 5,
                    'attributesToRetrieve' => ['username', 'name']
                ]);
                $suggestions['users'] = $userResults['hits'];
            }
            
            if ($type === 'all' || $type === 'hashtags') {
                $hashtagResults = $this->client->index('hashtags')->search($query, [
                    'limit' => 5,
                    'attributesToRetrieve' => ['name', 'slug']
                ]);
                $suggestions['hashtags'] = $hashtagResults['hits'];
            }

            return $suggestions;
        } catch (\Exception $e) {
            Log::error('Suggestions failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function indexPost($post)
    {
        try {
            $this->client->index('posts')->addDocuments([
                [
                    'id' => $post->id,
                    'content' => $post->content,
                    'user_id' => $post->user_id,
                    'created_at' => $post->created_at->timestamp,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Post indexing failed', ['error' => $e->getMessage()]);
        }
    }

    public function indexUser($user)
    {
        try {
            $this->client->index('users')->addDocuments([
                [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'bio' => $user->bio,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('User indexing failed', ['error' => $e->getMessage()]);
        }
    }

    public function deletePost($postId)
    {
        try {
            $this->client->index('posts')->deleteDocument($postId);
        } catch (\Exception $e) {
            Log::error('Post deletion failed', ['error' => $e->getMessage()]);
        }
    }
}
