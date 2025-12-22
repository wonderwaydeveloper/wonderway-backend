<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommunityNoteVote extends Model
{
    protected $fillable = [
        'community_note_id',
        'user_id',
        'vote_type',
    ];

    public function communityNote()
    {
        return $this->belongsTo(CommunityNote::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isHelpful(): bool
    {
        return $this->vote_type === 'helpful';
    }
}