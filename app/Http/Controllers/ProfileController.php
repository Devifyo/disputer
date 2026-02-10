<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\UserEmailConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        $emailConfig = UserEmailConfig::firstOrNew(['user_id' => $request->user()->id]);

        return view('profile.edit', [
            'user' => $request->user(),
            'emailConfig' => $emailConfig,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Update the User's SMTP/IMAP Configuration.
     */
    public function updateEmailConfig(Request $request): RedirectResponse
    {
        // VALIDATION RULES FOR EMAIL CONFIG FORM
        $validated = $request->validate([
            // Sending (SMTP)
            'from_name'       => ['required', 'string', 'max:255'],
            'from_email'      => ['required', 'email', 'max:255'],
            'smtp_host'       => ['required', 'string', 'max:255'],
            'smtp_port'       => ['required', 'integer', 'between:1,65535'],
            'smtp_username'   => ['required', 'string', 'max:255'],
            'smtp_password'   => ['required', 'string', 'max:255'], // Will be encrypted by Model cast
            'smtp_encryption' => ['required', 'in:tls,ssl,none'],

            // Receiving (IMAP)
            'imap_host'       => ['required', 'string', 'max:255'],
            'imap_port'       => ['required', 'integer', 'between:1,65535'],
            'imap_username'   => ['required', 'string', 'max:255'],
            'imap_password'   => ['required', 'string', 'max:255'], // Will be encrypted by Model cast
            'imap_encryption' => ['required', 'in:tls,ssl,none'],
        ]);

        UserEmailConfig::updateOrCreate(
            ['user_id' => $request->user()->id],
            $validated
        );

        return Redirect::route('profile.edit')->with('success', 'Email configuration updated successfully.');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        // VALIDATION FOR DELETE ACCOUNT FORM
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}