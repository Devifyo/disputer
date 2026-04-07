@extends('layouts.marketing')

@section('title', 'Support - Unjamm')
@section('meta_description', 'Contact our team for help. We are here to help you navigate institutions and get unstuck.')

@push('styles')
<style>
    /* ─── SUPPORT PAGE STYLES ─── */
    /* Reduced bottom padding to kill the huge white gap */
    header { padding: 140px 48px 40px; text-align: center; position: relative; overflow: hidden; }
    h1 { font-size: clamp(2.5rem, 4vw, 3.5rem); font-weight: 800; line-height: 1.05; letter-spacing: -0.035em; color: var(--ink); margin-bottom: 16px; }
    .hero-sub { font-size: 1.1rem; color: var(--muted); line-height: 1.6; font-weight: 400; max-width: 560px; margin: 0 auto; }
    
    /* Background shapes to break up empty space */
    .support-bg-shape { position: absolute; border-radius: 50%; pointer-events: none; }
    .shape-1 { top: -100px; right: 10%; width: 400px; height: 400px; background: radial-gradient(circle, rgba(37, 99, 235, 0.08) 0%, transparent 70%); }
    .shape-2 { top: 100px; left: -100px; width: 300px; height: 300px; background: radial-gradient(circle, rgba(129, 140, 248, 0.06) 0%, transparent 70%); }

    /* Asymmetric grid: Form is slightly wider (5fr) than FAQs (4fr) */
    #support-content { padding: 40px 48px 120px; max-width: 1100px; margin: 0 auto; display: grid; grid-template-columns: 5fr 4fr; gap: 80px; align-items: start; }
    
    .contact-form { background: var(--white); border: 1px solid var(--border); border-radius: 24px; padding: 40px; box-shadow: 0 20px 60px rgba(15, 23, 42, 0.05); }
    .form-group { margin-bottom: 20px; text-align: left; }
    .form-label { display: block; font-size: 0.875rem; font-weight: 600; color: var(--ink); margin-bottom: 8px; }
    .form-input, .form-textarea { width: 100%; padding: 14px 16px; border: 1px solid var(--border); border-radius: 12px; background: var(--paper); font-family: 'Inter', sans-serif; font-size: 1rem; color: var(--ink); outline: none; transition: border-color 0.2s, box-shadow 0.2s; }
    .form-input:focus, .form-textarea:focus { border-color: var(--accent); box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1); background: var(--white); }
    .form-textarea { min-height: 120px; resize: vertical; }

    /* Tighter FAQ gap */
    .faq-section { display: flex; flex-direction: column; gap: 16px; text-align: left; }
    .faq-card { padding: 24px; background: var(--cream); border-radius: 16px; border: 1px solid transparent; transition: all 0.2s; }
    .faq-card:hover { background: var(--white); border-color: var(--border); box-shadow: 0 10px 30px rgba(15, 23, 42, 0.03); }
    .faq-question { font-size: 1rem; font-weight: 700; color: var(--ink); margin-bottom: 8px; letter-spacing: -0.01em; }
    .faq-answer { font-size: 0.9rem; line-height: 1.6; color: var(--muted); }

    @media (max-width: 900px) {
        #support-content { grid-template-columns: 1fr; padding: 20px 24px 80px; gap: 48px; }
        header { padding: 120px 24px 20px; }
        .contact-form { padding: 32px 24px; }
    }
</style>
@endpush

@section('content')
    <div style="max-width:1100px; margin:0 auto; padding:88px 48px 0;">
        @php
            $backHref  = auth()->check() ? route('user.dashboard') : route('home');
            $backLabel = auth()->check() ? 'Back to Dashboard' : 'Back to Home';
        @endphp
        <a href="{{ $backHref }}" style="display:inline-flex; align-items:center; gap:8px; font-size:0.875rem; font-weight:700; color:#475569; background:#f1f5f9; padding:8px 16px; border-radius:8px; text-decoration:none; transition:background 0.2s, color 0.2s;" onmouseover="this.style.background='#e2e8f0';this.style.color='#0f172a'" onmouseout="this.style.background='#f1f5f9';this.style.color='#475569'">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            {{ $backLabel }}
        </a>
    </div>
    <header>
        <div class="support-bg-shape shape-1"></div>
        <div class="support-bg-shape shape-2"></div>
        <h1>How can we help?</h1>
        <p class="hero-sub">Whether you have a question about your account, need help with a case, or just want to leave feedback — we are here for you.</p>
    </header>

    <section id="support-content">
        <div class="contact-form">
            <h2 style="font-size: 1.5rem; font-weight: 800; color: var(--ink); margin-bottom: 8px; letter-spacing: -0.02em;">Send us a message</h2>
            
            <p style="font-size: 0.95rem; color: var(--muted); margin-bottom: 28px; line-height: 1.5;">
                Fill out the form below, or email us directly at <a href="mailto:{{config('mail.support_email')}}" style="color: var(--accent); font-weight: 600; text-decoration: none;">{{config('mail.support_email')}}</a>.
            </p>
            
            @if(session('success'))
                <div style="background: rgba(22, 163, 74, 0.1); color: #16a34a; padding: 16px; border-radius: 12px; margin-bottom: 24px; font-weight: 600; font-size: 0.9rem;">
                    {{ session('success') }}
                </div>
            @endif

            <form action="{{ route('support.submit') }}" method="POST">
                @csrf
                <div class="form-group">
                    <label class="form-label" for="name">Your Name</label>
                    <input type="text" id="name" name="name" class="form-input" placeholder="Jane Doe" required value="{{ old('name', auth()->user()->name ?? '') }}">
                    @error('name') <span class="text-red-500 text-xs font-bold mt-1 block">{{ $message }}</span> @enderror
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-input" placeholder="jane@example.com" required value="{{ old('email', auth()->user()->email ?? '') }}">
                    @error('email') <span class="text-red-500 text-xs font-bold mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div class="form-group">
                    <label class="form-label" for="message">How can we help?</label>
                    <textarea id="message" name="message" class="form-textarea" placeholder="Describe your issue or question here..." required>{{ old('message') }}</textarea>
                    @error('message') <span class="text-red-500 text-xs font-bold mt-1 block">{{ $message }}</span> @enderror
                </div>

                <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; margin-top: 8px;">
                    Send Message
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 18px; height: 18px;"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                </button>
            </form>
        </div>

        <div class="faq-section">
            <h2 style="font-size: 1.5rem; font-weight: 800; color: var(--ink); margin-bottom: 8px; letter-spacing: -0.02em;">Common Questions</h2>
            
            <div class="faq-card">
                <h3 class="faq-question">How does billing work?</h3>
                <p class="faq-answer">We offer one-time purchases for individual cases, or a yearly subscription for unlimited cases. You will never be charged hidden fees.</p>
            </div>

            <div class="faq-card">
                <h3 class="faq-question">Are my documents secure?</h3>
                <p class="faq-answer">Yes. We use industry-standard encryption for all data and documents. We never share your personal information with third parties.</p>
            </div>

            <div class="faq-card">
                <h3 class="faq-question">Can I cancel my subscription?</h3>
                <p class="faq-answer">Absolutely. You can cancel your subscription at any time directly from your billing dashboard. You will retain access until the end of your billing cycle.</p>
            </div>

            <!-- <div class="faq-card">
                <h3 class="faq-question">What languages do you support?</h3>
                <p class="faq-answer">Our AI can draft and respond in over 30 languages, making it perfect for navigating foreign institutions or cross-border issues.</p>
            </div> -->
        </div>
    </section>
@endsection