<?php

namespace App\Policies;

use App\Models\Space;
use App\Models\User;

class SpacePolicy
{
    public function update(User $user, Space $space)
    {
        return $user->id === $space->host_id;
    }

    public function delete(User $user, Space $space)
    {
        return $user->id === $space->host_id;
    }
}
