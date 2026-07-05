<aside 
    class="fixed inset-y-0 left-0 z-50 w-72 bg-slate-950 text-slate-400 flex flex-col transition-transform duration-300 ease-in-out lg:static lg:translate-x-0 border-r border-white/5"
    :class="sidebarOpen ? 'translate-x-0 shadow-2xl' : '-translate-x-full'"
>
    <div class="h-20 flex items-center px-6 border-b border-white/5 bg-slate-950 shrink-0">
        <div class="flex items-center gap-3 text-white">
            <span class="flex items-center justify-center shrink-0" style="color:#3FCB94">
                <svg viewBox="0 0 32 32" width="30" height="30" fill="none" aria-hidden="true"><path d="M5 23.5 L16 6 L27 23.5" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"></path><path d="M11 23.5 L16 15.5 L21 23.5" stroke="currentColor" stroke-opacity="0.4" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"></path><circle cx="16" cy="26" r="2.1" fill="currentColor"></circle></svg>
            </span>
            <div>
                <span class="font-bold tracking-tight text-lg block leading-none">{{ config('app.name') }}</span>
                <span class="text-[10px] text-slate-500 font-mono">Flight Compensation</span>
            </div>
        </div>
        <button @click="sidebarOpen = false" class="lg:hidden ml-auto text-slate-400 hover:text-white">
            <i data-lucide="x" class="w-6 h-6"></i>
        </button>
    </div>

    <nav class="flex-1 px-4 py-6 space-y-1 overflow-y-auto sidebar-scroll">
        <div class="px-2 mb-3 text-[10px] uppercase tracking-wider font-bold text-slate-600 font-mono">Workspace</div>

        @php
            if (!function_exists('navClass')) {
                function navClass($route) {
                    return request()->routeIs($route) 
                        ? 'bg-blue-600/10 text-blue-400 shadow-[inset_3px_0_0_0_#2563eb]' 
                        : 'hover:bg-white/5 hover:text-slate-200 text-slate-400';
                }
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

        <a href="{{ route('user.itineraries.index') }}" class="{{ navClass('user.itineraries.*') }} group flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all">
            <i data-lucide="plane" class="w-5 h-5 transition-colors {{ request()->routeIs('user.itineraries.*') ? 'text-blue-400' : 'text-slate-500 group-hover:text-slate-300' }}"></i>
            Flight Disputes
        </a>

        <a href="{{ route('user.cases.index') }}" class="{{ navClass('user.cases.*') }} group flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all">
            <i data-lucide="folder-kanban" class="w-5 h-5 transition-colors {{ request()->routeIs('user.cases.*') ? 'text-blue-400' : 'text-slate-500 group-hover:text-slate-300' }}"></i>
            My {{ __('common.cases') }}
        </a>

        <div class="px-2 mt-8 mb-3 text-[10px] uppercase tracking-wider font-bold text-slate-600 font-mono">Tools</div>

        @if(Auth::user()->canCreateCase())
            <a href="{{ route('user.templates.index') }}" class="{{ navClass('user.templates.*') }} group flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all">
                <i data-lucide="file-text" class="w-5 h-5 transition-colors {{ request()->routeIs('user.templates.*') ? 'text-blue-400' : 'text-slate-500 group-hover:text-slate-300' }}"></i>
                Cases Email Templates
            </a>
        @else
            <div x-data="{ open: false }">
                <button @click="open = true" class="w-full hover:bg-white/5 hover:text-slate-200 text-slate-400 group flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all">
                    <i data-lucide="file-text" class="w-5 h-5 text-slate-500 group-hover:text-slate-300 transition-colors"></i>
                    <span class="flex-1 text-left">Cases Email Templates</span>
                    <i data-lucide="lock" class="w-3.5 h-3.5 text-slate-600 group-hover:text-amber-400 transition-colors shrink-0"></i>
                </button>

                {{-- Subscription required popup --}}
                <div x-show="open" style="display:none" class="fixed inset-0 z-[999] flex items-center justify-center p-4"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     x-transition:leave="transition ease-in duration-150"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0">

                    <div class="absolute inset-0 bg-slate-900/70 backdrop-blur-sm" @click="open = false"></div>

                    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6 flex flex-col items-center text-center ring-1 ring-slate-900/5"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100">

                        <div class="w-14 h-14 rounded-full bg-amber-50 border border-amber-100 flex items-center justify-center mb-4">
                            <i data-lucide="lock" class="w-6 h-6 text-amber-500"></i>
                        </div>

                        <h3 class="text-slate-900 font-bold text-lg mb-1">Subscription Required</h3>
                        <p class="text-slate-500 text-sm leading-relaxed mb-6">
                            Access to <span class="font-semibold text-slate-700">Cases Email Templates</span> requires an active subscription or available cases.
                        </p>

                        <div class="flex flex-col gap-2 w-full">
                            <a href="{{ route('profile.edit') }}#billing"
                               class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-bold transition-all shadow-lg shadow-blue-500/20 flex items-center justify-center gap-2">
                                <i data-lucide="credit-card" class="w-4 h-4"></i>
                                View Plans
                            </a>
                            <button @click="open = false" class="w-full py-2.5 text-slate-500 hover:text-slate-700 text-sm font-medium transition-colors">
                                Maybe later
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- <a href="{{ route('user.emails.index') }}" class="{{ navClass('user.emails.*') }} group flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all justify-between">
            <div class="flex items-center gap-3">
                <i data-lucide="mail" class="w-5 h-5 transition-colors {{ request()->routeIs('user.emails.*') ? 'text-blue-400' : 'text-slate-500 group-hover:text-slate-300' }}"></i>
                Mailbox
            </div>
        </a> --}}
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