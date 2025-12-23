<?php

namespace App\Repositories\Cache;

use App\Contracts\Repositories\PostRepositoryInterface;
use App\Models\Post;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class CachedPostRepository implements PostRepositoryInterface
{
    private const CACHE_TTL = 1800; // 30 minutes

    public function __construct(
        private PostRepositoryInterface $repository
    ) {}

    public function findById(int $id): ?Post
    {
        return Cache::remember(
            "post.{$id}",
            self::CACHE_TTL,
            fn() => $this->repository->findById($id)
        );
    }

    public function create(array $data): Post
    {
        $post = $this->repository->create($data);
        $this->clearTimelineCache($data['user_id'] ?? 0);
        return $post;
    }

    public function findWithRelations(int $id, array $relations = []): ?Post
    {
        return $this->repository->findWithRelations($id, $relations);
    }

    public function update(Post $post, array $data): Post
    {
        $result = $this->repository->update($post, $data);
        $this->clearPostCache($post->id);
        return $result;
    }

    public function delete(Post $post): bool
    {
        $result = $this->repository->delete($post);
        if ($result) {
            $this->clearPostCache($post->id);
        }
        return $result;
    }

    public function getPublicPosts(int $page = 1, int $perPage = 20): LengthAwarePaginator
    {
        return Cache::remember(
            "posts.public.{$page}.{$perPage}",
            self::CACHE_TTL,
            fn() => $this->repository->getPublicPosts($page, $perPage)
        );
    }

    public function getTimelinePosts(int $userId, int $limit = 20): Collection
    {
        return Cache::remember(
            "timeline.{$userId}.{$limit}",
            600,
            fn() => $this->repository->getTimelinePosts($userId, $limit)
        );
    }

    public function getUserDrafts(int $userId): LengthAwarePaginator
    {
        return $this->repository->getUserDrafts($userId);
    }

    public function getPostQuotes(int $postId): LengthAwarePaginator
    {
        return $this->repository->getPostQuotes($postId);
    }

    public function getUserPosts(int $userId, int $limit = 20): Collection
    {
        return $this->repository->getUserPosts($userId, $limit);
    }

    public function searchPosts(string $query, int $limit = 20): Collection
    {
        return $this->repository->searchPosts($query, $limit);
    }

    private function clearPostCache(int $postId): void
    {
        Cache::forget("post.{$postId}");
        Cache::forget('posts.trending');
    }

    private function clearTimelineCache(int $userId): void
    {
        Cache::forget("timeline.{$userId}.*");
        Cache::forget("posts.public.*");
    }
}