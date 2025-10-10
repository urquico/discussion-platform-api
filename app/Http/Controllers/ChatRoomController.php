<?php

namespace App\Http\Controllers;

use App\Services\ChatRoomService;
use App\DTOs\ApiResponse;
use App\Events\ChatRoomUpdated;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ChatRoomController extends Controller
{
    public function __construct(
        private ChatRoomService $chatRoomService
    ) {}

    /**
     * Get all chat rooms for the authenticated user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getUserChatRooms(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search');
            $type = $request->get('type', 'all'); // all, private, group
            $user = Auth::user();

            $result = $this->chatRoomService->getUserChatRooms($user, $perPage, $search, $type);

            return ApiResponse::successWithPagination(
                $result['data'],
                $result['pagination'],
                'User chat rooms retrieved successfully'
            )->toJsonResponse();

        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to retrieve chat rooms: ' . $e->getMessage(),
                500
            )->toJsonResponse();
        }
    }

    /**
     * Create a new chat room.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function createChatRoom(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'nullable|string|max:255',
                'description' => 'nullable|string|max:1000',
                'type' => 'nullable|string|in:private,group',
                'user_ids' => 'nullable|array',
                'user_ids.*' => 'integer|exists:users,id',
            ]);

            $user = Auth::user();
            $chatRoom = $this->chatRoomService->createChatRoom($user, $validated);

            return ApiResponse::success(
                $chatRoom,
                'Chat room created successfully',
                201
            )->toJsonResponse();

        } catch (ValidationException $e) {
            return ApiResponse::error(
                'Validation failed: ' . implode(', ', $e->validator->errors()->all()),
                422
            )->toJsonResponse();

        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to create chat room: ' . $e->getMessage(),
                500
            )->toJsonResponse();
        }
    }

    /**
     * Get detailed information about a specific chat room.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function getChatRoomDetails(int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $chatRoom = $this->chatRoomService->getChatRoomDetails($id, $user);

            return ApiResponse::success(
                $chatRoom,
                'Chat room details retrieved successfully'
            )->toJsonResponse();

        } catch (\Exception $e) {
            $statusCode = $e->getMessage() === 'Chat room not found.' ? 404 : 
                         ($e->getMessage() === 'You are not authorized to view this chat room.' ? 403 : 500);

            return ApiResponse::error(
                $e->getMessage(),
                $statusCode
            )->toJsonResponse();
        }
    }

    /**
     * Add users to a chat room.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function addUsers(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'user_ids' => 'required|array|min:1',
                'user_ids.*' => 'integer|exists:users,id',
            ]);

            $user = Auth::user();
            $chatRoom = $this->chatRoomService->addUsersToChatRoom($id, $user, $validated['user_ids']);

            // Broadcast chat room update to all members
            broadcast(new ChatRoomUpdated($chatRoom, 'users_added', $validated['user_ids']));

            return ApiResponse::success(
                $chatRoom,
                'Users added to chat room successfully'
            )->toJsonResponse();

        } catch (ValidationException $e) {
            return ApiResponse::error(
                'Validation failed: ' . implode(', ', $e->validator->errors()->all()),
                422
            )->toJsonResponse();

        } catch (\Exception $e) {
            $statusCode = $e->getMessage() === 'Chat room not found.' ? 404 : 
                         ($e->getMessage() === 'Only admins can add users to this chat room.' ? 403 : 500);

            return ApiResponse::error(
                $e->getMessage(),
                $statusCode
            )->toJsonResponse();
        }
    }
}
