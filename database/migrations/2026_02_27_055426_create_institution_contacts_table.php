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
        Schema::create('institution_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            
            // Matches Paulo's 1, 2, 3, 4 logic
            $table->string('step_key')->index();
            
            // e.g., 'Customer Service', 'Executive Office', 'Better Business Bureau'
            $table->string('department_name'); 
            
            // 'email' for stages 1-3, 'url' for stage 4, could also support 'portal' or 'phone' later
            $table->enum('channel', ['email', 'url', 'portal', 'phone'])->default('email'); 
            
            // The actual email address or URL link
            $table->string('contact_value'); 
            
            // In case an institution has multiple emails for stage 1, which one is the default?
            $table->boolean('is_primary')->default(true); 
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('institution_contacts');
    }
};
