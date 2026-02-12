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
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        mono: ['JetBrains Mono', 'monospace'],
                    },
                    colors: {
                        slate: {
                            850: '#1e293b', 
                            900: '#0f172a',
                            950: '#020617',
                        }
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
    
    @livewireStyles
</head>

<body class="h-full overflow-hidden flex" x-data="{ sidebarOpen: false }">

    <div x-show="sidebarOpen" 
         x-transition.opacity
         @click="sidebarOpen = false"
         class="fixed inset-0 bg-slate-900/80 backdrop-blur-sm z-40 lg:hidden"
         x-cloak>
    </div>

    <aside 
        class="fixed inset-y-0 left-0 z-50 w-72 bg-slate-950 text-slate-400 flex flex-col transition-transform duration-300 ease-in-out lg:static lg:translate-x-0 border-r border-white/5"
        :class="sidebarOpen ? 'translate-x-0 shadow-2xl' : '-translate-x-full'"
    >
        <div class="h-20 flex items-center px-6 border-b border-white/5 bg-slate-950 shrink-0">
            <div class="flex items-center gap-3 text-white">
                <div class="w-8 h-8 rounded-lg bg-blue-600 flex items-center justify-center shadow-glow">
                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <span class="font-bold tracking-tight text-lg block leading-none">{{ config('app.name') }}</span>
                    <span class="text-[10px] text-slate-500 font-mono">Smart Dispute Management</span>
                </div>
            </div>
            <button @click="sidebarOpen = false" class="lg:hidden ml-auto text-slate-400 hover:text-white">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>

        <nav class="flex-1 px-4 py-6 space-y-1 overflow-y-auto sidebar-scroll">
            <div class="px-2 mb-3 text-[10px] uppercase tracking-wider font-bold text-slate-600 font-mono">Workspace</div>

            @php
                function navClass($route) {
                    return request()->routeIs($route) 
                        ? 'bg-blue-600/10 text-blue-400 shadow-[inset_3px_0_0_0_#2563eb]' 
                        : 'hover:bg-white/5 hover:text-slate-200 text-slate-400';
                }
            @endphp

            <a href="{{ route('user.dashboard') }}" class="{{ navClass('user.dashboard') }} group flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all">
                <i data-lucide="layout-dashboard" class="w-5 h-5 transition-colors {{ request()->routeIs('user.dashboard') ? 'text-blue-400' : 'text-slate-500 group-hover:text-slate-300' }}"></i>
                Dashboard
            </a>

            <a href="{{ route('user.documents.index') }}" class="{{ navClass('user.documents.*') }} group flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all">
                <i data-lucide="files" class="w-5 h-5 transition-colors {{ request()->routeIs('user.documents.*') ? 'text-blue-400' : 'text-slate-500 group-hover:text-slate-300' }}"></i>
                Documents
            </a>

            <a href="{{ route('user.cases.index') }}" class="{{ navClass('user.cases.*') }} group flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all">
                <i data-lucide="folder-kanban" class="w-5 h-5 transition-colors {{ request()->routeIs('user.cases.*') ? 'text-blue-400' : 'text-slate-500 group-hover:text-slate-300' }}"></i>
                My Disputes
            </a>

            <div class="px-2 mt-8 mb-3 text-[10px] uppercase tracking-wider font-bold text-slate-600 font-mono">Tools</div>

            <a href="{{ route('user.templates.index') }}" class="{{ navClass('user.templates.*') }} group flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all">
                <i data-lucide="file-text" class="w-5 h-5 transition-colors {{ request()->routeIs('user.templates.*') ? 'text-blue-400' : 'text-slate-500 group-hover:text-slate-300' }}"></i>
                Letter Templates
            </a>

            <a href="{{ route('user.emails.index') }}" class="{{ navClass('user.emails.*') }} group flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all justify-between">
                <div class="flex items-center gap-3">
                    <i data-lucide="mail" class="w-5 h-5 transition-colors {{ request()->routeIs('user.emails.*') ? 'text-blue-400' : 'text-slate-500 group-hover:text-slate-300' }}"></i>
                    Mailbox
                </div>
            </a>
        </nav>

        <div class="p-4 border-t border-white/5 bg-slate-950 shrink-0">
            {{--  --}}
            <div class="flex items-center gap-1 p-2 rounded-xl bg-slate-900/50 border border-white/5 hover:border-white/10 transition-all">
    
                <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 flex-1 min-w-0 p-1.5 rounded-lg hover:bg-white/5 transition-colors group">
                    <div class="relative shrink-0">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-sm text-white font-bold shadow-lg ring-2 ring-transparent group-hover:ring-blue-500/50 transition-all">
                            {{ substr(Auth::user()->name ?? 'U', 0, 2) }}
                        </div>
                        <div class="absolute bottom-0 right-0 w-3 h-3 bg-emerald-500 border-2 border-slate-900 rounded-full"></div>
                    </div>

                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-white truncate group-hover:text-blue-400 transition-colors">
                            {{ Auth::user()->name ?? 'Guest' }}
                        </p>
                        <p class="text-[10px] text-slate-500 truncate group-hover:text-slate-400">
                            Manage Profile
                        </p>
                    </div>
                </a>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="p-2.5 text-slate-500 hover:text-red-400 hover:bg-red-500/10 rounded-lg transition-colors" title="Log Out">
                        <i data-lucide="log-out" class="w-4.5 h-4.5"></i>
                    </button>
                </form>
    
            </div>
            {{--  --}}
        </div>
    </aside>

    <main class="flex-1 flex flex-col h-full overflow-hidden bg-slate-50 relative min-w-0">
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