<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageReaction extends Model
{
    protected $fillable = [
        'message_id',
        'user_id',
        'reaction_type',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the message that this reaction belongs to.
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'message_id');
    }

    /**
     * Get the user who made this reaction.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get all available reaction types.
     */
    public static function getReactionTypes(): array
    {
        return ['heart', 'angry', 'wow'];
    }

    /**
     * Get reaction emoji for display.
     */
    public function getEmoji(): string
    {
        return match ($this->reaction_type) {
            'heart' => '❤️',
            'angry' => '😠',
            'wow' => '😮',
            default => '❤️',
        };
    }
}