<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model {
    protected $fillable = [
        'sender_id', 'receiver_id', 'message', 'chat_room_id',
        'message_type', 'is_edited', 'edited_at', 'reply_to_message_id'
    ];

    protected $casts = [
        'is_edited' => 'boolean',
        'edited_at' => 'datetime',
    ];

    public function sender() {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver() {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function chatRoom() {
        return $this->belongsTo(ChatRoom::class);
    }

    public function replyTo() {
        return $this->belongsTo(ChatMessage::class, 'reply_to_message_id');
    }

    public function replies() {
        return $this->hasMany(ChatMessage::class, 'reply_to_message_id');
    }
}