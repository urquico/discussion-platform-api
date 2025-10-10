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
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_seen_at')->nullable()->after('updated_at');
            $table->boolean('is_online')->default(false)->after('last_seen_at');
            $table->string('status')->default('offline')->after('is_online'); // online, offline, away, busy
            
            $table->index('last_seen_at');
            $table->index('is_online');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['last_seen_at']);
            $table->dropIndex(['is_online']);
            $table->dropIndex(['status']);
            $table->dropColumn(['last_seen_at', 'is_online', 'status']);
        });
    }
};
