<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserList;

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
