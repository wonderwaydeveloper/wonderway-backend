<?php

namespace App\Monetization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;

class Advertisement extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'advertiser_id',
        'title',
        'content',
        'media_url',
        'target_audience',
        'budget',
        'cost_per_click',
        'cost_per_impression',
        'start_date',
        'end_date',
        'status',
        'impressions_count',
        'clicks_count',
        'conversions_count',
        'total_spent',
        'targeting_criteria',
    ];

    protected $casts = [
        'target_audience' => 'array',
        'targeting_criteria' => 'array',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'budget' => 'decimal:2',
        'cost_per_click' => 'decimal:4',
        'cost_per_impression' => 'decimal:4',
        'total_spent' => 'decimal:2',
    ];

    protected static function newFactory()
    {
        return \Database\Factories\Monetization\Models\AdvertisementFactory::new();
    }

    public function advertiser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'advertiser_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active' 
            && $this->start_date <= now() 
            && $this->end_date >= now()
            && $this->total_spent < $this->budget;
    }

    public function getRemainingBudget(): float
    {
        return $this->budget - $this->total_spent;
    }

    public function getCTR(): float
    {
        return $this->impressions_count > 0 
            ? ($this->clicks_count / $this->impressions_count) * 100 
            : 0;
    }

    public function getConversionRate(): float
    {
        return $this->clicks_count > 0 
            ? ($this->conversions_count / $this->clicks_count) * 100 
            : 0;
    }
}