<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Sent when a claim is found eligible - editable under Admin → Templates. */
    public function up(): void
    {
        DB::table('email_templates')->updateOrInsert(['slug' => 'claim-eligible-compensation'], [
            'is_active'  => 1,
            'subject'    => "Good news [NAME] - you're owed [AMOUNT]",
            'body'       => <<<'HTML'
<p>Hi [NAME],</p>
<p>Great news - your claim for flight <strong>[FLIGHT]</strong> ([ROUTE]) is <strong>eligible for compensation</strong>.</p>
<p style="font-size:22px; margin:18px 0 6px;">You're owed <strong>[AMOUNT]</strong></p>
<p>One step left: review your payout and authorise us to claim it. Takes about 2 minutes - no win, no fee.</p>
<p style="margin:22px 0;">
    <a href="[CLAIM_URL]" style="background:#0f172a; color:#ffffff; padding:12px 26px; border-radius:12px; text-decoration:none; font-weight:bold; display:inline-block;">Claim it now</a>
</p>
<p>If the airline doesn't pay, you owe nothing.</p>
HTML,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('email_templates')->where('slug', 'claim-eligible-compensation')->delete();
    }
};
