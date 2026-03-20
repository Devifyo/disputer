@extends('layouts.marketing')

@section('title', 'Unjamm - Resolve complaints with companies faster')
@section('meta_description', 'Unjamm helps you escalate disputes with banks, airlines, telecom companies, and other institutions using structured escalation workflows.')

@push('styles')
<style>
    /* ─── EXACT HOME STYLES ─── */
    header {
        min-height: 100vh;
        padding: 140px 48px 80px;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 60px;
        align-items: center;
        position: relative;
        overflow: hidden;
    }
    .hero-bg-shape {
        position: absolute; right: -100px; top: 50%;
        transform: translateY(-50%);
        width: 600px; height: 600px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(37, 99, 235, 0.12) 0%, transparent 70%);
        pointer-events: none;
    }
    .hero-tag {
        display: inline-flex; align-items: center; gap: 8px;
        padding: 6px 14px;
        border: 1px solid rgba(37, 99, 235, 0.2);
        border-radius: 100px;
        font-size: 0.75rem; font-weight: 600; letter-spacing: 0.08em;
        text-transform: uppercase; color: var(--accent);
        background: rgba(37, 99, 235, 0.06);
        margin-bottom: 28px;
    }
    .hero-tag span { width: 6px; height: 6px; background: var(--accent); border-radius: 50%; display: block; }
    h1 {
        font-size: clamp(3rem, 4.5vw, 4.5rem);
        font-weight: 800;
        line-height: 1.05;
        letter-spacing: -0.035em;
        color: var(--ink);
        margin-bottom: 28px;
    }
    h1 em {
        font-style: normal;
        color: var(--accent);
        position: relative;
    }
    h1 em::after {
        content: '';
        position: absolute; bottom: 4px; left: 0; right: 0;
        height: 3px;
        background: var(--accent-light);
        border-radius: 2px;
    }
    .hero-sub {
        font-size: 1.15rem; color: var(--muted);
        line-height: 1.7; font-weight: 400;
        max-width: 480px;
        margin-bottom: 40px;
    }
    .hero-actions { display: flex; gap: 16px; align-items: center; flex-wrap: wrap; }
    
    /* NEW: Secondary Button Style */
    .btn-secondary {
        display: inline-flex; align-items: center; gap: 8px;
        padding: 14px 28px; border-radius: 12px;
        font-size: 1rem; font-weight: 600;
        color: var(--ink); background: transparent;
        border: 1px solid var(--border);
        transition: all 0.2s; text-decoration: none;
    }
    .btn-secondary:hover { background: rgba(15, 23, 42, 0.03); border-color: rgba(15, 23, 42, 0.2); }

    .hero-visual {
        position: relative;
        display: flex; flex-direction: column; gap: 16px;
    }
    .hero-card {
        background: var(--white);
        border: 1px solid var(--border);
        border-radius: 20px;
        padding: 24px 28px;
        box-shadow: 0 2px 20px rgba(15, 23, 42, 0.05);
        transition: transform 0.3s;
        width: 100%;
    }
    .hero-card:hover { transform: translateX(-6px); }
    .hero-card-label {
        font-size: 0.7rem; font-weight: 700; letter-spacing: 0.1em;
        text-transform: uppercase; color: var(--muted);
        margin-bottom: 8px;
    }
    .hero-card-title {
        font-size: 1rem; font-weight: 700; color: var(--ink);
        margin-bottom: 4px; letter-spacing: -0.01em;
    }
    .hero-card-body {
        font-size: 0.875rem; color: var(--muted); line-height: 1.5; font-weight: 400;
    }
    .hero-card-badge {
        display: inline-flex; align-items: center; gap: 5px;
        margin-top: 12px; padding: 5px 12px;
        border-radius: 100px; font-size: 0.72rem; font-weight: 700;
        letter-spacing: 0.04em; text-transform: uppercase;
    }
    .badge-resolved { background: rgba(22,163,74,0.1); color: #16a34a; }
    .badge-inprogress { background: rgba(37, 99, 235, 0.1); color: var(--accent); }

    /* ─── EXACT HOW IT WORKS (NEW) ─── */
    #how-it-works {
        padding: 120px 48px;
        background: var(--white);
        border-bottom: 1px solid var(--border);
    }
    .how-inner { max-width: 1200px; margin: 0 auto; }
    .how-header { text-align: center; max-width: 600px; margin: 0 auto 60px; }
    .how-header h2 { color: var(--ink); margin-top: 16px; }
    .steps-grid {
        display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px;
    }
    .step-card {
        background: var(--paper); border: 1px solid var(--border);
        border-radius: 24px; padding: 36px 28px;
        position: relative; transition: transform 0.3s;
    }
    .step-card:hover { transform: translateY(-4px); box-shadow: 0 12px 30px rgba(15, 23, 42, 0.04); }
    .step-number {
        display: inline-flex; align-items: center; justify-content: center;
        background: rgba(37, 99, 235, 0.1); color: var(--accent);
        width: 48px; height: 48px; border-radius: 14px;
        font-size: 1.25rem; font-weight: 800; margin-bottom: 24px;
    }
    .step-title {
        font-size: 1.15rem; font-weight: 700; color: var(--ink);
        margin-bottom: 12px; letter-spacing: -0.01em;
    }
    .step-desc {
        font-size: 0.95rem; color: var(--muted); line-height: 1.6; font-weight: 400;
    }

    /* ─── EXACT WHY ─── */
    #why-this-exists {
        padding: 120px 48px;
        background: var(--ink); color: var(--paper);
        position: relative; overflow: hidden;
    }
    #why-this-exists::before {
        content: 'WHY';
        position: absolute; right: -20px; top: -40px;
        font-weight: 800;
        font-size: 20rem; color: rgba(255,255,255,0.02);
        line-height: 1; pointer-events: none; user-select: none;
    }
    .why-inner { max-width: 1200px; margin: 0 auto; display: grid; grid-template-columns: 1fr 1fr; gap: 80px; align-items: center; }
    .section-eyebrow {
        font-size: 0.72rem; font-weight: 700; letter-spacing: 0.12em;
        text-transform: uppercase; color: var(--accent-light);
        margin-bottom: 20px;
        display: flex; align-items: center; gap: 12px;
    }
    .section-eyebrow::before {
        content: ''; display: block;
        width: 32px; height: 2px; background: var(--accent-light); border-radius: 2px;
    }
    h2 {
        font-size: clamp(2rem, 3.5vw, 3.2rem);
        font-weight: 800; line-height: 1.1;
        letter-spacing: -0.03em;
    }
    .why-right { display: flex; flex-direction: column; gap: 28px; }
    .why-right p {
        font-size: 1.1rem; line-height: 1.8; color: rgba(248, 250, 252, 0.65); font-weight: 300;
    }
    .why-highlight {
        padding: 28px;
        border: 1px solid rgba(248, 250, 252, 0.1);
        border-left: 3px solid var(--accent);
        border-radius: 0 16px 16px 0;
        background: rgba(37, 99, 235, 0.1);
    }
    .why-highlight p {
        font-size: 1.2rem; font-weight: 500;
        color: var(--paper) !important;
        line-height: 1.6;
    }

    /* ─── EXACT STORY ─── */
    #story {
        padding: 120px 48px;
        background: var(--cream);
    }
    .story-inner {
        max-width: 800px; margin: 0 auto; text-align: center;
    }
    .story-inner h2 { margin-bottom: 48px; color: var(--ink); }
    .story-body {
        text-align: left;
        display: flex; flex-direction: column; gap: 20px;
    }
    .story-body p {
        font-size: 1.15rem; line-height: 1.85; color: var(--muted); font-weight: 400;
    }
    .story-body p strong {
        font-weight: 700;
        font-size: 1.5rem; color: var(--ink); display: block;
        margin-top: 12px; letter-spacing: -0.02em;
    }

    /* ─── EXACT OUTCOMES ─── */
    #outcomes {
        padding: 120px 48px;
        background: var(--paper);
    }
    .outcomes-inner { max-width: 1200px; margin: 0 auto; }
    .outcomes-header {
        display: grid; grid-template-columns: 1fr 1fr;
        gap: 60px; align-items: end; margin-bottom: 60px;
    }
    .outcomes-header h2 { color: var(--ink); }
    .outcomes-header p {
        font-size: 1.05rem; line-height: 1.8; color: var(--muted); font-weight: 400;
    }
    .outcomes-grid { 
        display: grid; 
        grid-template-columns: repeat(3, 1fr); 
        gap: 24px; 
        align-items: stretch;
    }
    .outcome-card {
        background: var(--white);
        border: 1px solid var(--border);
        border-radius: 24px;
        padding: 36px;
        display: flex;
        flex-direction: column; 
        position: relative;
        transition: all 0.3s;
    }
    .outcome-card:hover { transform: translateY(-6px); box-shadow: 0 20px 60px rgba(15, 23, 42, 0.08); }
    .outcome-body {
        flex-grow: 1; 
        margin-bottom: 20px;
    }
    .outcome-tag {
        display: inline-block;
        padding: 4px 12px;
        background: var(--cream); border-radius: 100px;
        font-size: 0.7rem; font-weight: 700; letter-spacing: 0.08em;
        text-transform: uppercase; color: var(--muted);
        margin-bottom: 16px;
    }
    .outcome-card h3 {
        font-size: 1.2rem; font-weight: 700; color: var(--ink);
        margin-bottom: 16px; letter-spacing: -0.02em;
    }
    .outcome-card p { font-size: 0.95rem; color: var(--muted); line-height: 1.7; font-weight: 400; }
    .outcome-result {
        padding-top: 20px;
        border-top: 1px solid var(--border);
        font-size: 0.875rem; font-weight: 700; color: var(--ink);
        display: flex; align-items: center; gap: 8px;
        min-height: 3.5rem; 
    }
    .outcome-result svg { color: var(--accent); width: 14px; height: 14px; flex-shrink: 0; }

    /* ─── EXACT SITUATIONS ─── */
    #situations {
        padding: 120px 48px;
        background: var(--ink);
    }
    .situations-inner { max-width: 1200px; margin: 0 auto; }
    .situations-header { margin-bottom: 60px; }
    .situations-header h2 { color: var(--paper); }
    .situations-header p {
        font-size: 1.1rem; color: rgba(248, 250, 252, 0.6);
        line-height: 1.7; font-weight: 300; margin-top: 20px; max-width: 560px;
    }
    .sit-grid {
        display: grid; grid-template-columns: repeat(3, 1fr); gap: 2px;
        border: 2px solid rgba(248, 250, 252, 0.08);
        border-radius: 24px; overflow: hidden;
    }
    .sit-item {
        padding: 28px;
        background: rgba(248, 250, 252, 0.02);
        display: flex; align-items: flex-start; gap: 16px;
        transition: background 0.2s;
        cursor: default;
        border-right: 1px solid rgba(248, 250, 252, 0.05);
        border-bottom: 1px solid rgba(248, 250, 252, 0.05);
    }
    .sit-item:hover { background: rgba(37, 99, 235, 0.1); }
    .sit-icon {
        width: 44px; height: 44px; flex-shrink: 0;
        background: rgba(248, 250, 252, 0.06);
        border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        transition: background 0.2s;
    }
    .sit-item:hover .sit-icon { background: var(--accent); }
    .sit-icon svg { width: 20px; height: 20px; color: rgba(248, 250, 252, 0.5); transition: color 0.2s; }
    .sit-item:hover .sit-icon svg { color: white; }
    .sit-text { font-size: 0.95rem; font-weight: 500; color: rgba(248, 250, 252, 0.7); line-height: 1.4; padding-top: 10px; transition: color 0.2s; }
    .sit-item:hover .sit-text { color: var(--paper); }

    /* ─── EXACT FAQ (NEW) ─── */
    #faq {
        padding: 120px 48px;
        background: var(--cream);
    }
    .faq-inner { max-width: 800px; margin: 0 auto; }
    .faq-header { text-align: center; margin-bottom: 60px; }
    .faq-header h2 { color: var(--ink); margin-top: 16px; }
    details.faq-item {
        border-bottom: 1px solid var(--border);
        padding: 24px 0;
    }
    details.faq-item summary::-webkit-details-marker { display: none; }
    details.faq-item summary {
        list-style: none;
        font-size: 1.15rem; font-weight: 700; color: var(--ink);
        cursor: pointer; display: flex; justify-content: space-between; align-items: center;
        letter-spacing: -0.01em;
    }
    details.faq-item summary svg {
        flex-shrink: 0; color: var(--muted); transition: transform 0.3s ease;
    }
    details.faq-item[open] summary svg {
        transform: rotate(180deg); color: var(--accent);
    }
    .faq-answer {
        padding-top: 16px; padding-right: 40px;
        font-size: 1.05rem; color: var(--muted); line-height: 1.7;
    }

    /* ─── EXACT CTA ─── */
    #cta {
        padding: 80px 48px 120px;
        background: var(--ink);
    }
    .cta-inner {
        max-width: 1200px; margin: 0 auto;
    }
    .cta-box {
        background: var(--accent);
        border-radius: 32px;
        padding: 80px;
        display: grid; grid-template-columns: 1fr auto;
        gap: 60px; align-items: center;
        position: relative; overflow: hidden;
    }
    .cta-box::after {
        content: '';
        position: absolute; right: -80px; top: -80px;
        width: 400px; height: 400px;
        background: rgba(255,255,255,0.08);
        border-radius: 50%;
    }
    .cta-box::before {
        content: '';
        position: absolute; right: 120px; bottom: -100px;
        width: 250px; height: 250px;
        background: rgba(0,0,0,0.08);
        border-radius: 50%;
    }
    .cta-left { position: relative; z-index: 1; }
    .cta-left h2 {
        color: white; margin-bottom: 16px;
        font-size: clamp(2rem, 3vw, 3rem);
    }
    .cta-left p { color: rgba(255,255,255,0.8); font-size: 1.1rem; line-height: 1.7; font-weight: 400; }
    .cta-right { position: relative; z-index: 1; flex-shrink: 0; }

    /* ─── EXACT ANIMATIONS ─── */
    @keyframes fadeUp {
        from { opacity: 0; transform: translateY(24px); }
        to { opacity: 1; transform: translateY(0); }
    }
    @keyframes slideIn {
        from { opacity: 0; transform: translateX(20px); }
        to { opacity: 1; transform: translateX(0); }
    }
    .hero-left > * {
        opacity: 0;
        animation: fadeUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }
    .hero-left > *:nth-child(1) { animation-delay: 0.1s; }
    .hero-left > *:nth-child(2) { animation-delay: 0.2s; }
    .hero-left > *:nth-child(3) { animation-delay: 0.35s; }
    .hero-left > *:nth-child(4) { animation-delay: 0.5s; }
    .hero-visual .hero-card {
        opacity: 0;
        animation: slideIn 0.7s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }
    .hero-visual .hero-card:nth-child(1) { animation-delay: 0.5s; }
    .hero-visual .hero-card:nth-child(2) { animation-delay: 0.65s; }
    .hero-visual .hero-card:nth-child(3) { animation-delay: 0.8s; }

    /* ─── EXACT RESPONSIVE ─── */
    @media (max-width: 1024px) {
        .steps-grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 900px) {
        header { grid-template-columns: 1fr; padding: 110px 24px 60px; }
        .hero-visual { display: none; }
        #how-it-works { padding: 80px 24px; }
        #why-this-exists { padding: 80px 24px; }
        .why-inner { grid-template-columns: 1fr; gap: 40px; }
        #story { padding: 80px 24px; }
        #outcomes { padding: 80px 24px; }
        .outcomes-header { grid-template-columns: 1fr; }
        .outcomes-grid { grid-template-columns: 1fr; }
        #situations { padding: 80px 24px; }
        .sit-grid { grid-template-columns: 1fr 1fr; }
        #faq { padding: 80px 24px; }
        #cta { padding: 40px 24px 80px; }
        .cta-box { grid-template-columns: 1fr; padding: 48px; gap: 32px; }
    }
    @media (max-width: 560px) {
        .steps-grid { grid-template-columns: 1fr; }
        .sit-grid { grid-template-columns: 1fr; }
        .hero-actions { flex-direction: column; align-items: stretch; }
        .hero-actions .btn-primary, .hero-actions .btn-secondary { width: 100%; justify-content: center; }
    }
</style>
@endpush

@section('content')
    <header>
        <div class="hero-bg-shape"></div>
        <div class="hero-left">
            <div class="hero-tag"><span></span> Get Unstuck Today</div>
            <h1>Resolve complaints<br>with companies <em>faster.</em></h1>
            <p class="hero-sub">Unjamm helps you escalate disputes with banks, airlines, telecom companies, insurance companies and other institutions using structured escalation workflows and AI-generated complaint letters.</p>
            <div class="hero-actions">
                @auth
                    @if(auth()->user()->role === 'admin')
                        <a href="{{ route('admin.dashboard') }}" class="btn-primary">
                            Go to Admin Panel
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                        </a>
                    @else
                        <a href="{{ route('user.dashboard') }}" class="btn-primary">
                            Go to Dashboard
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                        </a>
                    @endif
                @else
                    <a href="{{ route('register') }}" class="btn-primary">
                        Start a Dispute
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    </a>
                @endauth
                <a href="#how-it-works" class="btn-secondary">
                    How It Works
                </a>
            </div>
        </div>

        <div class="hero-visual">
            <div class="hero-card">
                <div class="hero-card-label">Case #0034 · Municipal Office</div>
                <div class="hero-card-title">Pending Information Request</div>
                <div class="hero-card-body">
                    Structured AI-drafted follow-up sent in Portuguese with formal escalation path.
                </div>
                <span class="hero-card-badge badge-resolved">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                    Resolved 8w
                </span>
            </div>

            <div class="hero-card">
                <div class="hero-card-label">Case #0041 · E-Commerce</div>
                <div class="hero-card-title">Amazon Refund Escalation</div>
                <div class="hero-card-body">
                    Three-stage escalation culminating in Better Business Bureau complaint.
                </div>
                <span class="hero-card-badge badge-resolved">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                    Refund Obtained
                </span>
            </div>

            <div class="hero-card">
                <div class="hero-card-label">Case #0055 · Healthcare</div>
                <div class="hero-card-title">Public Health Waitlist</div>
                <div class="hero-card-body">
                    Written request drafted to place parents on the correct waiting list.
                </div>
                <span class="hero-card-badge badge-inprogress">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3" fill="currentColor"/></svg>
                    Access secured
                </span>
            </div>
        </div>
    </header>

    <section id="how-it-works">
        <div class="how-inner">
            <div class="how-header">
                <div class="section-eyebrow" style="color: var(--accent); justify-content: center;">Simple Process</div>
                <h2>How It Works</h2>
            </div>
            
            <div class="steps-grid">
                <div class="step-card">
                    <div class="step-number">1</div>
                    <div class="step-title">Create a Dispute</div>
                    <div class="step-desc">Select the company and describe your issue.</div>
                </div>
                
                <div class="step-card">
                    <div class="step-number">2</div>
                    <div class="step-title">Generate the Email</div>
                    <div class="step-desc">Unjamm generates a structured complaint email.</div>
                </div>
                
                <div class="step-card">
                    <div class="step-number">3</div>
                    <div class="step-title">Escalate the Issue</div>
                    <div class="step-desc">Move through customer service, escalation teams and executive offices.</div>
                </div>
                
                <div class="step-card">
                    <div class="step-number">4</div>
                    <div class="step-title">Track Everything</div>
                    <div class="step-desc">All communications stay organized in your dispute timeline.</div>
                </div>
            </div>
        </div>
    </section>

    <section id="why-this-exists">
        <div class="why-inner">
            <div class="why-left">
                <div class="section-eyebrow">Why This Exists</div>
                <h2 style="color:var(--paper)">Most people don't ignore important issues because they don't care.</h2>
            </div>
            <div class="why-right">
                <p>They ignore them because they don't know what to say, who to contact, or how to escalate properly.</p>
                <div class="why-highlight">
                    <p>Unjamm was built for that moment. Not just to dispute charges — but to help you get unstuck. Through structured communication, guided escalation, and AI-assisted drafting.</p>
                </div>
                <p>We act as your human copilot when dealing with institutions — bringing clarity where there was only confusion, and momentum where there was only stall.</p>
            </div>
        </div>
    </section>

    <section id="story">
        <div class="story-inner">
            <div class="section-eyebrow" style="justify-content: center; color: var(--accent);">Founder Story</div>
            <h2 style="margin-bottom: 16px; color: var(--ink);">Built from personal experience.</h2>
            <div class="story-body">
                <p>While developing the platform, the founder used AI-drafted Portuguese emails to obtain long-pending municipal information that had been stalled for years.</p>
                <p>Using structured follow-up and escalation, the issue was resolved in two months — an outcome that had seemed impossible without knowing the right language, tone, or process.</p>
                <p>That experience revealed a common problem: people don't act because they lack clarity, structure, and confidence in how to engage institutions.</p>
                <p><strong>Unjamm exists to help people get unstuck.</strong></p>
            </div>
        </div>
    </section>

    <section id="outcomes">
        <div class="outcomes-inner">
            <div class="outcomes-header">
                <div>
                    <div class="section-eyebrow" style="color: var(--accent-light)">Real Situations</div>
                    <h2 style="color: var(--ink);">Real Progress, <br>Real Results</h2>
                </div>
                <div>
                    <p>Many important issues stay unresolved for months or years simply because people don't know how to start or escalate properly. These real situations helped inspire the platform.</p>
                </div>
            </div>

            <div class="outcomes-grid">
                <div class="outcome-card">
                    <div class="outcome-tag">Portugal · Government</div>
                    <div class="outcome-body">
                        <h3>Municipal Office Information Request</h3>
                        <p>For years, a pending municipal information request went unresolved. Language was a barrier and formal communication was unclear. AI-drafted Portuguese emails and structured follow-up changed everything.</p>
                    </div>
                    <div class="outcome-result">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="width:16px; color:var(--accent);"><path d="M20 6L9 17l-5-5"/></svg>
                        <span>Information received within 2 months</span>
                    </div>
                </div>

                <div class="outcome-card">
                    <div class="outcome-tag">E-Commerce · Refund</div>
                    <div class="outcome-body">
                        <h3>Amazon Refund Escalation</h3>
                        <p>A refund request approved and then rejected multiple times. A structured three-stage escalation process — ultimately reaching the Better Business Bureau — broke the stalemate.</p>
                    </div>
                    <div class="outcome-result">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="width:16px; color:var(--accent);"><path d="M20 6L9 17l-5-5"/></svg>
                        <span>Full refund obtained</span>
                    </div>
                </div>

                <div class="outcome-card">
                    <div class="outcome-tag">Portugal · Healthcare</div>
                    <div class="outcome-body">
                        <h3>Healthcare Waiting List Access</h3>
                        <p>Securing access to public healthcare through the correct channels is notoriously complicated. Structured written requests navigated the system effectively.</p>
                    </div>
                    <div class="outcome-result">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="width:16px; color:var(--accent);"><path d="M20 6L9 17l-5-5"/></svg>
                        <span>Placed on correct waiting list</span>
                    </div>
                </div>
            </div>
            
            <div style="margin-top: 48px; padding-top: 24px; border-top: 1px solid var(--border);">
                <p style="color: var(--muted); font-size: 0.95rem;">
                    Have a success story? <button @click.prevent="showSuccessModal = true" style="color: var(--accent); font-weight: 600; text-decoration: underline; text-underline-offset: 4px; background: none; border: none; cursor: pointer; padding: 0; font-family: inherit; font-size: inherit;">
                        Share how you got unstuck.
                    </button>
                </p>
            </div>
        </div>
    </section>

    <section id="situations">
        <div class="situations-inner">
            <div class="situations-header">
                <div class="section-eyebrow" style="color: var(--accent-light);">Common Use Cases</div>
                <h2 style="color: var(--paper);">Things People<br>Get Stuck On</h2>
                <p>Many everyday situations stall simply because communication with institutions is unclear or complicated. Unjamm helps structure that communication.</p>
            </div>
            <div class="sit-grid">
                <div class="sit-item">
                    <div class="sit-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 22l7-3 7 3V2l-7 3-7-3v20zM10 5v16"/></svg></div>
                    <span class="sit-text">Government requests and permits</span>
                </div>
                <div class="sit-item">
                    <div class="sit-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg></div>
                    <span class="sit-text">Healthcare access or waiting lists</span>
                </div>
                <div class="sit-item">
                    <div class="sit-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M9 15l2 2 4-4"/></svg></div>
                    <span class="sit-text">Refunds or billing disputes</span>
                </div>
                <div class="sit-item">
                    <div class="sit-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div>
                    <span class="sit-text">Insurance claims</span>
                </div>
                <div class="sit-item">
                    <div class="sit-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/></svg></div>
                    <span class="sit-text">Property or municipal issues</span>
                </div>
                <div class="sit-item">
                    <div class="sit-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.36 11.5a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L7.91 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg></div>
                    <span class="sit-text">Airline or travel compensation</span>
                </div>
                <div class="sit-item">
                    <div class="sit-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9h18v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9zM3 9V6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v3M12 12v6"/></svg></div>
                    <span class="sit-text">Banking or financial institution issues</span>
                </div>
                <div class="sit-item">
                    <div class="sit-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg></div>
                    <span class="sit-text">Cross-border communication challenges</span>
                </div>
                <div class="sit-item">
                    <div class="sit-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></div>
                    <span class="sit-text">Condo or property management issues</span>
                </div>
            </div>
        </div>
    </section>

    <section id="faq">
        <div class="faq-inner">
            <div class="faq-header">
                <div class="section-eyebrow" style="color: var(--accent); justify-content: center;">Questions & Answers</div>
                <h2>Frequently Asked Questions</h2>
            </div>
            
            <div class="faq-list">
                <details class="faq-item" open>
                    <summary>What is Unjamm? <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg></summary>
                    <div class="faq-answer">Unjamm is a platform that helps people resolve complaints with companies by generating structured dispute emails and guiding users through escalation steps.</div>
                </details>

                <details class="faq-item">
                    <summary>Is Unjamm a law firm? <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg></summary>
                    <div class="faq-answer">No. Unjamm is a technology platform that helps organize and generate dispute communications. It does not provide legal advice.</div>
                </details>

                <details class="faq-item">
                    <summary>What types of disputes can I use Unjamm for? <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg></summary>
                    <div class="faq-answer">Common disputes include airline refunds, bank fee disputes, telecom billing issues, unauthorized transactions, insurance claims, and other consumer complaints.</div>
                </details>

                <details class="faq-item">
                    <summary>Do companies actually respond to these emails? <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg></summary>
                    <div class="faq-answer">While responses cannot be guaranteed, structured escalation significantly improves the chances of receiving a response.</div>
                </details>

                <details class="faq-item">
                    <summary>Can I use Unjamm for any company? <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg></summary>
                    <div class="faq-answer">Yes. If a company is not already listed in the system, users can add it manually when creating a dispute.</div>
                </details>

                <details class="faq-item">
                    <summary>Does Unjamm send the emails automatically? <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg></summary>
                    <div class="faq-answer">Unjamm generates the dispute email for you, but users review the message before it is sent.</div>
                </details>

                <details class="faq-item">
                    <summary>What happens if the company does not respond? <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg></summary>
                    <div class="faq-answer">Users can escalate the dispute through additional stages such as executive escalation or regulatory complaints depending on the dispute type.</div>
                </details>

                <details class="faq-item">
                    <summary>Is my information private? <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg></summary>
                    <div class="faq-answer">Yes. Dispute information is only used to generate emails and manage your case timeline. Payment processing is handled securely through Stripe.</div>
                </details>

                <details class="faq-item">
                    <summary>Can I cancel my subscription? <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg></summary>
                    <div class="faq-answer">Yes. You can cancel your subscription at any time through your account settings.</div>
                </details>

                <details class="faq-item">
                    <summary>Can I use Unjamm for business-related disputes, or is it only for personal issues? <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg></summary>
                    <div class="faq-answer">Unjamm can be used for both personal and business-related disputes. Many professionals and small business owners use the platform to resolve issues with banks, service providers, vendors, insurance companies, and other institutions.</div>
                </details>

                <details class="faq-item">
                    <summary>My insurance claim was denied or the settlement offer was lower than expected. Can Unjamm help? <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg></summary>
                    <div class="faq-answer">Yes. Unjamm can help organize and escalate insurance disputes when a claim is denied, delayed, or when the settlement offer does not reflect the expected value of the loss. The platform helps users generate structured appeal or negotiation emails, present supporting documentation or comparable estimates, and escalate the issue through additional stages such as claims management, executive escalation, or regulatory channels when appropriate.</div>
                </details>
            </div>
        </div>
    </section>

    <section id="cta">
        <div class="cta-inner">
            <div class="cta-box">
                <div class="cta-left">
                    <h2 style="color: var(--white);">When something important stalls, Unjamm helps you take action.</h2>
                    <p style="color: rgba(255,255,255,0.75);">Don't let bureaucracy, language barriers, or unclear processes hold you back any longer. Start your first case today — it's free to begin.</p>
                </div>
                <div class="cta-right">
                    @auth
                        @if(auth()->user()->role === 'admin')
                            <a href="{{ route('admin.dashboard') }}" class="btn-primary" style="background: white; color: var(--accent) !important;">
                                Go to Admin Panel
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                            </a>
                        @else
                            <a href="{{ route('user.dashboard') }}" class="btn-primary" style="background: white; color: var(--accent) !important;">
                                Go to Dashboard
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                            </a>
                        @endif
                    @else
                        <a href="{{ route('register') }}" class="btn-primary" style="background: white; color: var(--accent) !important;">
                            Get Started Today
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                        </a>
                    @endauth
                </div>
            </div>
        </div>
    </section>
@endsection

@push('modals')
    @livewire('landing-page.success-story-form')
@endpush