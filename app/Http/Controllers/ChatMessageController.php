<?php

namespace App\Http\Controllers;

use App\Services\ChatRoomService;
use App\DTOs\ApiResponse;
use App\Events\MessageSent;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ChatMessageController extends Controller
{
    public function __construct(
        private ChatRoomService $chatRoomService
    ) {}

    /**
     * Send a message to a chat room.
     *
     * @param Request $request
     * @param int $chatRoomId
     * @return JsonResponse
     */
    public function sendMessage(Request $request, int $chatRoomId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'message' => 'required|string|max:2000',
                'message_type' => 'nullable|string|in:text,image,file',
                'reply_to_message_id' => 'nullable|integer|exists:chat_messages,id',
            ]);

            $user = Auth::user();
            $message = $this->chatRoomService->sendMessage($chatRoomId, $user, $validated);

            // Broadcast the message to all users in the chat room
            broadcast(new MessageSent($message))->toOthers();

            return ApiResponse::success(
                $message,
                'Message sent successfully',
                201
            )->toJsonResponse();

        } catch (ValidationException $e) {
            return ApiResponse::error(
                'Validation failed: ' . implode(', ', $e->validator->errors()->all()),
                422
            )->toJsonResponse();

        } catch (\Exception $e) {
            $statusCode = $e->getMessage() === 'Chat room not found.' ? 404 : 
                         ($e->getMessage() === 'You are not authorized to send messages to this chat room.' ? 403 : 500);

            return ApiResponse::error(
                $e->getMessage(),
                $statusCode
            )->toJsonResponse();
        }
    }

    /**
     * Get messages for a chat room.
     *
     * @param Request $request
     * @param int $chatRoomId
     * @return JsonResponse
     */
    public function getMessages(Request $request, int $chatRoomId): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 50);
            $user = Auth::user();

            $messages = $this->chatRoomService->getChatRoomMessages($chatRoomId, $user, $perPage);

            return ApiResponse::successWithPagination(
                $messages['data'],
                $messages['pagination'],
                'Messages retrieved successfully'
            )->toJsonResponse();

        } catch (\Exception $e) {
            $statusCode = $e->getMessage() === 'Chat room not found.' ? 404 : 
                         ($e->getMessage() === 'You are not authorized to view messages in this chat room.' ? 403 : 500);

            return ApiResponse::error(
                $e->getMessage(),
                $statusCode
            )->toJsonResponse();
        }
    }

    /**
     * Edit a message.
     *
     * @param Request $request
     * @param int $messageId
     * @return JsonResponse
     */
    public function editMessage(Request $request, int $messageId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'message' => 'required|string|max:2000',
            ]);

            $user = Auth::user();
            $message = $this->chatRoomService->editMessage($messageId, $user, $validated['message']);

            // Broadcast the edited message
            broadcast(new MessageSent($message, 'edited'))->toOthers();

            return ApiResponse::success(
                $message,
                'Message edited successfully'
            )->toJsonResponse();

        } catch (ValidationException $e) {
            return ApiResponse::error(
                'Validation failed: ' . implode(', ', $e->validator->errors()->all()),
                422
            )->toJsonResponse();

        } catch (\Exception $e) {
            $statusCode = $e->getMessage() === 'Message not found.' ? 404 : 
                         ($e->getMessage() === 'You are not authorized to edit this message.' ? 403 : 500);

            return ApiResponse::error(
                $e->getMessage(),
                $statusCode
            )->toJsonResponse();
        }
    }

    /**
     * Delete a message.
     *
     * @param int $messageId
     * @return JsonResponse
     */
    public function deleteMessage(int $messageId): JsonResponse
    {
        try {
            $user = Auth::user();
            $message = $this->chatRoomService->deleteMessage($messageId, $user);

            // Broadcast the deleted message
            broadcast(new MessageSent($message, 'deleted'))->toOthers();

            return ApiResponse::success(
                ['message_id' => $messageId],
                'Message deleted successfully'
            )->toJsonResponse();

        } catch (\Exception $e) {
            $statusCode = $e->getMessage() === 'Message not found.' ? 404 : 
                         ($e->getMessage() === 'You are not authorized to delete this message.' ? 403 : 500);

            return ApiResponse::error(
                $e->getMessage(),
                $statusCode
            )->toJsonResponse();
        }
    }
}
