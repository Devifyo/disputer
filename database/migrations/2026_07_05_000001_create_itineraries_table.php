<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('itineraries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Uploaded file
            $table->string('original_filename');
            $table->string('file_path');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('mime_type')->nullable();

            // Processing state: uploaded | processing | parsed | failed
            $table->string('status')->default('uploaded')->index();
            $table->text('parse_error')->nullable();
            $table->timestamp('parsed_at')->nullable();

            // Parsed summary fields
            $table->string('booking_reference')->nullable();
            $table->string('primary_airline')->nullable();

            // Raw artifacts (for debugging / re-processing)
            $table->longText('raw_text')->nullable();
            $table->longText('parsed_raw')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('itineraries');
    }
};
