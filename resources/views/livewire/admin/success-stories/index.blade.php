<div class="p-6">
    <x-flash />

    {{-- SweetAlert2 Asset --}}
    @assets
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    @endassets

    {{-- Top Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Success Stories</h1>
            <p class="text-sm text-slate-500">Review, publish, and manage community success stories.</p>
        </div>
    </div>

    {{-- Filters Section --}}
    <div class="flex flex-col md:flex-row gap-4 mb-6">
        {{-- Search Input --}}
        <div class="relative flex-1 max-w-md">
            <i data-lucide="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search stories, names, or emails..." class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:border-primary-600 focus:ring-2 focus:ring-primary-600/20 outline-none transition-all shadow-sm">
        </div>
        
        {{-- Status Filter Dropdown --}}
        <div class="w-full md:w-56">
            <select wire:model.live="filterStatus" class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-medium text-slate-600 focus:border-primary-600 focus:ring-2 focus:ring-primary-600/20 outline-none transition-all shadow-sm appearance-none cursor-pointer">
                <option value="">All Stories</option>
                <option value="pending">Pending Review</option>
                <option value="published">Published</option>
            </select>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-[10px] tracking-widest">
                <tr>
                    <th class="px-6 py-4">Submitter</th>
                    <th class="px-6 py-4">Story Snippet</th>
                    <th class="px-6 py-4">Media</th>
                    <th class="px-6 py-4">Public Status</th>
                    <th class="px-6 py-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($stories as $story)
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-primary-600/10 text-primary-600 flex items-center justify-center font-bold text-xs">
                                    {{ substr($story->first_name, 0, 1) }}
                                </div>
                                <div>
                                    <div class="font-bold text-slate-900 leading-none mb-1">
                                        {{ $story->first_name }}
                                        @if($story->user_id)
                                            <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-[8px] font-bold bg-blue-100 text-blue-800" title="Registered User">USER</span>
                                        @endif
                                    </div>
                                    <div class="text-xs text-slate-400">{{ $story->email ?? 'No email provided' }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 max-w-xs">
                            <p class="text-slate-600 truncate text-xs" title="{{ $story->story }}">
                                {{ Str::limit($story->story, 60) }}
                            </p>
                            <span class="text-[10px] text-slate-400 mt-1 block">{{ $story->created_at->diffForHumans() }}</span>
                        </td>
                        <td class="px-6 py-4">
                            @if(is_array($story->media_files) && count($story->media_files) > 0)
                                <div class="flex items-center gap-1.5">
                                    <i data-lucide="paperclip" class="w-3.5 h-3.5 text-slate-400"></i>
                                    <span class="text-xs font-bold text-slate-600">{{ count($story->media_files) }} file(s)</span>
                                </div>
                            @else
                                <span class="text-slate-300 text-xs italic">None</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <button wire:click="togglePublish({{ $story->id }})" 
                                    class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none {{ $story->is_published ? 'bg-emerald-500' : 'bg-slate-300' }}"
                                    title="{{ $story->is_published ? 'Unpublish Story' : 'Publish Story' }}">
                                <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform {{ $story->is_published ? 'translate-x-4' : 'translate-x-1' }}"></span>
                            </button>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex justify-end gap-1">
                                <button wire:click="viewStory({{ $story->id }})" class="p-2 text-slate-400 hover:text-primary-600 hover:bg-primary-600/10 rounded-lg transition-all" title="Read Full Story">
                                    <i data-lucide="book-open" class="w-4 h-4"></i>
                                </button>
                                
                                {{-- Updated to use the SweetAlert JS function --}}
                                <button onclick="confirmDelete({{ $story->id }})" class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-all" title="Delete Story">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-slate-500">
                            <div class="flex flex-col items-center justify-center gap-2">
                                <i data-lucide="message-square" class="w-8 h-8 text-slate-300"></i>
                                <p class="text-sm font-medium text-slate-600">No success stories found</p>
                                <p class="text-xs text-slate-400">Try adjusting your search or filters.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if($stories->hasPages())
            <div class="p-4 border-t border-slate-50">{{ $stories->links() }}</div>
        @endif
    </div>

    {{-- INCLUDED STORY MODAL PARTIAL --}}
    @include('livewire.admin.success-stories.partials.story-modal')

    {{-- SCRIPTS --}}
    <script>
        document.addEventListener('livewire:init', () => {
           Livewire.hook('morph.updated', ({ el, component }) => {
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
           });
        });

        function confirmDelete(id) {
            Swal.fire({
                title: 'Delete this story?',
                text: "This action cannot be undone.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444', 
                cancelButtonColor: '#94a3b8',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel',
                heightAuto: false, 
                scrollbarPadding: false,
                customClass: {
                    popup: 'rounded-2xl',
                    confirmButton: 'px-6 py-2.5 rounded-xl font-bold text-sm',
                    cancelButton: 'px-6 py-2.5 rounded-xl font-bold text-sm'
                }
            }).then((result) => {
                if (result.isConfirmed) @this.call('delete', id);
            })
        }
    </script>
</div>