<?php

namespace App\Livewire\Admin\Settings;

use App\Models\Setting;
use App\Services\Claims\AdminAlertRecipients;
use App\Services\Eligibility\EligibilityEngine;
use Livewire\Component;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class Index extends Component
{
    // Profile
    public $name;
    public $email;

    // Security
    public $current_password;
    public $new_password;
    public $new_password_confirmation;

    // System Settings (Placeholder variables)
    public $app_name = 'ApplicantBill';
    public $support_email = 'support@example.com';

    // Trip Eligibility — engine verdicts below this confidence are auto-rejected.
    public $eligibility_confidence_threshold;

    // Flight Claims — success fee + confirmation screen trust indicators.
    public $claims_success_fee;
    public $claims_social_won;
    public $claims_social_recovered;

    // Where operational alerts go: [{name, email, alerts[]}] rows.
    public array $alert_recipients = [];

    // Website configuration — feature toggles.
    public $site_plus_promo;

    public function mount()
    {
        $admin = auth()->user();
        $this->name = $admin->name;
        $this->email = $admin->email;
        $this->eligibility_confidence_threshold = EligibilityEngine::confidenceThreshold();
        $this->claims_success_fee = Setting::get('claims.success_fee_percent', 25);
        $this->claims_social_won = Setting::get('claims.social_claims_won', '12,000+');
        $this->claims_social_recovered = Setting::get('claims.social_recovered', 'EUR 6.4M');
        $this->alert_recipients = AdminAlertRecipients::configured()
            ?: [['name' => '', 'email' => '', 'alerts' => array_keys(AdminAlertRecipients::TYPES)]];
        $this->site_plus_promo = (bool) Setting::get('app.plus_promo_enabled', true);
    }

    public function updateWebsite()
    {
        Setting::set('app.plus_promo_enabled', $this->site_plus_promo ? 1 : 0);

        $this->dispatch('toast', ['type' => 'success', 'message' => 'Website configuration saved.']);
    }

    public function updateClaims()
    {
        $this->validate([
            'claims_success_fee'      => 'required|numeric|min:0|max:50',
            'claims_social_won'       => 'required|string|max:40',
            'claims_social_recovered' => 'required|string|max:40',
            'alert_recipients.*.email' => 'nullable|email|max:190',
            'alert_recipients.*.name'  => 'nullable|string|max:80',
        ], [
            'alert_recipients.*.email.email' => 'Each alert recipient needs a valid email address.',
        ], ['claims_success_fee' => 'success fee']);

        Setting::set('claims.success_fee_percent', $this->claims_success_fee + 0);
        Setting::set('claims.social_claims_won', $this->claims_social_won);
        Setting::set('claims.social_recovered', $this->claims_social_recovered);
        AdminAlertRecipients::store($this->alert_recipients);

        $this->dispatch('toast', ['type' => 'success', 'message' => 'Claim settings saved.']);
    }

    public function addAlertRecipient(): void
    {
        $this->alert_recipients[] = ['name' => '', 'email' => '', 'alerts' => array_keys(AdminAlertRecipients::TYPES)];
    }

    public function removeAlertRecipient(int $index): void
    {
        unset($this->alert_recipients[$index]);
        $this->alert_recipients = array_values($this->alert_recipients);

        if ($this->alert_recipients === []) {
            $this->addAlertRecipient();
        }
    }

    public function updateEligibility()
    {
        $this->validate([
            'eligibility_confidence_threshold' => 'required|integer|min:0|max:100',
        ], [], ['eligibility_confidence_threshold' => 'confidence threshold']);

        Setting::set(EligibilityEngine::SETTING_THRESHOLD, (int) $this->eligibility_confidence_threshold);

        $this->dispatch('toast', ['type' => 'success', 'message' => 'Eligibility settings saved.']);
    }

    public function updateProfile()
    {
        $admin = auth()->user();

        $this->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($admin->id)],
        ]);

        $admin->update([
            'name' => $this->name,
            'email' => $this->email,
        ]);

        $this->dispatch('toast', ['type' => 'success', 'message' => 'Profile updated successfully.']);
    }

    public function updatePassword()
    {
        $this->validate([
            'current_password' => 'required|current_password',
            'new_password' => 'required|min:8|confirmed',
        ]);

        auth()->user()->update([
            'password' => Hash::make($this->new_password),
        ]);

        $this->reset(['current_password', 'new_password', 'new_password_confirmation']);
        $this->dispatch('toast', ['type' => 'success', 'message' => 'Password updated successfully.']);
    }

    public function updateSystem()
    {
        // Here you would typically save to a 'settings' table or a config file.
        // Setting::updateOrCreate(['key' => 'app_name'], ['value' => $this->app_name]);
        
        $this->dispatch('toast', ['type' => 'success', 'message' => 'System preferences saved.']);
    }

    public function render()
    {
        return view('livewire.admin.settings.index', [
                   'alertTypes' => AdminAlertRecipients::TYPES,
               ])
               ->extends('layouts.admin')
               ->section('content');
    }
}