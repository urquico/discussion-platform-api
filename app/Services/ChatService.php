<?php

namespace App\Services;

use App\DTOs\ChatDTO;
use App\Events\MessageSent;
use App\Models\Message;
use App\Models\User;
use App\Models\ChatRoom;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Chat Service
 *
 * Handles creation and retrieval of chat messages between users,
 * focusing on chat room context.
 *
 * Features:
 * - Send messages within a specific chat room
 * - Create or find 1-on-1 chat rooms
 * - Persist messages in database
 * - Broadcast messages to chat room participants
 *
 * @package App\Services
 * @author Kurt Jacob Urquico
 * @version 1.1.0
 * @since 2025-10-07
 */
class ChatService
{
	/**
	 * Send a message from the authenticated user to a specified chat room.
	 *
	 * @param User $sender The authenticated user sending the message.
	 * @param string $roomId The ID of the chat room.
	 * @param string $messageText The message content.
	 * @return ChatDTO
	 * @throws ModelNotFoundException
	 * @throws \Exception
	 */
	public function sendRoomMessage(User $sender, string $roomId, string $messageText): ChatDTO
	{
		return DB::transaction(function () use ($sender, $roomId, $messageText) {
			// Retrieve the chat room and its users
			$room = ChatRoom::with('users')->findOrFail($roomId);

			// In a 1-on-1 chat, determine the receiver (the other user in the room).
			// This is necessary to maintain the receiver_id column in the messages table
			// and is a safeguard for broadcasting logic.
			$receiver = $room->users
				->where('id', '!=', $sender->id)
				->first();

			// Check if the sender is a member of the room and if a receiver was found (for 1-on-1 DMs)
			if (!$room->users->contains($sender->id)) {
				throw new \Exception('Sender is not a member of this chat room.');
			}

			// Determine receiver ID, defaulting to the found user's ID or null/0 if not found
			$receiverId = $receiver ? $receiver->id : null;

			// Save message in DB
			$message = Message::create([
				'chat_room_id' => $room->id,
				'sender_id'    => $sender->id,
				'receiver_id'  => $receiverId, // Still stored for DM clarity, although room-based broadcast is primary
				'message'      => $messageText,
				'read_at'      => null,
			]);

			$chatDto = ChatDTO::fromModel($message);

			// Broadcast to the room (e.g., private-chat.{room_id})
			// Reverb will handle authorization for this private channel.
			broadcast(new MessageSent($room->id, $chatDto))->toOthers();

			return $chatDto;
		});
	}

	/**
	 * Retrieve conversation for a chat room.
	 *
	 * @param string $chatRoomId
	 * @return ChatDTO[]
	 */
	public function getConversation(string $chatRoomId): array
	{
		$messages = Message::where('chat_room_id', $chatRoomId)
			->orderBy('created_at', 'asc')
			->get();

		return $messages->map(fn($message) => ChatDTO::fromModel($message))->toArray();
	}

	/**
	 * Find or create a chat room between two users (1-on-1 DM).
	 *
	 * @param User $user1
	 * @param User $user2
	 * @return ChatRoom
	 */
	public function findOrCreateRoom(User $user1, User $user2): ChatRoom
	{
		// Find a room that contains both user IDs
		$room = ChatRoom::whereHas('users', fn($q) => $q->where('user_id', $user1->id))
			->whereHas('users', fn($q) => $q->where('user_id', $user2->id))
			->first();

		if ($room) {
			return $room;
		}

		// If no room exists, create a new one and attach both users
		$room = ChatRoom::create();
		$room->users()->attach([$user1->id, $user2->id]);

		return $room;
	}
}
