<?php

namespace App\Policies;

use App\Models\UserList;
use App\Models\User;

class UserListPolicy
{
    public function update(User $user, UserList $list)
    {
        return $user->id === $list->user_id;
    }

    public function delete(User $user, UserList $list)
    {
        return $user->id === $list->user_id;
    }
}