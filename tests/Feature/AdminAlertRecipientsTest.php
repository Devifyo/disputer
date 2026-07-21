<?php

namespace Tests\Feature;

use App\Livewire\Admin\Settings\Index as SettingsIndex;
use App\Models\Setting;
use App\Models\User;
use App\Services\Claims\AdminAlertRecipients;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Operational alerts go to the configured recipients - each subscribed to
 * the alert types they care about - and never silently nowhere.
 */
class AdminAlertRecipientsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Role::findOrCreate('admin');
    }

    public function test_each_alert_type_reaches_only_its_subscribers(): void
    {
        AdminAlertRecipients::store([
            ['name' => 'Ops', 'email' => 'ops@unjamm.com', 'alerts' => ['airline_reply']],
            ['name' => 'Manager', 'email' => 'boss@unjamm.com', 'alerts' => ['escalation']],
            ['name' => 'Claims', 'email' => 'claims@unjamm.com', 'alerts' => ['escalation', 'airline_reply']],
        ]);

        $this->assertSame(
            ['boss@unjamm.com', 'claims@unjamm.com'],
            collect(AdminAlertRecipients::for(AdminAlertRecipients::TYPE_ESCALATION))->pluck('email')->all()
        );

        $this->assertSame(
            ['ops@unjamm.com', 'claims@unjamm.com'],
            collect(AdminAlertRecipients::for(AdminAlertRecipients::TYPE_AIRLINE_REPLY))->pluck('email')->all()
        );
    }

    public function test_admin_accounts_are_the_fallback_when_nobody_subscribes(): void
    {
        $admin = User::factory()->create(['email' => 'boss@unjamm.com']);
        $admin->assignRole('admin');

        // Nothing configured at all.
        $this->assertSame(['boss@unjamm.com'], collect(AdminAlertRecipients::for())->pluck('email')->all());

        // Configured, but nobody subscribed to escalations - still not dropped.
        AdminAlertRecipients::store([['name' => 'Ops', 'email' => 'ops@unjamm.com', 'alerts' => ['airline_reply']]]);
        $this->assertSame(
            ['boss@unjamm.com'],
            collect(AdminAlertRecipients::for(AdminAlertRecipients::TYPE_ESCALATION))->pluck('email')->all()
        );
    }

    public function test_legacy_comma_separated_value_still_receives_every_alert(): void
    {
        Setting::set('claims.alert_recipients', 'a@x.com, b@x.com');

        $this->assertSame(
            ['a@x.com', 'b@x.com'],
            collect(AdminAlertRecipients::for(AdminAlertRecipients::TYPE_ESCALATION))->pluck('email')->all()
        );
    }

    public function test_blank_rows_and_duplicates_are_discarded_on_save(): void
    {
        AdminAlertRecipients::store([
            ['name' => 'Ops', 'email' => 'Ops@Unjamm.com', 'alerts' => ['escalation']],
            ['name' => '', 'email' => '', 'alerts' => []],
            ['name' => 'Dup', 'email' => 'ops@unjamm.com', 'alerts' => ['airline_reply']],
        ]);

        $stored = AdminAlertRecipients::configured();
        $this->assertCount(1, $stored);
        $this->assertSame('ops@unjamm.com', $stored[0]['email']);
    }

    public function test_settings_screen_manages_recipient_rows(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)
            ->test(SettingsIndex::class)
            ->set('claims_success_fee', 25)
            ->set('claims_social_won', '12,000+')
            ->set('claims_social_recovered', 'EUR 6.4M')
            ->call('addAlertRecipient')
            ->set('alert_recipients.0.email', 'ops@unjamm.com')
            ->set('alert_recipients.0.alerts', ['airline_reply'])
            ->set('alert_recipients.1.email', 'not-an-email')
            ->call('updateClaims')
            ->assertHasErrors('alert_recipients.1.email')
            ->set('alert_recipients.1.email', 'boss@unjamm.com')
            ->set('alert_recipients.1.alerts', ['escalation'])
            ->call('updateClaims')
            ->assertHasNoErrors();

        $this->assertSame(
            ['ops@unjamm.com'],
            collect(AdminAlertRecipients::for(AdminAlertRecipients::TYPE_AIRLINE_REPLY))->pluck('email')->all()
        );
        $this->assertSame(
            ['boss@unjamm.com'],
            collect(AdminAlertRecipients::for(AdminAlertRecipients::TYPE_ESCALATION))->pluck('email')->all()
        );
    }
}
