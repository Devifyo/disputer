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
        Schema::create('ai_suggestions', function (Blueprint $table) {
            $table->id();
            
            // Link to the Case
            $table->foreignId('case_id')->constrained('cases')->onDelete('cascade');
            
            // Optional: Link to a specific file (e.g., "This PDF looks like a receipt")
            $table->foreignId('attachment_id')->nullable()->constrained('attachments')->onDelete('set null');

            // What kind of suggestion is this? 
            // e.g., 'escalation_recommendation', 'draft_reply', 'status_update', 'extraction'
            $table->string('type'); 
            
            // How confident is the AI? (0-100 or 0.0-1.0)
            $table->integer('confidence_score')->nullable();

            // The Payload: What should we update?
            // e.g., {"status": "escalated"} or {"amount": 500.00, "date": "2023-10-12"}
            $table->json('suggested_data')->nullable();

            // The Explanation: Why did the AI suggest this?
            // e.g., "The airline replied with 'Denied', so we recommend escalation."
            $table->text('reasoning')->nullable();

            // The Guardrail: State of the suggestion
            $table->string('status')->default('pending'); // 'pending', 'accepted', 'rejected'
            
            // When did the user click Approve/Reject?
            $table->timestamp('acted_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_suggestions');
    }
};
