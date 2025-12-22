<?php

namespace App\Models;

use App\Traits\Mentionable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class Post extends Model
{
    use HasFactory;
    use Searchable;
    use Mentionable;

    protected $guarded = ['id'];

    protected $fillable = [
        'user_id',
        'content',
        'image',
        'gif_url',
        'likes_count',
        'comments_count',
        'is_draft',
        'published_at',
        'reply_settings',
        'thread_id',
        'thread_position',
        'quoted_post_id',
        'last_edited_at',
        'is_edited',
    ];

    protected $casts = [
        'likes_count' => 'integer',
        'comments_count' => 'integer',
        'is_draft' => 'boolean',
        'published_at' => 'datetime',
        'last_edited_at' => 'datetime',
        'is_edited' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class)->select(['id', 'name', 'username', 'avatar']);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function likes()
    {
        return $this->morphMany(Like::class, 'likeable');
    }

    public function isLikedBy($userId)
    {
        return $this->likes()->where('user_id', $userId)->exists();
    }

    public function hashtags()
    {
        return $this->belongsToMany(Hashtag::class)->withTimestamps();
    }

    // Query Scopes
    public function scopeWithUser($query)
    {
        return $query->with(['user:id,name,username,avatar']);
    }

    public function scopeWithCounts($query)
    {
        return $query->withCount(['likes', 'comments', 'quotes']);
    }

    public function scopeWithBasicRelations($query)
    {
        return $query->with([
            'user:id,name,username,avatar',
            'quotedPost:id,content,user_id',
            'quotedPost.user:id,name,username',
        ]);
    }

    public function scopeForTimeline($query)
    {
        return $query->published()
            ->withBasicRelations()
            ->withCounts()
            ->latest();
    }

    public function scopeByHashtag($query, $hashtag)
    {
        return $query->whereHas('hashtags', function ($q) use ($hashtag) {
            $q->where('name', $hashtag);
        });
    }

    public function syncHashtags()
    {
        $hashtags = Hashtag::createFromText($this->content);
        $hashtagIds = collect($hashtags)->pluck('id')->toArray();

        $this->hashtags()->sync($hashtagIds);

        foreach ($hashtags as $hashtag) {
            $hashtag->update(['posts_count' => $hashtag->posts()->count()]);
        }
    }



    public function reposts()
    {
        return $this->hasMany(Repost::class);
    }

    public function scopePublished($query)
    {
        return $query->where('is_draft', false);
    }

    public function scopeDrafts($query)
    {
        return $query->where('is_draft', true);
    }

    public function thread()
    {
        return $this->belongsTo(Post::class, 'thread_id');
    }

    public function threadPosts()
    {
        return $this->hasMany(Post::class, 'thread_id')->orderBy('thread_position');
    }

    public function poll()
    {
        return $this->hasOne(Poll::class);
    }

    public function hasPoll(): bool
    {
        return $this->poll()->exists();
    }

    public function quotedPost()
    {
        return $this->belongsTo(Post::class, 'quoted_post_id');
    }

    public function quotes()
    {
        return $this->hasMany(Post::class, 'quoted_post_id');
    }

    public function isQuote(): bool
    {
        return ! is_null($this->quoted_post_id);
    }

    public function isThread(): bool
    {
        return ! is_null($this->thread_id) || $this->threadPosts()->exists();
    }

    public function isMainThread(): bool
    {
        return is_null($this->thread_id) && $this->threadPosts()->exists();
    }

    public function getThreadRoot()
    {
        return $this->thread_id ? Post::find($this->thread_id) : $this;
    }

    public function getFullThread()
    {
        $root = $this->getThreadRoot();

        return $root->load('threadPosts');
    }

    public function toSearchableArray()
    {
        return [
            'id' => $this->id,
            'content' => $this->content,
            'user_id' => $this->user_id,
            'user_name' => $this->user->name,
            'user_username' => $this->user->username,
            'hashtags' => $this->hashtags->pluck('name')->toArray(),
            'created_at' => $this->created_at->timestamp,
            'is_draft' => $this->is_draft,
            'likes_count' => $this->likes_count,
            'comments_count' => $this->comments_count,
            'has_media' => ! empty($this->image) || ! empty($this->gif_url),
            'thread_id' => $this->thread_id,
            'quoted_post_id' => $this->quoted_post_id,
        ];
    }

    public function shouldBeSearchable()
    {
        return ! $this->is_draft;
    }

    public function edits()
    {
        return $this->hasMany(PostEdit::class)->orderBy('edited_at', 'desc');
    }

    public function canBeEdited(): bool
    {
        return $this->created_at->diffInMinutes(now()) <= 30;
    }

    public function editPost(string $newContent, ?string $reason = null): void
    {
        if (! $this->canBeEdited()) {
            throw new \Exception('Post cannot be edited after 30 minutes');
        }

        $this->edits()->create([
            'original_content' => $this->content,
            'new_content' => $newContent,
            'edit_reason' => $reason,
            'edited_at' => now(),
        ]);

        $this->update([
            'content' => $newContent,
            'is_edited' => true,
            'last_edited_at' => now(),
        ]);
    }
}
