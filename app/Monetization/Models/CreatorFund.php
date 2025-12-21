<?php

namespace App\Monetization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class CreatorFund extends Model
{
    protected $fillable = [
        'creator_id',
        'month',
        'year',
        'total_views',
        'total_engagement',
        'quality_score',
        'earnings',
        'status',
        'paid_at',
        'metrics',
    ];

    protected $casts = [
        'earnings' => 'decimal:2',
        'quality_score' => 'decimal:2',
        'paid_at' => 'datetime',
        'metrics' => 'array',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function calculateEarnings(): float
    {
        if ($this->total_views == 0) {
            return 0;
        }
        
        $baseRate = 0.001; // $0.001 per view
        $engagementMultiplier = min($this->total_engagement / $this->total_views, 0.1);
        $qualityMultiplier = $this->quality_score / 100;
        
        return $this->total_views * $baseRate * (1 + $engagementMultiplier) * $qualityMultiplier;
    }

    public function isEligible(): bool
    {
        return $this->total_views >= 10000 
            && $this->quality_score >= 70 
            && $this->creator->followers()->count() >= 1000;
    }
}