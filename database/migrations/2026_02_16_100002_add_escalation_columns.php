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
        Schema::table('institutions', function (Blueprint $table) {
            $table->string('escalation_email')->nullable();
            $table->string('escalation_contact_name')->nullable();
        });

        Schema::table('institution_categories', function (Blueprint $table) {
            $table->string('fallback_escalation_email')->nullable();
        });

        Schema::table('cases', function (Blueprint $table) {
            // 0 = Normal, 1 = Escalated (Level 1), 2 = Regulator (Level 2)
            $table->integer('escalation_level')->default(0); 
            $table->timestamp('last_escalated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
       Schema::table('institutions', function (Blueprint $table) {
            $table->dropColumn(['escalation_email', 'escalation_contact_name']);
        });
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('fallback_escalation_email');
        });
        Schema::table('cases', function (Blueprint $table) {
            $table->dropColumn(['escalation_level', 'last_escalated_at']);
        });
    }
};
