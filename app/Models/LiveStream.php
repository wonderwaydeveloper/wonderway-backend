<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LiveStream extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'stream_key',
        'rtmp_url',
        'hls_url',
        'status',
        'viewer_count',
        'max_viewers',
        'started_at',
        'ended_at',
        'is_private',
        'category',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'is_private' => 'boolean',
        'viewer_count' => 'integer',
        'max_viewers' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function viewers()
    {
        return $this->belongsToMany(User::class, 'stream_viewers')
            ->withTimestamps();
    }

    public function isLive()
    {
        return $this->status === 'live';
    }

    public function scopeLive($query)
    {
        return $query->where('status', 'live');
    }
}