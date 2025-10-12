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

    protected $appends = ['reactions_summary', 'reactions_with_users'];

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

    public function reactions() {
        return $this->hasMany(MessageReaction::class, 'message_id');
    }

    /**
     * Get reactions grouped by type with counts.
     */
    public function getReactionsSummary() {
        return $this->reactions()
            ->selectRaw('reaction_type, COUNT(*) as count')
            ->groupBy('reaction_type')
            ->get()
            ->pluck('count', 'reaction_type')
            ->toArray();
    }

    /**
     * Get reactions with user details.
     */
    public function getReactionsWithUsers() {
        return $this->reactions()
            ->with('user:id,name')
            ->get()
            ->groupBy('reaction_type')
            ->map(function ($reactions) {
                return $reactions->map(function ($reaction) {
                    return [
                        'id' => $reaction->id,
                        'user_id' => $reaction->user_id,
                        'user_name' => $reaction->user->name,
                        'reaction_type' => $reaction->reaction_type,
                        'emoji' => $reaction->getEmoji(),
                        'created_at' => $reaction->created_at,
                    ];
                });
            });
    }

    /**
     * Get reactions summary for API response.
     */
    public function getReactionsSummaryAttribute() {
        return $this->getReactionsSummary();
    }

    /**
     * Get reactions with users for API response.
     */
    public function getReactionsWithUsersAttribute() {
        return $this->getReactionsWithUsers();
    }
}