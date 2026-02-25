<div class="flex h-full" x-data="{ mobileSidebarOpen: false }">
    
    {{-- MOBILE BACKDROP --}}
    <div 
        x-show="mobileSidebarOpen"
        x-transition:enter="transition-opacity ease-linear duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity ease-linear duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="mobileSidebarOpen = false"
        class="fixed inset-0 bg-slate-900/80 backdrop-blur-sm z-40 lg:hidden"
        x-cloak
    ></div>

    {{-- SIDEBAR --}}
    <aside 
        :class="mobileSidebarOpen ? 'translate-x-0 shadow-2xl' : '-translate-x-full'"
        class="fixed inset-y-0 left-0 z-50 w-72 h-full bg-slate-950 text-slate-400 flex flex-col transition-transform duration-300 ease-in-out lg:static lg:translate-x-0 border-r border-white/5"
    >
        <div class="h-20 flex items-center justify-between px-6 border-b border-white/5 bg-slate-950 shrink-0">
            <div class="flex items-center gap-3 text-white min-w-0">
                {{-- Logo Icon --}}
                <div class="w-8 h-8 rounded-lg bg-primary-600 flex items-center justify-center shadow-glow shrink-0">
                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </div>
                
                {{-- Text Content --}}
                <div class="flex flex-col justify-center min-w-0 py-1">
                    <span class="font-bold tracking-tight text-lg block leading-none truncate mb-1.5">
                        {{ config('app.name') }}
                    </span>
                    <div class="flex items-center gap-1.5">
                        <div class="w-1.5 h-1.5 rounded-full bg-primary-400 animate-pulse"></div>
                        <span class="text-[9px] font-bold text-primary-300 uppercase tracking-widest leading-none">
                            Admin Console
                        </span>
                    </div>
                </div>
            </div>
            
            {{-- Close Button for Mobile --}}
            <button @click="mobileSidebarOpen = false" class="lg:hidden shrink-0 ml-2 p-1 rounded-md text-slate-400 hover:text-white hover:bg-white/10 transition-colors">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>

        <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto sidebar-scroll">
            @php
                // Modern Pill Design: Active gets a blue pill background, inactive gets a subtle hover effect
                $navClass = fn($route) => request()->routeIs($route) 
                    ? 'bg-primary-500/10 text-primary-400 font-semibold' 
                    : 'hover:bg-white/5 hover:text-slate-200 text-slate-400 font-medium';
                
                // Icon Color
                $iconClass = fn($route) => request()->routeIs($route)
                    ? 'text-primary-400'
                    : 'text-slate-500 group-hover:text-slate-300';
            @endphp

            <div class="px-3 mb-2 text-[10px] uppercase tracking-wider font-bold text-slate-600">Overview</div>

            <a href="{{ route('admin.dashboard') }}" wire:navigate class="{{ $navClass('admin.dashboard') }} group flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-all duration-200" @click="mobileSidebarOpen = false">
                <i data-lucide="layout-dashboard" class="w-4.5 h-4.5 transition-colors {{ $iconClass('admin.dashboard') }}"></i>
                Dashboard
            </a>

            <div class="px-3 mt-6 mb-2 text-[10px] uppercase tracking-wider font-bold text-slate-600">Management</div>

            <a href="{{ route('admin.users.index') }}" wire:navigate class="{{ $navClass('admin.users.*') }} group flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-all duration-200" @click="mobileSidebarOpen = false">
                <i data-lucide="users" class="w-4.5 h-4.5 transition-colors {{ $iconClass('admin.users.*') }}"></i>
                Users
            </a>

            {{-- CHANGED ICON FROM 'gavel' TO 'file-text' --}}
            <a href="{{ route('admin.templates.index') }}" wire:navigate class="{{ $navClass('admin.templates.*') }} group flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-all duration-200" @click="mobileSidebarOpen = false">
                <i data-lucide="file-text" class="w-4.5 h-4.5 transition-colors {{ $iconClass('admin.templates.*') }}"></i>
                Templates
            </a>

            <div class="px-3 mt-6 mb-2 text-[10px] uppercase tracking-wider font-bold text-slate-600">Institutes</div>

            <a href="{{ route('admin.institutions.index') }}" wire:navigate class="{{ $navClass('admin.institutions.*') }} group flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-all duration-200" @click="mobileSidebarOpen = false">
                <i data-lucide="building-2" class="w-4.5 h-4.5 transition-colors {{ $iconClass('admin.institutions.*') }}"></i>
                All Institutes
            </a>

            <a href="{{ route('admin.categories.index') }}" wire:navigate class="{{ $navClass('admin.categories.*') }} group flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-all duration-200" @click="mobileSidebarOpen = false">
                <i data-lucide="layers" class="w-4.5 h-4.5 transition-colors {{ $iconClass('admin.categories.*') }}"></i>
                Categories
            </a>

            <div class="px-3 mt-6 mb-2 text-[10px] uppercase tracking-wider font-bold text-slate-600">System</div>
            
            <a href="{{ route('admin.settings.index') }}" wire:navigate class="{{ $navClass('admin.settings.*') }} group flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-all duration-200" @click="mobileSidebarOpen = false">
                <i data-lucide="settings" class="w-4.5 h-4.5 transition-colors {{ $iconClass('admin.settings.*') }}"></i>
                Settings
            </a>
        </nav>

        <div class="p-4 border-t border-white/5 bg-slate-950 shrink-0 mt-auto">
            <div class="flex items-center gap-1 p-2 rounded-2xl bg-slate-900 border border-white/5 hover:border-white/10 transition-all group">
                <a href="{{ route('admin.settings.index') }}" wire:navigate class="flex-1 min-w-0">
                    <div class="flex items-center gap-3 p-1">
                        <div class="relative shrink-0">
                            <div class="w-9 h-9 rounded-full bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center text-xs text-white font-bold ring-2 ring-transparent group-hover:ring-primary-500/30 transition-all">
                                {{ substr(Auth::user()->name ?? 'A', 0, 2) }}
                            </div>
                            <div class="absolute bottom-0 right-0 w-2.5 h-2.5 bg-emerald-500 border-2 border-slate-900 rounded-full"></div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-[13px] font-bold text-slate-200 truncate group-hover:text-white transition-colors">{{ Auth::user()->name ?? 'Admin' }}</p>
                            <p class="text-[10px] text-slate-500 truncate font-medium">Super Admin</p>
                        </div>
                    </div>
                </a>

                <button wire:click="logout" class="p-2 text-slate-500 hover:text-rose-400 hover:bg-rose-500/10 rounded-xl transition-all" title="Log Out">
                    <i data-lucide="log-out" class="w-4 h-4"></i>
                </button>
            </div>
        </div>
    </aside>

    {{-- MOBILE TOP NAV --}}
    <div class="lg:hidden fixed top-0 left-0 right-0 h-16 bg-white border-b border-slate-200 flex items-center justify-between px-4 z-30">
        <div class="flex items-center gap-3">
            {{-- Open Button for Mobile --}}
            <button @click="mobileSidebarOpen = true" class="text-slate-500 hover:text-slate-900 p-2 -ml-2 rounded-lg hover:bg-slate-100 transition-colors">
                <i data-lucide="menu" class="w-5 h-5"></i>
            </button>
            <span class="font-bold text-slate-800 tracking-tight">Admin Console</span>
        </div>
    </div>
</div>