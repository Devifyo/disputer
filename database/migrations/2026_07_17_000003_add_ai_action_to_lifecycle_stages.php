<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('claim_lifecycle_stages', function (Blueprint $table) {
            // AI assistance triggered on entering the stage: draft only,
            // never sent - airline_claim | follow_up | regulator_complaint.
            $table->string('ai_action')->nullable()->after('notify_customer');
        });

        // Customer email when entering a stage with "notify customer" on.
        DB::table('email_templates')->updateOrInsert(['slug' => 'claim-stage-update'], [
            'is_active'  => 1,
            'subject'    => 'Update on your claim [CLAIM]: [STAGE]',
            'body'       => <<<'HTML'
<p>Hi [NAME],</p>
<p>There's an update on your compensation claim for flight <strong>[FLIGHT]</strong> ([ROUTE]):</p>
<p style="font-size:18px; margin:16px 0;"><strong>[STAGE]</strong></p>
<p><a href="[CLAIM_URL]">View your claim</a> for the full picture. No action is needed unless the page asks for something.</p>
HTML,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('claim_lifecycle_stages', function (Blueprint $table) {
            $table->dropColumn('ai_action');
        });
        DB::table('email_templates')->where('slug', 'claim-stage-update')->delete();
    }
};
