<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function createLogin()
    {   
        return view('auth.login');
    }

    public function storeLogin(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            
            return Auth::user()->hasRole('admin') 
                ? redirect()->route('admin.dashboard') 
                : redirect()->route('user.dashboard');
        }

        return back()->withErrors(['email' => 'The provided credentials do not match our records.'])->onlyInput('email');
    }

    public function createRegister()
    {
        return view('auth.register');
    }

    public function storeRegister(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);
        
        $user->assignRole('user');
        Auth::login($user);

        return redirect()->route('user.dashboard');
    }

    public function createForgotPassword()
    {
        return view('auth.forgot-password');
    }

    public function storeForgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        if ($user = User::where('email', $request->email)->first()) {
            $token = Password::broker()->createToken($user);
            
            send_dynamic_email($user->email, 'forgot-password', [
                '[USER_NAME]' => $user->name,
                '[RESET_LINK]' => route('password.reset', ['token' => $token, 'email' => $user->email])
            ]);
        }

        return back()->with('status', 'If that email matches an account, we have sent a password reset link!');
    }

    public function showResetPassword(Request $request, $token)
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->email]);
    }

    public function submitResetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill(['password' => Hash::make($password)])
                     ->setRememberToken(Str::random(60));
                     
                $user->save();
                event(new PasswordReset($user));
            }
        );

        // Redirect to login with a custom success message, or back with the error
        return $status == Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', 'Your password has been successfully reset! You can now log in.')
            : back()->withErrors(['email' => [__($status)]]);
    }

    public function destroy(Request $request)
    {
        Auth::guard('web')->logout();
        
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}