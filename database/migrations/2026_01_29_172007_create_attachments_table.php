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
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();

            // Nullable: File might be attached to an Email OR a direct User Upload
            $table->foreignId('email_id')->nullable()->constrained('emails')->cascadeOnDelete();

            $table->string('file_path'); // S3 or Local path
            $table->string('file_name'); // Original filename
            $table->string('mime_type')->nullable();

            // Reserved for Phase 2 AI
            $table->enum('ai_analysis_status', ['pending', 'processed', 'failed'])->default('pending');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
