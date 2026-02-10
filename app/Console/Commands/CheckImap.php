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
        $this->info('Starting IMAP check...');

        if ($this->option('sync')) {
            // Run immediately in this process (Good for debugging/testing)
            $this->info('Running synchronously (please wait)...');
            
            try {
                // Dispatch logic directly via dispatchSync or instantiating the class
                $job = new CheckImapReplies();
                $job->handle();
                
                $this->info('✅ IMAP check completed successfully.');
            } catch (\Exception $e) {
                $this->error('❌ Error: ' . $e->getMessage());
                Log::error('Manual IMAP Check Failed: ' . $e->getMessage());
            }
        } else {
            // Dispatch to the queue (Background worker)
            CheckImapReplies::dispatch();
            $this->info('🚀 Job dispatched to queue successfully.');
        }
    }
}