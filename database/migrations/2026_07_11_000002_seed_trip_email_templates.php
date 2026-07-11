<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Trip Protection emails, editable by admins under Admin → Templates. */
    private const TEMPLATES = [
        [
            'slug'    => 'trip-disruption-detected',
            'subject' => 'Your monitored trip [FLIGHT] was [DISRUPTION]',
            'body'    => <<<'HTML'
<p>Hi [NAME],</p>
<p>[HEADLINE]</p>
<p><strong>Route:</strong> [ROUTE]<br><strong>Date:</strong> [DATE]</p>
<p>We're reviewing your eligibility for compensation - no action is needed from you right now.</p>
<p><a href="[TRIP_URL]">View your trip</a></p>
<p>Thank you for protecting your trip with Unjamm.</p>
HTML,
        ],
        [
            'slug'    => 'trip-eligible-compensation',
            'subject' => 'Good news - your trip [FLIGHT] is eligible for compensation',
            'body'    => <<<'HTML'
<p>Hi [NAME],</p>
<p>Your monitored trip [FLIGHT] ([ROUTE], [DATE]) is <strong>eligible for compensation</strong> under [REGULATION] - [ARTICLE].</p>
<p><a href="[TRIP_URL]">View your trip and start your claim</a></p>
<p>We'll guide you through the next steps to claim what you're owed.</p>
HTML,
        ],
        [
            'slug'    => 'trip-eligibility-rejected',
            'subject' => 'Update on your trip [FLIGHT] - review complete',
            'body'    => <<<'HTML'
<p>Hi [NAME],</p>
<p>Our team has reviewed your case for [FLIGHT] ([ROUTE]) and unfortunately it doesn't qualify for compensation.</p>
<p><strong>Reason:</strong> [REASON]</p>
<p><a href="[TRIP_URL]">View your trip</a></p>
<p>If you have new information or documents that change the picture, you can reach us any time through support.</p>
HTML,
        ],
    ];

    public function up(): void
    {
        foreach (self::TEMPLATES as $template) {
            DB::table('email_templates')->updateOrInsert(
                ['slug' => $template['slug']],
                $template + ['is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    public function down(): void
    {
        DB::table('email_templates')->whereIn('slug', array_column(self::TEMPLATES, 'slug'))->delete();
    }
};
