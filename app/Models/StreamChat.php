<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StreamChat extends Model
{
    protected $fillable = [
        'stream_id',
        'user_id',
        'message',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
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
