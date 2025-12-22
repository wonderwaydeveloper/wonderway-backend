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

class UserListPolicy
{
    public function update(User $user, \App\Models\UserList $list)
    {
        return $user->id === $list->user_id;
    }

    public function delete(User $user, \App\Models\UserList $list)
    {
        return $user->id === $list->user_id;
    }
}
