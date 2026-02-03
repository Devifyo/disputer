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
        Schema::create('institutions', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., "Chase Bank"
            $table->foreignId('institution_category_id')
              ->nullable()
              ->constrained('institution_categories') // Point explicitly to new table
              ->nullOnDelete();
            $table->string('contact_email')->nullable(); // Default dispute email
            $table->boolean('is_verified')->default(false); // true = Admin added, false = User added

            // Self-referencing FK to merge duplicates (e.g., merge "Chase" into "Chase Bank")
            $table->foreignId('parent_id')->nullable()->constrained('institutions')->nullOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('institutions');
    }
};
