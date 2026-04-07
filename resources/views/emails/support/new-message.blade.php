@extends('emails.layout')

@section('content')

    {{-- Header Card with User Details --}}
    <div style="background-color: #f8fafc; padding: 20px; border-radius: 8px; margin-bottom: 24px; border: 1px solid #e2e8f0;">
        <h2 style="margin-top: 0; color: #0f172a; font-size: 1.25rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 12px; margin-bottom: 16px;">
            New Support Request
        </h2>
        
        <p style="margin: 0 0 8px 0; color: #475569; font-size: 0.95rem;">
            <strong style="color: #0f172a;">From:</strong> {{ $supportMessage->name }}
        </p>
        <p style="margin: 0 0 8px 0; color: #475569; font-size: 0.95rem;">
            <strong style="color: #0f172a;">Email:</strong> 
            <a href="mailto:{{ $supportMessage->email }}" style="color: #2563eb; text-decoration: none;">
                {{ $supportMessage->email }}
            </a>
        </p>
        <p style="margin: 0 0 8px 0; color: #475569; font-size: 0.95rem;">
            <strong style="color: #0f172a;">Subject:</strong>
            <span style="display:inline-block; background:#dbeafe; color:#1d4ed8; font-size:0.8rem; font-weight:700; padding:2px 10px; border-radius:20px; margin-left:4px;">{{ $supportMessage->subject }}</span>
        </p>
        <p style="margin: 0; color: #475569; font-size: 0.95rem;">
            <strong style="color: #0f172a;">User ID:</strong> {{ $supportMessage->user_id ?? 'Guest (Not logged in)' }}
        </p>
    </div>

    {{-- The Actual Message --}}
    <h3 style="color: #0f172a; margin-bottom: 12px; font-size: 1.1rem;">Message:</h3>
    
    <div style="background-color: #ffffff; border: 1px solid #e2e8f0; padding: 24px; border-radius: 8px; color: #334155; line-height: 1.6; white-space: pre-wrap; font-size: 0.95rem;">{{ $supportMessage->message }}</div>

@endsection