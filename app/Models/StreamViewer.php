<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StreamViewer extends Model
{
    protected $fillable = [
        'stream_id',
        'user_id',
        'joined_at',
        'left_at',
        'duration',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
    ];

    public function stream(): BelongsTo
    {
        return $this->belongsTo(Stream::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
