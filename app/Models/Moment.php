<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Moment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'cover_image',
        'privacy',
        'is_featured',
        'posts_count',
        'views_count',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'posts_count' => 'integer',
        'views_count' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function posts()
    {
        return $this->belongsToMany(Post::class, 'moment_posts')
            ->withPivot('position')
            ->withTimestamps()
            ->orderBy('moment_posts.position');
    }

    public function scopePublic($query)
    {
        return $query->where('privacy', 'public');
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function addPost($postId, $position = null)
    {
        if ($position === null) {
            $position = $this->posts()->count();
        }

        $this->posts()->attach($postId, ['position' => $position]);
        $this->increment('posts_count');
    }

    public function removePost($postId)
    {
        $this->posts()->detach($postId);
        $this->decrement('posts_count');
    }

    public function incrementViews()
    {
        $this->increment('views_count');
    }
}
