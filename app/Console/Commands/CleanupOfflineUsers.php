<?php

namespace App\Console\Commands;

use App\Services\UserStatusService;
use Illuminate\Console\Command;

class CleanupOfflineUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:cleanup-offline';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up users who have been inactive and mark them as offline';

    protected UserStatusService $userStatusService;

    public function __construct(UserStatusService $userStatusService)
    {
        parent::__construct();
        $this->userStatusService = $userStatusService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting cleanup of offline users...');
        
        $this->userStatusService->cleanupOfflineUsers();
        
        $this->info('Cleanup completed successfully.');
        
        return Command::SUCCESS;
    }
}
