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
            // Add the boolean field with a default of 0 (false)
            $table->boolean('is_internal')->default(0)->after('escalation_contact_name');
            
            // Add the created_by field as a foreign key linking to the users table
            $table->foreignId('created_by')->nullable()->after('is_internal')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn(['is_internal', 'created_by']);
        });
    }
};
