<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stream extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'title',
        'description',
        'stream_key',
        'status',
        'is_private',
        'category',
        'scheduled_at',
        'started_at',
        'ended_at',
        'duration',
        'peak_viewers',
        'recording_path',
        'recording_size',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'is_private' => 'boolean',
    ];

    protected $hidden = [
        'stream_key',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function viewers(): HasMany
    {
        return $this->hasMany(StreamViewer::class);
    }

    public function chats(): HasMany
    {
        return $this->hasMany(StreamChat::class);
    }

    public function scopeLive($query)
    {
        return $query->where('status', 'live');
    }

    public function scopePublic($query)
    {
        return $query->where('is_private', false);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function getIsLiveAttribute(): bool
    {
        return $this->status === 'live';
    }

    public function getDurationFormattedAttribute(): string
    {
        if (! $this->duration) {
            return '00:00:00';
        }

        $hours = floor($this->duration / 3600);
        $minutes = floor(($this->duration % 3600) / 60);
        $seconds = $this->duration % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }

    public function getStreamUrlsAttribute(): array
    {
        return app(\App\Services\StreamingService::class)->getStreamUrls($this->stream_key);
    }

    public function getThumbnailAttribute(): ?string
    {
        $thumbnailPath = "thumbnails/{$this->stream_key}.jpg";

        if (file_exists(storage_path("app/public/{$thumbnailPath}"))) {
            return asset("storage/{$thumbnailPath}");
        }

        return null;
    }
}
