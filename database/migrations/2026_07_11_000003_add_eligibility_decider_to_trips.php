<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Accountability for eligibility decisions: what made the call
        // (ai | rules | ai+rules | admin) and, for manual ones, which
        // admin and when.
        Schema::table('trips', function (Blueprint $table) {
            $table->string('eligibility_decision_source', 16)->nullable();
            $table->foreignId('eligibility_decided_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('eligibility_decided_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropConstrainedForeignId('eligibility_decided_by');
            $table->dropColumn(['eligibility_decision_source', 'eligibility_decided_at']);
        });
    }
};
