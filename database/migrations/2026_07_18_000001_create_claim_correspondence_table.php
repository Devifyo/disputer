<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every email exchanged with the airline about a claim - outbound
        // (sent by an admin from the composer) and inbound (airline replies
        // routed back by the claims inbound webhook via the reply-to token).
        Schema::create('claim_correspondence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claim_id')->constrained()->cascadeOnDelete();
            $table->string('direction', 10);                         // outbound | inbound
            $table->string('from_email');
            $table->string('from_name')->nullable();
            $table->string('to_email')->nullable();
            $table->string('subject')->nullable();
            $table->longText('body')->nullable();
            $table->json('attachments')->nullable();                 // [{name, path, mime}]
            $table->string('matched_by')->nullable();                // reply_token | subject_reference (inbound only)
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['claim_id', 'direction']);
        });

        // Admin alert when an airline reply lands on a claim.
        DB::table('email_templates')->updateOrInsert(['slug' => 'claim-airline-reply-alert'], [
            'is_active'  => 1,
            'subject'    => 'Airline replied: claim [CLAIM] - [SUBJECT]',
            'body'       => <<<'HTML'
<p>Hi [NAME],</p>
<p>An email from <strong>[FROM]</strong> just arrived on claim <strong>[CLAIM]</strong> (flight [FLIGHT], [ROUTE]).</p>
<p><strong>Subject:</strong> [SUBJECT]</p>
<blockquote>[PREVIEW]</blockquote>
<p><a href="[CLAIM_URL]">Open the claim</a> to read the full message and record the airline's response.</p>
HTML,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_correspondence');
        DB::table('email_templates')->where('slug', 'claim-airline-reply-alert')->delete();
    }
};
