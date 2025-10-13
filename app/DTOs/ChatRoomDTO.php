<?php

namespace App\DTOs;

use App\Models\ChatRoom;
use App\Models\User;

class ChatRoomDTO
{
    public function __construct(
        public readonly int $id,
        public readonly ?string $name,
        public readonly ?string $description,
        public readonly string $type,
        public readonly int $created_by,
        public readonly bool $is_active,
        public readonly string $created_at,
        public readonly string $updated_at,
        public readonly ?UserDTO $creator = null,
        public readonly ?array $users = null,
        public readonly ?string $last_message = null,
        public readonly ?string $last_message_at = null,
        public readonly ?UserDTO $other_user = null,
        public readonly bool $has_unread_messages = false,
    ) {}

    public static function fromModel(ChatRoom $chatRoom, ?User $currentUser = null): self
    {
        $users = null;
        if ($chatRoom->relationLoaded('users')) {
            $users = $chatRoom->users->map(function (User $user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->pivot->role,
                    'joined_at' => self::formatDateTime($user->pivot->joined_at),
                    'is_active' => $user->pivot->is_active,
                ];
            })->toArray();
        }

        $messages = null;
        if ($chatRoom->relationLoaded('messages')) {
            $messages = $chatRoom->messages->map(function ($message) {
                return [
                    'id' => $message->id,
                    'message' => $message->message,
                    'message_type' => $message->message_type,
                    'sender_id' => $message->sender_id,
                    'sender_name' => $message->sender?->name,
                    'created_at' => $message->created_at->toISOString(),
                    'is_edited' => $message->is_edited,
                    'reply_to_message_id' => $message->reply_to_message_id,
                ];
            })->toArray();
        }

        // Get latest message info for preview
        $lastMessage = null;
        $lastMessageAt = null;
        if ($chatRoom->relationLoaded('messages') && $chatRoom->messages->isNotEmpty()) {
            $latestMessage = $chatRoom->messages->first();
            $lastMessage = $latestMessage->message;
            $lastMessageAt = $latestMessage->created_at->toISOString();
        }

        // Determine the other user for direct chats
        $otherUser = null;
        if ($currentUser && $chatRoom->type === 'private' && $chatRoom->relationLoaded('users')) {
            $otherUser = $chatRoom->users->filter(function (User $user) use ($currentUser) {
                return $user->id !== $currentUser->id;
            })->first();
            $otherUser = $otherUser ? UserDTO::fromModel($otherUser) : null;
        }

        // Determine if there are unread messages
        $hasUnreadMessages = false;
        if ($currentUser && $lastMessageAt) {
            // Get the user's last visit time for this chat room
            $lastVisit = \App\Models\ChatRoomVisit::where('user_id', $currentUser->id)
                ->where('chat_room_id', $chatRoom->id)
                ->first();
            
            if ($lastVisit) {
                // Compare last message time with last visit time
                $lastMessageTime = \Carbon\Carbon::parse($lastMessageAt);
                $lastVisitTime = $lastVisit->last_visited_at;
                
                // If last message is newer than last visit, there are unread messages
                $hasUnreadMessages = $lastMessageTime->isAfter($lastVisitTime);
            } else {
                // If no visit record exists, assume there are unread messages
                $hasUnreadMessages = true;
            }
        }

        return new self(
            id: $chatRoom->id,
            name: $chatRoom->name,
            description: $chatRoom->description,
            type: $chatRoom->type,
            created_by: $chatRoom->created_by,
            is_active: $chatRoom->is_active,
            created_at: $chatRoom->created_at->toISOString(),
            updated_at: $chatRoom->updated_at->toISOString(),
            creator: $chatRoom->relationLoaded('creator') && $chatRoom->creator 
                ? UserDTO::fromModel($chatRoom->creator) 
                : null,
            users: $users,
            last_message: $lastMessage,
            last_message_at: $lastMessageAt,
            other_user: $otherUser,
            has_unread_messages: $hasUnreadMessages,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'created_by' => $this->created_by,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'creator' => $this->creator?->toArray(),
            'users' => $this->users,
            'last_message' => $this->last_message,
            'last_message_at' => $this->last_message_at,
            'other_user' => $this->other_user?->toArray(),
            'has_unread_messages' => $this->has_unread_messages,
        ];
    }

    /**
     * Format a datetime value to ISO string, handling both Carbon instances and strings.
     */
    private static function formatDateTime($dateTime): ?string
    {
        if (!$dateTime) {
            return null;
        }

        if (is_string($dateTime)) {
            return $dateTime;
        }

        return $dateTime->toISOString();
    }
}
