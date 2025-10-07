<?php

namespace App\Events;

use App\DTOs\ChatDTO;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

/**
 * MessageSent Event
 *
 * Broadcasts a chat message to a private channel for real-time updates
 * within a chat room context.
 *
 * @package App\Events
 * @author Kurt Jacob Urquico
 * @version 1.0.0
 * @since 2025-10-07
 *
 * @see App\DTOs\ChatDTO
 */
class MessageSent implements ShouldBroadcast
{
    use SerializesModels;

    public readonly ChatDTO $chat;
    public readonly string $chatRoomId;

    /**
     * Create a new event instance.
     *
     * @param string $chatRoomId Unique ID of the chat room
     * @param ChatDTO $chat DTO representing the message
     */
    public function __construct(string $chatRoomId, ChatDTO $chat)
    {
        $this->chatRoomId = $chatRoomId;
        $this->chat = $chat;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return Channel|array
     */
    public function broadcastOn(): Channel|array
    {
        // Each chat room has its own private channel
        return new PrivateChannel("chat.{$this->chatRoomId}");
    }

    /**
     * Data to broadcast.
     *
     * @return array
     */
    public function broadcastWith(): array
    {
        return [
            'chat' => $this->chat->toArray(),
            'chat_room_id' => $this->chatRoomId,
        ];
    }
}
