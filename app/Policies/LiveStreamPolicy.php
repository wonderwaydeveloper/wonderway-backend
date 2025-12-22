<?php

namespace App\Policies;

use App\Models\LiveStream;
use App\Models\User;

class LiveStreamPolicy
{
    public function update(User $user, LiveStream $stream): bool
    {
        return $user->id === $stream->user_id;
    }

    public function delete(User $user, LiveStream $stream): bool
    {
        return $user->id === $stream->user_id;
    }

    public function view(User $user, LiveStream $stream): bool
    {
        if (! $stream->is_private) {
            return true;
        }

        return $user->id === $stream->user_id || $stream->user->isFollowing($user->id);
    }
}
