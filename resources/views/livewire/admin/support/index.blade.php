<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 h-full overflow-y-auto">
    
    {{-- Header --}}
    <div class="mb-8 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Support Inbox</h1>
            <p class="text-sm text-slate-500 mt-1">Manage and respond to user inquiries.</p>
        </div>
    </div>

    {{-- Filters & Search --}}
    <div class="bg-white p-4 rounded-xl shadow-sm ring-1 ring-slate-900/5 mb-6 flex flex-col sm:flex-row gap-4 justify-between">
        <div class="relative max-w-md w-full">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400">
                <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
            </svg>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search names, emails, or messages..." 
                   class="w-full pl-9 pr-4 py-2 border border-slate-200 rounded-lg text-sm focus:border-primary-500 focus:ring-primary-500">
        </div>
        
        <div class="w-full sm:w-48 shrink-0">
            <select wire:model.live="filter" class="w-full py-2 px-3 border border-slate-200 rounded-lg text-sm focus:border-primary-500 focus:ring-primary-500 bg-white">
                <option value="all">All Messages</option>
                <option value="unread">Unread (New)</option>
                <option value="read">Read</option>
                <option value="resolved">Resolved</option>
            </select>
        </div>
    </div>

    {{-- Messages Table --}}
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-900/5 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 border-b border-slate-200 text-slate-500">
                    <tr>
                        <th class="px-4 sm:px-6 py-3 font-semibold">Sender</th>
                        <th class="hidden sm:table-cell px-6 py-3 font-semibold">Message Preview</th>
                        <th class="px-4 sm:px-6 py-3 font-semibold">Status</th>
                        <th class="hidden md:table-cell px-6 py-3 font-semibold">Date</th>
                        <th class="px-4 sm:px-6 py-3 font-semibold text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($messages as $msg)
                        <tr class="hover:bg-slate-50 transition-colors {{ $msg->status === 'new' ? 'bg-blue-50/30' : '' }}">
                            <td class="px-4 sm:px-6 py-4">
                                <div class="font-bold {{ $msg->status === 'new' ? 'text-slate-900' : 'text-slate-700' }}">{{ $msg->name }}</div>
                                <div class="text-xs text-slate-500 truncate max-w-[120px] sm:max-w-none">{{ $msg->email }}</div>
                            </td>
                            <td class="hidden sm:table-cell px-6 py-4 max-w-[150px] md:max-w-xs truncate text-slate-600 {{ $msg->status === 'new' ? 'font-medium text-slate-800' : '' }}">
                                {{ \Illuminate\Support\Str::limit($msg->message, 50) }}
                            </td>
                            <td class="px-4 sm:px-6 py-4">
                                @if($msg->status === 'new')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-700 uppercase tracking-wider">Unread</span>
                                @elseif($msg->status === 'resolved')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-700 uppercase tracking-wider">Resolved</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-600 uppercase tracking-wider">Read</span>
                                @endif
                            </td>
                            <td class="hidden md:table-cell px-6 py-4 text-slate-500 text-xs whitespace-nowrap">
                                {{ $msg->created_at->format('M d, Y h:i A') }}
                            </td>
                            <td class="px-4 sm:px-6 py-4 text-right">
                                {{-- NEW ICON-ONLY BUTTON --}}
                                <button wire:click="openMessage({{ $msg->id }})" title="View Message" class="p-2 text-slate-400 hover:text-primary-600 hover:bg-primary-600/10 rounded-lg transition-all inline-flex items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                                        <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-slate-500">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-12 h-12 mx-auto text-slate-300 mb-3">
                                    <polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>
                                </svg>
                                <p>No messages found matching your criteria.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($messages->hasPages())
            <div class="px-6 py-4 border-t border-slate-200">
                {{ $messages->links() }}
            </div>
        @endif
    </div>

    {{-- Message Reader Modal --}}
    @if($showModal && $selectedMessage)
        <div class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" wire:click="closeMessage"></div>
            
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col overflow-hidden">
                
                {{-- Modal Header --}}
                <div class="px-4 sm:px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50 shrink-0">
                    <h3 class="font-bold text-lg text-slate-900">Message Details</h3>
                    <button wire:click="closeMessage" class="text-slate-400 hover:text-rose-500 p-1.5 rounded-lg hover:bg-rose-50 transition-colors group">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 group-hover:rotate-90 transition-transform">
                            <path d="M18 6 6 18"/><path d="m6 6 12 12"/>
                        </svg>
                    </button>
                </div>

                {{-- Modal Body --}}
                <div class="p-4 sm:p-6 overflow-y-auto">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6 bg-slate-50 p-4 rounded-xl border border-slate-100">
                        <div>
                            <span class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">From</span>
                            <div class="font-bold text-slate-900">{{ $selectedMessage->name }}</div>
                            <a href="mailto:{{ $selectedMessage->email }}" class="text-sm text-primary-600 hover:underline break-all">{{ $selectedMessage->email }}</a>
                        </div>
                        <div>
                            <span class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Date Sent</span>
                            <div class="text-sm text-slate-700">{{ $selectedMessage->created_at->format('F d, Y \a\t h:i A') }}</div>
                            <div class="text-xs text-slate-500 mt-0.5">{{ $selectedMessage->created_at->diffForHumans() }}</div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <span class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Message</span>
                        <div class="bg-white border border-slate-200 p-4 rounded-xl text-slate-700 whitespace-pre-wrap leading-relaxed text-sm sm:text-base">
                            {{ $selectedMessage->message }}
                        </div>
                    </div>
                </div>

                {{-- Modal Footer --}}
                <div class="px-4 sm:px-6 py-4 border-t border-slate-100 bg-slate-50/50 flex flex-col-reverse sm:flex-row justify-between items-center gap-3 shrink-0">
                    <a href="mailto:{{ $selectedMessage->email }}" class="w-full sm:w-auto justify-center inline-flex items-center gap-2 px-4 py-2 bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 hover:text-slate-900 rounded-lg text-sm font-bold transition-all shadow-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                            <polyline points="9 17 4 12 9 7"/><path d="M20 18v-2a4 4 0 0 0-4-4H4"/>
                        </svg>
                        Reply via Email
                    </a>
                    
                    <div class="flex gap-3 w-full sm:w-auto">
                        <button wire:click="closeMessage" class="flex-1 sm:flex-none justify-center inline-flex items-center gap-2 px-4 py-2 text-slate-600 hover:text-slate-900 bg-slate-100 sm:bg-transparent hover:bg-slate-200 sm:hover:bg-slate-100 rounded-lg text-sm font-bold transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                                <path d="M18 6 6 18"/><path d="m6 6 12 12"/>
                            </svg>
                            Close
                        </button>
                        
                        @if($selectedMessage->status !== 'resolved')
                            <button wire:click="markAsResolved({{ $selectedMessage->id }})" class="flex-1 sm:flex-none justify-center inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-bold transition-all shadow-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                                    <polyline points="20 6 9 17 4 12"/>
                                </svg>
                                Mark Resolved
                            </button>
                        @endif
                    </div>
                </div>

            </div>
        </div>
    @endif
</div>