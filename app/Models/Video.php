<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    use HasFactory;
    protected $fillable = [
        'post_id',
        'original_path',
        'processed_paths',
        'thumbnail_path',
        'duration',
        'resolution',
        'file_size',
        'encoding_status',
        'metadata',
    ];

    protected $casts = [
        'processed_paths' => 'array',
        'metadata' => 'array',
        'duration' => 'integer',
        'file_size' => 'integer',
    ];

    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    public function isProcessed(): bool
    {
        return $this->encoding_status === 'completed';
    }

    public function getUrl(string $quality = '720p'): ?string
    {
        if (!$this->isProcessed()) {
            return null;
        }

        return $this->processed_paths[$quality] ?? $this->processed_paths['original'] ?? null;
    }
}