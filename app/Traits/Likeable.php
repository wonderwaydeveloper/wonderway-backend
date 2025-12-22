<?php

namespace App\Traits;

use App\Models\Like;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait Likeable
{
    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, 'likeable');
    }

    public function isLikedBy(int $userId): bool
    {
        return $this->likes()->where('user_id', $userId)->exists();
    }

    public function like(int $userId): void
    {
        if (! $this->isLikedBy($userId)) {
            $this->likes()->create(['user_id' => $userId]);
            $this->increment('likes_count');
        }
    }

    public function unlike(int $userId): void
    {
        if ($this->isLikedBy($userId)) {
            $this->likes()->where('user_id', $userId)->delete();
            $this->decrement('likes_count');
        }
    }
}
