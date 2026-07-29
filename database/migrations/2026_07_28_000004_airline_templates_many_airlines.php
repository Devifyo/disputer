<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A letter usually suits several carriers - or all of them. One rule
        // now decides scope: NO airlines attached means every airline, any
        // attached means exactly those.
        Schema::dropIfExists('airline_email_template_airline');

        Schema::create('airline_email_template_airline', function (Blueprint $table) {
            $table->id();
            $table->foreignId('airline_email_template_id')->constrained('airline_email_templates', 'id', 'aeta_template_fk')->cascadeOnDelete();
            $table->foreignId('airline_id')->constrained()->cascadeOnDelete();
            $table->unique(['airline_email_template_id', 'airline_id'], 'aeta_unique');
        });

        // Existing single-airline templates keep working: their airline moves
        // into the pivot untouched.
        foreach (DB::table('airline_email_templates')->whereNotNull('airline_id')->get(['id', 'airline_id']) as $template) {
            DB::table('airline_email_template_airline')->insert([
                'airline_email_template_id' => $template->id,
                'airline_id'                => $template->airline_id,
            ]);
        }

        // Order matters: the foreign key leans on that unique index, so the
        // key goes first, then the index, then the column itself. The index
        // was scoped to a single airline, which no longer describes reach -
        // one default per type is enforced in the service instead.
        Schema::table('airline_email_templates', fn (Blueprint $table) => $table->dropForeign(['airline_id']));
        Schema::table('airline_email_templates', fn (Blueprint $table) => $table->dropUnique('airline_template_default_unique'));
        Schema::table('airline_email_templates', fn (Blueprint $table) => $table->dropColumn('airline_id'));
    }

    public function down(): void
    {
        Schema::table('airline_email_templates', function (Blueprint $table) {
            $table->foreignId('airline_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        foreach (DB::table('airline_email_template_airline')->get() as $row) {
            DB::table('airline_email_templates')->where('id', $row->airline_email_template_id)
                ->update(['airline_id' => $row->airline_id]);
        }

        Schema::dropIfExists('airline_email_template_airline');
    }
};
