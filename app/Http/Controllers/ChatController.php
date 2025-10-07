<?php

namespace App\Http\Controllers;

use App\DTOs\ApiResponse;
use App\DTOs\ChatDTO;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;
use App\Models\User; // Required to fetch the target user for room creation

/**
 * Chat Management Controller
 *
 * Handles real-time chat messaging between users.
 * Supports storing messages persistently and broadcasting
 * them to specific private channels using Reverb.
 *
 * Features:
 * - Sending direct messages within a chat room
 * - Creating or retrieving direct message chat rooms
 * - Persisting messages in the database
 * - Broadcasting messages to receiver in real time
 *
 * @package App\Http\Controllers
 * @author Kurt Jacob Urquico
 * @version 1.1.0
 * @since 2025-10-07
 *
 * @see App\Services\ChatService
 * @see App\DTOs\ApiResponse
 * @see App\DTOs\ChatDTO
 */
class ChatController extends Controller
{
	protected ChatService $chatService;

	public function __construct(ChatService $chatService)
	{
		$this->chatService = $chatService;
	}

	/**
	 * Creates a new chat room between the authenticated user and a specified user.
	 * If a room already exists, it returns the existing room ID.
	 *
	 * @param Request $request
	 * @return JsonResponse
	 */
	public function createRoom(Request $request): JsonResponse
	{
		try {
			// Validate request input for the target user ID
			$validated = $request->validate([
				'target_user_id' => ['required', 'integer', 'exists:users,id'],
			]);

			// Ensure the user is not trying to create a room with themselves (optional check)
			if ($request->user()->id == $validated['target_user_id']) {
				throw ValidationException::withMessages([
					'target_user_id' => ['Cannot create a chat room with yourself.'],
				]);
			}

			// Retrieve the target user model
			$targetUser = User::findOrFail($validated['target_user_id']);

			// Find or create the chat room using the service
			$room = $this->chatService->findOrCreateRoom($request->user(), $targetUser);

			// Return standardized API response with the chat room ID
			return ApiResponse::success(
				data: ['chat_room_id' => $room->id],
				message: 'Chat room created or retrieved successfully.',
				statusCode: 200
			)->toJsonResponse();
		} catch (ValidationException $e) {
			return ApiResponse::error(
				message: 'Validation failed.',
				statusCode: 422,
				data: $e->errors()
			)->toJsonResponse();
		} catch (ModelNotFoundException $e) {
			return ApiResponse::error(
				message: 'Target user not found.',
				statusCode: 404,
				data: $e->getMessage()
			)->toJsonResponse();
		} catch (Exception $e) {
			return ApiResponse::error(
				message: 'Failed to create chat room.',
				statusCode: 500,
				data: $e->getMessage()
			)->toJsonResponse();
		}
	}

	/**
	 * Send a message from the authenticated user to a specified chat room.
	 *
	 * NOTE: This assumes you have updated your ChatService to include a
	 * `sendRoomMessage(User $sender, int $roomId, string $messageText)` method
	 * that uses the room ID instead of the receiver ID.
	 *
	 * @param Request $request
	 * @return JsonResponse
	 */
	public function send(Request $request): JsonResponse
	{
		try {
			// Validate request input
			$validated = $request->validate([
				// UPDATED: Now requires chat_room_id
				'chat_room_id' => ['required', 'exists:chat_rooms,id'],
				'message' => ['required', 'string', 'max:500'],
			]);

			// Assuming the ChatService has been updated for room-based messaging:
			$message = $this->chatService->sendRoomMessage( // Assumed new method name
				$request->user(),
				$validated['chat_room_id'],
				$validated['message']
			);

			// Return standardized API response with ChatDTO
			return ApiResponse::success(
				data: $message,
				message: 'Message sent successfully.',
				statusCode: 201
			)->toJsonResponse();
		} catch (ValidationException $e) {
			return ApiResponse::error(
				message: 'Validation failed.',
				statusCode: 422,
				data: $e->errors()
			)->toJsonResponse();
		} catch (ModelNotFoundException $e) {
			return ApiResponse::error(
				message: 'Chat room or required component not found.',
				statusCode: 404,
				data: $e->getMessage()
			)->toJsonResponse();
		} catch (Exception $e) {
			return ApiResponse::error(
				message: 'Failed to send message.',
				statusCode: 500,
				data: $e->getMessage()
			)->toJsonResponse();
		}
	}
}
