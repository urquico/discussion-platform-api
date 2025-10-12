<?php

namespace App\Events;

use App\Models\ChatMessage;
use App\Models\MessageReaction;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageReactionUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $reaction;
    public $action;

    /**
     * Create a new event instance.
     *
     * @param ChatMessage $message
     * @param MessageReaction $reaction
     * @param string $action
     */
    public function __construct(ChatMessage $message, MessageReaction $reaction, string $action = 'added')
    {
        $this->message = $message;
        $this->reaction = $reaction;
        $this->action = $action;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat-room.' . $this->message->chat_room_id),
        ];
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->message->id,
            'reaction' => [
                'id' => $this->reaction->id,
                'user_id' => $this->reaction->user_id,
                'user_name' => $this->reaction->user->name ?? 'Unknown User',
                'reaction_type' => $this->reaction->reaction_type,
                'emoji' => $this->reaction->getEmoji(),
                'created_at' => $this->reaction->created_at,
            ],
            'reactions_summary' => $this->message->getReactionsSummary(),
            'action' => $this->action,
        ];
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'message.reaction.' . $this->action;
    }
}