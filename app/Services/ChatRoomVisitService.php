<?php

namespace App\Services;

use App\Models\ChatRoomVisit;
use Carbon\Carbon;

class ChatRoomVisitService
{
    /**
     * Record or update a user's visit to a chat room.
     *
     * @param int $userId
     * @param int $chatRoomId
     * @return ChatRoomVisit
     */
    public function recordVisit(int $userId, int $chatRoomId): ChatRoomVisit
    {
        return ChatRoomVisit::updateOrCreate(
            [
                'user_id' => $userId,
                'chat_room_id' => $chatRoomId,
            ],
            [
                'last_visited_at' => Carbon::now(),
            ]
        );
    }

    /**
     * Get the last visit time for a user in a specific chat room.
     *
     * @param int $userId
     * @param int $chatRoomId
     * @return Carbon|null
     */
    public function getLastVisitTime(int $userId, int $chatRoomId): ?Carbon
    {
        $visit = ChatRoomVisit::where('user_id', $userId)
            ->where('chat_room_id', $chatRoomId)
            ->first();

        return $visit?->last_visited_at;
    }

    /**
     * Get all chat rooms a user has visited with their last visit times.
     *
     * @param int $userId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUserVisits(int $userId)
    {
        return ChatRoomVisit::with('chatRoom')
            ->where('user_id', $userId)
            ->orderBy('last_visited_at', 'desc')
            ->get();
    }

    /**
     * Get users who have visited a specific chat room.
     *
     * @param int $chatRoomId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getChatRoomVisitors(int $chatRoomId)
    {
        return ChatRoomVisit::with('user')
            ->where('chat_room_id', $chatRoomId)
            ->orderBy('last_visited_at', 'desc')
            ->get();
    }

    /**
     * Get users who haven't visited a chat room recently (e.g., in the last 24 hours).
     *
     * @param int $chatRoomId
     * @param int $hoursThreshold
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getInactiveUsers(int $chatRoomId, int $hoursThreshold = 24)
    {
        $threshold = Carbon::now()->subHours($hoursThreshold);

        return ChatRoomVisit::with('user')
            ->where('chat_room_id', $chatRoomId)
            ->where('last_visited_at', '<', $threshold)
            ->orderBy('last_visited_at', 'asc')
            ->get();
    }

    /**
     * Delete visit records for a specific user.
     *
     * @param int $userId
     * @return int Number of deleted records
     */
    public function deleteUserVisits(int $userId): int
    {
        return ChatRoomVisit::where('user_id', $userId)->delete();
    }

    /**
     * Delete visit records for a specific chat room.
     *
     * @param int $chatRoomId
     * @return int Number of deleted records
     */
    public function deleteChatRoomVisits(int $chatRoomId): int
    {
        return ChatRoomVisit::where('chat_room_id', $chatRoomId)->delete();
    }

    /**
     * Record visits for multiple users in a chat room.
     *
     * @param array $userIds
     * @param int $chatRoomId
     * @return array Array of ChatRoomVisit models
     */
    public function recordVisitsForUsers(array $userIds, int $chatRoomId): array
    {
        $visits = [];
        foreach ($userIds as $userId) {
            $visits[] = $this->recordVisit($userId, $chatRoomId);
        }
        return $visits;
    }

    /**
     * Get all active users in a chat room (users who are members).
     *
     * @param int $chatRoomId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getActiveUsersInChatRoom(int $chatRoomId)
    {
        return \App\Models\ChatRoom::find($chatRoomId)
            ->users()
            ->wherePivot('is_active', true)
            ->get();
    }

    /**
     * Record visits for all active users in a chat room except the specified user.
     *
     * @param int $chatRoomId
     * @param int $excludeUserId
     * @return array Array of ChatRoomVisit models
     */
    public function recordVisitsForOtherUsers(int $chatRoomId, int $excludeUserId): array
    {
        $activeUsers = $this->getActiveUsersInChatRoom($chatRoomId);
        $otherUserIds = $activeUsers->where('id', '!=', $excludeUserId)->pluck('id')->toArray();
        
        return $this->recordVisitsForUsers($otherUserIds, $chatRoomId);
    }
}
