<?php

namespace App\Traits;

use App\Models\Mention;
use App\Models\User;

trait Mentionable
{
    /**
     * Get all mentions for this model
     */
    public function mentions()
    {
        return $this->morphMany(Mention::class, 'mentionable');
    }

    /**
     * Get mentioned users
     */
    public function mentionedUsers()
    {
        return $this->belongsToMany(User::class, 'mentions', 'mentionable_id', 'user_id')
            ->wherePivot('mentionable_type', get_class($this));
    }

    /**
     * Extract mentions from content and create mention records
     */
    public function processMentions($content)
    {
        // Extract @username patterns
        preg_match_all('/@([a-zA-Z0-9_]+)/', $content, $matches);

        if (empty($matches[1])) {
            return [];
        }

        $usernames = array_unique($matches[1]);
        $mentionedUsers = [];

        foreach ($usernames as $username) {
            $user = User::where('username', $username)->first();

            if ($user) {
                // Create mention record
                $this->mentions()->firstOrCreate([
                    'user_id' => $user->id,
                ]);

                $mentionedUsers[] = $user;
            }
        }

        return $mentionedUsers;
    }

    /**
     * Check if a user is mentioned in this content
     */
    public function isMentioned($userId)
    {
        return $this->mentions()->where('user_id', $userId)->exists();
    }
}
