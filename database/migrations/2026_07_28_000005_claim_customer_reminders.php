<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('claims', function (Blueprint $table) {
            // When the customer was last nudged - so a reminder cannot become
            // spam, and the admin can see it has already been sent.
            $table->timestamp('reminded_at')->nullable()->after('confirmed_at');
        });

        DB::table('email_templates')->updateOrInsert(['slug' => 'claim-confirm-reminder'], [
            'is_active' => 1,
            'subject'   => 'Your [AMOUNT] claim is waiting - just one step left',
            'body'      => <<<'HTML'
<p>Hi [NAME],</p>
<p>Good news: your claim for <strong>[FLIGHT]</strong> ([ROUTE]) has been assessed and you're entitled to <strong>[AMOUNT]</strong>.</p>
<p>We can't send it to the airline until you confirm the details and authorise us to act for you. It takes about a minute.</p>
<p style="margin:22px 0;"><a href="[CLAIM_URL]" style="background:#0f172a; color:#ffffff; padding:12px 26px; border-radius:12px; text-decoration:none; font-weight:bold; display:inline-block;">Confirm my claim</a></p>
<p>No win, no fee - if the airline doesn't pay, you owe us nothing.</p>
HTML,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('email_templates')->updateOrInsert(['slug' => 'claim-sign-reminder'], [
            'is_active' => 1,
            'subject'   => 'One signature left on your [AMOUNT] claim',
            'body'      => <<<'HTML'
<p>Hi [NAME],</p>
<p>Your claim for <strong>[FLIGHT]</strong> ([ROUTE]) is ready to go to the airline - we just need the authorisation signed.</p>
<p>Signing takes seconds and can be done on your phone. Until it's signed, the airline can't be contacted about your <strong>[AMOUNT]</strong> claim.</p>
<p style="margin:22px 0;"><a href="[CLAIM_URL]" style="background:#0f172a; color:#ffffff; padding:12px 26px; border-radius:12px; text-decoration:none; font-weight:bold; display:inline-block;">Sign my authorisation</a></p>
<p>Thanks - we'll take it from there.</p>
HTML,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('claims', fn (Blueprint $table) => $table->dropColumn('reminded_at'));
        DB::table('email_templates')->whereIn('slug', ['claim-confirm-reminder', 'claim-sign-reminder'])->delete();
    }
};
