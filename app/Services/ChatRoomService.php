<?php

namespace App\Services;

use App\Models\ChatRoom;
use App\Models\User;
use App\DTOs\ChatRoomDTO;
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
    /**
     * Get all chat rooms for a specific user.
     *
     * @param User $user
     * @param int $perPage
     * @param string|null $search
     * @return array
     */
    public function getUserChatRooms(User $user, int $perPage = 15, ?string $search = null): array
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

        $chatRooms = $query->orderBy('updated_at', 'desc')
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
            'messages' => function ($query) {
                $query->with(['sender', 'receiver'])->latest()->limit(50);
            }
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
}
