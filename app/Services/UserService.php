<?php

namespace App\Services;

use App\Contracts\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class UserService
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {
    }

    /**
     * Get user profile with counts
     */
    public function getUserProfile(User $user): User
    {
        return $this->userRepository->getUserWithCounts($user->id);
    }

    /**
     * Get user posts with pagination
     */
    public function getUserPosts(User $user): LengthAwarePaginator
    {
        return $this->userRepository->getUserPosts($user->id);
    }

    /**
     * Update user profile
     */
    public function updateProfile(User $user, array $data): User
    {
        $allowedFields = ['name', 'bio', 'avatar'];
        $updateData = array_intersect_key($data, array_flip($allowedFields));

        return $this->userRepository->update($user, $updateData);
    }

    /**
     * Search users by name or username
     */
    public function searchUsers(string $query, int $limit = 20): Collection
    {
        return $this->userRepository->searchUsers($query, $limit);
    }

    /**
     * Update user privacy settings
     */
    public function updatePrivacySettings(User $user, bool $isPrivate): array
    {
        $user->update(['is_private' => $isPrivate]);

        return [
            'message' => 'Privacy settings updated',
            'is_private' => $user->is_private,
        ];
    }

    /**
     * Block user
     */
    public function blockUser(User $user, User $targetUser): array
    {
        // Add block logic here
        // For now, just return success message
        return [
            'message' => 'User blocked successfully',
            'blocked_user' => $targetUser->only(['id', 'name', 'username']),
        ];
    }

    /**
     * Unblock user
     */
    public function unblockUser(User $user, User $targetUser): array
    {
        // Add unblock logic here
        // For now, just return success message
        return [
            'message' => 'User unblocked successfully',
            'unblocked_user' => $targetUser->only(['id', 'name', 'username']),
        ];
    }

    /**
     * Mute user
     */
    public function muteUser(User $user, User $targetUser): array
    {
        // Add mute logic here
        // For now, just return success message
        return [
            'message' => 'User muted successfully',
            'muted_user' => $targetUser->only(['id', 'name', 'username']),
        ];
    }

    /**
     * Unmute user
     */
    public function unmuteUser(User $user, User $targetUser): array
    {
        // Add unmute logic here
        // For now, just return success message
        return [
            'message' => 'User unmuted successfully',
            'unmuted_user' => $targetUser->only(['id', 'name', 'username']),
        ];
    }
}
