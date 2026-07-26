<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    {{-- SEO --}}
    <title>@yield('title', 'Unjamm — Get the money airlines owe you')</title>
    <meta name="description" content="@yield('meta_description', 'Forward your flight confirmation to Unjamm. We monitor every flight in real time and file compensation claims automatically under EU 261, UK 261, Canada APPR, US DOT and the Montreal Convention. No win, no fee.')">
    <link rel="canonical" href="{{ request()->url() }}">

    {{-- Open Graph --}}
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ request()->url() }}">
    <meta property="og:title" content="@yield('title', 'Unjamm — Get the money airlines owe you')">
    <meta property="og:description" content="@yield('meta_description', 'We monitor every flight and file air-passenger compensation claims automatically. No win, no fee.')">

    {{-- Twitter --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('title', 'Unjamm — Get the money airlines owe you')">
    <meta name="twitter:description" content="@yield('meta_description', 'We monitor every flight and file air-passenger compensation claims automatically. No win, no fee.')">

    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='7' fill='%230B0E13'/%3E%3Cpath d='M6 23 L16 7 L26 23' stroke='%233FCB94' stroke-width='2.6' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3Ccircle cx='16' cy='25' r='2' fill='%233FCB94'/%3E%3C/svg%3E">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400;12..96,600;12..96,700;12..96,800&family=Hanken+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg:#0B0E13; --bg2:#0E1219; --card:#12171F; --border:rgba(255,255,255,.08); --border2:rgba(255,255,255,.14);
            --text:#EAEEF3; --muted:#9BA4B0; --faint:#6B7480;
            --accent:#3FCB94; --accent2:#2FB98A; --on-accent:#06231A; --chip:rgba(63,203,148,.12);
            --nav:rgba(11,14,19,.80); --field:rgba(0,0,0,.28);
            --danger:#F87171;
        }
        [data-theme="light"] {
            --bg:#F4F1EA; --bg2:#EDE8DE; --card:#FFFFFF; --border:#E7E1D6; --border2:#D6CFC1;
            --text:#15171B; --muted:#5A5F67; --faint:#8A8F97;
            --accent:#0B6B4C; --accent2:#0A5C41; --on-accent:#FFFFFF; --chip:#E6EFE9;
            --nav:rgba(244,241,234,.82); --field:#F4F1EA;
            --danger:#C0392B;
        }
        *, *::before, *::after { box-sizing: border-box; }
        /* Anchors: CSS smoothing is the fallback when Lenis is not running
           (touch, reduced motion). Lenis switches it off for itself, since
           two animators on one scroll position fight each other. */
        html { scroll-behavior: smooth; }
        html.lenis, html.lenis body { height: auto; }
        .lenis.lenis-smooth { scroll-behavior: auto !important; }
        .lenis.lenis-smooth [data-lenis-prevent] { overscroll-behavior: contain; }
        .lenis.lenis-stopped { overflow: hidden; }
        /* Slim, themed scrollbar for panels that scroll inside the page. */
        .uj-scroll { scrollbar-width: thin; scrollbar-color: rgba(63,203,148,.45) transparent; }
        .uj-scroll::-webkit-scrollbar { width: 8px; height: 8px; }
        .uj-scroll::-webkit-scrollbar-track { background: transparent; border-radius: 8px; }
        .uj-scroll::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,.16);
            border-radius: 8px;
            border: 2px solid transparent;
            background-clip: content-box;
        }
        .uj-scroll::-webkit-scrollbar-thumb:hover { background: rgba(63,203,148,.55); background-clip: content-box; }
        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            font-family: 'Hanken Grotesk', system-ui, sans-serif;
            -webkit-font-smoothing: antialiased;
            text-rendering: optimizeLegibility;
            overflow-x: hidden;
            transition: background .4s ease, color .4s ease;
        }
        ::selection { background: var(--accent); color: var(--on-accent); }
        h1, h2, h3, h4 { font-family: 'Bricolage Grotesque', sans-serif; }

        @keyframes ujPulse { 0% { box-shadow: 0 0 0 0 rgba(63,203,148,.5); } 70% { box-shadow: 0 0 0 9px rgba(63,203,148,0); } 100% { box-shadow: 0 0 0 0 rgba(63,203,148,0); } }
        @keyframes ujFloat { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-7px); } }
        @keyframes ujZoom { 0% { transform: scale(1.02); } 100% { transform: scale(1.12); } }
        @keyframes ujMarquee { 0% { transform: translateX(0); } 100% { transform: translateX(-50%); } }
        @keyframes ujAurora { 0%,100% { transform: translate(-10%, -6%) scale(1); } 50% { transform: translate(8%, 6%) scale(1.15); } }
        @keyframes ujBlink { 0%,100% { opacity: 1; } 50% { opacity: .25; } }
        @keyframes ujSpin { to { transform: rotate(360deg); } }

        /* Shared button */
        .uj-btn-primary {
            display:inline-flex; align-items:center; gap:9px;
            background:var(--accent); color:var(--on-accent);
            padding:14px 24px; border-radius:999px; text-decoration:none;
            font-family:'Hanken Grotesk',sans-serif; font-weight:700; font-size:15px;
            border:none; cursor:pointer;
            transition:transform .2s ease, background .2s ease;
        }
        .uj-btn-primary:hover { transform:translateY(-1px); background:var(--accent2); }

        @media (max-width: 760px) {
            #ujNavLinks { display:none !important; }
        }
    </style>

    @vite('resources/js/marketing.js')

    @stack('styles')
</head>
<body>

    {{-- Scroll progress bar --}}
    <div id="ujProgress" style="position:fixed;top:0;left:0;height:3px;width:0;background:linear-gradient(90deg,var(--accent),var(--accent2));z-index:101;pointer-events:none;box-shadow:0 0 12px rgba(63,203,148,.6);"></div>

    {{-- NAV --}}
    <nav id="ujNav" style="position:fixed;top:0;left:0;right:0;z-index:100;transition:background .35s ease, box-shadow .35s ease, border-color .35s ease;border-bottom:1px solid transparent;">
        <div style="max-width:1220px;margin:0 auto;padding:18px 32px;display:flex;align-items:center;justify-content:space-between;gap:24px;">
            <a href="{{ route('home') }}#top" style="display:flex;align-items:center;gap:11px;text-decoration:none;color:var(--text);">
                <span style="display:flex;color:var(--accent);">
                    <svg viewBox="0 0 32 32" width="27" height="27" fill="none" aria-hidden="true"><path d="M5 23.5 L16 6 L27 23.5" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"></path><path d="M11 23.5 L16 15.5 L21 23.5" stroke="currentColor" stroke-opacity="0.4" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"></path><circle cx="16" cy="26" r="2.1" fill="currentColor"></circle></svg>
                </span>
                <span style="font-family:'Bricolage Grotesque',sans-serif;font-weight:700;font-size:21px;letter-spacing:-.02em;">Unjamm</span>
            </a>
            <div id="ujNavLinks" style="display:flex;align-items:center;gap:34px;">
                <a href="{{ route('home') }}#how" data-hover="color:var(--accent)" style="text-decoration:none;color:var(--muted);font-weight:500;font-size:15px;transition:color .2s;">How it works</a>
                <a href="{{ route('home') }}#coverage" data-hover="color:var(--accent)" style="text-decoration:none;color:var(--muted);font-weight:500;font-size:15px;transition:color .2s;">Coverage</a>
                <a href="{{ route('home') }}#pricing" data-hover="color:var(--accent)" style="text-decoration:none;color:var(--muted);font-weight:500;font-size:15px;transition:color .2s;">Pricing</a>
                <a href="{{ route('support') }}" data-hover="color:var(--accent)" style="text-decoration:none;color:var(--muted);font-weight:500;font-size:15px;transition:color .2s;">Support</a>
            </div>
            <div style="display:flex;align-items:center;gap:12px;">
                <button id="ujThemeBtn" type="button" aria-label="Toggle theme" data-hover="color:var(--accent);border-color:var(--accent)" style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:999px;background:transparent;border:1px solid var(--border);color:var(--muted);cursor:pointer;transition:color .2s,border-color .2s;">
                    <span id="ujThemeIcon" style="display:flex;">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4.2"></circle><path d="M12 2.5v2.4M12 19.1v2.4M4.6 4.6l1.7 1.7M17.7 17.7l1.7 1.7M2.5 12h2.4M19.1 12h2.4M4.6 19.4l1.7-1.7M17.7 6.3l1.7-1.7"></path></svg>
                    </span>
                </button>
                @auth
                    @if(auth()->user()->role === 'admin')
                        <a href="{{ route('admin.dashboard') }}" class="uj-btn-primary">Admin Panel</a>
                    @else
                        <a href="{{ route('user.dashboard') }}" class="uj-btn-primary">Dashboard</a>
                    @endif
                @else
                    <a href="{{ route('register') }}" class="uj-btn-primary">Get started</a>
                @endauth
            </div>
        </div>
    </nav>

    {{-- Page content --}}
    @yield('content')

    @include('layouts.partials.global_footer')

    @stack('modals')

    {{-- ─── Shared behaviour: theme toggle, nav solidify, progress, reveal, count-up, hover ─── --}}
    <script>
    (function () {
        var root = document.documentElement;

        /* Theme (default dark; remembered for the session) */
        try {
            var saved = localStorage.getItem('uj-theme');
            if (saved) root.setAttribute('data-theme', saved);
        } catch (e) {}
        function setThemeIcon(theme) {
            var icon = document.getElementById('ujThemeIcon');
            if (!icon) return;
            icon.innerHTML = theme === 'light'
                ? '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13.5A8 8 0 1 1 10.5 4 6.3 6.3 0 0 0 20 13.5z"></path></svg>'
                : '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4.2"></circle><path d="M12 2.5v2.4M12 19.1v2.4M4.6 4.6l1.7 1.7M17.7 17.7l1.7 1.7M2.5 12h2.4M19.1 12h2.4M4.6 19.4l1.7-1.7M17.7 6.3l1.7-1.7"></path></svg>';
        }
        setThemeIcon(root.getAttribute('data-theme') === 'light' ? 'light' : 'dark');
        var themeBtn = document.getElementById('ujThemeBtn');
        if (themeBtn) themeBtn.addEventListener('click', function () {
            var next = root.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
            root.setAttribute('data-theme', next);
            setThemeIcon(next);
            try { localStorage.setItem('uj-theme', next); } catch (e) {}
            if (window.ujRefreshChrome) window.ujRefreshChrome();
        });

        var cssVar = function (name) { return getComputedStyle(root).getPropertyValue(name).trim(); };

        /* Generic hover handler (data-hover="prop:val;prop2:val2") */
        function kebab(s) { return s.trim().replace(/[A-Z]/g, function (m) { return '-' + m.toLowerCase(); }); }
        document.querySelectorAll('[data-hover]').forEach(function (el) {
            var decls = el.getAttribute('data-hover').split(';').map(function (s) { return s.trim(); }).filter(Boolean).map(function (s) {
                var i = s.indexOf(':');
                return [kebab(s.slice(0, i)), s.slice(i + 1).trim()];
            });
            el.addEventListener('mouseenter', function () {
                el._ujOrig = {};
                decls.forEach(function (d) {
                    el._ujOrig[d[0]] = el.style.getPropertyValue(d[0]);
                    var val = d[1].replace('!important', '').trim();
                    el.style.setProperty(d[0], val, d[1].indexOf('!important') > -1 ? 'important' : '');
                });
            });
            el.addEventListener('mouseleave', function () {
                if (!el._ujOrig) return;
                decls.forEach(function (d) { el.style.setProperty(d[0], el._ujOrig[d[0]] || ''); });
            });
        });

        /* Reveal on scroll + count-up */
        var tween = function (dur, apply) {
            var start = performance.now();
            var frame = function (now) {
                var p = Math.min(1, (now - start) / dur);
                apply(1 - Math.pow(1 - p, 3));
                if (p < 1) requestAnimationFrame(frame);
            };
            requestAnimationFrame(frame);
        };
        var revealIn = function (el) {
            if (el._shown) return; el._shown = true;
            el.style.transition = 'none';
            tween(720, function (e) {
                el.style.opacity = String(e);
                el.style.transform = 'translateY(' + ((1 - e) * 22).toFixed(2) + 'px) scale(' + (0.985 + e * 0.015).toFixed(4) + ')';
            });
        };
        var fmt = function (n) { return n.toLocaleString('en-US'); };
        var runCount = function (el) {
            if (el._done) return; el._done = true;
            var to = parseFloat(el.getAttribute('data-count-to'));
            var pre = el.getAttribute('data-count-prefix') || '';
            var suf = el.getAttribute('data-count-suffix') || '';
            tween(1400, function (e) { el.textContent = pre + fmt(Math.round(to * e)) + suf; });
        };
        var revealEls = Array.prototype.slice.call(document.querySelectorAll('[data-reveal]'));
        var countEls = Array.prototype.slice.call(document.querySelectorAll('[data-count-to]'));
        /* Reveal + count-up via IntersectionObserver — no per-scroll layout reads */
        if ('IntersectionObserver' in window) {
            var revealIO = new IntersectionObserver(function (entries) {
                entries.forEach(function (e) { if (e.isIntersecting) { revealIn(e.target); revealIO.unobserve(e.target); } });
            }, { rootMargin: '0px 0px -10% 0px', threshold: 0.01 });
            revealEls.forEach(function (el) { revealIO.observe(el); });

            var countIO = new IntersectionObserver(function (entries) {
                entries.forEach(function (e) { if (e.isIntersecting) { runCount(e.target); countIO.unobserve(e.target); } });
            }, { rootMargin: '0px 0px -15% 0px', threshold: 0.01 });
            countEls.forEach(function (el) { countIO.observe(el); });
            /* The -15% rootMargin means elements hugging the bottom edge of the
               viewport (e.g. the hero stats strip) never intersect on initial load
               and sit at 0 until the user scrolls. Do one real-viewport pass now:
               anything already on screen counts up immediately. */
            var vh0 = window.innerHeight || document.documentElement.clientHeight;
            countEls.forEach(function (el) {
                var r = el.getBoundingClientRect();
                if (r.top < vh0 && r.bottom > 0) { runCount(el); countIO.unobserve(el); }
            });
        } else {
            revealEls.forEach(revealIn);
            countEls.forEach(runCount);
        }

        /* Nav solidify + progress bar + parallax — rAF-throttled, no per-event getComputedStyle */
        var nav = document.getElementById('ujNav');
        var prog = document.getElementById('ujProgress');
        var navVars = { nav: '', border: '' };
        var navSolid = null;
        function refreshNavVars() { navVars.nav = cssVar('--nav'); navVars.border = cssVar('--border'); navSolid = null; }
        function paint() {
            var y = window.pageYOffset || document.documentElement.scrollTop || 0;
            if (nav) {
                var solid = y > 24;
                if (solid !== navSolid) {
                    navSolid = solid;
                    nav.style.background = solid ? navVars.nav : 'transparent';
                    nav.style.backdropFilter = solid ? 'blur(14px)' : 'none';
                    nav.style.webkitBackdropFilter = solid ? 'blur(14px)' : 'none';
                    nav.style.borderBottomColor = solid ? navVars.border : 'transparent';
                    nav.style.boxShadow = solid ? '0 8px 30px -24px rgba(0,0,0,.7)' : 'none';
                }
            }
            if (prog) {
                var max = (document.documentElement.scrollHeight - window.innerHeight) || 1;
                prog.style.width = (Math.max(0, Math.min(1, y / max)) * 100).toFixed(2) + '%';
            }
            if (window.ujOnScrollExtra) window.ujOnScrollExtra(y);
        }
        var ticking = false;
        function requestFrame(force) {
            if (force) { paint(); return; }
            if (ticking) return;
            ticking = true;
            requestAnimationFrame(function () { ticking = false; paint(); });
        }
        window.ujRefreshChrome = function () { refreshNavVars(); requestFrame(true); };
        refreshNavVars();
        window.addEventListener('scroll', function () { requestFrame(false); }, { passive: true });
        window.addEventListener('resize', function () { requestFrame(false); }, { passive: true });
        requestFrame(true);

        /* Safety net: if a reveal somehow never fires, show everything after 4s */
        setTimeout(function () {
            revealEls.forEach(function (el) { if (!el._shown) { el._shown = true; el.style.transition = 'none'; el.style.opacity = '1'; el.style.transform = 'none'; } });
        }, 4000);
    })();
    </script>

    @stack('scripts')

    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</body>
</html>
