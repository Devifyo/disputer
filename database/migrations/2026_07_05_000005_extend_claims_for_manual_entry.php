<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Make the itinerary links optional so claims can be created manually.
        Schema::table('claims', function (Blueprint $table) {
            $table->dropForeign(['itinerary_id']);
            $table->dropForeign(['itinerary_passenger_id']);
        });
        Schema::table('claims', function (Blueprint $table) {
            $table->unsignedBigInteger('itinerary_id')->nullable()->change();
            $table->unsignedBigInteger('itinerary_passenger_id')->nullable()->change();
        });
        Schema::table('claims', function (Blueprint $table) {
            $table->foreign('itinerary_id')->references('id')->on('itineraries')->nullOnDelete();
            $table->foreign('itinerary_passenger_id')->references('id')->on('itinerary_passengers')->nullOnDelete();
        });

        // Denormalised flight / claim details (populated on manual entry or from a parsed itinerary).
        Schema::table('claims', function (Blueprint $table) {
            $table->unsignedInteger('number')->nullable()->unique()->after('reference');

            $table->string('departure_city')->nullable();
            $table->string('departure_airport')->nullable();
            $table->string('arrival_city')->nullable();
            $table->string('arrival_airport')->nullable();

            $table->string('airline')->nullable();
            $table->string('flight_number')->nullable();
            $table->date('flight_date')->nullable();
            $table->string('disruption_type')->nullable(); // delayed | cancelled | denied_boarding | missed_connection

            $table->string('passenger_name')->nullable();
            $table->string('booking_reference')->nullable();
            $table->string('contact_email')->nullable();

            // Compensation placeholders — populated later by the Eligibility Engine.
            $table->string('compensation_currency', 3)->nullable();
            $table->decimal('compensation_amount', 10, 2)->nullable();

            $table->timestamp('submitted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('claims', function (Blueprint $table) {
            $table->dropForeign(['itinerary_id']);
            $table->dropForeign(['itinerary_passenger_id']);
            $table->dropColumn([
                'number', 'departure_city', 'departure_airport', 'arrival_city', 'arrival_airport',
                'airline', 'flight_number', 'flight_date', 'disruption_type',
                'passenger_name', 'booking_reference', 'contact_email',
                'compensation_currency', 'compensation_amount', 'submitted_at',
            ]);
            $table->foreign('itinerary_id')->references('id')->on('itineraries')->cascadeOnDelete();
            $table->foreign('itinerary_passenger_id')->references('id')->on('itinerary_passengers')->cascadeOnDelete();
        });
    }
};
