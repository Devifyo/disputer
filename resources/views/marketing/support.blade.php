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
    .form-input, .form-textarea, .form-select { width: 100%; padding: 14px 16px; border: 1px solid var(--border); border-radius: 12px; background: var(--paper); font-family: 'Inter', sans-serif; font-size: 1rem; color: var(--ink); outline: none; transition: border-color 0.2s, box-shadow 0.2s; }
    .form-input:focus, .form-textarea:focus, .form-select:focus { border-color: var(--accent); box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1); background: var(--white); }
    .form-select { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 14px center; padding-right: 44px; cursor: pointer; }
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
        <button onclick="history.back()" style="display:inline-flex; align-items:center; gap:8px; font-size:0.875rem; font-weight:700; color:#475569; background:#f1f5f9; padding:8px 16px; border-radius:8px; border:none; cursor:pointer; transition:background 0.2s, color 0.2s;" onmouseover="this.style.background='#e2e8f0';this.style.color='#0f172a'" onmouseout="this.style.background='#f1f5f9';this.style.color='#475569'">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Back
        </button>
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
                <div style="display:flex; align-items:center; gap:12px; background:rgba(22,163,74,0.08); border:1px solid rgba(22,163,74,0.2); color:#15803d; padding:16px 20px; border-radius:12px; margin-bottom:24px; font-weight:600; font-size:0.9rem;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    {{ session('success') }}
                </div>
            @endif

            <form id="support-form" action="{{ route('support.submit') }}" method="POST" novalidate>
                @csrf
                <div class="form-group">
                    <label class="form-label" for="name">Your Name</label>
                    <input type="text" id="name" name="name" class="form-input" placeholder="Jane Doe" required value="{{ old('name', auth()->user()->name ?? '') }}">
                    @error('name') <span style="color:#ef4444; font-size:0.75rem; font-weight:700; margin-top:4px; display:block;">{{ $message }}</span> @enderror
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-input" placeholder="jane@example.com" required value="{{ old('email', auth()->user()->email ?? '') }}">
                    <span id="email-error" style="display:none; color:#ef4444; font-size:0.75rem; font-weight:700; margin-top:4px;">Please enter a valid email address.</span>
                    @error('email') <span style="color:#ef4444; font-size:0.75rem; font-weight:700; margin-top:4px; display:block;">{{ $message }}</span> @enderror
                </div>

                <div class="form-group">
                    <label class="form-label" for="subject">Subject</label>
                    <select id="subject" name="subject" class="form-select" required onchange="toggleCustomSubject(this.value)">
                        <option value="" disabled {{ old('subject') ? '' : 'selected' }}>Select a topic...</option>
                        <option value="Billing Question" {{ old('subject') === 'Billing Question' ? 'selected' : '' }}>Billing Question</option>
                        <option value="Technical Issue"  {{ old('subject') === 'Technical Issue'  ? 'selected' : '' }}>Technical Issue</option>
                        <option value="Case Advice"      {{ old('subject') === 'Case Advice'      ? 'selected' : '' }}>Case Advice</option>
                        <option value="Other"            {{ old('subject') === 'Other'            ? 'selected' : '' }}>Other</option>
                    </select>
                    @error('subject') <span style="color:#ef4444; font-size:0.75rem; font-weight:700; margin-top:4px; display:block;">{{ $message }}</span> @enderror

                    <div id="custom-subject-wrap" style="display:{{ old('subject') === 'Other' ? 'block' : 'none' }}; margin-top:12px;">
                        <input type="text" id="custom_subject" name="custom_subject" class="form-input"
                               placeholder="Briefly describe your subject..."
                               value="{{ old('custom_subject') }}"
                               maxlength="255">
                        @error('custom_subject') <span style="color:#ef4444; font-size:0.75rem; font-weight:700; margin-top:4px; display:block;">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="message">How can we help?</label>
                    <textarea id="message" name="message" class="form-textarea" placeholder="Describe your issue or question here..." required>{{ old('message') }}</textarea>
                    @error('message') <span style="color:#ef4444; font-size:0.75rem; font-weight:700; margin-top:4px; display:block;">{{ $message }}</span> @enderror
                </div>

                <button id="submit-btn" type="submit" class="btn-primary" style="width:100%; justify-content:center; margin-top:8px;">
                    <span id="btn-default" style="display:flex; align-items:center; gap:8px;">
                        Send Message
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:18px;height:18px;"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                    </span>
                    <span id="btn-loading" style="display:none; align-items:center; gap:8px;">
                        <svg style="width:18px;height:18px;animation:spin 1s linear infinite;" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="60" stroke-dashoffset="20" stroke-linecap="round"/></svg>
                        Sending...
                    </span>
                </button>
            </form>

            @push('scripts')
            <style>@keyframes spin { to { transform: rotate(360deg); } }</style>
            <script>
                function toggleCustomSubject(value) {
                    var wrap  = document.getElementById('custom-subject-wrap');
                    var input = document.getElementById('custom_subject');
                    var show  = value === 'Other';
                    wrap.style.display = show ? 'block' : 'none';
                    input.required     = show;
                    if (!show) input.value = '';
                }
            </script>
            <script>
                document.getElementById('support-form').addEventListener('submit', function(e) {
                    const emailInput = document.getElementById('email');
                    const emailError = document.getElementById('email-error');
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

                    if (!emailRegex.test(emailInput.value.trim())) {
                        e.preventDefault();
                        emailError.style.display = 'block';
                        emailInput.style.borderColor = '#ef4444';
                        emailInput.focus();
                        return;
                    }

                    emailError.style.display = 'none';
                    emailInput.style.borderColor = '';

                    const btn = document.getElementById('submit-btn');
                    btn.disabled = true;
                    btn.style.opacity = '0.75';
                    btn.style.cursor = 'not-allowed';
                    document.getElementById('btn-default').style.display = 'none';
                    document.getElementById('btn-loading').style.display = 'flex';
                });

                document.getElementById('email').addEventListener('input', function() {
                    document.getElementById('email-error').style.display = 'none';
                    this.style.borderColor = '';
                });
            </script>
            @endpush
        </div>

        <div class="faq-section">
            <h2 style="font-size: 1.5rem; font-weight: 800; color: var(--ink); margin-bottom: 8px; letter-spacing: -0.02em;">Common Questions</h2>
            
            <div class="faq-card">
                <h3 class="faq-question">What are the pricing plans?</h3>
                <p class="faq-answer">We offer three tiers: <strong>Starter</strong> at $14.99 (1 case), <strong>Standard</strong> at $29.99 (3 cases), and <strong>Pro</strong> at $99.99/year for unlimited cases. No hidden fees.</p>
            </div>

            <div class="faq-card">
                <h3 class="faq-question">Can I get a refund?</h3>
                <p class="faq-answer">If you experience a technical issue that prevents you from using the platform, contact us within 7 days of purchase. We review all refund requests fairly and promptly.</p>
            </div>

            <div class="faq-card">
                <h3 class="faq-question">Are my documents secure?</h3>
                <p class="faq-answer">Yes. We use industry-standard encryption for all data and documents. We never share your personal information with third parties.</p>
            </div>

            <div class="faq-card">
                <h3 class="faq-question">Can I cancel my subscription?</h3>
                <p class="faq-answer">Absolutely. You can cancel at any time from your billing dashboard. You retain full access until the end of your current billing cycle.</p>
            </div>
        </div>
    </section>
@endsection