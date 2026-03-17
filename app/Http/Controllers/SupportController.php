<?php

namespace App\Http\Controllers;

use App\Models\SupportMessage;
use App\Mail\NewSupportMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SupportController extends Controller
{
    public function index()
    {
        return view('marketing.support');
    }

    public function submit(Request $request)
    {
        // 1. Validate the incoming request
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'message' => 'required|string|min:10|max:5000',
        ]);

        // 2. Save the message to the database
        $supportMessage = SupportMessage::create([
            'user_id' => auth()->check() ? auth()->id() : null,
            'name' => $request->name,
            'email' => $request->email,
            'message' => $request->message,
            'status' => 'new',
        ]);

        // 3. Send the Email via SendGrid
        try {
            Mail::to(config('mail.support_email'))->send(new NewSupportMessage($supportMessage));
        } catch (\Exception $e) {
            // Log the error so you can see it in storage/logs/laravel.log, 
            // but don't break the user's experience by showing them an ugly error screen.
            Log::error('Failed to send support email: ' . $e->getMessage());
        }

        // 4. Redirect back with a success message
        return back()->with('success', 'Your message has been sent. We will get back to you shortly.');
    }
}