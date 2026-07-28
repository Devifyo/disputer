<?php

use App\Models\AirlineEmailTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        // Airline directory: the spec's remaining identity fields.
        Schema::table('airlines', function (Blueprint $table) {
            $table->string('icao_code', 4)->nullable()->after('iata_code');
            $table->string('country', 80)->nullable()->after('icao_code');
        });

        // Per-airline claim letter templates. The AI uses the default one as
        // its base so airline-specific wording survives; admins can also send
        // a template verbatim with no AI involved.
        Schema::create('airline_email_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('airline_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 40)->index();
            $table->string('subject');
            $table->text('body');
            // NULL rather than false for "not the default": MySQL treats NULLs
            // as distinct, so the unique index below enforces exactly one
            // default per airline and type in the DATABASE - a race cannot
            // leave two. The model casts NULL to false for reading.
            $table->boolean('is_default')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['airline_id', 'type', 'is_default'], 'airline_template_default_unique');
        });

        // Email history gains provenance: which template, AI or not, and the
        // copy list. Scheduling rides on the same record.
        Schema::table('claim_correspondence', function (Blueprint $table) {
            $table->foreignId('template_id')->nullable()->after('claim_id')
                ->constrained('airline_email_templates')->nullOnDelete();
            $table->boolean('ai_generated')->default(false)->after('template_id');
            $table->json('cc')->nullable()->after('to_email');
            $table->json('bcc')->nullable()->after('cc');
            $table->string('status', 20)->default('sent')->after('matched_by');
            $table->timestamp('scheduled_at')->nullable()->after('status');
        });

        foreach ([
            'airlines.manage'         => 'Create, edit and remove airlines and their contacts',
            'claim_templates.manage'  => 'Create and edit airline email templates',
            'claim_templates.delete'  => 'Delete airline email templates',
            'claim_drafts.generate'   => 'Generate AI claim drafts',
            'claim_emails.send'       => 'Send claim emails to airlines',
        ] as $name => $description) {
            Permission::findOrCreate($name, 'web');
        }

        // Existing admins keep working exactly as before.
        if ($role = Role::where('name', 'admin')->first()) {
            $role->givePermissionTo([
                'airlines.manage', 'claim_templates.manage', 'claim_templates.delete',
                'claim_drafts.generate', 'claim_emails.send',
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('claim_correspondence', function (Blueprint $table) {
            $table->dropForeign(['template_id']);
            $table->dropColumn(['template_id', 'ai_generated', 'cc', 'bcc', 'status', 'scheduled_at']);
        });

        Schema::dropIfExists('airline_email_templates');

        Schema::table('airlines', fn (Blueprint $table) => $table->dropColumn(['icao_code', 'country']));

        Permission::whereIn('name', [
            'airlines.manage', 'claim_templates.manage', 'claim_templates.delete',
            'claim_drafts.generate', 'claim_emails.send',
        ])->delete();
    }
};
