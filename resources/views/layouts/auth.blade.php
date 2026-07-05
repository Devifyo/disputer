<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Portal') - Unjamm</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='7' fill='%230B0E13'/%3E%3Cpath d='M6 23 L16 7 L26 23' stroke='%233FCB94' stroke-width='2.6' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3Ccircle cx='16' cy='25' r='2' fill='%233FCB94'/%3E%3C/svg%3E">
    <script src="https://cdn.tailwindcss.com"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        /* Remap the brand blue → Unjamm green so every form inherits the new palette */
                        blue: {
                            50:  '#E6EFE9', 100: '#CFE6DC', 200: '#A5D8C2', 300: '#6FC6A2',
                            400: '#3FB588', 500: '#149468', 600: '#0B6B4C', 650: '#0B6B4C',
                            700: '#0A5C41', 800: '#083F2D', 900: '#062E21',
                        },
                        indigo: {
                            300: '#6FC6A2', 400: '#3FB588', 500: '#149468', 600: '#0B6B4C', 700: '#0A5C41',
                        },
                        accent: '#3FCB94',
                        slate: { 850: '#1e293b', 900: '#0f172a', 950: '#0B0E13' },
                    },
                    fontFamily: {
                        sans: ['Hanken Grotesk', 'sans-serif'],
                        display: ['Bricolage Grotesque', 'sans-serif'],
                        mono: ['ui-monospace', 'JetBrains Mono', 'monospace'],
                    },
                    boxShadow: {
                        'glow': '0 0 25px rgba(63, 203, 148, 0.3)',
                        'card': '0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1)',
                    },
                }
            }
        }
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400;12..96,600;12..96,700;12..96,800&family=Hanken+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        body { font-family: 'Hanken Grotesk', sans-serif; }
        h1, h2, h3 { font-family: 'Bricolage Grotesque', sans-serif; letter-spacing: -0.02em; }
        .fade-in { animation: fadeIn 0.6s cubic-bezier(0.16, 1, 0.3, 1); }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes ujZoomAuth { 0% { transform: scale(1.03); } 100% { transform: scale(1.12); } }
    </style>
</head>
<body class="h-full flex overflow-hidden" style="background:#F4F1EA;">

    {{-- ─── Brand panel (dark, flight-compensation) ─── --}}
    <div class="hidden lg:flex w-5/12 relative flex-col justify-between p-12 text-white overflow-hidden" style="background:#0B0E13;">

        {{-- Aerial clouds hero --}}
        <div class="absolute inset-0 z-0 overflow-hidden">
            <img src="https://images.unsplash.com/photo-1551748629-08d916ed6682?q=80&w=1600&auto=format&fit=crop" alt="Above the clouds" class="w-full h-full object-cover" style="object-position:center 42%;animation:ujZoomAuth 26s ease-in-out infinite alternate;">
        </div>
        <div class="absolute inset-0 z-0" style="background:linear-gradient(180deg,rgba(11,14,19,.72) 0%,rgba(11,14,19,.55) 45%,rgba(11,14,19,.88) 100%);"></div>
        <div class="absolute top-0 right-0 w-[500px] h-[500px] rounded-full blur-[120px] -translate-y-1/2 translate-x-1/2 z-0" style="background:rgba(63,203,148,.18);"></div>

        {{-- Logo --}}
        <div class="relative z-10 flex items-center gap-3">
            <span class="flex" style="color:#3FCB94;">
                <svg viewBox="0 0 32 32" width="30" height="30" fill="none" aria-hidden="true"><path d="M5 23.5 L16 6 L27 23.5" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"></path><path d="M11 23.5 L16 15.5 L21 23.5" stroke="currentColor" stroke-opacity="0.4" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"></path><circle cx="16" cy="26" r="2.1" fill="currentColor"></circle></svg>
            </span>
            <div>
                <span class="font-display font-bold tracking-tight text-xl block leading-none text-white">Unjamm</span>
                <span class="text-[10px] font-mono uppercase tracking-widest mt-1 block" style="color:rgba(63,203,148,.9);">Flight Compensation</span>
            </div>
        </div>

        {{-- Pitch --}}
        <div class="relative z-10 max-w-md mt-auto mb-auto">
            <h1 class="text-4xl font-bold mb-6 tracking-tight text-white leading-[1.05]">
                Get the money<br>airlines owe you.
            </h1>

            <p class="text-lg leading-relaxed mb-8" style="color:#CBD2DA;">
                Forward a flight confirmation and we handle the rest — filing your claim automatically under EU 261, UK 261, Canada APPR, US DOT and the Montreal Convention.
            </p>

            <div class="grid gap-4">
                <div class="flex items-center gap-4 p-3 rounded-xl border" style="background:rgba(255,255,255,.05);border-color:rgba(255,255,255,.08);backdrop-filter:blur(6px);">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center shrink-0" style="background:rgba(63,203,148,.15);color:#3FCB94;">
                        <i data-lucide="radar" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-white">Real-time flight monitoring</h3>
                        <p class="text-xs" style="color:#9BA4B0;">We watch every flight 24/7 and start your claim the moment a disruption qualifies.</p>
                    </div>
                </div>

                <div class="flex items-center gap-4 p-3 rounded-xl border" style="background:rgba(255,255,255,.05);border-color:rgba(255,255,255,.08);backdrop-filter:blur(6px);">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center shrink-0" style="background:rgba(63,203,148,.15);color:#3FCB94;">
                        <i data-lucide="badge-check" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-white">No win, no fee</h3>
                        <p class="text-xs" style="color:#9BA4B0;">Keep the majority of every payout. Pay nothing unless we recover compensation for you.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="relative z-10 border-t pt-6 mt-8" style="border-color:rgba(255,255,255,.12);">
            <div class="text-xs font-mono" style="color:#6B7480;">&copy; {{ date('Y') }} Unjamm Inc. · Toronto, Canada</div>
        </div>
    </div>

    {{-- ─── Form panel ─── --}}
    <div class="w-full lg:w-7/12 flex flex-col h-full overflow-y-auto relative" style="background:#F4F1EA;">

        <div class="lg:hidden p-6 flex items-center gap-2">
            <span class="flex" style="color:#0B6B4C;">
                <svg viewBox="0 0 32 32" width="30" height="30" fill="none" aria-hidden="true"><path d="M5 23.5 L16 6 L27 23.5" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"></path><path d="M11 23.5 L16 15.5 L21 23.5" stroke="currentColor" stroke-opacity="0.4" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"></path><circle cx="16" cy="26" r="2.1" fill="currentColor"></circle></svg>
            </span>
            <span class="font-display font-bold text-slate-900 tracking-tight text-xl">Unjamm</span>
        </div>

        <div class="flex-grow flex items-center justify-center p-6 sm:p-12">
            <div class="w-full max-w-[400px] space-y-6 fade-in">

                @yield('content')

            </div>
        </div>

        <footer class="py-6 shrink-0">
            <div class="max-w-7xl mx-auto px-6 flex flex-wrap justify-center gap-x-6 gap-y-2">
                <a href="{{ route('home') }}"    class="text-xs text-slate-400 hover:text-blue-600 transition-colors">Home</a>
                <a href="{{ route('support') }}" class="text-xs text-slate-400 hover:text-blue-600 transition-colors">Support</a>
                <a href="{{ route('terms') }}"   class="text-xs text-slate-400 hover:text-blue-600 transition-colors">Terms</a>
                <a href="{{ route('privacy') }}" class="text-xs text-slate-400 hover:text-blue-600 transition-colors">Privacy Policy</a>
            </div>
        </footer>

    </div>

    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.20.0/jquery.validate.min.js"></script>
    @stack('scripts')
    <script>
        lucide.createIcons();
        if (typeof $.validator !== 'undefined') {
            $.validator.setDefaults({
                errorElement: "p",
                errorPlacement: function (error, element) {
                    error.addClass("text-red-600 text-xs mt-1 font-medium flex items-center gap-1");
                    error.prepend('<i data-lucide="alert-circle" class="w-3 h-3"></i>');
                    error.insertAfter(element.parent(".relative"));
                    lucide.createIcons();
                },
                highlight: function (element) {
                    $(element)
                        .removeClass("border-slate-200 focus:border-blue-500 focus:ring-blue-500/10")
                        .addClass("border-red-500 focus:border-red-500 focus:ring-red-500/10");
                },
                unhighlight: function (element) {
                    $(element)
                        .removeClass("border-red-500 focus:border-red-500 focus:ring-red-500/10")
                        .addClass("border-slate-200 focus:border-blue-500 focus:ring-blue-500/10");
                },
                submitHandler: function (form) {
                    form.dispatchEvent(new CustomEvent('valid-submit'));
                    form.submit();
                }
            });
        }
    </script>
</body>
</html>
