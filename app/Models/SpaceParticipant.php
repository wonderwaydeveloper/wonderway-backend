<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpaceParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'space_id',
        'user_id',
        'role',
        'status',
        'is_muted',
        'joined_at',
        'left_at'
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
        'is_muted' => 'boolean'
    ];

    public function space()
    {
        return $this->belongsTo(Space::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isSpeaker()
    {
        return in_array($this->role, ['host', 'co_host', 'speaker']);
    }

    public function canSpeak()
    {
        return $this->isSpeaker() && !$this->is_muted;
    }
}