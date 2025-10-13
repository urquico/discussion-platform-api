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
        $latestMessage = $this->chatRoom->messages()->latest()->first();
        $latestMessageAt = $latestMessage ? $latestMessage->created_at : null;
        
        // For message_sent events, recipients should have unread messages
        // since they haven't seen the new message yet
        $hasUnreadMessages = false;
        
        if ($latestMessageAt && $this->action === 'message_sent') {
            // For message_sent events, recipients should have unread messages
            // The sender won't receive this event due to .toOthers()
            $hasUnreadMessages = true;
        } else {
            // For other events, calculate based on visit record
            $senderId = auth()->id();
            if ($senderId) {
                $lastVisit = \App\Models\ChatRoomVisit::where('user_id', $senderId)
                    ->where('chat_room_id', $this->chatRoom->id)
                    ->first();
                
                if ($lastVisit) {
                    // Ensure we have proper Carbon instances for comparison
                    $lastMessageTime = $latestMessageAt instanceof \Carbon\Carbon 
                        ? $latestMessageAt 
                        : \Carbon\Carbon::parse($latestMessageAt);
                    $lastVisitTime = $lastVisit->last_visited_at;
                    
                    // If last message is newer than last visit, there are unread messages
                    $hasUnreadMessages = $lastMessageTime->isAfter($lastVisitTime);
                } else {
                    // If no visit record exists, assume there are unread messages
                    $hasUnreadMessages = true;
                }
            }
        }

        return [
            'chat_room' => [
                'id' => $this->chatRoom->id,
                'name' => $this->chatRoom->name,
                'description' => $this->chatRoom->description,
                'type' => $this->chatRoom->type,
                'creator_id' => $this->chatRoom->creator_id,
                'created_at' => $this->chatRoom->created_at,
                'updated_at' => $this->chatRoom->updated_at,
                'latest_message' => $latestMessage ? [
                    'id' => $latestMessage->id,
                    'sender_id' => $latestMessage->sender_id,
                    'sender_name' => $latestMessage->sender->name,
                    'message' => $latestMessage->message,
                    'message_type' => $latestMessage->message_type,
                    'created_at' => $latestMessage->created_at,
                ] : null,
                'has_unread_messages' => $hasUnreadMessages,
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
