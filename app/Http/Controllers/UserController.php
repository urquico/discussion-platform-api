<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\DTOs\ApiResponse;
use App\DTOs\UserDTO;
use App\Services\UserStatusService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    protected UserStatusService $userStatusService;

    public function __construct(UserStatusService $userStatusService)
    {
        $this->userStatusService = $userStatusService;
    }
    /**
     * Get list of users available for conversations.
     * Excludes the currently logged-in user and users they already have chat rooms with.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getAvailableUsers(Request $request): JsonResponse
    {
        try {
            $currentUser = Auth::user();
            $perPage = $request->get('per_page', 20);
            $search = $request->get('search');

            // Get user IDs that the current user already has PRIVATE chat rooms with
            $existingChatUserIds = DB::table('chat_room_users')
                ->where('chat_room_users.user_id', $currentUser->id)
                ->where('chat_room_users.is_active', true)
                ->join('chat_room_users as other_users', function($join) {
                    $join->on('chat_room_users.chat_room_id', '=', 'other_users.chat_room_id')
                         ->whereRaw('other_users.user_id != chat_room_users.user_id');
                })
                ->join('chat_rooms', 'chat_room_users.chat_room_id', '=', 'chat_rooms.id')
                ->where('chat_rooms.type', 'private') // Only exclude users from private chat rooms
                ->where('chat_rooms.is_active', true)
                ->pluck('other_users.user_id')
                ->unique()
                ->toArray();

            // Build query to exclude current user and users with existing private chat rooms
            $query = User::where('id', '!=', $currentUser->id)
                ->whereNotIn('id', $existingChatUserIds);

            // Add search functionality if provided
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            // Order by name and paginate
            $users = $query->orderBy('name', 'asc')
                          ->paginate($perPage);

            // Convert to DTOs
            $userDTOs = $users->map(function (User $user) {
                return UserDTO::fromModel($user);
            });

            return ApiResponse::successWithPagination(
                $userDTOs,
                [
                    'current_page' => $users->currentPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                    'last_page' => $users->lastPage(),
                    'from' => $users->firstItem(),
                    'to' => $users->lastItem(),
                ],
                'Available users retrieved successfully'
            )->toJsonResponse();

        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to retrieve available users: ' . $e->getMessage(),
                500
            )->toJsonResponse();
        }
    }

    /**
     * Get user suggestions for starting conversations.
     * Excludes users that the current user already has chat rooms with.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getUserSuggestions(Request $request): JsonResponse
    {
        try {
            $currentUser = Auth::user();
            $limit = $request->get('limit', 10);

            // Get user IDs that the current user already has PRIVATE chat rooms with
            $existingChatUserIds = DB::table('chat_room_users')
                ->where('chat_room_users.user_id', $currentUser->id)
                ->where('chat_room_users.is_active', true)
                ->join('chat_room_users as other_users', function($join) {
                    $join->on('chat_room_users.chat_room_id', '=', 'other_users.chat_room_id')
                         ->whereRaw('other_users.user_id != chat_room_users.user_id');
                })
                ->join('chat_rooms', 'chat_room_users.chat_room_id', '=', 'chat_rooms.id')
                ->where('chat_rooms.type', 'private') // Only exclude users from private chat rooms
                ->where('chat_rooms.is_active', true)
                ->pluck('other_users.user_id')
                ->unique()
                ->toArray();

            // For now, return recent users (excluding current user and existing private chat partners)
            // Implement more sophisticated suggestions
            // based on mutual connections, shared interests, etc.
            $suggestedUsers = User::where('id', '!=', $currentUser->id)
                ->whereNotIn('id', $existingChatUserIds)
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            $userDTOs = $suggestedUsers->map(function (User $user) {
                return UserDTO::fromModel($user);
            });

            return ApiResponse::success(
                $userDTOs,
                'User suggestions retrieved successfully'
            )->toJsonResponse();

        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to retrieve user suggestions: ' . $e->getMessage(),
                500
            )->toJsonResponse();
        }
    }

    /**
     * Search users by name or email.
     * Excludes users that the current user already has chat rooms with.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function searchUsers(Request $request): JsonResponse
    {
        try {
            $currentUser = Auth::user();
            $search = $request->get('q', '');
            $limit = $request->get('limit', 20);

            if (empty($search)) {
                return ApiResponse::error(
                    'Search query is required',
                    400
                )->toJsonResponse();
            }

            // Get user IDs that the current user already has PRIVATE chat rooms with
            $existingChatUserIds = DB::table('chat_room_users')
                ->where('chat_room_users.user_id', $currentUser->id)
                ->where('chat_room_users.is_active', true)
                ->join('chat_room_users as other_users', function($join) {
                    $join->on('chat_room_users.chat_room_id', '=', 'other_users.chat_room_id')
                         ->whereRaw('other_users.user_id != chat_room_users.user_id');
                })
                ->join('chat_rooms', 'chat_room_users.chat_room_id', '=', 'chat_rooms.id')
                ->where('chat_rooms.type', 'private') // Only exclude users from private chat rooms
                ->where('chat_rooms.is_active', true)
                ->pluck('other_users.user_id')
                ->unique()
                ->toArray();

            $users = User::where('id', '!=', $currentUser->id)
                ->whereNotIn('id', $existingChatUserIds)
                ->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                          ->orWhere('email', 'like', "%{$search}%");
                })
                ->orderBy('name', 'asc')
                ->limit($limit)
                ->get();

            $userDTOs = $users->map(function (User $user) {
                return UserDTO::fromModel($user);
            });

            return ApiResponse::success(
                $userDTOs,
                'User search completed successfully'
            )->toJsonResponse();

        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to search users: ' . $e->getMessage(),
                500
            )->toJsonResponse();
        }
    }

    /**
     * Get all users for group chat creation.
     * Excludes the currently logged-in user.
     * Includes pagination and search functionality.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getAllUsersForGroupChat(Request $request): JsonResponse
    {
        try {
            $currentUser = Auth::user();
            $perPage = $request->get('per_page', 20);
            $search = $request->get('search');

            // Validate sort parameters
            $allowedSortFields = ['name', 'created_at', 'email'];
            $allowedSortOrders = ['asc', 'desc'];
        
            // Build query to exclude current user
            $query = User::where('id', '!=', $currentUser->id);

            // Add search functionality if provided
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            // Paginate results
            $users = $query->paginate($perPage);

            // Convert to DTOs
            $userDTOs = $users->map(function (User $user) {
                return UserDTO::fromModel($user);
            });

            return ApiResponse::successWithPagination(
                $userDTOs,
                [
                    'current_page' => $users->currentPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                    'last_page' => $users->lastPage(),
                    'from' => $users->firstItem(),
                    'to' => $users->lastItem(),
                ],
                'Users for group chat retrieved successfully'
            )->toJsonResponse();

        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to retrieve users for group chat: ' . $e->getMessage(),
                500
            )->toJsonResponse();
        }
    }

    /**
     * Set the current user's online status.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function setStatus(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'status' => 'required|string|in:online,offline,away,busy'
            ]);

            $user = Auth::user();
            $status = $request->input('status');

            $success = $this->userStatusService->setUserStatus($user, $status);

            if ($success) {
                return ApiResponse::success([
                    'user_id' => $user->id,
                    'status' => $user->fresh()->status,
                    'is_online' => $user->fresh()->is_online,
                    'last_seen_at' => $user->fresh()->last_seen_at,
                ], 'User status updated successfully')->toJsonResponse();
            }

            return ApiResponse::error('Failed to update user status', 400)->toJsonResponse();

        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to update user status: ' . $e->getMessage(),
                500
            )->toJsonResponse();
        }
    }

    /**
     * Get online users for a specific chat room.
     *
     * @param Request $request
     * @param int $chatRoomId
     * @return JsonResponse
     */
    public function getOnlineUsersForChatRoom(Request $request, int $chatRoomId): JsonResponse
    {
        try {
            $onlineUsers = $this->userStatusService->getOnlineUsersForChatRoom($chatRoomId);

            return ApiResponse::success([
                'chat_room_id' => $chatRoomId,
                'online_users' => $onlineUsers,
                'count' => count($onlineUsers),
            ], 'Online users retrieved successfully')->toJsonResponse();

        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to retrieve online users: ' . $e->getMessage(),
                500
            )->toJsonResponse();
        }
    }

    /**
     * Get all online users.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getAllOnlineUsers(Request $request): JsonResponse
    {
        try {
            $onlineUsers = $this->userStatusService->getAllOnlineUsers();

            return ApiResponse::success([
                'online_users' => $onlineUsers,
                'count' => count($onlineUsers),
            ], 'All online users retrieved successfully')->toJsonResponse();

        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to retrieve online users: ' . $e->getMessage(),
                500
            )->toJsonResponse();
        }
    }

    /**
     * Check if a specific user is online.
     *
     * @param Request $request
     * @param int $userId
     * @return JsonResponse
     */
    public function checkUserOnlineStatus(Request $request, int $userId): JsonResponse
    {
        try {
            $user = User::find($userId);
            
            if (!$user) {
                return ApiResponse::error('User not found', 404)->toJsonResponse();
            }

            $isOnline = $this->userStatusService->isUserOnline($userId);

            return ApiResponse::success([
                'user_id' => $userId,
                'is_online' => $isOnline,
                'status' => $user->status,
                'last_seen_at' => $user->last_seen_at,
                'online_status' => $user->online_status,
            ], 'User online status retrieved successfully')->toJsonResponse();

        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to check user online status: ' . $e->getMessage(),
                500
            )->toJsonResponse();
        }
    }

    /**
     * Get current user's status.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getCurrentUserStatus(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            return ApiResponse::success([
                'user_id' => $user->id,
                'status' => $user->status,
                'is_online' => $user->is_online,
                'last_seen_at' => $user->last_seen_at,
                'online_status' => $user->online_status,
            ], 'Current user status retrieved successfully')->toJsonResponse();

        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to retrieve current user status: ' . $e->getMessage(),
                500
            )->toJsonResponse();
        }
    }
}
