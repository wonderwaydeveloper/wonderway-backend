<?php

namespace App\Contracts;

use App\Models\Post;

interface PostServiceInterface
{
    public function createPost(array $data, $imageFile = null, bool $isDraft = false): Post;

    public function deletePost(Post $post): bool;

    public function toggleLike(Post $post, int $userId): array;

    public function getTimeline(int $userId, int $limit = 20): array;

    public function getUserPosts(int $userId, int $limit = 20): array;

    public function searchPosts(string $query, array $filters = []): array;
}
