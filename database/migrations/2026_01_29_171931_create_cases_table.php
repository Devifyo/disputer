<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Nullable: User might enter a custom institution name not in our DB
            $table->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();

            // Snapshot of name (Preserves history even if Institution is deleted)
            $table->string('institution_name');

            // Public Reference ID (e.g., "CASE-2024-991")
            $table->string('case_reference_id')->unique();

            // Unique Hash for Email Routing (e.g., "c82a1b" -> reply+c82a1b@app.com)
            $table->string('email_route_id')->unique()->index();

            $table->enum('status', ['draft', 'sent', 'waiting_reply', 'escalated', 'resolved'])->default('draft');
            $table->integer('stage')->default(1); // Escalation Level (1, 2, 3)

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cases');
    }
};
