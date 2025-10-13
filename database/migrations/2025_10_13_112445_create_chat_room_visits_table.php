<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('chat_room_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('chat_room_id')->constrained('chat_rooms')->onDelete('cascade');
            $table->timestamp('last_visited_at')->useCurrent();
            $table->timestamps();

            // Ensure one record per user per chat room
            $table->unique(['user_id', 'chat_room_id']);
            
            // Add indexes for efficient queries
            $table->index(['user_id', 'last_visited_at']);
            $table->index(['chat_room_id', 'last_visited_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_room_visits');
    }
};
