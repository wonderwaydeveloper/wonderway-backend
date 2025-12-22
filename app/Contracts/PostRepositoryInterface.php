<?php

namespace App\Contracts;

use App\Models\Post;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface PostRepositoryInterface
{
    public function create(array $data): Post;

    public function findById(int $id): ?Post;

    public function findWithRelations(int $id, array $relations = []): ?Post;

    public function update(Post $post, array $data): Post;

    public function delete(Post $post): bool;

    public function getPublicPosts(int $page = 1, int $perPage = 20): LengthAwarePaginator;

    public function getTimelinePosts(int $userId, int $limit = 20): Collection;

    public function getUserDrafts(int $userId): LengthAwarePaginator;

    public function getPostQuotes(int $postId): LengthAwarePaginator;

    public function getUserPosts(int $userId, int $limit = 20): Collection;

    public function searchPosts(string $query, int $limit = 20): Collection;
}
