<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, HasApiTokens, HasRoles;

    protected $guarded = ['id'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'phone',
        'password',
        'bio',
        'avatar',
        'date_of_birth',
        'is_child',
        'requires_parental_approval',
        'subscription_plan',
        'is_premium',
        'is_private',
        'google_id',
        'github_id',
        'facebook_id',
        'two_factor_enabled',
        'two_factor_secret',
        'two_factor_backup_codes',
        'is_online',
        'last_seen_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_backup_codes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'date_of_birth' => 'date',
            'is_child' => 'boolean',
            'requires_parental_approval' => 'boolean',
            'is_premium' => 'boolean',
            'is_private' => 'boolean',
            'two_factor_enabled' => 'boolean',
            'notification_preferences' => 'array',
            'is_online' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function likes()
    {
        return $this->hasMany(Like::class);
    }

    public function followers()
    {
        return $this->belongsToMany(User::class, 'follows', 'following_id', 'follower_id')
            ->withTimestamps();
    }

    public function following()
    {
        return $this->belongsToMany(User::class, 'follows', 'follower_id', 'following_id')
            ->withTimestamps();
    }

    public function isFollowing($userId)
    {
        return $this->following()->where('following_id', $userId)->exists();
    }

    public function parentalControl()
    {
        return $this->hasOne(ParentalControl::class, 'child_id');
    }

    public function parents()
    {
        return $this->belongsToMany(User::class, 'parental_links', 'child_id', 'parent_id')
            ->withPivot('status')
            ->withTimestamps();
    }

    public function children()
    {
        return $this->belongsToMany(User::class, 'parental_links', 'parent_id', 'child_id')
            ->withPivot('status')
            ->withTimestamps();
    }

    public function getAgeAttribute()
    {
        return $this->date_of_birth?->age;
    }

    public function isUnder18()
    {
        return $this->age < 18;
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription()
    {
        return $this->hasOne(Subscription::class)
            ->where('status', 'active')
            ->where('ends_at', '>', now());
    }

    public function isPremium()
    {
        return $this->is_premium && $this->activeSubscription()->exists();
    }

    public function bookmarks()
    {
        return $this->hasMany(Bookmark::class);
    }

    public function reposts()
    {
        return $this->hasMany(Repost::class);
    }

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new \App\Notifications\ResetPasswordNotification($token));
    }

    public function followRequests()
    {
        return $this->hasMany(FollowRequest::class, 'following_id');
    }

    public function sentFollowRequests()
    {
        return $this->hasMany(FollowRequest::class, 'follower_id');
    }

    public function scheduledPosts()
    {
        return $this->hasMany(ScheduledPost::class);
    }

    public function devices()
    {
        return $this->hasMany(DeviceToken::class);
    }

    public function reports()
    {
        return $this->hasMany(\App\Models\Report::class);
    }

    public function mentions()
    {
        return $this->hasMany(Mention::class);
    }
}
