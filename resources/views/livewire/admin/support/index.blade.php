<div class="p-6 h-full overflow-y-auto">
    <x-flash />

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Support Inbox</h1>
            <p class="text-sm text-slate-500">Manage and respond to user inquiries.</p>
        </div>
    </div>

    {{-- Filters Section --}}
    <div class="flex flex-col md:flex-row gap-4 mb-6">
        {{-- Search Input --}}
        <div class="relative flex-1 max-w-md">
            <i data-lucide="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search names, emails, or messages..." class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:border-primary-600 focus:ring-2 focus:ring-primary-600/20 outline-none transition-all shadow-sm">
        </div>
        
        {{-- Filter Dropdown --}}
        <div class="w-full md:w-56">
            <select wire:model.live="filter" class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-medium text-slate-600 focus:border-primary-600 focus:ring-2 focus:ring-primary-600/20 outline-none transition-all shadow-sm appearance-none cursor-pointer">
                <option value="all">All Messages</option>
                <option value="unread">Unread (New)</option>
                <option value="read">Read</option>
                <option value="resolved">Resolved</option>
            </select>
        </div>
    </div>

    {{-- Messages Table --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-[10px] tracking-widest">
                    <tr>
                        <th class="px-6 py-4">Sender Details</th>
                        <th class="px-6 py-4">Message Preview</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4">Date</th>
                        <th class="px-6 py-4 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($messages as $msg)
                        <tr class="hover:bg-slate-50/50 transition-colors {{ $msg->status === 'new' ? 'bg-primary-50/10' : '' }}">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-primary-600/10 text-primary-600 flex items-center justify-center font-bold text-xs shrink-0">
                                        {{ substr($msg->name, 0, 1) }}
                                    </div>
                                    <div>
                                        <div class="font-bold {{ $msg->status === 'new' ? 'text-slate-900' : 'text-slate-700' }} leading-none mb-1">{{ $msg->name }}</div>
                                        <div class="text-xs text-slate-400">{{ $msg->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 max-w-xs truncate text-slate-600 {{ $msg->status === 'new' ? 'font-bold text-slate-900' : '' }}">
                                {{ \Illuminate\Support\Str::limit($msg->message, 60) }}
                            </td>
                            <td class="px-6 py-4">
                                @if($msg->status === 'new')
                                    <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold border bg-blue-50 text-blue-600 border-blue-200 uppercase tracking-widest">Unread</span>
                                @elseif($msg->status === 'resolved')
                                    <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold border bg-emerald-50 text-emerald-600 border-emerald-200 uppercase tracking-widest">Resolved</span>
                                @else
                                    <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold border bg-slate-50 text-slate-600 border-slate-200 uppercase tracking-widest">Read</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-slate-500 text-xs whitespace-nowrap">
                                {{ $msg->created_at->format('M d, Y h:i A') }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-1">
                                    <button wire:click="openMessage({{ $msg->id }})" title="View Message" class="p-2 text-slate-400 hover:text-primary-600 hover:bg-primary-600/10 rounded-lg transition-all">
                                        <i data-lucide="eye" class="w-4 h-4"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-slate-500">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <i data-lucide="inbox" class="w-8 h-8 text-slate-300"></i>
                                    <p class="text-sm font-medium text-slate-600">No messages found</p>
                                    <p class="text-xs text-slate-400">Try adjusting your search or filters.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($messages->hasPages())
            <div class="p-4 border-t border-slate-50">{{ $messages->links() }}</div>
        @endif
    </div>

    {{-- Message Reader Modal (Updated to match the sleek User Management modal design) --}}
    @if($showModal && $selectedMessage)
        <div class="fixed inset-0 z-[100] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm animate-in fade-in" wire:click="closeMessage"></div>
            
            <div class="relative bg-white rounded-[2rem] shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col overflow-hidden animate-in zoom-in-95">
                
                {{-- Modal Header --}}
                <div class="px-8 py-6 border-b flex items-center justify-between bg-white shrink-0">
                    <h2 class="text-xl font-bold text-slate-900">Message Details</h2>
                    <button wire:click="closeMessage" class="text-slate-400 hover:text-rose-500 p-1.5 rounded-lg hover:bg-rose-50 transition-colors group">
                        <i data-lucide="x" class="w-5 h-5 group-hover:rotate-90 transition-transform"></i>
                    </button>
                </div>

                {{-- Modal Body --}}
                <div class="p-8 overflow-y-auto">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-8 bg-slate-50 p-6 rounded-2xl border border-slate-100">
                        <div>
                            <span class="block text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-2">From</span>
                            <div class="font-bold text-slate-900">{{ $selectedMessage->name }}</div>
                            <a href="mailto:{{ $selectedMessage->email }}" class="text-sm text-primary-600 hover:underline break-all">{{ $selectedMessage->email }}</a>
                        </div>
                        <div>
                            <span class="block text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-2">Date Sent</span>
                            <div class="text-sm font-medium text-slate-700">{{ $selectedMessage->created_at->format('F d, Y \a\t h:i A') }}</div>
                            <div class="text-xs text-slate-400 mt-1">{{ $selectedMessage->created_at->diffForHumans() }}</div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <span class="block text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-3">Message</span>
                        <div class="bg-white border border-slate-200 p-6 rounded-2xl text-slate-700 whitespace-pre-wrap leading-relaxed text-sm">
                            {{ $selectedMessage->message }}
                        </div>
                    </div>
                </div>

                {{-- Modal Footer --}}
                <div class="px-8 py-6 bg-slate-50 border-t flex flex-col-reverse sm:flex-row justify-between items-center gap-3 shrink-0">
                    <a href="mailto:{{ $selectedMessage->email }}" class="w-full sm:w-auto justify-center inline-flex items-center gap-2 px-5 py-2.5 bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 hover:text-slate-900 rounded-xl text-sm font-bold transition-all shadow-sm">
                        <i data-lucide="reply" class="w-4 h-4"></i>
                        Reply via Email
                    </a>
                    
                    <div class="flex gap-3 w-full sm:w-auto">
                        <button wire:click="closeMessage" class="flex-1 sm:flex-none text-sm font-bold text-slate-400 hover:text-slate-600 px-4 py-2.5 transition-colors">
                            Close
                        </button>
                        
                        @if($selectedMessage->status !== 'resolved')
                            <button wire:click="markAsResolved({{ $selectedMessage->id }})" class="flex-1 sm:flex-none justify-center inline-flex items-center gap-2 px-6 py-2.5 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-sm font-bold transition-all shadow-xl active:scale-95">
                                <i data-lucide="check-circle" class="w-4 h-4"></i>
                                Mark Resolved
                            </button>
                        @endif
                    </div>
                </div>

            </div>
        </div>
    @endif
    
    {{-- Persistent Icon Fix (Matching the first template) --}}
    <script>
        document.addEventListener('livewire:init', () => {
           Livewire.hook('morph.updated', ({ el, component }) => {
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
           });
        });
    </script>
</div>