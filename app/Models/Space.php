<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Space extends Model
{
    use HasFactory;

    protected $fillable = [
        'host_id',
        'title',
        'description',
        'status',
        'privacy',
        'max_participants',
        'current_participants',
        'scheduled_at',
        'started_at',
        'ended_at',
        'settings'
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'settings' => 'array'
    ];

    public function host()
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function participants()
    {
        return $this->hasMany(SpaceParticipant::class);
    }

    public function activeParticipants()
    {
        return $this->participants()->where('status', 'joined');
    }

    public function speakers()
    {
        return $this->participants()->whereIn('role', ['host', 'co_host', 'speaker']);
    }

    public function listeners()
    {
        return $this->participants()->where('role', 'listener');
    }

    public function isLive()
    {
        return $this->status === 'live';
    }

    public function canJoin($userId)
    {
        // Always allow joining public spaces
        if ($this->privacy === 'public') return true;
        
        // For followers-only spaces
        if ($this->privacy === 'followers') {
            return $this->host->followers()->where('follower_id', $userId)->exists();
        }
        
        // For invited-only spaces
        if ($this->privacy === 'invited') {
            return $this->participants()->where('user_id', $userId)->where('status', 'invited')->exists();
        }
        
        return false;
    }

    public function scopeLive($query)
    {
        return $query->where('status', 'live');
    }

    public function scopePublic($query)
    {
        return $query->where('privacy', 'public');
    }
}