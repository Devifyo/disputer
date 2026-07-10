<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('itinerary_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source')->default('manual'); // manual | upload
            $table->string('status')->default('protected');
            $table->string('airline')->nullable();
            $table->string('flight_number')->nullable();
            $table->string('departure_airport', 8)->nullable();
            $table->string('departure_city')->nullable();
            $table->string('arrival_airport', 8)->nullable();
            $table->string('arrival_city')->nullable();
            $table->date('departure_date')->nullable();
            $table->string('departure_time', 8)->nullable();
            $table->string('booking_reference')->nullable();
            $table->string('passenger_name')->nullable();
            // Historical route delay score — populated by a future feature.
            $table->unsignedTinyInteger('delay_score')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'departure_date']);
        });

        // Itineraries uploaded to protect a future trip must not spawn claims
        // and stay out of the Flight Disputes listing.
        Schema::table('itineraries', function (Blueprint $table) {
            $table->string('purpose')->default('claim')->after('status'); // claim | trip
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trips');
        Schema::table('itineraries', function (Blueprint $table) {
            $table->dropColumn('purpose');
        });
    }
};
