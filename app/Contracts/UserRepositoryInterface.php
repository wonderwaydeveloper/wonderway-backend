<?php

namespace App\Contracts;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface UserRepositoryInterface
{
    public function create(array $data): User;

    public function findById(int $id): ?User;

    public function findByEmail(string $email): ?User;

    public function findByUsername(string $username): ?User;

    public function update(User $user, array $data): User;

    public function delete(User $user): bool;

    public function getUserWithCounts(int $id): ?User;

    public function getUserPosts(int $userId): LengthAwarePaginator;

    public function searchUsers(string $query, int $limit = 20): Collection;

    public function getFollowers(int $userId, int $limit = 20): Collection;

    public function getFollowing(int $userId, int $limit = 20): Collection;

    public function getSuggestedUsers(int $userId, int $limit = 10): Collection;

    public function getMentionableUsers(string $query, int $limit = 10): Collection;
}
