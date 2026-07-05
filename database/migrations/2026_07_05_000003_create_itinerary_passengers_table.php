<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('itinerary_passengers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('itinerary_id')->constrained()->cascadeOnDelete();

            $table->string('full_name')->nullable();
            $table->string('type')->nullable();          // adult | child | infant
            $table->string('ticket_number')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('itinerary_passengers');
    }
};
