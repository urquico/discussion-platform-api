<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->uuid('chat_room_id')->after('id'); // Add chat room ID
            $table->foreign('chat_room_id')
                ->references('id')
                ->on('chat_rooms')
                ->onDelete('cascade'); // Delete messages if chat room deleted
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['chat_room_id']);
            $table->dropColumn('chat_room_id');
        });
    }
};
