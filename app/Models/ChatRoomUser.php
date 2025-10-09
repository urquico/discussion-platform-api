<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ChatRoomUser extends Pivot {
    protected $table = 'chat_room_users';

    protected $fillable = ['chat_room_id', 'user_id', 'role', 'is_active', 'joined_at'];

    protected $casts = [
        'is_active' => 'boolean',
        'joined_at' => 'datetime',
    ];
}