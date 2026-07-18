<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('airlines', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('iata_code', 3)->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();                    // e.g. "portal submissions only"
            $table->timestamps();
        });

        Schema::create('airline_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('airline_id')->constrained()->cascadeOnDelete();
            $table->string('purpose');                            // claims | legal | escalation | customer_relations
            $table->string('email');
            $table->string('label')->nullable();                  // e.g. "EU claims desk"
            $table->timestamps();
            $table->unique(['airline_id', 'purpose']);
        });

        Schema::table('claim_lifecycle_stages', function (Blueprint $table) {
            // Which airline contact this stage's outbound email goes to.
            $table->string('airline_contact_purpose')->nullable()->after('ai_action');
        });

        // The filing stage targets the airline's claims department by default.
        DB::table('claim_lifecycle_stages')->where('key', 'filed')->update(['airline_contact_purpose' => 'claims']);

        // Seed the carriers already in the system - contacts are added by admins.
        $now = now();
        DB::table('airlines')->insert([
            ['name' => 'Air Canada', 'iata_code' => 'AC', 'is_active' => 1, 'notes' => null, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Air Canada Express', 'iata_code' => 'QK', 'is_active' => 1, 'notes' => 'Jazz Aviation - claims handled by Air Canada.', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Air France', 'iata_code' => 'AF', 'is_active' => 1, 'notes' => null, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Lufthansa', 'iata_code' => 'LH', 'is_active' => 1, 'notes' => null, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'British Airways', 'iata_code' => 'BA', 'is_active' => 1, 'notes' => null, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'United Airlines', 'iata_code' => 'UA', 'is_active' => 1, 'notes' => null, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::table('claim_lifecycle_stages', function (Blueprint $table) {
            $table->dropColumn('airline_contact_purpose');
        });
        Schema::dropIfExists('airline_contacts');
        Schema::dropIfExists('airlines');
    }
};
