<?php

namespace App\Services;

use App\Models\ChatRoom;
use App\Models\User;
use App\Models\ChatMessage;
use App\DTOs\ChatRoomDTO;
use App\Services\MessageEncryptionService;
use Illuminate\Support\Facades\DB;

/**
 * Chat Room Service
 *
 * Handles chat room operations including creation, retrieval, and user management.
 * Provides business logic for chat room functionality.
 *
 * Features:
 * - Get user's chat rooms
 * - Create new chat rooms
 * - Get chat room details with users and messages
 * - Add users to chat rooms
 *
 * @package App\Services
 * @version 1.0.0
 * @since 2025-01-09
 *
 * @see App\Models\ChatRoom
 * @see App\DTOs\ChatRoomDTO
 */
class ChatRoomService
{
    public function __construct(
        private MessageEncryptionService $encryptionService
    ) {}

    /**
     * Get all chat rooms for a specific user.
     *
     * @param User $user
     * @param int $perPage
     * @param string|null $search
     * @param string $type
     * @return array
     */
    public function getUserChatRooms(User $user, int $perPage = 15, ?string $search = null, string $type = 'all'): array
    {
        $query = $user->chatRooms()
            ->with([
                'creator', 
                'users' => function ($query) {
                    $query->wherePivot('is_active', true);
                },
                'messages' => function ($query) {
                    $query->latest()->limit(1); // Get latest message for preview
                }
            ])
            ->wherePivot('is_active', true);

        // Add type filter
        if ($type !== 'all') {
            $query->where('chat_rooms.type', $type);
        }

        // Add search functionality if provided
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('chat_rooms.name', 'like', "%{$search}%")
                  ->orWhere('chat_rooms.description', 'like', "%{$search}%")
                  ->orWhereHas('users', function ($userQuery) use ($search) {
                      $userQuery->where('name', 'like', "%{$search}%")
                               ->where('users.id', '!=', auth()->id());
                  });
            });
        }

        $chatRooms = $query->leftJoin('chat_messages', function ($join) {
                                $join->on('chat_rooms.id', '=', 'chat_messages.chat_room_id')
                                     ->whereRaw('chat_messages.id = (SELECT MAX(id) FROM chat_messages WHERE chat_room_id = chat_rooms.id)');
                            })
                            ->orderBy('chat_messages.updated_at', 'desc')
                            ->orderBy('chat_rooms.updated_at', 'desc')
                            ->select('chat_rooms.*')
                            ->paginate($perPage);

        $chatRoomDTOs = $chatRooms->map(function ($chatRoom) use ($user) {
            return ChatRoomDTO::fromModel($chatRoom, $user);
        });

        return [
            'data' => $chatRoomDTOs,
            'pagination' => [
                'current_page' => $chatRooms->currentPage(),
                'per_page' => $chatRooms->perPage(),
                'total' => $chatRooms->total(),
                'last_page' => $chatRooms->lastPage(),
                'from' => $chatRooms->firstItem(),
                'to' => $chatRooms->lastItem(),
            ]
        ];
    }

    /**
     * Create a new chat room.
     *
     * @param User $user
     * @param array $data
     * @return ChatRoomDTO
     */
    public function createChatRoom(User $user, array $data): ChatRoomDTO
    {
        return DB::transaction(function () use ($user, $data) {
            // Create the chat room
            $chatRoom = ChatRoom::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'type' => $data['type'] ?? 'private',
                'created_by' => $user->id,
                'is_active' => true,
            ]);

            // Add the creator as the first user with admin role
            $chatRoom->users()->attach($user->id, [
                'role' => 'admin',
                'is_active' => true,
                'joined_at' => now(),
            ]);

            // Add additional users if provided
            if (isset($data['user_ids']) && is_array($data['user_ids'])) {
                foreach ($data['user_ids'] as $userId) {
                    $chatRoom->users()->attach($userId, [
                        'role' => 'member',
                        'is_active' => true,
                        'joined_at' => now(),
                    ]);
                }
            }

            // Load relationships for the response
            $chatRoom->load(['creator', 'users']);

            return ChatRoomDTO::fromModel($chatRoom, $user);
        });
    }

    /**
     * Get detailed information about a specific chat room.
     *
     * @param int $chatRoomId
     * @param User $user
     * @return ChatRoomDTO
     * @throws \Exception When chat room not found or user not authorized
     */
    public function getChatRoomDetails(int $chatRoomId, User $user): ChatRoomDTO
    {
        $chatRoom = ChatRoom::with([
            'creator',
            'users' => function ($query) {
                $query->wherePivot('is_active', true);
            },
        ])->find($chatRoomId);

        if (!$chatRoom) {
            throw new \Exception('Chat room not found.');
        }

        // Check if user is a member of this chat room
        $isMember = $chatRoom->users()->wherePivot('user_id', $user->id)
            ->wherePivot('is_active', true)
            ->exists();

        if (!$isMember) {
            throw new \Exception('You are not authorized to view this chat room.');
        }

        return ChatRoomDTO::fromModel($chatRoom, $user);
    }

    /**
     * Add users to a chat room.
     *
     * @param int $chatRoomId
     * @param User $user
     * @param array $userIds
     * @return ChatRoomDTO
     * @throws \Exception When not authorized or chat room not found
     */
    public function addUsersToChatRoom(int $chatRoomId, User $user, array $userIds): ChatRoomDTO
    {
        $chatRoom = ChatRoom::find($chatRoomId);

        if (!$chatRoom) {
            throw new \Exception('Chat room not found.');
        }

        // Check if user is admin of this chat room
        $userRole = $chatRoom->users()
            ->wherePivot('user_id', $user->id)
            ->wherePivot('is_active', true)
            ->value('role');

        if ($userRole !== 'admin') {
            throw new \Exception('Only admins can add users to this chat room.');
        }

        // Add users to the chat room
        foreach ($userIds as $userId) {
            // Check if user is already a member
            $isAlreadyMember = $chatRoom->users()
                ->wherePivot('user_id', $userId)
                ->exists();

            if (!$isAlreadyMember) {
                $chatRoom->users()->attach($userId, [
                    'role' => 'member',
                    'is_active' => true,
                    'joined_at' => now(),
                ]);
            }
        }

        // Reload the chat room with relationships
        $chatRoom->load(['creator', 'users']);

        return ChatRoomDTO::fromModel($chatRoom, $user);
    }

    /**
     * Send a message to a chat room.
     *
     * @param int $chatRoomId
     * @param User $user
     * @param array $data
     * @return ChatMessage
     * @throws \Exception When not authorized or chat room not found
     */
    public function sendMessage(int $chatRoomId, User $user, array $data): ChatMessage
    {
        $chatRoom = ChatRoom::find($chatRoomId);

        if (!$chatRoom) {
            throw new \Exception('Chat room not found.');
        }

        // Check if user is a member of this chat room
        $isMember = $chatRoom->users()
            ->wherePivot('user_id', $user->id)
            ->wherePivot('is_active', true)
            ->exists();

        if (!$isMember) {
            throw new \Exception('You are not authorized to send messages to this chat room.');
        }

        // Create the message
        $message = ChatMessage::create([
            'sender_id' => $user->id,
            'chat_room_id' => $chatRoomId,
            'message' => $this->encryptionService->encryptMessage($data['message']),
            'message_type' => $data['message_type'] ?? 'text',
            'reply_to_message_id' => $data['reply_to_message_id'] ?? null,
        ]);

        // Load the sender and reply relationships for broadcasting
        $message->load(['sender', 'replyTo.sender']);

        return $message;
    }

    /**
     * Get messages for a chat room.
     *
     * @param int $chatRoomId
     * @param User $user
     * @param int $perPage
     * @return array
     * @throws \Exception When not authorized or chat room not found
     */
    public function getChatRoomMessages(int $chatRoomId, User $user, int $perPage = 50): array
    {
        $chatRoom = ChatRoom::find($chatRoomId);

        if (!$chatRoom) {
            throw new \Exception('Chat room not found.');
        }

        // Check if user is a member of this chat room
        $isMember = $chatRoom->users()
            ->wherePivot('user_id', $user->id)
            ->wherePivot('is_active', true)
            ->exists();

        if (!$isMember) {
            throw new \Exception('You are not authorized to view messages in this chat room.');
        }

        $messages = ChatMessage::where('chat_room_id', $chatRoomId)
            ->with(['sender', 'replyTo.sender', 'replies.sender', 'reactions.user'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return [
            'data' => $messages->items(),
            'pagination' => [
                'current_page' => $messages->currentPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
                'last_page' => $messages->lastPage(),
                'from' => $messages->firstItem(),
                'to' => $messages->lastItem(),
            ]
        ];
    }

    /**
     * Get replies to a specific message.
     *
     * @param int $messageId
     * @param User $user
     * @param int $perPage
     * @return array
     * @throws \Exception When not authorized or message not found
     */
    public function getMessageReplies(int $messageId, User $user, int $perPage = 20): array
    {
        // Get the original message to verify access
        $originalMessage = ChatMessage::with('chatRoom')->find($messageId);
        
        if (!$originalMessage) {
            throw new \Exception('Message not found.');
        }

        // Check if user is a member of the chat room
        $isMember = $originalMessage->chatRoom->users()
            ->wherePivot('user_id', $user->id)
            ->wherePivot('is_active', true)
            ->exists();

        if (!$isMember) {
            throw new \Exception('You are not authorized to view replies for this message.');
        }

        // Get replies to this message
        $replies = ChatMessage::where('reply_to_message_id', $messageId)
            ->with(['sender', 'replyTo.sender'])
            ->orderBy('created_at', 'asc')
            ->paginate($perPage);

        return [
            'data' => $replies->items(),
            'pagination' => [
                'current_page' => $replies->currentPage(),
                'per_page' => $replies->perPage(),
                'total' => $replies->total(),
                'last_page' => $replies->lastPage(),
                'from' => $replies->firstItem(),
                'to' => $replies->lastItem(),
            ]
        ];
    }

    /**
     * Edit a message.
     *
     * @param int $messageId
     * @param User $user
     * @param string $newMessage
     * @return ChatMessage
     * @throws \Exception When not authorized or message not found
     */
    public function editMessage(int $messageId, User $user, string $newMessage): ChatMessage
    {
        $message = ChatMessage::find($messageId);

        if (!$message) {
            throw new \Exception('Message not found.');
        }

        // Check if user is the sender of the message
        if ($message->sender_id !== $user->id) {
            throw new \Exception('You are not authorized to edit this message.');
        }

        // Update the message
        $message->update([
            'message' => $this->encryptionService->encryptMessage($newMessage),
            'is_edited' => true,
            'edited_at' => now(),
        ]);

        // Load the sender relationship
        $message->load('sender');

        return $message;
    }

    /**
     * Delete a message.
     *
     * @param int $messageId
     * @param User $user
     * @return ChatMessage
     * @throws \Exception When not authorized or message not found
     */
    public function deleteMessage(int $messageId, User $user): ChatMessage
    {
        $message = ChatMessage::find($messageId);

        if (!$message) {
            throw new \Exception('Message not found.');
        }

        // Check if user is the sender of the message or admin of the chat room
        $isSender = $message->sender_id === $user->id;
        $isAdmin = $message->chatRoom->users()
            ->wherePivot('user_id', $user->id)
            ->wherePivot('is_active', true)
            ->wherePivot('role', 'admin')
            ->exists();

        if (!$isSender && !$isAdmin) {
            throw new \Exception('You are not authorized to delete this message.');
        }

        // Soft delete the message by updating its content
        $message->update([
            'message' => $this->encryptionService->encryptMessage('[Message deleted]'),
            'is_edited' => true,
            'edited_at' => now(),
        ]);

        // Load the sender relationship
        $message->load('sender');

        return $message;
    }
}
