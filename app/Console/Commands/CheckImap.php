<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\CheckImapReplies;
use Illuminate\Support\Facades\Log;

class CheckImap extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'imap:check {--sync : Run immediately without queueing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check all user mailboxes for new replies via IMAP';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting continuous IMAP fetcher...');
        $sleepInterval = 15; // Check every 15 seconds

        // The infinite loop keeps the command running forever
        while (true) {
            \Illuminate\Support\Facades\Log::info('IMAP Check ticked at: ' . now());
            
            if ($this->option('sync')) {
                // Run immediately in this process (Good for debugging/testing)
                $this->info('[' . now() . '] Running synchronously...');
                
                try {
                    $job = new CheckImapReplies();
                    $job->handle();
                    
                    $this->info('✅ IMAP check completed.');
                } catch (\Exception $e) {
                    $this->error('❌ Error: ' . $e->getMessage());
                    Log::error('Manual IMAP Check Failed: ' . $e->getMessage());
                }
            } else {
                // Dispatch to the queue (Background worker)
                CheckImapReplies::dispatch();
                $this->info('[' . now() . '] 🚀 Job dispatched to queue.');
            }

            // CRITICAL: Pause the script so we don't crash the server or get IP banned
            sleep($sleepInterval);
        }
    }
}