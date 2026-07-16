<?php

namespace App\Console\Commands;

use App\Services\Claims\ClaimSignatureService;
use Illuminate\Console\Command;

class SendSignatureReminders extends Command
{
    protected $signature   = 'claims:signature-reminders';
    protected $description = 'Remind claim signers who were invited 48h+ ago and have not signed';

    public function handle(ClaimSignatureService $signatures): int
    {
        $count = $signatures->sendReminders();
        $this->info("Sent {$count} signature reminder(s).");

        return self::SUCCESS;
    }
}
