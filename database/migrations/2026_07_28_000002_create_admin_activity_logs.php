<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Admin actions that are not scoped to a single claim - template and
        // airline management - need their own trail. Claim-scoped events keep
        // living on claim_audit_logs where the claim timeline can show them.
        Schema::create('admin_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->morphs('subject');
            $table->string('action', 60)->index();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip', 45)->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_activity_logs');
    }
};
