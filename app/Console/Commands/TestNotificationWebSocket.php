<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class TestNotificationWebSocket extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:notification-websocket {user_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test WebSocket notification by creating a test notification for a user';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->argument('user_id');
        
        $user = User::find($userId);
        
        if (!$user) {
            $this->error("User with ID {$userId} not found.");
            return 1;
        }

        $notificationService = new NotificationService();
        
        $notification = $notificationService->createSystemNotification(
            $user,
            'Test Notification',
            'This is a test notification to verify WebSocket functionality!',
            ['test' => true, 'timestamp' => now()],
            '/test'
        );

        $this->info("Test notification created for user: {$user->name}");
        $this->info("Notification ID: {$notification->id}");
        $this->info("WebSocket event should have been broadcasted to channel: notifications.{$user->id}");
        
        return 0;
    }
}
