<div class="overflow-x-auto transition-opacity duration-200" 
     wire:loading.class="opacity-50" 
     wire:target="search, filterCategory, filterStatus, filterPopular, gotoPage, nextPage, previousPage">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-[10px] tracking-widest whitespace-nowrap">
            <tr>
                <th class="px-6 py-4">Name</th>
                <th class="px-6 py-4">Category</th>
                <th class="px-6 py-4">Verification</th>
                <th class="px-6 py-4">System Institute</th>
                <th class="px-6 py-4 text-right">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse($institutions as $inst)
                <tr class="hover:bg-slate-50/50 transition-colors">
                    <td class="px-6 py-4">
                        <div class="font-bold text-slate-900 flex items-center gap-2">
                            {{ $inst->name }}
                            @if($inst->is_popular)
                                <i data-lucide="star" class="w-3.5 h-3.5 text-amber-400 fill-amber-400" title="Popular Institute"></i>
                            @endif
                        </div>
                        <div class="text-xs text-slate-400">{{ $inst->contact_email }}</div>
                    </td>
                    <td class="px-6 py-4">
                        <span class="px-2.5 py-1 rounded-md text-[10px] font-bold bg-slate-100 text-slate-600 border border-slate-200">
                            {{ $inst->category->name ?? 'N/A' }}
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        <button wire:click="toggleVerified({{ $inst->id }})" 
                                wire:loading.attr="disabled"
                                class="flex items-center gap-1.5 px-2 py-1 rounded-full text-[10px] font-bold border transition-all disabled:opacity-70 disabled:cursor-not-allowed {{ $inst->is_verified ? 'bg-emerald-50 text-emerald-700 border-emerald-100' : 'bg-slate-50 text-slate-500 border-slate-200' }}">
                            <span wire:loading.remove wire:target="toggleVerified({{ $inst->id }})" class="inline-flex items-center gap-1.5">
                                <i data-lucide="{{ $inst->is_verified ? 'check-circle-2' : 'circle-dashed' }}" class="w-3 h-3"></i>
                                {{ $inst->is_verified ? 'Verified' : 'Unverified' }}
                            </span>
                            <span wire:loading.flex wire:target="toggleVerified({{ $inst->id }})" class="inline-flex items-center gap-1.5">
                                <i data-lucide="loader-2" class="w-3 h-3 animate-spin"></i> Updating...
                            </span>
                        </button>
                    </td>
                    <td class="px-6 py-4">
                        @if($inst->is_internal)
                            <div class="flex flex-col gap-1">
                                <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-md text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-100 w-fit">
                                    <i data-lucide="shield-check" class="w-3 h-3 text-indigo-500"></i>
                                    System Internal
                                </span>
                                @if($inst->creator)
                                    <span class="text-[9px] text-slate-400 pl-1">
                                        By {{ $inst->creator->name }}
                                    </span>
                                @endif
                            </div>
                        @else
                            <span class="text-[10px] font-bold text-slate-300 uppercase tracking-widest pl-2">External</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex justify-end gap-1">
                            <button wire:click="edit({{ $inst->id }})" 
                                    class="p-2 text-slate-400 hover:text-blue-600 rounded-lg transition-all disabled:opacity-50">
                                <div wire:loading.remove wire:target="edit({{ $inst->id }})"><i data-lucide="edit-3" class="w-4 h-4"></i></div>
                                <div wire:loading.flex wire:target="edit({{ $inst->id }})"><i data-lucide="loader-2" class="w-4 h-4 animate-spin text-blue-600"></i></div>
                            </button>
                            <button onclick="confirmDelete({{ $inst->id }})" class="p-2 text-slate-400 hover:text-rose-600 rounded-lg transition-all">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-6 py-12 text-center text-slate-500">No institutes found matching your filters.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@if($institutions->hasPages())
    <div class="p-4 border-t border-slate-50">{{ $institutions->links() }}</div>
@endif