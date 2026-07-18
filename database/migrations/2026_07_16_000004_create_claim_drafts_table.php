<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('claim_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claim_id')->constrained()->cascadeOnDelete();
            $table->string('type');                    // airline_claim | follow_up | regulator_complaint
            $table->unsignedInteger('version');        // per claim + type
            $table->string('to')->nullable();
            $table->string('subject');
            $table->text('body');
            $table->json('context')->nullable();       // {reason, airline_response, ...}
            $table->string('generated_by');            // ai | template | admin
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['claim_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_drafts');
    }
};
