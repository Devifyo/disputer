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
        Schema::table('institution_categories', function (Blueprint $table) {
            $table->json('workflow_config')->nullable()->after('slug');
            $table->boolean('is_verified')->default(true);
        });
    
        Schema::table('cases', function (Blueprint $table) {
            $table->integer('current_workflow_step')->default(1)->after('status');
            $table->timestamp('next_action_at')->nullable()->after('current_workflow_step');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('institution_categories', function (Blueprint $table) {
            $table->dropColumn(['workflow_config', 'is_verified']);
        });
        
        Schema::table('cases', function (Blueprint $table) {
            $table->dropColumn(['current_workflow_step', 'next_action_at']);
        });
    }
};
