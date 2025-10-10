<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;
    public $status;
    public $isOnline;
    public $lastSeenAt;

    /**
     * Create a new event instance.
     *
     * @param User $user
     * @param string $status
     * @param bool $isOnline
     * @param string|null $lastSeenAt
     */
    public function __construct(User $user, string $status, bool $isOnline, ?string $lastSeenAt = null)
    {
        $this->user = $user;
        $this->status = $status;
        $this->isOnline = $isOnline;
        $this->lastSeenAt = $lastSeenAt;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        // Broadcast to all chat rooms where this user is a member
        $channels = [];
        
        $chatRoomIds = $this->user->chatRooms()
            ->wherePivot('is_active', true)
            ->pluck('chat_rooms.id')
            ->toArray();
        
        foreach ($chatRoomIds as $chatRoomId) {
            $channels[] = new PrivateChannel('chat-room.' . $chatRoomId);
        }
        
        // Also broadcast to a general user status channel for friends/contacts
        $channels[] = new PrivateChannel('user-status');
        
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
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ],
            'status' => $this->status,
            'is_online' => $this->isOnline,
            'last_seen_at' => $this->lastSeenAt,
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
        return 'user.status.changed';
    }
}
