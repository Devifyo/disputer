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
        Schema::create('emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();

            // Link to the specific timeline event
            $table->foreignId('timeline_id')->nullable()->constrained('case_timelines')->cascadeOnDelete();

            $table->enum('direction', ['inbound', 'outbound']);
            $table->string('sender_email');
            $table->string('recipient_email');
            $table->string('subject')->nullable();

            $table->longText('body_text')->nullable();
            $table->longText('body_html')->nullable();

            // Unique ID from Email Provider (Mailgun/SendGrid)
            $table->string('message_id')->nullable()->index();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emails');
    }
};
