<?php

namespace App\Events;

use App\Models\ChatRoom;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatRoomUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $chatRoom;
    public $action;
    public $affectedUserIds;

    /**
     * Create a new event instance.
     *
     * @param ChatRoom $chatRoom
     * @param string $action
     * @param array $affectedUserIds
     */
    public function __construct(ChatRoom $chatRoom, string $action = 'updated', array $affectedUserIds = [])
    {
        $this->chatRoom = $chatRoom;
        $this->action = $action;
        $this->affectedUserIds = $affectedUserIds;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        // Broadcast to individual user channels for each affected user
        $channels = [];
        
        if (empty($this->affectedUserIds)) {
            // If no specific users, broadcast to all chat room members
            $this->affectedUserIds = $this->chatRoom->users()
                ->wherePivot('is_active', true)
                ->pluck('users.id')
                ->toArray();
        }
        
        foreach ($this->affectedUserIds as $userId) {
            $channels[] = new PrivateChannel('App.Models.User.' . $userId);
        }
        
        return $channels;
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith(): array
    {
        return [
            'chat_room' => [
                'id' => $this->chatRoom->id,
                'name' => $this->chatRoom->name,
                'description' => $this->chatRoom->description,
                'type' => $this->chatRoom->type,
                'creator_id' => $this->chatRoom->creator_id,
                'created_at' => $this->chatRoom->created_at,
                'updated_at' => $this->chatRoom->updated_at,
                'latest_message' => $this->chatRoom->messages()
                    ->latest()
                    ->first() ? [
                        'id' => $this->chatRoom->messages()->latest()->first()->id,
                        'sender_id' => $this->chatRoom->messages()->latest()->first()->sender_id,
                        'sender_name' => $this->chatRoom->messages()->latest()->first()->sender->name,
                        'message' => $this->chatRoom->messages()->latest()->first()->message,
                        'message_type' => $this->chatRoom->messages()->latest()->first()->message_type,
                        'created_at' => $this->chatRoom->messages()->latest()->first()->created_at,
                    ] : null,
                'unread_count' => $this->chatRoom->messages()
                    ->where('created_at', '>', $this->chatRoom->users()
                        ->wherePivot('user_id', auth()->id())
                        ->wherePivot('is_active', true)
                        ->first()
                        ->pivot
                        ->last_read_at ?? $this->chatRoom->created_at)
                    ->where('sender_id', '!=', auth()->id())
                    ->count(),
                'members_count' => $this->chatRoom->users()
                    ->wherePivot('is_active', true)
                    ->count(),
            ],
            'action' => $this->action,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'chatroom.updated';
    }
}
