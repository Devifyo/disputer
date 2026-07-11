<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // FlightAware verification + Eligibility Engine verdict for claims
        // (past flights), mirroring the trips columns.
        Schema::table('claims', function (Blueprint $table) {
            $table->string('fa_flight_id')->nullable();
            $table->integer('flight_arrival_delay_minutes')->nullable();
            $table->boolean('flight_cancelled')->default(false);
            $table->boolean('flight_diverted')->default(false);
            $table->timestamp('flight_verified_at')->nullable();

            $table->string('eligibility_status', 16)->nullable();      // eligible | review | rejected
            $table->string('eligibility_regulation', 16)->nullable();
            $table->string('eligibility_article')->nullable();
            $table->unsignedTinyInteger('eligibility_confidence')->nullable();
            $table->string('eligibility_reason', 500)->nullable();
            $table->json('eligibility_details')->nullable();
            $table->timestamp('eligibility_evaluated_at')->nullable();
            $table->string('eligibility_decision_source', 16)->nullable();

            $table->string('compensation_basis')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('claims', function (Blueprint $table) {
            $table->dropColumn([
                'fa_flight_id', 'flight_arrival_delay_minutes', 'flight_cancelled',
                'flight_diverted', 'flight_verified_at',
                'eligibility_status', 'eligibility_regulation', 'eligibility_article',
                'eligibility_confidence', 'eligibility_reason', 'eligibility_details',
                'eligibility_evaluated_at', 'eligibility_decision_source',
                'compensation_basis',
            ]);
        });
    }
};
