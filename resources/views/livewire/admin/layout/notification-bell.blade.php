<div class="relative" @click.outside="$wire.open && $wire.toggle()">
    <button wire:click="toggle" class="relative p-2 text-slate-500 hover:text-slate-200 hover:bg-white/5 rounded-xl transition-all" title="Notifications">
        <i data-lucide="bell" class="w-4 h-4"></i>
        @if ($unread)
            <span class="absolute -top-0.5 -right-0.5 min-w-[16px] h-4 px-1 rounded-full bg-rose-500 text-white text-[9px] font-black flex items-center justify-center">{{ $unread > 9 ? '9+' : $unread }}</span>
        @endif
    </button>

    @if ($open)
        <div class="absolute bottom-12 left-0 w-80 bg-white rounded-2xl border border-slate-200 shadow-2xl z-50 overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-100 text-sm font-bold text-slate-900">Notifications</div>
            <ul class="max-h-80 overflow-y-auto divide-y divide-slate-50">
                @forelse ($notifications as $notification)
                    <li>
                        <a href="{{ $notification->data['claim_url'] ?? '#' }}" class="block px-4 py-3 hover:bg-slate-50 transition-colors">
                            <span class="block text-[13px] font-bold text-slate-800">{{ $notification->data['title'] ?? 'Update' }}</span>
                            <span class="block text-[12px] text-slate-500 mt-0.5">{{ $notification->data['description'] ?? '' }}</span>
                            <span class="block text-[10px] text-slate-400 mt-1">{{ $notification->created_at->diffForHumans() }}</span>
                        </a>
                    </li>
                @empty
                    <li class="px-4 py-8 text-center text-[12px] text-slate-400">Nothing yet - payment and payout events land here.</li>
                @endforelse
            </ul>
        </div>
    @endif
</div>
