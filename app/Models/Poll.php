<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Poll extends Model
{
    use HasFactory;

    protected $fillable = [
        'post_id',
        'question',
        'ends_at',
        'total_votes',
    ];

    protected $casts = [
        'ends_at' => 'datetime',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(PollOption::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(PollVote::class);
    }

    public function hasVoted(int $userId): bool
    {
        return $this->votes()->where('user_id', $userId)->exists();
    }

    public function getUserVote(int $userId): ?PollVote
    {
        return $this->votes()->where('user_id', $userId)->first();
    }

    public function isExpired(): bool
    {
        return $this->ends_at->isPast();
    }

    public function results(): array
    {
        $options = $this->options()->withCount('votes')->get();

        return $options->map(function ($option) {
            $percentage = $this->total_votes > 0
                ? round(($option->votes_count / $this->total_votes) * 100, 1)
                : 0;

            return [
                'id' => $option->id,
                'text' => $option->text,
                'votes_count' => $option->votes_count,
                'percentage' => $percentage,
            ];
        })->toArray();
    }
}
