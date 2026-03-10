<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unjamm - Get Unstuck</title>
    <meta name="description" content="We help you take action when life gets stuck. Navigate institutions with clarity, structure, and confidence.">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    
    <script>
        tailwind.config = {
            corePlugins: {
                preflight: false, // <--- THIS PREVENTS TAILWIND FROM BREAKING YOUR PAGE
            },
            theme: {
                extend: {
                    colors: {
                        ink: 'var(--ink)',
                        paper: 'var(--paper)',
                        cream: 'var(--cream)',
                        accent: 'var(--accent)',
                        muted: 'var(--muted)',
                        border: 'var(--border)',
                    }
                }
            }
        }
    </script>
   {{-- <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script> --}}
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --ink: #0f172a;         /* slate-900 */
            --paper: #f8fafc;       /* slate-50 */
            --cream: #f1f5f9;       /* slate-100 */
            --accent: #2563eb;      /* blue-600 */
            --accent-light: #818cf8;/* indigo-400 */
            --muted: #64748b;       /* slate-500 */
            --border: rgba(15, 23, 42, 0.1);
            --white: #ffffff;
        }

        html { font-size: 16px; }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--paper);
            color: var(--ink);
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
        }

        /* ─── NAV ─── */
        nav {
            position: fixed; top: 0; left: 0; right: 0; z-index: 100;
            height: 72px;
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 48px;
            background: rgba(248, 250, 252, 0.9);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--border);
        }
        .nav-logo {
            display: flex; align-items: center; gap: 12px;
            text-decoration: none;
        }
        .nav-logo-text {
            display: flex; flex-direction: column; justify-content: center;
        }
        .nav-logo-title {
            font-weight: 800; font-size: 1.4rem;
            color: var(--ink); 
            letter-spacing: -0.02em;
            line-height: 1;
        }
        .nav-logo-subtitle {
            font-size: 0.65rem; 
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.15em;
            margin-top: 4px;
            line-height: 1;
        }
        .logo-mark {
            width: 38px; height: 38px;
            background: linear-gradient(135deg, var(--accent), #4f46e5);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
        }
        .logo-mark img { width: 18px; height: 18px; filter: brightness(0) invert(1); }
        
        .nav-links {
            display: flex; align-items: center; gap: 36px;
        }
        .nav-links a {
            font-size: 0.875rem; font-weight: 500;
            color: var(--muted); text-decoration: none;
            letter-spacing: 0.01em;
            transition: color 0.2s;
        }
        .nav-links a:not(.btn-nav):hover { color: var(--accent); }

        /* ALWAYS HOVER STATE FOR NAV BUTTON */
        .btn-nav {
            padding: 10px 24px;
            background: var(--accent); 
            color: var(--white) !important;
            border-radius: 100px; font-size: 0.875rem; font-weight: 600;
            text-decoration: none;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
            transition: background 0.2s, transform 0.15s;
        }

        /* ─── HERO ─── */
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
            font-size: clamp(3rem, 5vw, 5rem);
            font-weight: 800;
            line-height: 1.02;
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
            font-size: 1.2rem; color: var(--muted);
            line-height: 1.7; font-weight: 300;
            max-width: 420px;
            margin-bottom: 40px;
        }
        .hero-actions { display: flex; gap: 16px; align-items: center; flex-wrap: wrap; }

        /* ALWAYS HOVER STATE FOR HERO BUTTON */
        .btn-primary {
            display: inline-flex; align-items: center; gap: 10px;
            padding: 16px 32px;
            background: var(--accent); 
            color: var(--white) !important;
            border-radius: 14px; font-weight: 600; font-size: 1rem;
            text-decoration: none;
            transform: translateY(-2px);
            box-shadow: 0 12px 32px rgba(37, 99, 235, 0.25);
            letter-spacing: -0.01em;
            transition: all 0.2s;
        }
        .btn-primary svg { width: 18px; height: 18px; }
        
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
            width: 100%; /* Ensures they are all the same width */
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

        /* ─── WHY ─── */
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

        /* ─── STORY ─── */
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

        /* ─── OUTCOMES (SYMMETRY FIX) ─── */
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
            align-items: stretch; /* Cards in row same height */
        }
        .outcome-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 36px;
            display: flex;
            flex-direction: column; /* Stack vertically */
            position: relative;
            transition: all 0.3s;
        }
        .outcome-card:hover { transform: translateY(-6px); box-shadow: 0 20px 60px rgba(15, 23, 42, 0.08); }
        .outcome-body {
            flex-grow: 1; /* Pushes result section to bottom */
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
            min-height: 3.5rem; /* Symmetry fix for text wrapping */
        }
        .outcome-result svg { color: var(--accent); width: 14px; height: 14px; flex-shrink: 0; }

        /* ─── SITUATIONS ─── */
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

        /* ─── CTA ─── */
        #cta {
            padding: 80px 48px 120px;
            background: var(--ink);
        }
        .cta-inner {
            max-width: 1200px; margin: 0 auto;
        }

        /* ALWAYS HOVER STATE FOR CTA BUTTON */
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
        
        .btn-cta {
            display: inline-flex; align-items: center; gap: 12px;
            padding: 20px 36px;
            background: var(--paper); 
            color: var(--ink) !important;
            border-radius: 16px; 
            font-weight: 700; font-size: 1.05rem;
            text-decoration: none; white-space: nowrap;
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 16px 48px rgba(15, 23, 42, 0.3);
            transition: all 0.25s;
        }

        /* ─── FOOTER ─── */
        footer {
            padding: 40px 48px;
            background: var(--ink);
            border-top: 1px solid rgba(248, 250, 252, 0.08);
        }
        .footer-inner {
            max-width: 1200px; margin: 0 auto;
            display: flex; align-items: center; justify-content: space-between;
        }
        .footer-logo {
            display: flex; align-items: center; gap: 10px;
            text-decoration: none; 
        }
        .footer-logo-mark {
            width: 32px; height: 32px;
            background: var(--accent);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
        }
        .footer-logo-mark img { width: 14px; height: 14px; filter: brightness(0) invert(1); }
        footer p { font-size: 0.8rem; color: rgba(248, 250, 252, 0.4); }
        .footer-links { display: flex; gap: 24px; }
        .footer-links a {
            font-size: 0.8rem; color: rgba(248, 250, 252, 0.5);
            text-decoration: none; transition: color 0.2s;
        }
        .footer-links a:hover { color: var(--paper); }

        /* ─── ANIMATIONS ─── */
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

        /* ─── RESPONSIVE ─── */
        @media (max-width: 900px) {
            nav { padding: 0 24px; }
            header { grid-template-columns: 1fr; padding: 110px 24px 60px; }
            .hero-visual { display: none; }
            #why-this-exists { padding: 80px 24px; }
            .why-inner { grid-template-columns: 1fr; gap: 40px; }
            #story { padding: 80px 24px; }
            #outcomes { padding: 80px 24px; }
            .outcomes-header { grid-template-columns: 1fr; }
            .outcomes-grid { grid-template-columns: 1fr; }
            #situations { padding: 80px 24px; }
            .sit-grid { grid-template-columns: 1fr 1fr; }
            #cta { padding: 40px 24px 80px; }
            .cta-box { grid-template-columns: 1fr; padding: 48px; gap: 32px; }
            footer { padding: 32px 24px; }
            .footer-inner { flex-direction: column; gap: 20px; text-align: center; }
        }
        @media (max-width: 560px) {
            .sit-grid { grid-template-columns: 1fr; }
            .nav-links a:not(.btn-nav) { display: none; }
        }
    </style>
</head>
<body x-data="{ showSuccessModal: false }">

    <nav>
        <a class="nav-logo" href="#">
            <div class="logo-mark">
                <img src="/icon.svg" alt="Icon" />
            </div>
            <div class="nav-logo-text">
                <span class="nav-logo-title">Unjamm</span>
                <span class="nav-logo-subtitle">Get Unstuck.</span>
            </div>
        </a>
        <div class="nav-links">
            <a href="#why-this-exists">Why Unjamm?</a>
            <a href="#outcomes">Real Outcomes</a>
            <a href="#situations">Situations</a>
            
            @auth
                @if(auth()->user()->role === 'admin')
                    <a href="{{ route('admin.dashboard') }}" class="btn-nav">Admin Panel</a>
                @else
                    <a href="{{ route('user.dashboard') }}" class="btn-nav">Dashboard</a>
                @endif
            @else
                <a href="{{ route('login') }}" class="btn-nav">Get Started</a>
            @endauth
        </div>
    </nav>

    <header>
        <div class="hero-bg-shape"></div>
        <div class="hero-left">
            <div class="hero-tag"><span></span> Get Unstuck Today</div>
            <h1>We help you<br>take action <em>when<br>life gets stuck.</em></h1>
            <p class="hero-sub">Navigate institutions with clarity, structure, and confidence — from billing disputes to government bureaucracy.</p>
            <div class="hero-actions">
                @auth
                    @if(auth()->user()->role === 'admin')
                        <a href="{{ route('admin.dashboard') }}" class="btn-primary">
                            Go to Admin Panel
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                        </a>
                    @else
                        <a href="{{ route('user.dashboard') }}" class="btn-primary">
                            Go to Dashboard
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                        </a>
                    @endif
                @else
                    <a href="{{ route('register') }}" class="btn-primary">
                        Start Your First Case
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    </a>
                @endauth
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
                            <a href="{{ route('admin.dashboard') }}" class="btn-cta">
                                Go to Admin Panel
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                            </a>
                        @else
                            <a href="{{ route('user.dashboard') }}" class="btn-cta">
                                Go to Dashboard
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                            </a>
                        @endif
                    @else
                        <a href="{{ route('register') }}" class="btn-cta">
                            Get Started Today
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                        </a>
                    @endauth
                </div>
            </div>
        </div>
    </section>

    <footer>
        <div class="footer-inner">
            <a class="footer-logo" href="#">
                <div class="footer-logo-mark">
                    <img src="/icon.svg" alt="Icon" />
                </div>
                <div class="nav-logo-text">
                    <span class="nav-logo-title" style="font-size: 1.1rem; color: var(--paper);">Unjamm</span>
                    <span class="nav-logo-subtitle" style="font-size: 0.55rem; color: rgba(248, 250, 252, 0.6);">Get Unstuck.</span>
                </div>
            </a>
            <p>&copy; {{ date('Y') }} Unjamm. All rights reserved.</p>
            <div class="footer-links">
                <a href="{{ route('privacy') }}">Privacy</a>
                <a href="{{ route('terms') }}">Terms</a>
                <a href="#">Support</a>
            </div>
        </div>
    </footer>
    @livewire('landing-page.success-story-form')
    <script>
        lucide.createIcons();
    </script>
</body>
</html>