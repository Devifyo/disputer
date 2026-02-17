<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin Portal') - {{config('app.name')}}</title>

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
                        slate: { 850: '#1e293b', 900: '#0f172a', 950: '#020617' }
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .sidebar-scroll::-webkit-scrollbar { width: 4px; }
        .sidebar-scroll::-webkit-scrollbar-track { background: transparent; }
        .sidebar-scroll::-webkit-scrollbar-thumb { background: #334155; border-radius: 2px; }
        .sidebar-scroll:hover::-webkit-scrollbar-thumb { background: #475569; }
    </style>
    @livewireStyles
</head>

<body class="h-full overflow-hidden flex">

    {{-- 
        LIVEWIRE NAVIGATION COMPONENT 
        Handles Sidebar, Mobile Toggle, and Logout logic.
    --}}
    @livewire('admin.layout.navigation')

    {{-- MAIN CONTENT AREA --}}
    <main class="flex-1 flex flex-col h-full overflow-hidden bg-slate-50 relative min-w-0 pt-16 lg:pt-0">
        {{-- The 'pt-16' above pushes content down on mobile to account for the fixed header --}}
        
        @yield('content')
        
    </main>

    @livewireScripts
    <script>
        // Ensure icons load on navigation
        lucide.createIcons();
        document.addEventListener('livewire:navigated', () => { lucide.createIcons(); });
        document.addEventListener("livewire:init", () => {
            Livewire.hook('morph.updated', () => { lucide.createIcons(); });
        });
    </script>
</body>
</html>