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
          Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('receiver_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->text('message');
            $table->foreignId('chat_room_id')->nullable()->constrained('chat_rooms')->onDelete('cascade');
            $table->enum('message_type', ['text', 'image', 'file', 'system'])->default('text');
            $table->boolean('is_edited')->default(false);
            $table->timestamp('edited_at')->nullable();
            $table->foreignId('reply_to_message_id')->nullable()->constrained('chat_messages')->onDelete('cascade');
            $table->timestamps();

            $table->index(['chat_room_id', 'created_at']);
            $table->index(['sender_id', 'receiver_id']);
            $table->index('message_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
