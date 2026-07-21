<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Out-of-pocket expenses the passenger uploads (meals, hotel, taxi,
        // rebooking...). The admin verifies each receipt and decides which
        // ones are claimed from the airline - amounts here are evidence,
        // never statutory compensation.
        Schema::create('claim_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claim_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category');                              // meal | hotel | taxi | transport | rebooking | other
            $table->string('description')->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->date('expense_date')->nullable();
            $table->string('file_path');
            $table->string('original_filename');
            $table->string('mime')->nullable();
            $table->unsignedInteger('size_bytes')->nullable();
            $table->string('status')->default('pending');            // pending | approved | rejected
            $table->string('review_reason')->nullable();             // shown to the customer when rejected
            $table->text('admin_note')->nullable();                  // internal only
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['claim_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_expenses');
    }
};
