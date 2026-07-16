<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Signature request email, editable by admins under Admin → Templates. */
    public function up(): void
    {
        DB::table('email_templates')->updateOrInsert(['slug' => 'claim-signature-request'], [
            'is_active'  => 1,
            'subject'    => 'Your signature is needed for flight claim [FLIGHT]',
            'body'       => <<<'HTML'
<p>Hi [NAME],</p>
<p>A compensation claim for flight [FLIGHT] ([ROUTE]) includes you as a passenger, and your signature is needed before it can be filed with the airline.</p>
<p><a href="[SIGN_URL]">Review and sign your authorisation documents</a></p>
<p>Signing takes under a minute. If you don't recognise this claim, you can ignore this email.</p>
HTML,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('email_templates')->where('slug', 'claim-signature-request')->delete();
    }
};
