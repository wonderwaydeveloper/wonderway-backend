<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'plan',
        'status',
        'amount',
        'starts_at',
        'ends_at',
        'expires_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isActive()
    {
        return $this->status === 'active' && $this->ends_at->isFuture();
    }

    public function cancel()
    {
        $this->update(['status' => 'cancelled']);
    }

    public static function plans()
    {
        return [
            'basic' => [
                'name' => 'رایگان',
                'price' => 0,
                'features' => [
                    '10 پست در روز',
                    '5 عکس در پست',
                    'تبلیغات دارد',
                ],
            ],
            'premium' => [
                'name' => 'پرمیوم',
                'price' => 4.99,
                'features' => [
                    'پست نامحدود',
                    'بدون تبلیغات',
                    'ویرایش پست',
                    'آپلود ویدیو تا 10 دقیقه',
                    'نشان Premium',
                ],
            ],
            'creator' => [
                'name' => 'سازنده محتوا',
                'price' => 9.99,
                'features' => [
                    'همه امکانات Premium',
                    'آمار پیشرفته',
                    'مونتایز محتوا',
                    'لایو استریم',
                    'API access',
                ],
            ],
        ];
    }
}
