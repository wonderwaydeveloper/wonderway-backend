<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserList extends Model
{
    use HasFactory;

    protected $table = 'lists';

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'privacy',
        'members_count',
        'subscribers_count',
        'banner_image',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'list_members', 'list_id', 'user_id')
                    ->withTimestamps();
    }

    public function subscribers()
    {
        return $this->belongsToMany(User::class, 'list_subscribers', 'list_id', 'user_id')
                    ->withTimestamps();
    }

    public function posts()
    {
        return Post::whereIn('user_id', $this->members()->pluck('users.id'))
                   ->published()
                   ->latest('published_at');
    }

    public function isSubscribedBy($userId)
    {
        return $this->subscribers()->where('user_id', $userId)->exists();
    }

    public function hasMember($userId)
    {
        return $this->members()->where('user_id', $userId)->exists();
    }

    public function scopePublic($query)
    {
        return $query->where('privacy', 'public');
    }
}
