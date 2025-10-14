<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\ChatRoom;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('chat-room.{chatRoomId}', function ($user, $chatRoomId) {
    // Check if user is a member of the chat room
    $chatRoom = ChatRoom::find($chatRoomId);
    
    if (!$chatRoom) {
        return false;
    }
    
    return $chatRoom->users()
        ->wherePivot('user_id', $user->id)
        ->wherePivot('is_active', true)
        ->exists();
});

Broadcast::channel('user-status', function ($user) {
    // Allow all authenticated users to listen to user status changes
    return true;
});

Broadcast::channel('notifications.{userId}', function ($user, $userId) {
    // Users can only listen to their own notifications
    return (int) $user->id === (int) $userId;
});
