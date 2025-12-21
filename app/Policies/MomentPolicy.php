<?php

namespace App\Policies;

use App\Models\Moment;
use App\Models\User;

class MomentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Moment $moment): bool
    {
        return $moment->privacy === 'public' || $moment->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Moment $moment): bool
    {
        return $moment->user_id === $user->id;
    }

    public function delete(User $user, Moment $moment): bool
    {
        return $moment->user_id === $user->id;
    }
}