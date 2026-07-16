<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('claim_signers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claim_id')->constrained()->cascadeOnDelete();
            $table->foreignId('itinerary_passenger_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('role')->default('passenger');       // passenger | guardian
            $table->string('signs_for')->nullable();             // minor's name when role=guardian
            $table->string('status')->default('pending');        // pending | signed | declined
            $table->string('provider')->default('native');       // native | dropbox_sign
            $table->string('provider_request_id')->nullable();
            $table->string('provider_signature_id')->nullable();
            $table->string('sign_token', 64)->unique();
            $table->string('signature_path')->nullable();
            $table->string('poa_path')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_signers');
    }
};
