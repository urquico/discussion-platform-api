<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatRoom extends Model {
    protected $fillable = ['name', 'description', 'type', 'created_by', 'is_active'];

    public function users() {
        return $this->belongsToMany(User::class, 'chat_room_users')
                    ->withPivot('role', 'is_active', 'joined_at')
                    ->withTimestamps();
    }

    public function messages() {
        return $this->hasMany(ChatMessage::class);
    }

    public function creator() {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function activeUsers() {
        return $this->users()->wherePivot('is_active', true);
    }
}
