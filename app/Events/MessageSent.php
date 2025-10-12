<?php

namespace App\Events;

use App\Models\ChatMessage;
use App\Events\ChatRoomUpdated;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $action;

    /**
     * Create a new event instance.
     *
     * @param ChatMessage $message
     * @param string $action
     */
    public function __construct(ChatMessage $message, string $action = 'sent')
    {
        $this->message = $message;
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
        $replyToMessage = null;
        if ($this->message->reply_to_message_id) {
            // Load the replyTo relationship if not already loaded
            if (!$this->message->relationLoaded('replyTo')) {
                $this->message->load(['replyTo.sender']);
            }
            
            $replyTo = $this->message->replyTo;
            if ($replyTo && $replyTo->relationLoaded('sender')) {
                $replyToMessage = [
                    'id' => $replyTo->id,
                    'sender_id' => $replyTo->sender_id,
                    'sender_name' => $replyTo->sender->name,
                    'message' => $replyTo->message,
                    'message_type' => $replyTo->message_type,
                    'created_at' => $replyTo->created_at,
                    'is_edited' => $replyTo->is_edited,
                ];
            }
        }

        return [
            'message' => [
                'id' => $this->message->id,
                'sender_id' => $this->message->sender_id,
                'sender_name' => $this->message->sender->name,
                'sender_online_status' => $this->message->sender->online_status,
                'sender_is_online' => $this->message->sender->is_online,
                'sender_status' => $this->message->sender->status,
                'message' => $this->message->message,
                'message_type' => $this->message->message_type,
                'chat_room_id' => $this->message->chat_room_id,
                'reply_to_message_id' => $this->message->reply_to_message_id,
                'reply_to_message' => $replyToMessage,
                'is_edited' => $this->message->is_edited,
                'edited_at' => $this->message->edited_at,
                'created_at' => $this->message->created_at,
                'updated_at' => $this->message->updated_at,
            ],
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
        return 'message.' . $this->action;
    }
}
