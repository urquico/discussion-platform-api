<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('chat.{chatRoomId}', function ($user, $chatRoomId) {
    // This check MUST return TRUE.
    return \App\Models\ChatRoom::where('id', $chatRoomId)
        ->whereHas('users', fn($q) => $q->where('user_id', $user->id))
        ->exists();
});
