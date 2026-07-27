<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_payout_accounts', function (Blueprint $table) {
            // The account the CUSTOMER chose for payouts - admins don't pick.
            $table->boolean('is_default')->default(false)->after('masked');
        });

        // Existing single accounts become the default automatically.
        DB::statement('UPDATE user_payout_accounts SET is_default = 1');

        // Admin nudge: "please add your bank details".
        DB::table('email_templates')->updateOrInsert(['slug' => 'payout-details-request'], [
            'is_active'  => 1,
            'subject'    => 'Add your bank details - your payout for claim [CLAIM] is waiting',
            'body'       => <<<'HTML'
<p>Hi [NAME],</p>
<p>Good news - your payout of <strong>[NET]</strong> for claim <strong>[CLAIM]</strong> is ready to be sent.</p>
<p>We just need to know where to send it. Add your bank account on your claim page - it takes under a minute, and the money goes straight to your account.</p>
<p style="margin:22px 0;"><a href="[CLAIM_URL]" style="background:#0f172a; color:#ffffff; padding:12px 26px; border-radius:12px; text-decoration:none; font-weight:bold; display:inline-block;">Add my bank details</a></p>
<p>Your details are stored encrypted and only ever shown partially, even to our team.</p>
HTML,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('user_payout_accounts', fn (Blueprint $table) => $table->dropColumn('is_default'));
        DB::table('email_templates')->where('slug', 'payout-details-request')->delete();
    }
};
