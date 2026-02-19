<?php

namespace App\Livewire\Admin\Settings;

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

    public function mount()
    {
        $admin = auth()->user();
        $this->name = $admin->name;
        $this->email = $admin->email;
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
        return view('livewire.admin.settings.index')
               ->extends('layouts.admin')
               ->section('content');
    }
}