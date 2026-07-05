@extends('layouts.marketing')

@section('title', 'Support — Unjamm')
@section('meta_description', 'Get help with your Unjamm flight compensation claim, your account, or billing. We are here to help air passengers get paid.')

@push('styles')
<style>
    .sup-header { padding: 150px 32px 20px; text-align: center; position: relative; overflow: hidden; }
    .sup-header h1 { font-size: clamp(2.4rem, 4vw, 3.4rem); font-weight: 700; line-height: 1.03; letter-spacing: -.03em; color: var(--text); margin: 0 0 16px; }
    .sup-sub { font-size: 1.05rem; color: var(--muted); line-height: 1.6; max-width: 560px; margin: 0 auto; }
    .sup-shape { position: absolute; border-radius: 50%; pointer-events: none; }
    .sup-shape-1 { top: -120px; right: 8%; width: 420px; height: 420px; background: radial-gradient(circle, rgba(63,203,148,.14) 0%, transparent 70%); }
    .sup-shape-2 { top: 60px; left: -120px; width: 320px; height: 320px; background: radial-gradient(circle, rgba(63,203,148,.08) 0%, transparent 70%); }

    #support-content { padding: 40px 32px 120px; max-width: 1100px; margin: 0 auto; display: grid; grid-template-columns: 5fr 4fr; gap: 64px; align-items: start; }

    .contact-form { background: var(--card); border: 1px solid var(--border); border-radius: 24px; padding: 40px; box-shadow: 0 40px 90px -50px rgba(0,0,0,.8); }
    .form-group { margin-bottom: 20px; text-align: left; }
    .form-label { display: block; font-size: .875rem; font-weight: 600; color: var(--text); margin-bottom: 8px; }
    .form-input, .form-textarea, .form-select { width: 100%; padding: 14px 16px; border: 1px solid var(--border); border-radius: 12px; background: var(--field); font-family: 'Hanken Grotesk', sans-serif; font-size: 1rem; color: var(--text); outline: none; transition: border-color .2s, box-shadow .2s; }
    .form-input::placeholder, .form-textarea::placeholder { color: var(--faint); }
    .form-input:focus, .form-textarea:focus, .form-select:focus { border-color: var(--accent); box-shadow: 0 0 0 4px rgba(63,203,148,.12); }
    .form-select { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%239BA4B0' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 14px center; padding-right: 44px; cursor: pointer; }
    .form-select option { color: #15171B; }
    .form-textarea { min-height: 120px; resize: vertical; }

    .faq-section { display: flex; flex-direction: column; gap: 16px; text-align: left; }
    .faq-card { padding: 24px; background: var(--card); border-radius: 16px; border: 1px solid var(--border); transition: all .2s; }
    .faq-card:hover { border-color: rgba(63,203,148,.35); box-shadow: 0 24px 50px -34px rgba(0,0,0,.8); }
    .faq-question { font-family: 'Bricolage Grotesque', sans-serif; font-size: 1rem; font-weight: 600; color: var(--text); margin: 0 0 8px; letter-spacing: -.01em; }
    .faq-answer { font-size: .9rem; line-height: 1.6; color: var(--muted); margin: 0; }

    @media (max-width: 900px) {
        #support-content { grid-template-columns: 1fr; padding: 20px 24px 80px; gap: 40px; }
        .sup-header { padding: 130px 24px 12px; }
        .contact-form { padding: 32px 24px; }
    }

    @keyframes shake { 0%,100% { transform: translateX(0); } 15%,45%,75% { transform: translateX(-6px); } 30%,60%,90% { transform: translateX(6px); } }
    .shake { animation: shake .5s ease-in-out; }
    @keyframes slideDown { from { opacity: 0; transform: translateY(-4px); } to { opacity: 1; transform: translateY(0); } }
    .slide-down { animation: slideDown .2s ease-out forwards; }

    .field-error { border-color: var(--danger) !important; }
    .field-success { border-color: var(--accent) !important; }
    .field-error:focus { border-color: var(--danger) !important; box-shadow: 0 0 0 4px rgba(248,113,113,.12) !important; }
    .field-success:focus { border-color: var(--accent) !important; box-shadow: 0 0 0 4px rgba(63,203,148,.12) !important; }
</style>
@endpush

@section('content')
    <header class="sup-header">
        <div class="sup-shape sup-shape-1"></div>
        <div class="sup-shape sup-shape-2"></div>
        <h1>How can we help?</h1>
        <p class="sup-sub">Questions about a claim, your account, or a payout — we're here for you. Most messages get a reply within one business day.</p>
    </header>

    <section id="support-content">
        <div class="contact-form">
            <h2 style="font-family:'Bricolage Grotesque',sans-serif;font-size:1.5rem;font-weight:700;color:var(--text);margin:0 0 8px;letter-spacing:-.02em;">Send us a message</h2>

            <p style="font-size:.95rem;color:var(--muted);margin:0 0 28px;line-height:1.5;">
                Fill out the form below, or email us directly at <a href="mailto:{{ config('mail.support_email') }}" style="color:var(--accent);font-weight:600;text-decoration:none;">{{ config('mail.support_email') }}</a>.
            </p>

            @if(session('success'))
                <div style="display:flex;align-items:center;gap:12px;background:var(--chip);border:1px solid rgba(63,203,148,.3);color:var(--accent);padding:16px 20px;border-radius:12px;margin-bottom:24px;font-weight:600;font-size:.9rem;">
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
                       class="slide-down" style="color:var(--danger); font-size:.75rem; font-weight:700; margin-top:4px;"></p>
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
                       class="slide-down" style="color:var(--danger); font-size:.75rem; font-weight:700; margin-top:4px;"></p>
                </div>

                {{-- Subject --}}
                <div class="form-group">
                    <label class="form-label" for="subject">Subject</label>
                    <select id="subject" name="subject"
                        x-model="subject"
                        @change="validateSubject()"
                        :class="subjectState === 'error' ? 'form-select field-error' : subjectState === 'success' ? 'form-select field-success' : 'form-select'">
                        <option value="" disabled>Select a topic...</option>
                        <option value="Claim Status">Claim Status</option>
                        <option value="Forwarding an Itinerary">Forwarding an Itinerary</option>
                        <option value="Payout Question">Payout Question</option>
                        <option value="Billing Question">Billing Question</option>
                        <option value="Technical Issue">Technical Issue</option>
                        <option value="Other">Other</option>
                    </select>
                    <p x-show="subjectState === 'error'" x-text="errors.subject"
                       class="slide-down" style="color:var(--danger); font-size:.75rem; font-weight:700; margin-top:4px;"></p>

                    <div x-show="subject === 'Other'" style="margin-top:12px;">
                        <input type="text" id="custom_subject" name="custom_subject"
                            placeholder="Briefly describe your subject..."
                            maxlength="255"
                            x-model="custom_subject"
                            @blur="validateCustom()"
                            @input="if(touched.custom_subject) errors.custom_subject = ''"
                            :class="customState === 'error' ? 'form-input field-error' : customState === 'success' ? 'form-input field-success' : 'form-input'">
                        <p x-show="customState === 'error'" x-text="errors.custom_subject"
                           class="slide-down" style="color:var(--danger); font-size:.75rem; font-weight:700; margin-top:4px;"></p>
                    </div>
                </div>

                {{-- Message --}}
                <div class="form-group">
                    <label class="form-label" for="message">How can we help?</label>
                    <textarea id="message" name="message" placeholder="Tell us your flight number, date, and what went wrong..."
                        x-model="message"
                        @blur="validateMessage()"
                        @input="if(touched.message) errors.message = ''"
                        :class="messageState === 'error' ? 'form-textarea field-error' : messageState === 'success' ? 'form-textarea field-success' : 'form-textarea'"></textarea>
                    <p x-show="messageState === 'error'" x-text="errors.message"
                       class="slide-down" style="color:var(--danger); font-size:.75rem; font-weight:700; margin-top:4px;"></p>
                </div>

                <button type="submit" class="uj-btn-primary" style="width:100%; justify-content:center; margin-top:8px; padding:15px;"
                    :disabled="loading" :style="loading ? 'opacity:0.75; cursor:not-allowed;' : ''">
                    <span x-show="!loading" style="display:flex; align-items:center; gap:8px;">
                        Send Message
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:18px;height:18px;"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                    </span>
                    <span x-show="loading" style="display:none; align-items:center; gap:8px;">
                        <svg style="width:18px;height:18px;animation:ujSpin 1s linear infinite;" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="60" stroke-dashoffset="20" stroke-linecap="round"/></svg>
                        Sending...
                    </span>
                </button>
            </form>
        </div>

        <div class="faq-section">
            <h2 style="font-family:'Bricolage Grotesque',sans-serif;font-size:1.5rem;font-weight:700;color:var(--text);margin:0 0 8px;letter-spacing:-.02em;">Common Questions</h2>

            <div class="faq-card">
                <h3 class="faq-question">How much does Unjamm cost?</h3>
                <p class="faq-answer">Nothing up front. Unjamm works on a <strong>no win, no fee</strong> basis — we only take a flat <strong>25% success fee</strong> on compensation we actually recover for you. If we don't get paid, neither do we.</p>
            </div>

            <div class="faq-card">
                <h3 class="faq-question">Which flights qualify for compensation?</h3>
                <p class="faq-answer">Delays of 3+ hours, cancellations, denied boarding, missed connections and long baggage delays can all qualify under EU 261, UK 261, Canada APPR, US DOT or the Montreal Convention. Forward your itinerary and we'll check every rule automatically.</p>
            </div>

            <div class="faq-card">
                <h3 class="faq-question">How do I start a claim?</h3>
                <p class="faq-answer">Forward your flight confirmation to <a href="mailto:claims@unjamm.com" style="color:var(--accent);font-weight:600;text-decoration:none;">claims@unjamm.com</a> from any email provider, then sign a one-time Power of Attorney. We monitor the flight and file the moment a disruption qualifies.</p>
            </div>

            <div class="faq-card">
                <h3 class="faq-question">How long until I get paid?</h3>
                <p class="faq-answer">It depends on the airline, but the average time to payout is around <strong>12 days</strong> once a claim is filed. You'll see live status updates in your dashboard the whole way through.</p>
            </div>

            <div class="faq-card">
                <h3 class="faq-question">Is my data secure?</h3>
                <p class="faq-answer">Yes. We use industry-standard encryption and only use your itinerary details to pursue your claim. We never sell your personal information. See our <a href="{{ route('privacy') }}" style="color:var(--accent);font-weight:600;text-decoration:none;">Privacy Policy</a> for details.</p>
            </div>
        </div>
    </section>
@endsection
