<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhoneVerificationCode extends Model
{
    protected $fillable = [
        'phone',
        'code',
        'expires_at',
        'verified',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'verified' => 'boolean',
    ];

    public function isExpired()
    {
        return $this->expires_at->isPast();
    }

    public function isValid()
    {
        return ! $this->verified && ! $this->isExpired();
    }
}
