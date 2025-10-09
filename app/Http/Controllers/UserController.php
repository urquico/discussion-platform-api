<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\DTOs\ApiResponse;
use App\DTOs\UserDTO;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
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

            // Get user IDs that the current user already has chat rooms with
            $existingChatUserIds = DB::table('chat_room_users')
                ->where('chat_room_users.user_id', $currentUser->id)
                ->where('chat_room_users.is_active', true)
                ->join('chat_room_users as other_users', function($join) {
                    $join->on('chat_room_users.chat_room_id', '=', 'other_users.chat_room_id')
                         ->whereRaw('other_users.user_id != chat_room_users.user_id');
                })
                ->pluck('other_users.user_id')
                ->unique()
                ->toArray();

            // Build query to exclude current user and users with existing chat rooms
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

            // Get user IDs that the current user already has chat rooms with
            $existingChatUserIds = DB::table('chat_room_users')
                ->where('chat_room_users.user_id', $currentUser->id)
                ->where('chat_room_users.is_active', true)
                ->join('chat_room_users as other_users', function($join) {
                    $join->on('chat_room_users.chat_room_id', '=', 'other_users.chat_room_id')
                         ->whereRaw('other_users.user_id != chat_room_users.user_id');
                })
                ->pluck('other_users.user_id')
                ->unique()
                ->toArray();

            // For now, return recent users (excluding current user and existing chat partners)
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

            // Get user IDs that the current user already has chat rooms with
            $existingChatUserIds = DB::table('chat_room_users')
                ->where('chat_room_users.user_id', $currentUser->id)
                ->where('chat_room_users.is_active', true)
                ->join('chat_room_users as other_users', function($join) {
                    $join->on('chat_room_users.chat_room_id', '=', 'other_users.chat_room_id')
                         ->whereRaw('other_users.user_id != chat_room_users.user_id');
                })
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
}
