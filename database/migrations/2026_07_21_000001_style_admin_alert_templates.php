<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Admin alerts were plain paragraphs while customer emails had the branded
 * layout with a slate CTA button. Bring both admin templates in line so
 * every Unjamm email looks the same, whoever receives it.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('email_templates')->where('slug', 'claim-escalation-alert')->update([
            'subject'    => 'Action needed: claim [CLAIM] - [STAGE]',
            'body'       => <<<'HTML'
<p>Hi [NAME],</p>
<p>Claim <strong>[CLAIM]</strong> needs a decision - it has moved to <strong>[STAGE]</strong>.</p>
<p style="margin:16px 0 6px;"><strong>Flight:</strong> [FLIGHT] ([ROUTE])</p>
<p style="margin:0 0 6px;"><strong>Why:</strong> [REASON]</p>
<p style="margin:22px 0;">
    <a href="[CLAIM_URL]" style="background:#0f172a; color:#ffffff; padding:12px 26px; border-radius:12px; text-decoration:none; font-weight:bold; display:inline-block;">Open the claim</a>
</p>
<p>Escalation and legal decisions always require an explicit action - nothing moves forward on its own.</p>
HTML,
            'updated_at' => now(),
        ]);

        DB::table('email_templates')->where('slug', 'claim-airline-reply-alert')->update([
            'subject'    => 'Airline replied: claim [CLAIM] - [SUBJECT]',
            'body'       => <<<'HTML'
<p>Hi [NAME],</p>
<p>An email from <strong>[FROM]</strong> just arrived on claim <strong>[CLAIM]</strong>.</p>
<p style="margin:16px 0 6px;"><strong>Flight:</strong> [FLIGHT] ([ROUTE])</p>
<p style="margin:0 0 6px;"><strong>Subject:</strong> [SUBJECT]</p>
<p style="background:#f8fafc; border-left:3px solid #cbd5e1; padding:12px 16px; margin:16px 0; color:#475569;">[PREVIEW]</p>
<p style="margin:22px 0;">
    <a href="[CLAIM_URL]" style="background:#0f172a; color:#ffffff; padding:12px 26px; border-radius:12px; text-decoration:none; font-weight:bold; display:inline-block;">Read the full reply</a>
</p>
<p>Record the airline's response on the claim once you have reviewed it - an acknowledgement is not a substantive reply, so the response deadline keeps running until you say otherwise.</p>
HTML,
            'updated_at' => now(),
        ]);

        // Where operational alerts are delivered - blank keeps the previous
        // behaviour (every admin account).
        if (!DB::table('settings')->where('key', 'claims.alert_emails')->exists()) {
            DB::table('settings')->insert([
                'key'        => 'claims.alert_emails',
                'value'      => '',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'claims.alert_emails')->delete();
    }
};
