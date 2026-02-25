<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin Portal') - {{ config('app.name') }}</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        // This maps every shade of blue (50-950) to 'primary'
                        primary: tailwind.colors.blue, 
                        
                        slate: { 850: '#1e293b', 900: '#0f172a', 950: '#020617' }
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        mono: ['JetBrains Mono', 'monospace'],
                    },
                    boxShadow: {
                        // Use the primary-600 RGB value here for the glow
                        'glow': '0 0 20px rgba(37, 99, 235, 0.15)', 
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
        [x-cloak] { display: none !important; }
    </style>
    @livewireStyles
</head>

<body class="h-full overflow-hidden flex">

    <x-livewire-alert />
    
    @livewire('admin.layout.navigation')

    <main class="flex-1 flex flex-col h-full overflow-hidden bg-slate-50 relative min-w-0 pt-16 lg:pt-0">
        @yield('content')
    </main>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    @stack('scripts')
    @livewireScripts
    <script>
        lucide.createIcons();
        document.addEventListener('livewire:navigated', () => { lucide.createIcons(); });
        document.addEventListener("livewire:init", () => {
            Livewire.hook('morph.updated', () => { lucide.createIcons(); });
        });
    </script>
</body>
</html>