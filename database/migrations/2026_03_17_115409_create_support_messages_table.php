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
        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            
            // If a logged-in user submits the form, link it to their account. 
            // nullOnDelete() ensures the message stays even if the user deletes their account.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            
            $table->string('name');
            $table->string('email');
            $table->text('message');
            
            // Helpful for the admin panel to track if a message has been handled
            $table->string('status')->default('new'); // Options: 'new', 'in_progress', 'resolved'
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_messages');
    }
};