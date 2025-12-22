<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(
        private UserService $userService
    ) {
    }

    public function show(User $user): JsonResponse
    {
        $userProfile = $this->userService->getUserProfile($user);

        return response()->json($userProfile);
    }

    public function posts(User $user): JsonResponse
    {
        $posts = $this->userService->getUserPosts($user);

        return response()->json($posts);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->userService->updateProfile(
            $request->user(),
            $request->validated()
        );

        return response()->json($user);
    }

    public function search(Request $request): JsonResponse
    {
        $request->validate(['q' => 'required|string|min:2|max:50']);

        $users = $this->userService->searchUsers($request->input('q'));

        return response()->json($users);
    }

    public function updatePrivacy(Request $request): JsonResponse
    {
        $request->validate(['is_private' => 'required|boolean']);

        $result = $this->userService->updatePrivacySettings(
            $request->user(),
            $request->boolean('is_private')
        );

        return response()->json($result);
    }

    public function block(User $user, Request $request): JsonResponse
    {
        $result = $this->userService->blockUser($request->user(), $user);

        return response()->json($result);
    }

    public function unblock(User $user, Request $request): JsonResponse
    {
        $result = $this->userService->unblockUser($request->user(), $user);

        return response()->json($result);
    }

    public function mute(User $user, Request $request): JsonResponse
    {
        $result = $this->userService->muteUser($request->user(), $user);

        return response()->json($result);
    }

    public function unmute(User $user, Request $request): JsonResponse
    {
        $result = $this->userService->unmuteUser($request->user(), $user);

        return response()->json($result);
    }
}
