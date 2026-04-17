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
        .back-wrapper { padding: 88px 24px 0 !important; }
    }

    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        15%, 45%, 75% { transform: translateX(-6px); }
        30%, 60%, 90% { transform: translateX(6px); }
    }
    .shake { animation: shake 0.5s ease-in-out; }
    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-4px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    .slide-down { animation: slideDown 0.2s ease-out forwards; }

    .field-error  { border-color: #f87171 !important; background: rgba(239,68,68,0.03) !important; }
    .field-success { border-color: #34d399 !important; background: rgba(52,211,153,0.04) !important; }
    .field-error:focus  { border-color: #f87171 !important; box-shadow: 0 0 0 4px rgba(239,68,68,0.1) !important; }
    .field-success:focus { border-color: #34d399 !important; box-shadow: 0 0 0 4px rgba(52,211,153,0.1) !important; }
</style>
@endpush

@section('content')
    <div class="back-wrapper" style="padding: 88px 48px 0; max-width:1100px; margin:0 auto;">
        <button onclick="history.back()" style="display:inline-flex; align-items:center; gap:8px; font-size:0.875rem; font-weight:600; color:#334155; background:#fff; padding:8px 16px; border-radius:8px; border:1.5px solid #cbd5e1; cursor:pointer; transition:all 0.2s; box-shadow:0 1px 3px rgba(15,23,42,0.08);" onmouseover="this.style.background='#f8fafc';this.style.borderColor='#94a3b8';this.style.color='#0f172a'" onmouseout="this.style.background='#fff';this.style.borderColor='#cbd5e1';this.style.color='#334155'">
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

            <form
                action="{{ route('support.submit') }}"
                method="POST"
                x-data="{
                    name: '{{ old('name', auth()->user()->name ?? '') }}',
                    email: '{{ old('email', auth()->user()->email ?? '') }}',
                    subject: '{{ old('subject', '') }}',
                    custom_subject: '{{ old('custom_subject', '') }}',
                    message: '{{ old('message', '') }}',
                    loading: false,
                    touched: {
                        name: {{ old('name') || $errors->has('name') ? 'true' : 'false' }},
                        email: {{ old('email') || $errors->has('email') ? 'true' : 'false' }},
                        subject: {{ old('subject') || $errors->has('subject') ? 'true' : 'false' }},
                        custom_subject: {{ old('custom_subject') || $errors->has('custom_subject') ? 'true' : 'false' }},
                        message: {{ old('message') || $errors->has('message') ? 'true' : 'false' }}
                    },
                    errors: {
                        name: '{{ addslashes($errors->first('name')) }}',
                        email: '{{ addslashes($errors->first('email')) }}',
                        subject: '{{ addslashes($errors->first('subject')) }}',
                        custom_subject: '{{ addslashes($errors->first('custom_subject')) }}',
                        message: '{{ addslashes($errors->first('message')) }}'
                    },
                    get nameState()    { if (!this.touched.name)    return 'idle'; return this.errors.name    ? 'error' : (this.name.trim()    ? 'success' : 'idle'); },
                    get emailState()   { if (!this.touched.email)   return 'idle'; return this.errors.email   ? 'error' : (this.email          ? 'success' : 'idle'); },
                    get subjectState() { if (!this.touched.subject) return 'idle'; return this.errors.subject ? 'error' : (this.subject        ? 'success' : 'idle'); },
                    get customState()  { if (!this.touched.custom_subject) return 'idle'; return this.errors.custom_subject ? 'error' : (this.custom_subject.trim() ? 'success' : 'idle'); },
                    get messageState() { if (!this.touched.message) return 'idle'; return this.errors.message ? 'error' : (this.message.trim() ? 'success' : 'idle'); },
                    validateName()    { this.touched.name = true;    const v = this.name.trim();    if (!v) { this.errors.name = 'Your name is required.'; return; } if (v.length < 2) { this.errors.name = 'Name must be at least 2 characters.'; return; } this.errors.name = ''; },
                    validateEmail()   { this.touched.email = true;   const v = this.email.trim();   if (!v) { this.errors.email = 'Email address is required.'; return; } if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) { this.errors.email = 'Please enter a valid email address.'; return; } this.errors.email = ''; },
                    validateSubject() { this.touched.subject = true; if (!this.subject) { this.errors.subject = 'Please select a subject.'; return; } this.errors.subject = ''; if (this.subject !== 'Other') { this.errors.custom_subject = ''; } },
                    validateCustom()  { this.touched.custom_subject = true; if (this.subject === 'Other' && !this.custom_subject.trim()) { this.errors.custom_subject = 'Please describe your subject.'; return; } this.errors.custom_subject = ''; },
                    validateMessage() { this.touched.message = true; const v = this.message.trim(); if (!v) { this.errors.message = 'Please describe how we can help.'; return; } if (v.length < 10) { this.errors.message = 'Message must be at least 10 characters.'; return; } this.errors.message = ''; },
                    handleSubmit() {
                        this.validateName(); this.validateEmail(); this.validateSubject();
                        if (this.subject === 'Other') this.validateCustom();
                        this.validateMessage();
                        const hasError = this.errors.name || this.errors.email || this.errors.subject || this.errors.custom_subject || this.errors.message;
                        if (hasError) {
                            this.$el.classList.add('shake');
                            setTimeout(() => this.$el.classList.remove('shake'), 500);
                            return;
                        }
                        this.loading = true;
                        this.$el.submit();
                    }
                }"
                @submit.prevent="handleSubmit()"
            >
                @csrf

                {{-- Name --}}
                <div class="form-group">
                    <label class="form-label" for="name">Your Name</label>
                    <input type="text" id="name" name="name" placeholder="Jane Doe"
                        x-model="name"
                        @blur="validateName()"
                        @input="if(touched.name) errors.name = ''"
                        :class="nameState === 'error' ? 'form-input field-error' : nameState === 'success' ? 'form-input field-success' : 'form-input'">
                    <p x-show="nameState === 'error'" x-text="errors.name"
                       class="slide-down" style="color:#ef4444; font-size:0.75rem; font-weight:700; margin-top:4px;"></p>
                </div>

                {{-- Email --}}
                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <input type="email" id="email" name="email" placeholder="jane@example.com"
                        x-model="email"
                        @blur="validateEmail()"
                        @input="if(touched.email) errors.email = ''"
                        :class="emailState === 'error' ? 'form-input field-error' : emailState === 'success' ? 'form-input field-success' : 'form-input'">
                    <p x-show="emailState === 'error'" x-text="errors.email"
                       class="slide-down" style="color:#ef4444; font-size:0.75rem; font-weight:700; margin-top:4px;"></p>
                </div>

                {{-- Subject --}}
                <div class="form-group">
                    <label class="form-label" for="subject">Subject</label>
                    <select id="subject" name="subject"
                        x-model="subject"
                        @change="validateSubject()"
                        :class="subjectState === 'error' ? 'form-select field-error' : subjectState === 'success' ? 'form-select field-success' : 'form-select'">
                        <option value="" disabled>Select a topic...</option>
                        <option value="Billing Question">Billing Question</option>
                        <option value="Technical Issue">Technical Issue</option>
                        <option value="Case Advice">Case Advice</option>
                        <option value="Other">Other</option>
                    </select>
                    <p x-show="subjectState === 'error'" x-text="errors.subject"
                       class="slide-down" style="color:#ef4444; font-size:0.75rem; font-weight:700; margin-top:4px;"></p>

                    <div x-show="subject === 'Other'" style="margin-top:12px;">
                        <input type="text" id="custom_subject" name="custom_subject"
                            placeholder="Briefly describe your subject..."
                            maxlength="255"
                            x-model="custom_subject"
                            @blur="validateCustom()"
                            @input="if(touched.custom_subject) errors.custom_subject = ''"
                            :class="customState === 'error' ? 'form-input field-error' : customState === 'success' ? 'form-input field-success' : 'form-input'">
                        <p x-show="customState === 'error'" x-text="errors.custom_subject"
                           class="slide-down" style="color:#ef4444; font-size:0.75rem; font-weight:700; margin-top:4px;"></p>
                    </div>
                </div>

                {{-- Message --}}
                <div class="form-group">
                    <label class="form-label" for="message">How can we help?</label>
                    <textarea id="message" name="message" placeholder="Describe your issue or question here..."
                        x-model="message"
                        @blur="validateMessage()"
                        @input="if(touched.message) errors.message = ''"
                        :class="messageState === 'error' ? 'form-textarea field-error' : messageState === 'success' ? 'form-textarea field-success' : 'form-textarea'"></textarea>
                    <p x-show="messageState === 'error'" x-text="errors.message"
                       class="slide-down" style="color:#ef4444; font-size:0.75rem; font-weight:700; margin-top:4px;"></p>
                </div>

                <button type="submit" class="btn-primary" style="width:100%; justify-content:center; margin-top:8px;"
                    :disabled="loading" :style="loading ? 'opacity:0.75; cursor:not-allowed;' : ''">
                    <span x-show="!loading" style="display:flex; align-items:center; gap:8px;">
                        Send Message
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:18px;height:18px;"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                    </span>
                    <span x-show="loading" style="display:none; align-items:center; gap:8px;">
                        <svg style="width:18px;height:18px;animation:spin 1s linear infinite;" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="60" stroke-dashoffset="20" stroke-linecap="round"/></svg>
                        Sending...
                    </span>
                </button>
            </form>

            @push('scripts')
            <style>@keyframes spin { to { transform: rotate(360deg); } }</style>
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