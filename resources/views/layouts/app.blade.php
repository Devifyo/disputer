<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') - {{config('app.name')}}</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        slate: { 850: '#1e293b', 900: '#0f172a', 950: '#020617' },
                        // Explicitly defining the blue palette ensures the CDN never fails
                        primary: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            200: '#bfdbfe',
                            300: '#93c5fd',
                            400: '#60a5fa',
                            500: '#3b82f6',
                            600: '#2563eb', /* Your exact blue */
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                            950: '#172554',
                        }
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        mono: ['JetBrains Mono', 'monospace'],
                    },
                    boxShadow: {
                        'glow': '0 0 20px rgba(37, 99, 235, 0.15)',
                    }
                }
            }
        }
    </script>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <style>
        [x-cloak] { display: none !important; }
        body { font-family: 'Inter', sans-serif; }
        .sidebar-scroll::-webkit-scrollbar { width: 4px; }
        .sidebar-scroll::-webkit-scrollbar-track { background: transparent; }
        .sidebar-scroll::-webkit-scrollbar-thumb { background: #334155; border-radius: 2px; }
        .sidebar-scroll:hover::-webkit-scrollbar-thumb { background: #475569; }

        #compose-body, 
        .compose-modal-textarea {
            min-height: 300px !important;
            resize: vertical;
        }

        textarea[wire\:model="body"], 
    textarea[x-model="replyBody"] {
        min-height: 250px !important;
        line-height: 1.6 !important;
        padding: 1rem !important;
        font-family: inherit;
        resize: vertical !important; /* Allows you to manually pull it down */
        overflow-y: auto !important;
    }
    </style>
    <style>
        /* Clean up the huge Gmail history blocks */
        .email-content .gmail_quote, 
        .email-content blockquote {
            border-left: 3px solid #cbd5e1 !important;
            padding-left: 1rem !important;
            margin-top: 1.5rem !important;
            color: #64748b !important;
            font-size: 0.85rem !important;
            opacity: 0.8;
        }
        
        /* Hide the 'On Mon, Feb 16...' text or make it subtle */
        .email-content .gmail_attr {
            font-size: 0.75rem !important;
            color: #94a3b8 !important;
        }

        /* Ensure images inside emails don't overflow */
        .email-content img {
            max-width: 100%;
            height: auto;
            border-radius: 0.5rem;
        }
    </style>
    @stack('style')
    @livewireStyles
</head>

<body class="h-full overflow-hidden flex" x-data="{ sidebarOpen: false }">

    <div x-show="sidebarOpen" 
         x-transition.opacity
         @click="sidebarOpen = false"
         class="fixed inset-0 bg-slate-900/80 backdrop-blur-sm z-40 lg:hidden"
         x-cloak>
    </div>

        @include('layouts.partials.user_sidebar')


    <main class="flex-1 flex flex-col h-full overflow-hidden bg-slate-50 relative min-w-0">
        {{-- IMPERSONATION BANNER --}}
        @impersonating
            <div class="bg-primary-600 text-white px-6 py-3 flex items-center justify-between shrink-0 z-40 shadow-md">
                <div class="flex items-center gap-3">
                    <i data-lucide="shield-alert" class="w-5 h-5 opacity-90"></i>
                    <span class="text-sm font-medium">
                        Viewing as <strong class="ml-1">{{ auth()->user()->name }}</strong>
                    </span>
                </div>
                <a href="{{ route('admin.leave.impersonation') }}" class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold rounded-xl transition-all shadow-lg active:scale-95 flex items-center gap-2">
                    <i data-lucide="log-out" class="w-3.5 h-3.5"></i> Return to Admin
                </a>
            </div>
        @endImpersonating
        {{--  --}}
        <div class="lg:hidden h-16 flex items-center px-4 bg-white border-b border-slate-200 justify-between shrink-0 z-30">
            <div class="flex items-center gap-3">
                <button @click="sidebarOpen = true" class="text-slate-500 hover:text-slate-700 p-2 -ml-2 rounded-md hover:bg-slate-100">
                    <i data-lucide="menu" class="w-6 h-6"></i>
                </button>
                <span class="font-bold text-slate-800">Resolver</span>
            </div>
        </div>

        @yield('content')
    </main>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/jquery.validate.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/additional-methods.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    @stack('scripts')
    @livewireScripts
    <script>
        // Initialize Lucide icons on page load
        lucide.createIcons();
        
        // Re-initialize Lucide icons after Livewire updates the DOM
        document.addEventListener('livewire:navigated', () => {
            lucide.createIcons();
        });

        // This hook is crucial for Livewire 3 component updates
        document.addEventListener("livewire:init", () => {
            Livewire.hook('morph.updated', (component) => {
                lucide.createIcons();
            });
        });

        document.addEventListener('livewire:initialized', () => {
        Livewire.on('open-email', () => {
            setTimeout(() => { lucide.createIcons(); }, 100);
        });
    });
    </script>
</body>
</html>