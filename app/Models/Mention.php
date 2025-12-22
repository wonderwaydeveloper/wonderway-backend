<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Mention extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'mentionable_id',
        'mentionable_type',
    ];

    /**
     * Get the mentioned user
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the mentionable model (Post or Comment)
     */
    public function mentionable()
    {
        return $this->morphTo();
    }
}
