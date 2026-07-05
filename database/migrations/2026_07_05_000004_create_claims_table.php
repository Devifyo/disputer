<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('claims', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('itinerary_id')->constrained()->cascadeOnDelete();
            $table->foreignId('itinerary_passenger_id')->constrained()->cascadeOnDelete();

            // Lifecycle status. Eligibility / compensation are handled by a later milestone.
            $table->string('status')->default('draft')->index(); // draft | pending_eligibility_review | ...

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claims');
    }
};
