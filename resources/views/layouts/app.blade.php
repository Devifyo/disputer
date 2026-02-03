<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Dashboard') - BureaucracyResolver</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#0f172a',
                        accent: '#2563eb',
                        surface: '#ffffff',
                        background: '#f8fafc',
                    },
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                        mono: ['JetBrains Mono', 'Menlo', 'monospace'],
                    },
                    boxShadow: {
                        'card': '0 4px 6px -1px rgba(0, 0, 0, 0.02), 0 2px 4px -1px rgba(0, 0, 0, 0.02)',
                    }
                }
            }
        }
    </script>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <style>
        body { font-family: 'Inter', sans-serif; -webkit-font-smoothing: antialiased; }
        .sidebar-item:hover { background: rgba(255,255,255,0.08); }
        .sidebar-item.active { background: #2563eb; color: white; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3); }
        #mobile-sidebar { transition: transform 0.3s ease-in-out; }
    </style>
</head>
<body class="bg-background text-slate-800 flex h-screen overflow-hidden">

    <div id="mobile-overlay" class="fixed inset-0 bg-slate-900/50 z-20 hidden lg:hidden"></div>

    <aside id="mobile-sidebar" class="fixed inset-y-0 left-0 w-64 bg-primary text-slate-400 flex flex-col z-30 transform -translate-x-full lg:translate-x-0 lg:static lg:flex-shrink-0 shadow-2xl lg:shadow-none">

        <div class="h-16 flex items-center px-6 border-b border-slate-800/50 justify-between">
            <div class="flex items-center gap-3 text-white">
                <svg class="w-6 h-6 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" />
                </svg>
                <span class="font-bold tracking-tight text-lg">Resolver <span class="text-[10px] bg-slate-800 px-1.5 py-0.5 rounded text-slate-400 font-normal align-top ml-1">v1.0</span></span>
            </div>
            <button id="close-sidebar" class="lg:hidden text-slate-400 hover:text-white"><i data-lucide="x" class="w-6 h-6"></i></button>
        </div>

        <nav class="flex-1 px-3 py-6 space-y-1 overflow-y-auto">
            <div class="px-3 mb-2 text-[10px] uppercase tracking-widest font-bold text-slate-600">Workspace</div>

            <a href="{{ route('user.dashboard') }}" class="sidebar-item {{ request()->routeIs('user.dashboard') ? 'active' : '' }} flex items-center gap-3 px-3 py-2 rounded-lg text-white transition-all mb-1">
                <i data-lucide="layout-dashboard" class="w-4 h-4"></i><span class="text-sm font-medium">Dashboard</span>
            </a>

            <a href="#" class="sidebar-item flex items-center gap-3 px-3 py-2 rounded-lg hover:text-white transition-all">
                <i data-lucide="files" class="w-4 h-4 hover:text-blue-400"></i><span class="text-sm font-medium">Documents</span>
            </a>

            <a href="#" class="sidebar-item flex items-center gap-3 px-3 py-2 rounded-lg hover:text-white transition-all">
                <i data-lucide="folder-kanban" class="w-4 h-4 hover:text-blue-400"></i><span class="text-sm font-medium">My Disputes</span>
            </a>

            <div class="px-3 mt-8 mb-2 text-[10px] uppercase tracking-widest font-bold text-slate-600">Tools & History</div>

            <a href="#" class="sidebar-item flex items-center gap-3 px-3 py-2 rounded-lg hover:text-white transition-all">
                <i data-lucide="file-text" class="w-4 h-4 hover:text-purple-400"></i><span class="text-sm font-medium">Letter Templates</span>
            </a>

            <a href="#" class="sidebar-item flex items-center gap-3 px-3 py-2 rounded-lg hover:text-white transition-all">
                <i data-lucide="history" class="w-4 h-4 hover:text-blue-400"></i><span class="text-sm font-medium">Audit Logs</span>
            </a>
        </nav>

        {{--  --}}
        <div class="p-4 bg-slate-950/30 border-t border-slate-800/50">
    <div class="flex items-center gap-3 w-full p-2 rounded-lg hover:bg-slate-900/50 transition-colors group">

        <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-blue-600 to-indigo-600 flex items-center justify-center text-xs text-white font-bold border border-slate-600 shrink-0">
            {{ substr(Auth::user()->name ?? 'U', 0, 2) }}
        </div>

        <div class="flex-1 min-w-0">
            <p class="text-xs font-semibold text-white truncate">{{ Auth::user()->name ?? 'Guest' }}</p>
            <p class="text-[10px] text-slate-500 truncate">Free Tier</p>
        </div>

        <form method="POST" action="{{ route('logout') }}" class="shrink-0">
            @csrf
            <button type="submit" class="p-1.5 text-slate-500 hover:text-red-400 hover:bg-slate-800 rounded-md transition-all" title="Logout">
                <i data-lucide="log-out" class="w-4 h-4"></i>
            </button>
        </form>

    </div>
</div>
        {{--  --}}
    </aside>

    <main class="flex-1 flex flex-col h-full overflow-hidden w-full">
        @yield('content')
    </main>

    <script>
        lucide.createIcons();
        $(document).ready(function() {
            // Sidebar Toggles
            $('#open-sidebar, #close-sidebar, #mobile-overlay').on('click', function() {
                $('#mobile-sidebar').toggleClass('-translate-x-full');
                $('#mobile-overlay').toggleClass('hidden');
            });
        });
    </script>
    @stack('scripts')

    @livewireScripts
    <script>
    // Re-initialize icons after every Livewire update
    document.addEventListener('livewire:navigated', () => {
        lucide.createIcons();
    });

    // For standard updates
    document.addEventListener("livewire:init", () => {
        Livewire.hook('morph.updated', (el, component) => {
            lucide.createIcons();
        });
    });
</script>

</body>
</html>
