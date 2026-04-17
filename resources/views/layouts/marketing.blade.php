<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    {{-- SEO Optimization --}}
    <title>@yield('title', 'Unjamm - Get Unstuck')</title>
    <meta name="description" content="@yield('meta_description', 'We help you take action when life gets stuck. Navigate institutions with clarity, structure, and confidence.')">
    <link rel="canonical" href="{{ request()->url() }}">
    
    {{-- Open Graph / Facebook --}}
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ request()->url() }}">
    <meta property="og:title" content="@yield('title', 'Unjamm - Get Unstuck')">
    <meta property="og:description" content="@yield('meta_description', 'We help you take action when life gets stuck. Navigate institutions with clarity, structure, and confidence.')">
    
    {{-- Twitter --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('title', 'Unjamm - Get Unstuck')">
    <meta name="twitter:description" content="@yield('meta_description', 'We help you take action when life gets stuck. Navigate institutions with clarity, structure, and confidence.')">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    
    <script>
        tailwind.config = {
            corePlugins: {
                preflight: false,
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
    
    <style>
        /* ─── EXACT CORE STYLES ─── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --ink: #0f172a;         
            --paper: #f8fafc;       
            --cream: #f1f5f9;       
            --accent: #2563eb;      
            --accent-light: #818cf8;
            --muted: #64748b;       
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

        /* ─── EXACT NAV STYLES ─── */
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

        /* ─── GLOBAL BUTTON STYLES ─── */
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

        /* ─── EXACT FOOTER STYLES ─── */
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
        footer p { font-size: 0.8rem; color: rgba(248, 250, 252, 0.75); }
        .footer-links { display: flex; gap: 24px; }
        .footer-links a {
            font-size: 0.8rem; color: rgba(248, 250, 252, 0.75);
            text-decoration: none; transition: color 0.2s;
        }
        .footer-links a:hover { color: var(--paper); }

        /* ─── GLOBAL RESPONSIVE ─── */
        @media (max-width: 900px) {
            nav { padding: 0 24px; }
            footer { padding: 32px 24px; }
            .footer-inner { flex-direction: column; gap: 20px; text-align: center; }
        }
        @media (max-width: 560px) {
            .nav-links a:not(.btn-nav) { display: none; }
        }
    </style>

    {{-- Yield Page-Specific CSS --}}
    @stack('styles')

</head>
<body x-data="{ showSuccessModal: false }">

    <nav>
        <a class="nav-logo" href="{{ route('home') }}">
            <div class="logo-mark">
                <img src="/icon.svg" alt="Icon" />
            </div>
            <div class="nav-logo-text">
                <span class="nav-logo-title">Unjamm</span>
                <span class="nav-logo-subtitle">Get Unstuck.</span>
            </div>
        </a>
        <div class="nav-links">
            <a href="{{ route('home') }}#why-this-exists">Why Unjamm?</a>
            <a href="{{ route('home') }}#outcomes">Real Outcomes</a>
            <a href="{{ route('home') }}#situations">Situations</a>
            
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

    {{-- Page Content Injected Here --}}
    @yield('content')

    @include('layouts.partials.global_footer')

    {{-- Yield page-specific modals (like the success story form) --}}
    @stack('modals')

    {{-- Yield page-specific scripts --}}
    @stack('scripts')

    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
        lucide.createIcons();
    </script>
</body>
</html>