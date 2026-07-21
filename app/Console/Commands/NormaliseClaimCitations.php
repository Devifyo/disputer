<?php

namespace App\Console\Commands;

use App\Models\Claim;
use App\Services\Eligibility\RegulationCitation;
use Illuminate\Console\Command;

/**
 * Re-point existing claims at the canonical legal citation for their
 * disruption. Claims decided before the citation table existed carry
 * free-text articles the AI invented (e.g. "ss. 20-22" on a cancellation
 * that is governed by Section 19).
 */
class NormaliseClaimCitations extends Command
{
    protected $signature   = 'claims:normalise-citations {--dry-run : List the changes without saving}';
    protected $description = 'Replace AI-invented legal citations with the canonical article for each claim';

    public function handle(): int
    {
        $dry     = (bool) $this->option('dry-run');
        $changed = 0;

        Claim::whereNotNull('eligibility_regulation')->chunkById(200, function ($claims) use ($dry, &$changed) {
            foreach ($claims as $claim) {
                $scenario  = RegulationCitation::scenarioFromClaim($claim);
                $canonical = RegulationCitation::article((string) $claim->eligibility_regulation, $scenario);

                if ($canonical === '' || $canonical === $claim->eligibility_article) {
                    continue;
                }

                $this->line(sprintf(
                    '%s  %-7s %-22s -> %s   (%s)',
                    $claim->reference, $claim->eligibility_regulation,
                    (string) $claim->eligibility_article, $canonical, $scenario
                ));

                if (!$dry) {
                    $claim->forceFill(['eligibility_article' => $canonical])->save();
                }

                $changed++;
            }
        });

        $this->info($dry
            ? "{$changed} claim(s) would be corrected."
            : "{$changed} claim(s) corrected.");

        return self::SUCCESS;
    }
}
