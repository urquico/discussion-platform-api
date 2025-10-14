<?php

namespace App\Http\Controllers;

use App\Services\ChatRoomService;
use App\Services\ChatRoomVisitService;
use App\Services\NotificationService;
use App\DTOs\ApiResponse;
use App\Events\MessageSent;
use App\Events\TypingIndicator;
use App\Events\ChatRoomUpdated;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ChatMessageController extends Controller
{
    public function __construct(
        private ChatRoomService $chatRoomService,
        private ChatRoomVisitService $chatRoomVisitService,
        private NotificationService $notificationService
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

            // Record the user's visit to this chat room
            $this->chatRoomVisitService->recordVisit($user->id, $chatRoomId);

            // Create notifications for all active members except the sender
            $activeMembers = $message->chatRoom->activeUsers()->get();
            foreach ($activeMembers as $member) {
                if ($member->id !== $user->id) {
                    $this->notificationService->createChatNotification(
                        $member,
                        $user,
                        $message->chatRoom->name ?? '',
                        $chatRoomId
                    );
                }
            }

            // Broadcast the message to all users in the chat room
            broadcast(new MessageSent($message))->toOthers();
            
            // Broadcast chat room update to all members for real-time chat list updates
            broadcast(new ChatRoomUpdated($message->chatRoom, 'message_sent'))->toOthers();

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

            // Record the user's visit to this chat room
            $this->chatRoomVisitService->recordVisit($user->id, $chatRoomId);

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
     * Get replies to a specific message.
     *
     * @param Request $request
     * @param int $messageId
     * @return JsonResponse
     */
    public function getMessageReplies(Request $request, int $messageId): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 20);
            $user = Auth::user();

            $replies = $this->chatRoomService->getMessageReplies($messageId, $user, $perPage);

            return ApiResponse::successWithPagination(
                $replies['data'],
                $replies['pagination'],
                'Message replies retrieved successfully'
            )->toJsonResponse();

        } catch (\Exception $e) {
            $statusCode = $e->getMessage() === 'Message not found.' ? 404 : 
                         ($e->getMessage() === 'You are not authorized to view replies for this message.' ? 403 : 500);

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

            // Create notifications for all active members except the editor
            $activeMembers = $message->chatRoom->activeUsers()->get();
            foreach ($activeMembers as $member) {
                if ($member->id !== $user->id) {
                    $this->notificationService->createChatNotification(
                        $member,
                        $user,
                        $message->chatRoom->name ?? 'Chat Room',
                        $message->chatRoom->id
                    );
                }
            }

            // Broadcast the edited message
            broadcast(new MessageSent($message, 'edited'))->toOthers();
            
            // Broadcast chat room update
            broadcast(new ChatRoomUpdated($message->chatRoom, 'message_edited'))->toOthers();

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
            
            // Broadcast chat room update
            broadcast(new ChatRoomUpdated($message->chatRoom, 'message_deleted'))->toOthers();

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

    /**
     * Start typing indicator.
     *
     * @param Request $request
     * @param int $chatRoomId
     * @return JsonResponse
     */
    public function startTyping(Request $request, int $chatRoomId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Verify user is a member of the chat room
            $chatRoom = \App\Models\ChatRoom::find($chatRoomId);
            if (!$chatRoom) {
                return ApiResponse::error('Chat room not found.', 404)->toJsonResponse();
            }

            $isMember = $chatRoom->users()
                ->wherePivot('user_id', $user->id)
                ->wherePivot('is_active', true)
                ->exists();

            if (!$isMember) {
                return ApiResponse::error('You are not authorized to access this chat room.', 403)->toJsonResponse();
            }

            // Broadcast typing indicator to other users
            broadcast(new TypingIndicator($user, $chatRoomId, true))->toOthers();

            return ApiResponse::success(
                ['message' => 'Typing indicator started'],
                'Typing indicator started successfully'
            )->toJsonResponse();

        } catch (\Exception $e) {
            return ApiResponse::error(
                $e->getMessage(),
                500
            )->toJsonResponse();
        }
    }

    /**
     * Stop typing indicator.
     *
     * @param Request $request
     * @param int $chatRoomId
     * @return JsonResponse
     */
    public function stopTyping(Request $request, int $chatRoomId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Verify user is a member of the chat room
            $chatRoom = \App\Models\ChatRoom::find($chatRoomId);
            if (!$chatRoom) {
                return ApiResponse::error('Chat room not found.', 404)->toJsonResponse();
            }

            $isMember = $chatRoom->users()
                ->wherePivot('user_id', $user->id)
                ->wherePivot('is_active', true)
                ->exists();

            if (!$isMember) {
                return ApiResponse::error('You are not authorized to access this chat room.', 403)->toJsonResponse();
            }

            // Broadcast typing indicator stop to other users
            broadcast(new TypingIndicator($user, $chatRoomId, false))->toOthers();

            return ApiResponse::success(
                ['message' => 'Typing indicator stopped'],
                'Typing indicator stopped successfully'
            )->toJsonResponse();

        } catch (\Exception $e) {
            return ApiResponse::error(
                $e->getMessage(),
                500
            )->toJsonResponse();
        }
    }

    /**
     * Record when a user leaves a chat room.
     *
     * @param Request $request
     * @param int $chatRoomId
     * @return JsonResponse
     */
    public function leaveChatRoom(Request $request, int $chatRoomId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Verify user is a member of the chat room
            $chatRoom = \App\Models\ChatRoom::find($chatRoomId);
            if (!$chatRoom) {
                return ApiResponse::error('Chat room not found.', 404)->toJsonResponse();
            }

            $isMember = $chatRoom->users()
                ->wherePivot('user_id', $user->id)
                ->wherePivot('is_active', true)
                ->exists();

            if (!$isMember) {
                return ApiResponse::error('You are not authorized to access this chat room.', 403)->toJsonResponse();
            }

            // Record the user's visit (exit time) to this chat room
            // Note: We only record the exit for the leaving user, not for others
            $this->chatRoomVisitService->recordVisit($user->id, $chatRoomId);

            return ApiResponse::success(
                [
                    'message' => 'Chat room exit recorded',
                    'user_id' => $user->id,
                    'chat_room_id' => $chatRoomId,
                    'exited_at' => now()->toISOString()
                ],
                'Chat room exit recorded successfully'
            )->toJsonResponse();

        } catch (\Exception $e) {
            return ApiResponse::error(
                $e->getMessage(),
                500
            )->toJsonResponse();
        }
    }
}
