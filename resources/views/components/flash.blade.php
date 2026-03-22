@if (session()->has('success') || session()->has('error') || session()->has('warning') || session()->has('info'))
    <div class="fixed bottom-4 right-4 z-[9999] flex flex-col gap-3 pointer-events-none sm:bottom-8 sm:right-8">
        
        @foreach (['success', 'error', 'warning', 'info'] as $msg)
            @if(session()->has($msg))
                <div x-data="{ show: false }"
                     x-init="
                        setTimeout(() => show = true, 50);
                        setTimeout(() => show = false, 4500);
                     "
                     x-show="show"
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="translate-y-8 opacity-0 sm:translate-y-0 sm:translate-x-8"
                     x-transition:enter-end="translate-y-0 opacity-100 sm:translate-x-0"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0 translate-y-2 sm:translate-x-8"
                     class="pointer-events-auto flex items-start gap-3 bg-white p-4 shadow-2xl shadow-slate-200/50 rounded-2xl border border-slate-100 min-w-[320px] max-w-sm transform ring-1 ring-black/5">

                    <div class="shrink-0 mt-0.5 rounded-full p-1.5
                        {{ $msg === 'success' ? 'bg-emerald-50 text-emerald-500' : '' }}
                        {{ $msg === 'error'   ? 'bg-rose-50 text-rose-500'       : '' }}
                        {{ $msg === 'warning' ? 'bg-amber-50 text-amber-500'     : '' }}
                        {{ $msg === 'info'    ? 'bg-blue-50 text-blue-500'       : '' }}">

                        @if($msg === 'success')
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                        @elseif($msg === 'error')
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
                        @elseif($msg === 'warning')
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        @else
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        @endif
                    </div>

                    <div class="flex-1">
                        <p class="text-sm font-bold text-slate-900">
                            {{ $msg === 'success' ? 'Success!' : ucfirst($msg) }}
                        </p>
                        <p class="text-xs text-slate-500 font-medium mt-1 leading-relaxed">
                            {{ session($msg) }}
                        </p>
                    </div>

                    <button @click="show = false" class="shrink-0 -mt-1 -mr-1 p-2 text-slate-400 hover:bg-slate-50 hover:text-slate-600 rounded-xl transition-colors">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
            @endif
        @endforeach
    </div>
@endif