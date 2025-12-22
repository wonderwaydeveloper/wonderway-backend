<?php

namespace App\Models;

use App\Traits\Mentionable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    use HasFactory, Mentionable;

    protected $fillable = [
        'user_id',
        'post_id',
        'content',
        'likes_count',
    ];

    protected $casts = [
        'likes_count' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class)->select(['id', 'name', 'username', 'avatar']);
    }

    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    public function likes()
    {
        return $this->morphMany(Like::class, 'likeable');
    }

    public function isLikedBy($userId)
    {
        return $this->likes()->where('user_id', $userId)->exists();
    }

    // Query Scopes
    public function scopeWithUser($query)
    {
        return $query->with(['user:id,name,username,avatar']);
    }

    public function scopeWithCounts($query)
    {
        return $query->withCount(['likes']);
    }

    public function scopeForPost($query, $postId)
    {
        return $query->where('post_id', $postId)
            ->withUser()
            ->withCounts()
            ->latest();
    }
}
