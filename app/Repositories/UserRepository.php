<?php

namespace App\Repositories;

use App\Contracts\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class UserRepository implements UserRepositoryInterface
{
    public function create(array $data): User
    {
        return User::create($data);
    }

    public function findById(int $id): ?User
    {
        return Cache::remember("user:{$id}", 600, function () use ($id) {
            return User::select(['id', 'name', 'username', 'email', 'avatar', 'bio', 'location', 'website', 'is_private', 'created_at'])
                ->find($id);
        });
    }

    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function findByUsername(string $username): ?User
    {
        return Cache::remember("user:username:{$username}", 600, function () use ($username) {
            return User::where('username', $username)
                ->select(['id', 'name', 'username', 'avatar', 'bio', 'location', 'website', 'is_private', 'created_at'])
                ->first();
        });
    }

    public function update(User $user, array $data): User
    {
        $user->update($data);
        $this->clearUserCache($user);

        return $user->fresh();
    }

    public function delete(User $user): bool
    {
        $this->clearUserCache($user);

        return $user->delete();
    }

    public function getUserWithCounts(int $id): ?User
    {
        return Cache::remember("user:counts:{$id}", 300, function () use ($id) {
            return User::withCount([
                'posts' => function ($query) {
                    $query->published();
                },
                'followers',
                'following',
            ])->find($id);
        });
    }

    public function getUserPosts(int $userId): LengthAwarePaginator
    {
        return User::findOrFail($userId)
            ->posts()
            ->published()
            ->with([
                'user:id,name,username,avatar',
                'hashtags:id,name,slug',
            ])
            ->withCount(['likes', 'comments', 'quotes'])
            ->whereNull('thread_id')
            ->latest('published_at')
            ->paginate(20);
    }

    public function searchUsers(string $query, int $limit = 20): Collection
    {
        return User::where(function ($q) use ($query) {
            $q->where('name', 'like', "%{$query}%")
              ->orWhere('username', 'like', "%{$query}%");
        })
            ->active()
            ->select(['id', 'name', 'username', 'avatar', 'bio', 'is_private'])
            ->limit($limit)
            ->get();
    }

    public function getFollowers(int $userId, int $limit = 20): Collection
    {
        return User::whereIn('id', function ($query) use ($userId) {
            $query->select('follower_id')
                ->from('follows')
                ->where('following_id', $userId);
        })
            ->select(['id', 'name', 'username', 'avatar', 'bio'])
            ->limit($limit)
            ->get();
    }

    public function getFollowing(int $userId, int $limit = 20): Collection
    {
        return User::whereIn('id', function ($query) use ($userId) {
            $query->select('following_id')
                ->from('follows')
                ->where('follower_id', $userId);
        })
            ->select(['id', 'name', 'username', 'avatar', 'bio'])
            ->limit($limit)
            ->get();
    }

    public function getSuggestedUsers(int $userId, int $limit = 10): Collection
    {
        $followingIds = DB::table('follows')
            ->where('follower_id', $userId)
            ->pluck('following_id')
            ->push($userId)
            ->toArray();

        return User::whereNotIn('id', $followingIds)
            ->select(['id', 'name', 'username', 'avatar', 'bio'])
            ->withCount('followers')
            ->orderBy('followers_count', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getMentionableUsers(string $query, int $limit = 10): Collection
    {
        return User::where('username', 'like', "{$query}%")
            ->select(['id', 'name', 'username', 'avatar'])
            ->limit($limit)
            ->get();
    }

    private function clearUserCache(User $user): void
    {
        Cache::forget("user:{$user->id}");
        Cache::forget("user:username:{$user->username}");
        Cache::forget("user:counts:{$user->id}");
    }
}
