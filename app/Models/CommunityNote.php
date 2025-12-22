<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommunityNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'post_id',
        'author_id',
        'content',
        'sources',
        'status',
        'helpful_votes',
        'not_helpful_votes',
        'approved_at',
    ];

    protected $casts = [
        'sources' => 'array',
        'approved_at' => 'datetime',
    ];

    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function votes()
    {
        return $this->hasMany(CommunityNoteVote::class);
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function getHelpfulnessRatio(): float
    {
        $total = $this->helpful_votes + $this->not_helpful_votes;
        return $total > 0 ? $this->helpful_votes / $total : 0;
    }

    public function shouldBeApproved(): bool
    {
        return $this->helpful_votes >= 3 && $this->getHelpfulnessRatio() >= 0.7;
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}