<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class EloquentUserRepository implements UserRepositoryInterface
{
    public function create(array $data): User
    {
        return User::create($data);
    }

    public function findById(int $id): ?User
    {
        return User::find($id);
    }

    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function findByUsername(string $username): ?User
    {
        return User::where('username', $username)->first();
    }

    public function update(User $user, array $data): User
    {
        $user->update($data);
        return $user->fresh();
    }

    public function delete(User $user): bool
    {
        return $user->delete();
    }

    public function getUserWithCounts(int $id): ?User
    {
        return User::withCount(['posts', 'followers', 'following'])->find($id);
    }

    public function getUserPosts(int $userId): LengthAwarePaginator
    {
        return User::findOrFail($userId)->posts()->paginate(20);
    }

    public function searchUsers(string $query, int $limit = 20): Collection
    {
        return User::where('name', 'like', "%{$query}%")
            ->orWhere('username', 'like', "%{$query}%")
            ->limit($limit)
            ->get();
    }

    public function getFollowers(int $userId, int $limit = 20): Collection
    {
        return User::findOrFail($userId)->followers()->limit($limit)->get();
    }

    public function getFollowing(int $userId, int $limit = 20): Collection
    {
        return User::findOrFail($userId)->following()->limit($limit)->get();
    }

    public function getSuggestedUsers(int $userId, int $limit = 10): Collection
    {
        return User::where('id', '!=', $userId)
            ->whereNotIn('id', function ($query) use ($userId) {
                $query->select('following_id')
                    ->from('follows')
                    ->where('follower_id', $userId);
            })
            ->inRandomOrder()
            ->limit($limit)
            ->get();
    }

    public function getMentionableUsers(string $query, int $limit = 10): Collection
    {
        return User::where('username', 'like', "%{$query}%")
            ->limit($limit)
            ->get();
    }
}