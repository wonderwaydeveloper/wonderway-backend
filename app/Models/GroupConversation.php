<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GroupConversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'avatar',
        'created_by',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    public function members()
    {
        return $this->belongsToMany(User::class, 'group_members', 'group_conversation_id', 'user_id')
            ->withPivot('is_admin')
            ->withTimestamps();
    }

    public function messages()
    {
        return $this->hasMany(GroupMessage::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
