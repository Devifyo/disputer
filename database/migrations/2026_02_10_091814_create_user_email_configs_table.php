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
        Schema::create('user_email_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // SMTP (Sending)
            $table->string('smtp_host');
            $table->string('smtp_port')->default('587');
            $table->string('smtp_username');
            $table->text('smtp_password'); // Will be encrypted
            $table->string('smtp_encryption')->default('tls');
            
            // IMAP (Receiving - Optional for now, but good to have)
            $table->string('imap_host')->nullable();
            $table->string('imap_port')->default('993');
            $table->string('imap_username')->nullable();
            $table->text('imap_password')->nullable(); // Will be encrypted
            $table->string('imap_encryption')->default('ssl');

            // Identity
            $table->string('from_name')->nullable();
            $table->string('from_email');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_email_configs');
    }
};
