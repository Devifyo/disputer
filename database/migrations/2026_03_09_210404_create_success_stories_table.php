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
        Schema::create('success_stories', function (Blueprint $table) {
            $table->id();
            
            // Nullable user_id if they are logged in
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            
            // Form fields
            $table->string('first_name');
            $table->string('email')->nullable();
            $table->text('story');
            
            // JSON column to store an array of file paths for multiple images
            $table->json('media_files')->nullable();
            
            // Status field so admins can review before publishing on the website
            $table->boolean('is_published')->default(false);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('success_stories');
    }
};
