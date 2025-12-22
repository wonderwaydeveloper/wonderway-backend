<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConversionMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'event_type',
        'event_data',
        'conversion_type',
        'conversion_value',
        'source',
        'campaign',
        'session_id',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'event_data' => 'array',
        'conversion_value' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('conversion_type', $type);
    }

    public function scopeByDateRange($query, $start, $end)
    {
        return $query->whereBetween('created_at', [$start, $end]);
    }
}
