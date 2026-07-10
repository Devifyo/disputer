<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Outcome of the automatic eligibility evaluation that runs once a
        // monitored trip's disruption is final (flight completed/cancelled).
        Schema::table('trips', function (Blueprint $table) {
            $table->string('eligibility_status', 16)->nullable();      // eligible | rejected
            $table->string('eligibility_regulation', 16)->nullable();  // EU261 | UK261 | APPR | US_DOT
            $table->string('eligibility_article')->nullable();         // e.g. "Article 7(1)"
            $table->unsignedTinyInteger('eligibility_confidence')->nullable(); // 0–100
            $table->string('eligibility_reason', 500)->nullable();
            $table->json('eligibility_details')->nullable();           // per-regulation outcomes + factors
            $table->timestamp('eligibility_evaluated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn([
                'eligibility_status', 'eligibility_regulation', 'eligibility_article',
                'eligibility_confidence', 'eligibility_reason', 'eligibility_details',
                'eligibility_evaluated_at',
            ]);
        });
    }
};
