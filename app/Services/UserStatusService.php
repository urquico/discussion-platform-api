<?php

namespace App\Services;

use App\Events\UserStatusChanged;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class UserStatusService
{
    /**
     * Mark a user as online and broadcast the status change.
     */
    public function markUserOnline(User $user): bool
    {
        $success = $user->markAsOnline();
        
        if ($success) {
            $this->broadcastStatusChange($user, 'online', true);
            $this->cacheUserStatus($user);
        }
        
        return $success;
    }

    /**
     * Mark a user as offline and broadcast the status change.
     */
    public function markUserOffline(User $user): bool
    {
        $success = $user->markAsOffline();
        
        if ($success) {
            $this->broadcastStatusChange($user, 'offline', false);
            $this->removeCachedUserStatus($user);
        }
        
        return $success;
    }

    /**
     * Update user's last seen timestamp.
     */
    public function updateUserLastSeen(User $user): bool
    {
        $success = $user->updateLastSeen();
        
        if ($success) {
            $this->cacheUserStatus($user);
        }
        
        return $success;
    }

    /**
     * Set user's status and broadcast the change.
     */
    public function setUserStatus(User $user, string $status): bool
    {
        $success = $user->setStatus($status);
        
        if ($success) {
            $isOnline = $status === 'online';
            $this->broadcastStatusChange($user, $status, $isOnline);
            
            if ($isOnline) {
                $this->cacheUserStatus($user);
            } else {
                $this->removeCachedUserStatus($user);
            }
        }
        
        return $success;
    }

    /**
     * Get online users for a specific chat room.
     */
    public function getOnlineUsersForChatRoom(int $chatRoomId): array
    {
        $cacheKey = "chat_room_{$chatRoomId}_online_users";
        
        return Cache::remember($cacheKey, 60, function () use ($chatRoomId) {
            return User::whereHas('chatRooms', function ($query) use ($chatRoomId) {
                $query->where('chat_rooms.id', $chatRoomId)
                      ->wherePivot('is_active', true);
            })
            ->where('is_online', true)
            ->where('status', 'online')
            ->select(['id', 'name', 'status', 'last_seen_at'])
            ->get()
            ->toArray();
        });
    }

    /**
     * Get all online users.
     */
    public function getAllOnlineUsers(): array
    {
        return Cache::remember('all_online_users', 60, function () {
            return User::where('is_online', true)
                ->where('status', 'online')
                ->select(['id', 'name', 'status', 'last_seen_at'])
                ->get()
                ->toArray();
        });
    }

    /**
     * Check if a user is online.
     */
    public function isUserOnline(int $userId): bool
    {
        $cacheKey = "user_{$userId}_online_status";
        
        return Cache::remember($cacheKey, 60, function () use ($userId) {
            $user = User::find($userId);
            return $user ? $user->isOnline() : false;
        });
    }

    /**
     * Clean up offline users (mark as offline if they haven't been seen in a while).
     */
    public function cleanupOfflineUsers(): void
    {
        $offlineThreshold = now()->subMinutes(15);
        
        $usersToMarkOffline = User::where('is_online', true)
            ->where('last_seen_at', '<', $offlineThreshold)
            ->get();

        foreach ($usersToMarkOffline as $user) {
            $this->markUserOffline($user);
            Log::info("Marked user {$user->id} as offline due to inactivity");
        }
    }

    /**
     * Broadcast user status change to relevant channels.
     */
    private function broadcastStatusChange(User $user, string $status, bool $isOnline): void
    {
        try {
            broadcast(new UserStatusChanged(
                $user,
                $status,
                $isOnline,
                $user->last_seen_at?->toISOString()
            ));
        } catch (\Exception $e) {
            Log::error("Failed to broadcast user status change for user {$user->id}: " . $e->getMessage());
        }
    }

    /**
     * Cache user's online status.
     */
    private function cacheUserStatus(User $user): void
    {
        $cacheKey = "user_{$user->id}_online_status";
        Cache::put($cacheKey, true, 60);
        
        // Also cache in the all online users list
        $allOnlineUsers = $this->getAllOnlineUsers();
        $userExists = collect($allOnlineUsers)->contains('id', $user->id);
        
        if (!$userExists) {
            $allOnlineUsers[] = [
                'id' => $user->id,
                'name' => $user->name,
                'status' => $user->status,
                'last_seen_at' => $user->last_seen_at,
            ];
            Cache::put('all_online_users', $allOnlineUsers, 60);
        }
    }

    /**
     * Remove user's online status from cache.
     */
    private function removeCachedUserStatus(User $user): void
    {
        $cacheKey = "user_{$user->id}_online_status";
        Cache::forget($cacheKey);
        
        // Remove from all online users list
        $allOnlineUsers = $this->getAllOnlineUsers();
        $allOnlineUsers = collect($allOnlineUsers)->reject(function ($onlineUser) use ($user) {
            return $onlineUser['id'] === $user->id;
        })->values()->toArray();
        
        Cache::put('all_online_users', $allOnlineUsers, 60);
    }
}
