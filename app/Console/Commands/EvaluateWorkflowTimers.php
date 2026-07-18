<?php

namespace App\Console\Commands;

use App\Services\Claims\ClaimWorkflowService;
use Illuminate\Console\Command;

class EvaluateWorkflowTimers extends Command
{
    protected $signature   = 'claims:evaluate-workflow-timers';
    protected $description = 'Fire expired claim workflow timers (e.g. the 30-day airline response deadline)';

    public function handle(ClaimWorkflowService $workflow): int
    {
        $fired = $workflow->evaluateTimers();
        $this->info("Evaluated workflow timers - {$fired} transition(s) fired.");

        return self::SUCCESS;
    }
}
