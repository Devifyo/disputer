<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Portal') - {{ config('app.name') }}</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        slate: {
                            850: '#1e293b', 
                            900: '#0f172a',
                            950: '#020617',
                        },
                        blue: {
                            650: '#2563eb', // Richer blue
                        }
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        mono: ['JetBrains Mono', 'monospace'],
                    },
                    boxShadow: {
                        'glow': '0 0 25px rgba(37, 99, 235, 0.25)',
                        'card': '0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1)',
                    },
                    animation: {
                        'float': 'float 6s ease-in-out infinite',
                    },
                    keyframes: {
                        float: {
                            '0%, 100%': { transform: 'translateY(0)' },
                            '50%': { transform: 'translateY(-10px)' },
                        }
                    }
                }
            }
        }
    </script>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <style>
        body { font-family: 'Inter', sans-serif; }
        .fade-in { animation: fadeIn 0.6s cubic-bezier(0.16, 1, 0.3, 1); }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        
        /* Subtle pattern overlay */
        .bg-grid-pattern {
            background-image: radial-gradient(rgba(255, 255, 255, 0.1) 1px, transparent 1px);
            background-size: 24px 24px;
        }
    </style>
</head>
<body class="h-full flex overflow-hidden">

    <div class="hidden lg:flex w-5/12 bg-slate-950 relative flex-col justify-between p-12 text-white border-r border-white/5 overflow-hidden">
        
        <div class="absolute inset-0 bg-slate-950 z-0"></div>
        <div class="absolute top-0 right-0 w-[500px] h-[500px] bg-blue-600/20 rounded-full blur-[120px] -translate-y-1/2 translate-x-1/2"></div>
        <div class="absolute bottom-0 left-0 w-[400px] h-[400px] bg-indigo-600/10 rounded-full blur-[100px] translate-y-1/3 -translate-x-1/3"></div>
        
        <svg class="absolute inset-0 w-full h-full z-0 opacity-20 pointer-events-none" viewBox="0 0 100 100" preserveAspectRatio="none">
            <path d="M0 100 C 20 0 50 0 100 100 Z" fill="none" stroke="url(#gradient)" stroke-width="0.5"/>
            <defs>
                <linearGradient id="gradient" x1="0%" y1="0%" x2="100%" y2="0%">
                    <stop offset="0%" style="stop-color:#3b82f6;stop-opacity:0" />
                    <stop offset="50%" style="stop-color:#3b82f6;stop-opacity:1" />
                    <stop offset="100%" style="stop-color:#3b82f6;stop-opacity:0" />
                </linearGradient>
            </defs>
        </svg>

        <div class="relative z-10 flex items-center gap-3">
           <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-blue-600 to-indigo-600 flex items-center justify-center shadow-glow ring-1 ring-white/10">
                <img src="/icon.svg" class="w-6 h-6 invert brightness-0 invert-[1]" alt="Icon" />            
            </div>
            <div>
                <span class="font-bold tracking-tight text-xl block leading-none text-white">{{ config('app.name') }}</span>
                <span class="text-[10px] text-blue-200/80 font-mono uppercase tracking-widest mt-0.5 block">Dispute Resolution Platform</span>
            </div>
        </div>

        <div class="relative z-10 max-w-md mt-auto mb-auto">
            <h1 class="text-4xl font-bold mb-6 tracking-tight text-white leading-tight">
                Navigate Bureaucracy <br>
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-indigo-300">With Confidence.</span>
            </h1>
            
            <p class="text-slate-400 text-lg leading-relaxed mb-8">
                Stop fighting alone. Our intelligent platform helps you generate formal disputes, track responses, and reclaim what's rightfully yours.
            </p>

            <div class="grid gap-4">
                <div class="flex items-center gap-4 p-3 rounded-lg bg-white/5 border border-white/5 backdrop-blur-sm hover:bg-white/10 transition-colors group">
                    <div class="w-10 h-10 rounded-full bg-blue-500/20 flex items-center justify-center text-blue-400 group-hover:text-white group-hover:bg-blue-500 transition-all">
                        <i data-lucide="sparkles" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-white">AI-Powered Letters</h3>
                        <p class="text-xs text-slate-400">Generate professional legal templates in seconds.</p>
                    </div>
                </div>

                <div class="flex items-center gap-4 p-3 rounded-lg bg-white/5 border border-white/5 backdrop-blur-sm hover:bg-white/10 transition-colors group">
                    <div class="w-10 h-10 rounded-full bg-indigo-500/20 flex items-center justify-center text-indigo-400 group-hover:text-white group-hover:bg-indigo-500 transition-all">
                        <i data-lucide="shield-check" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-white">Bank-Grade Security</h3>
                        <p class="text-xs text-slate-400">Your documents and personal data are encrypted.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="relative z-10 flex items-center justify-between border-t border-white/10 pt-6 mt-8">
            <div class="text-xs text-slate-500 font-mono">
                &copy; {{ date('Y') }} {{ config('app.name') }} Inc.
            </div>
            <div class="flex gap-4">
                <a href="#" class="text-xs text-slate-400 hover:text-white transition-colors">About us</a>
                <a href="#" class="text-xs text-slate-400 hover:text-white transition-colors">Support</a>
            </div>
        </div>
    </div>

    <div class="w-full lg:w-7/12 flex flex-col bg-slate-50 h-full overflow-y-auto relative">
        
        <div class="lg:hidden p-6 flex items-center gap-2">
            <div class="w-10 h-10 rounded-xl bg-blue-600 flex items-center justify-center shadow-lg shadow-blue-600/20">
                <img src="/icon.svg" class="w-6 h-6 invert brightness-0 invert-[1]" alt="Icon" />            
            </div>
            <span class="font-bold text-slate-900 tracking-tight text-xl">{{ config('app.name') }}</span>
        </div>

        <div class="flex-grow flex items-center justify-center p-6 sm:p-12">
            <div class="w-full max-w-[400px] space-y-6 fade-in">
                
                @yield('content')

            </div>
        </div>

        <footer class="py-6 shrink-0">
            <div class="max-w-7xl mx-auto px-6 flex flex-wrap justify-center gap-x-6 gap-y-2">
                <a href="{{ route('privacy') }}" class="text-xs text-slate-400 hover:text-blue-600 transition-colors">Privacy Policy</a>
                <a href="{{ route('terms') }}" class="text-xs text-slate-400 hover:text-blue-600 transition-colors">Terms of Service</a>
            </div>
        </footer>

    </div>
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
                    
                    // Insert after the input's wrapper
                    error.insertAfter(element.parent(".relative"));
                    
                    // Re-render icons for the newly injected error message
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
                    // Trigger Alpine.js loading animation globally
                    form.dispatchEvent(new CustomEvent('valid-submit'));
                    form.submit();
                }
            });
        }
    </script>
</body>
</html>