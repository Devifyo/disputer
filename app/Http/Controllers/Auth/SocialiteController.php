<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class SocialiteController extends Controller
{
    public function redirect(string $provider)
    {
        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider)
    {
        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (Throwable) {
            return redirect()->route('login')->withErrors(['email' => 'Social login failed. Please try again.']);
        }

        $email = $socialUser->getEmail();

        if (!$email) {
            return redirect()->route('login')->withErrors(['email' => 'No email address returned. Please try another login method.']);
        }

        $user = User::where('email', $email)->first();

        if ($user) {
            // Merge: link this provider onto the existing account
            $user->update([
                'provider'        => $provider,
                'provider_id'     => $socialUser->getId(),
                'provider_avatar' => $socialUser->getAvatar(),
                'email_verified_at' => $user->email_verified_at ?? now(),
            ]);
        } else {
            $user = User::create([
                'name'              => $socialUser->getName() ?? $socialUser->getNickname() ?? 'User',
                'email'             => $email,
                'provider'          => $provider,
                'provider_id'       => $socialUser->getId(),
                'provider_avatar'   => $socialUser->getAvatar(),
                'email_verified_at' => now(),
            ]);

            $user->assignRole('user');
        }

        Auth::login($user, true);

        return $user->hasRole('admin')
            ? redirect()->route('admin.dashboard')
            : redirect()->route('user.dashboard');
    }
}
