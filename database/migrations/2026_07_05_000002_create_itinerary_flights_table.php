<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('itinerary_flights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('itinerary_id')->constrained()->cascadeOnDelete();

            $table->unsignedInteger('sequence')->default(0);
            $table->string('airline')->nullable();
            $table->string('flight_number')->nullable();
            $table->string('departure_airport')->nullable();
            $table->string('arrival_airport')->nullable();
            $table->dateTime('departure_at')->nullable();
            $table->dateTime('arrival_at')->nullable();
            $table->string('cabin_class')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('itinerary_flights');
    }
};
