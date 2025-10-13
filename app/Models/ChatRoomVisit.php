<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatRoomVisit extends Model
{
    protected $fillable = [
        'user_id',
        'chat_room_id',
        'last_visited_at',
    ];

    protected $casts = [
        'last_visited_at' => 'datetime',
    ];

    /**
     * Get the user that visited the chat room.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the chat room that was visited.
     */
    public function chatRoom(): BelongsTo
    {
        return $this->belongsTo(ChatRoom::class);
    }
}
