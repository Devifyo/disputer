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
        Schema::create('letter_templates', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            
            // CHANGE: Link to your existing categories table
            $table->foreignId('institution_category_id')
                ->constrained('institution_categories')
                ->onDelete('cascade'); 

            $table->text('description')->nullable();
            $table->longText('content'); 
            $table->string('icon')->default('file-text');
            $table->string('color')->default('slate');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('letter_templates');
    }
};
