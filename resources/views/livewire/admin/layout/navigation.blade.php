<div class="flex h-full">
    {{-- MOBILE BACKDROP --}}
    @if($mobileSidebarOpen)
        <div 
            wire:click="closeSidebar"
            class="fixed inset-0 bg-slate-900/80 backdrop-blur-sm z-40 lg:hidden animate-in fade-in duration-200"
        ></div>
    @endif

    {{-- SIDEBAR --}}
    <aside 
        class="fixed inset-y-0 left-0 z-50 w-72 h-full bg-slate-950 text-slate-400 flex flex-col transition-transform duration-300 ease-in-out lg:static lg:translate-x-0 border-r border-white/5 
        {{ $mobileSidebarOpen ? 'translate-x-0 shadow-2xl' : '-translate-x-full' }}"
    >
        <div class="h-20 flex items-center justify-between px-6 border-b border-white/5 bg-slate-950 shrink-0">
            {{--  --}}
            <div class="flex items-center gap-3 text-white min-w-0">
                {{-- Logo Icon --}}
                <div class="w-8 h-8 rounded-lg bg-primary-600 flex items-center justify-center shadow-glow shrink-0">
                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </div>
                
                {{-- Text Content (Stacked instead of side-by-side) --}}
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
            {{--  --}}
            
            <button wire:click="closeSidebar" class="lg:hidden shrink-0 ml-2 p-1 rounded-md text-slate-400 hover:text-white hover:bg-white/10 transition-colors">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>

        <nav class="flex-1 px-4 py-6 space-y-1 overflow-y-auto sidebar-scroll">
            @php
                $navClass = fn($route) => request()->routeIs($route) 
                    ? 'bg-blue-600/10 text-blue-400 shadow-[inset_3px_0_0_0_#2563eb]' 
                    : 'hover:bg-white/5 hover:text-slate-200 text-slate-400';
            @endphp

            <div class="px-2 mb-3 text-[10px] uppercase tracking-wider font-bold text-slate-600 font-mono">Overview</div>

            <a href="{{ route('admin.dashboard') }}" class="{{ $navClass('admin.dashboard') }} group flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all">
                <i data-lucide="layout-dashboard" class="w-5 h-5 transition-colors {{ request()->routeIs('admin.dashboard') ? 'text-blue-400' : 'text-slate-500 group-hover:text-slate-300' }}"></i>
                Dashboard
            </a>

            <div class="px-2 mt-8 mb-3 text-[10px] uppercase tracking-wider font-bold text-slate-600 font-mono">Management</div>

            <a href="{{ route('admin.users.index') }}" class="{{ $navClass('admin.users.*') }} group flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all">
                <i data-lucide="users" class="w-5 h-5 transition-colors {{ request()->routeIs('admin.users.*') ? 'text-blue-400' : 'text-slate-500 group-hover:text-slate-300' }}"></i>
                Users
            </a>

            <a href="#" class="{{ $navClass('admin.disputes.*') }} group flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all">
                <i data-lucide="gavel" class="w-5 h-5 transition-colors"></i>
                Templates
            </a>

            <div class="px-2 mt-8 mb-3 text-[10px] uppercase tracking-wider font-bold text-slate-600 font-mono">Institutes</div>

            <a href="{{ route('admin.institutions.index') }}" class="{{ $navClass('admin.institutions.*') }} group flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all">
                <i data-lucide="building-2" class="w-5 h-5 transition-colors"></i>
                All Institutes
            </a>

            <a href="{{ route('admin.categories.index') }}" class="{{ $navClass('admin.categories.*') }} group flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all">
                <i data-lucide="layers" class="w-5 h-5 transition-colors"></i>
                Categories
            </a>

            <div class="px-2 mt-8 mb-3 text-[10px] uppercase tracking-wider font-bold text-slate-600 font-mono">System</div>

            {{-- <a href="#" class="{{ $navClass('admin.logs.*') }} group flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all">
                <i data-lucide="scroll-text" class="w-5 h-5 transition-colors"></i>
                Audit Logs
            </a> --}}
            
            <a href="{{ route('admin.settings.index') }}" class="{{ $navClass('admin.settings.*') }} group flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all">
                <i data-lucide="settings" class="w-5 h-5 transition-colors"></i>
                Account Settings
            </a>
        </nav>

        <div class="p-4 border-t border-white/5 bg-slate-950 shrink-0 mt-auto">
            <div class="flex items-center gap-1 p-2 rounded-xl bg-slate-900/50 border border-white/5 hover:border-white/10 transition-all">
                <a href="{{ route('admin.settings.index') }}">
                    <div class="flex items-center gap-3 flex-1 min-w-0 p-1.5">
                        <div class="relative shrink-0">
                            <div class="w-10 h-10 rounded-full bg-blue-600 flex items-center justify-center text-sm text-white font-bold ring-2 ring-transparent">
                                {{ substr(Auth::user()->name ?? 'A', 0, 2) }}
                            </div>
                            <div class="absolute bottom-0 right-0 w-3 h-3 bg-emerald-500 border-2 border-slate-900 rounded-full"></div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-white truncate">{{ Auth::user()->name ?? 'Admin' }}</p>
                            <p class="text-[10px] text-slate-500 truncate">Super Admin</p>
                        </div>
                    </div>
                </a>

                <button wire:click="logout" class="p-2.5 text-slate-500 hover:text-rose-400 hover:bg-rose-500/10 rounded-lg transition-colors" title="Log Out">
                    <i data-lucide="log-out" class="w-4.5 h-4.5"></i>
                </button>
            </div>
        </div>
    </aside>

    <div class="lg:hidden fixed top-0 left-0 right-0 h-16 bg-white border-b border-slate-200 flex items-center justify-between px-4 z-30">
        <div class="flex items-center gap-3">
            <button wire:click="toggleSidebar" class="text-slate-500 hover:text-slate-700 p-2 -ml-2 rounded-md hover:bg-slate-100 transition-colors">
                <i data-lucide="menu" class="w-6 h-6"></i>
            </button>
            <span class="font-bold text-slate-800">Admin Console</span>
        </div>
    </div>
</div>