<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Sent when the team rejects a claim after review - editable under Admin → Templates. */
    public function up(): void
    {
        DB::table('email_templates')->updateOrInsert(['slug' => 'claim-eligibility-rejected'], [
            'is_active'  => 1,
            'subject'    => 'Update on your claim [CLAIM] - review complete',
            'body'       => <<<'HTML'
<p>Hi [NAME],</p>
<p>Our team has reviewed your claim for flight [FLIGHT] ([ROUTE]) and unfortunately it doesn't qualify for compensation.</p>
<p><strong>Reason:</strong> [REASON]</p>
<p><a href="[CLAIM_URL]">View your claim</a></p>
<p>If you have new information or documents that change the picture, add them to your claim and we'll take another look.</p>
HTML,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('email_templates')->where('slug', 'claim-eligibility-rejected')->delete();
    }
};
