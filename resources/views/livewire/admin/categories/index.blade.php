<div class="h-full overflow-y-auto p-6 pb-24 relative">
    <x-flash />

    {{-- SweetAlert2 Asset --}}
    @assets
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    @endassets

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Categories & Workflows</h1>
            <p class="text-sm text-slate-500">Manage institute categories and their specific dispute resolution workflows.</p>
        </div>
        
        <button wire:click="create" 
                wire:loading.attr="disabled"
                class="bg-slate-900 hover:bg-slate-800 text-white px-3 sm:px-4 py-2 rounded-lg text-xs font-bold shadow-lg shadow-slate-900/20 transition-all flex items-center gap-2 hover:scale-105 active:scale-95">
            <span wire:loading.remove wire:target="create" class="inline-flex items-center gap-2">
                <i data-lucide="plus" class="w-4 h-4"></i> Add Category
            </span>
            <span wire:loading.flex wire:target="create" class="inline-flex items-center gap-2">
                <i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Opening...
            </span>
        </button>
    </div>

    {{-- Search --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm flex flex-col overflow-hidden mb-6">
        <div class="p-4 bg-slate-50/50 border-b border-slate-100 flex flex-col md:flex-row gap-4">
            <div class="relative flex-1 max-w-md">
                <div class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">
                    <i data-lucide="search" class="w-4 h-4" wire:loading.remove wire:target="search"></i>
                    <i data-lucide="loader-2" class="w-4 h-4 animate-spin text-primary-600" wire:loading wire:target="search"></i>
                </div>
                <input type="text" wire:model.live.debounce.250ms="search" 
                       placeholder="Search categories..." 
                       class="w-full pl-10 pr-4 py-2 bg-white border border-slate-200 rounded-lg text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500/10 outline-none transition-all">
            </div>
        </div>
    
        {{-- Table --}}
        <div class="overflow-x-auto transition-opacity duration-200" wire:loading.class="opacity-50" wire:target="search, gotoPage, nextPage, previousPage">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-[10px] tracking-widest whitespace-nowrap">
                    <tr>
                        <th class="px-6 py-4">Category Details</th>
                        <th class="px-6 py-4">Workflow Steps</th>
                        <th class="px-6 py-4">Fallback Escalation</th>
                        <th class="px-6 py-4">Status & Usage</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($categories as $category)
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="font-bold text-slate-900">{{ $category->name }}</div>
                                <div class="text-[10px] text-slate-400 font-mono mt-0.5">{{ $category->slug }}</div>
                            </td>
                            <td class="px-6 py-4">
                                @php
                                    $stepCount = is_array($category->workflow_config) && isset($category->workflow_config['steps']) 
                                        ? count($category->workflow_config['steps']) 
                                        : 0;
                                @endphp
                                <span class="flex items-center gap-1.5 text-xs font-bold {{ $stepCount > 0 ? 'text-primary-600' : 'text-slate-400' }}">
                                    <i data-lucide="git-merge" class="w-3.5 h-3.5"></i> 
                                    {{ $stepCount }} {{ Str::plural('Step', $stepCount) }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                @if($category->fallback_escalation_email)
                                    <div class="text-[10px] text-primary-600 underline decoration-primary-600/30">
                                        {{ $category->fallback_escalation_email }}
                                    </div>
                                @else
                                    <span class="text-slate-300 text-xs italic">Not Set</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-col gap-1">
                                    <span class="flex items-center gap-1 text-[10px] font-bold uppercase {{ $category->is_verified ? 'text-emerald-600' : 'text-slate-400' }}">
                                        <i data-lucide="{{ $category->is_verified ? 'check-circle' : 'circle' }}" class="w-3 h-3"></i>
                                        {{ $category->is_verified ? 'Verified' : 'Unverified' }}
                                    </span>
                                    <span class="text-[10px] text-slate-500 font-medium">
                                        Used by {{ $category->institutions_count }} {{ Str::plural('Institute', $category->institutions_count) }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-1">
                                    {{-- Edit Button with Loader --}}
                                    <button wire:click="edit({{ $category->id }})" 
                                            wire:loading.attr="disabled"
                                            class="p-2 text-slate-400 hover:text-primary-600 rounded-lg transition-all disabled:opacity-50">
                                        <i data-lucide="edit-3" class="w-4 h-4" wire:loading.remove wire:target="edit({{ $category->id }})"></i>
                                        <i data-lucide="loader-2" class="w-4 h-4 animate-spin text-primary-600" wire:loading wire:target="edit({{ $category->id }})"></i>
                                    </button>
                                    <button onclick="confirmDelete({{ $category->id }})" class="p-2 text-slate-400 hover:text-rose-600 rounded-lg transition-all">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-12 text-center text-slate-500">No categories found matching your search.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($categories->hasPages())
            <div class="p-4 border-t border-slate-50 bg-slate-50">{{ $categories->links() }}</div>
        @endif
    </div>

    {{-- MODAL (Alpine Powered) --}}
    <div x-data="{ modalOpen: @entangle('showModal') }" 
         x-show="modalOpen" 
         x-cloak
         class="fixed inset-0 z-[100] flex items-center justify-center p-4">
        
        <div x-on:click="modalOpen = false" x-transition.opacity class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm"></div>
        
        <div x-show="modalOpen"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             class="relative bg-white rounded-[2rem] shadow-2xl w-full max-w-5xl flex flex-col overflow-hidden h-[90vh]">
            
            {{-- Header --}}
            <div class="px-8 py-6 border-b flex items-center justify-between bg-white shrink-0">
                <h2 class="text-xl font-bold text-slate-900">
                    {{ $isEditMode ? 'Edit Category & Workflow' : 'Create Category' }}
                </h2>
                <button x-on:click="modalOpen = false" class="text-slate-400 hover:text-slate-600 transition-colors p-1">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            {{-- Body (Split into 2 Columns on Large Screens) --}}
            <div class="p-0 overflow-hidden flex-1 flex flex-col lg:flex-row bg-slate-50/50">
                
                {{-- Left Side: Basic Info --}}
                <div class="p-8 lg:w-1/3 border-r border-slate-200 bg-white overflow-y-auto custom-scrollbar">
                    <div class="space-y-6">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-400 uppercase mb-2">Category Name <span class="text-rose-500">*</span></label>
                            <input type="text" wire:model.blur="name" placeholder="e.g. Banking" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none">
                            @error('name') <span class="text-rose-500 text-[10px] font-bold">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-slate-400 uppercase mb-2">URL Slug <span class="text-rose-500">*</span></label>
                            <input type="text" wire:model.blur="slug" placeholder="e.g. banking" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm font-mono text-slate-500 focus:border-primary-500 outline-none">
                            @error('slug') <span class="text-rose-500 text-[10px] font-bold">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-slate-400 uppercase mb-2">Fallback Escalation Email</label>
                            <input type="email" wire:model.blur="fallback_escalation_email" placeholder="Used if institute is missing one" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none">
                            @error('fallback_escalation_email') <span class="text-rose-500 text-[10px] font-bold">{{ $message }}</span> @enderror
                        </div>

                        <div class="flex items-center pt-2">
                            <label class="flex items-center gap-2 cursor-pointer group">
                                <div class="relative flex items-center">
                                    <input type="checkbox" wire:model="is_verified" class="peer appearance-none w-5 h-5 border-2 border-slate-300 rounded checked:bg-primary-600 checked:border-primary-600 transition-all">
                                    <i data-lucide="check" class="absolute w-3.5 h-3.5 text-white opacity-0 peer-checked:opacity-100 left-1 transition-all pointer-events-none"></i>
                                </div>
                                <span class="text-sm font-bold text-slate-600 group-hover:text-slate-800 transition-colors">Verified Category</span>
                            </label>
                        </div>
                    </div>
                </div>

                {{-- Right Side: Visual Workflow Builder --}}
                <div class="lg:w-2/3 flex flex-col bg-slate-100 overflow-hidden border-l border-slate-200">
                    
                    {{-- Builder Header --}}
                    <div class="px-6 py-4 bg-white border-b border-slate-200 shrink-0 flex items-center justify-between shadow-sm z-10">
                        <div>
                            <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                                <i data-lucide="git-merge" class="w-4 h-4 text-primary-600"></i> Workflow Builder
                            </h3>
                            <p class="text-[10px] text-slate-500 mt-0.5">Define the state machine steps and actions.</p>
                        </div>
                        <div class="flex items-center gap-2 relative">
                            <label class="text-[11px] font-bold text-slate-500 uppercase">Initial Step <span class="text-rose-500">*</span></label>
                            <select wire:model="initial_step" class="px-3 py-1.5 rounded-lg border {{ $errors->has('initial_step') ? 'border-rose-400 ring-2 ring-rose-500/20' : 'border-slate-200' }} text-xs focus:border-primary-500 outline-none w-48 font-mono text-primary-600 cursor-pointer">
                                <option value="">-- Select Start Step --</option>
                                @foreach($workflow_steps as $step)
                                    @if(!empty($step['step_key']))
                                        <option value="{{ $step['step_key'] }}">{{ $step['step_key'] }}</option>
                                    @endif
                                @endforeach
                            </select>
                            
                            @error('initial_step') 
                                <div class="absolute top-full right-0 mt-1 bg-rose-500 text-white text-[10px] font-bold px-2 py-1 rounded shadow-lg whitespace-nowrap z-50 animate-in fade-in slide-in-from-top-1">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>
                    </div>

                    {{-- Builder Body (Scrollable) --}}
                    <div class="p-6 overflow-y-auto flex-1 custom-scrollbar space-y-4">
                        
                        @forelse($workflow_steps as $index => $step)
                            {{--  CHANGED: wire:key now uses the unique UUID from the PHP component --}}
                            <div wire:key="step-{{ $step['id'] ?? $index }}" x-data="{ expanded: true }" class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden transition-all {{ $errors->has('workflow_steps.'.$index.'.step_key') ? 'ring-2 ring-rose-500' : '' }}">
                                
                                {{-- Step Card Header (Click to expand) --}}
                                <div class="px-5 py-3 bg-slate-50 border-b border-slate-100 flex items-center justify-between cursor-pointer hover:bg-slate-100 transition-colors" x-on:click="expanded = !expanded">
                                    <div class="flex items-center gap-3">
                                        <div class="w-6 h-6 rounded bg-white border border-slate-200 flex items-center justify-center text-slate-400">
                                            <i data-lucide="{{ $step['icon'] ?: 'file' }}" class="w-3.5 h-3.5"></i>
                                        </div>
                                        <div>
                                            <h4 class="text-sm font-bold text-slate-800">{{ $step['label'] ?: 'Unnamed Step' }}</h4>
                                            <div class="text-[10px] font-mono {{ $errors->has('workflow_steps.'.$index.'.step_key') ? 'text-rose-500 font-bold' : 'text-primary-500' }}">
                                                {{ $step['step_key'] ?: 'key_pending' }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span class="px-2 py-0.5 rounded text-[9px] font-bold uppercase border border-slate-200 text-slate-500 bg-white">
                                            {{ count($step['actions']) }} Actions
                                        </span>
                                        {{-- Remove Step Button: Disabled if only 1 step remains --}}
                                        <button wire:click.stop="removeStep({{ $index }})" 
                                                wire:loading.attr="disabled"
                                                @if(count($workflow_steps) <= 1) disabled title="Cannot delete the last step" @endif
                                                class="p-1.5 text-slate-400 hover:text-rose-500 hover:bg-rose-50 rounded-md transition-colors disabled:opacity-30 disabled:cursor-not-allowed">
                                            <span wire:loading.remove wire:target="removeStep({{ $index }})">
                                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                                            </span>
                                            <span wire:loading wire:target="removeStep({{ $index }})">
                                                <i data-lucide="loader-2" class="w-4 h-4 animate-spin text-rose-500"></i>
                                            </span>
                                        </button>
                                        <i data-lucide="chevron-down" class="w-4 h-4 text-slate-400 transition-transform" x-bind:class="expanded ? 'rotate-180' : ''"></i>
                                    </div>
                                </div>

                                {{-- Step Card Body (Expanded) --}}
                                <div x-show="expanded" x-collapse x-cloak>
                                    <div class="p-5 space-y-5">
                                        
                                        {{-- Row 1: Core details --}}
                                        <div class="grid grid-cols-12 gap-4">
                                            {{-- <div class="col-span-12 sm:col-span-4 relative">
                                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Step Key (ID) <span class="text-rose-500">*</span></label>
                                                <input type="text" wire:model.live.debounce.500ms="workflow_steps.{{ $index }}.step_key" placeholder="e.g. awaiting_reply" class="w-full px-3 py-2 rounded-lg border {{ $errors->has('workflow_steps.'.$index.'.step_key') ? 'border-rose-400 bg-rose-50' : 'border-slate-200' }} text-xs font-mono focus:border-primary-500 outline-none">
                                                @error('workflow_steps.'.$index.'.step_key') <p class="text-[9px] text-rose-500 mt-1 font-bold absolute">{{ $message }}</p> @enderror
                                            </div> --}}
                                            {{--  step key --}}
                                            <div class="col-span-12 sm:col-span-4 relative">
                                                <div class="flex items-center justify-between mb-1">
                                                    <label class="block text-[10px] font-bold text-slate-500 uppercase">Step Key (ID) <span class="text-rose-500">*</span></label>
                                                    
                                                    {{-- Lock Icon & Guide Message Tooltip (Only shows on existing steps) --}}
                                                    @if($isEditMode && !isset($step['is_new']))
                                                        <div class="group/tooltip relative flex items-center cursor-help">
                                                            <i data-lucide="lock" class="w-3 h-3 text-slate-400 hover:text-primary-600 transition-colors"></i>
                                                            
                                                            {{-- Tooltip Bubble --}}
                                                            <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-48 p-2 bg-slate-800 text-white text-[9px] rounded-lg shadow-xl opacity-0 invisible group-hover/tooltip:opacity-100 group-hover/tooltip:visible transition-all z-50 text-center leading-relaxed pointer-events-none">
                                                                Keys are locked to prevent breaking active disputes and routing links.
                                                                <div class="absolute -bottom-1 left-1/2 -translate-x-1/2 border-4 border-transparent border-t-slate-800"></div>
                                                            </div>
                                                        </div>
                                                    @endif
                                                </div>
                                                
                                                <input type="text" 
                                                    wire:model.live.debounce.500ms="workflow_steps.{{ $index }}.step_key" 
                                                    placeholder="e.g. awaiting_reply" 
                                                    
                                                    {{-- If editing an existing step, make it read-only and look locked --}}
                                                    @if($isEditMode && !isset($step['is_new'])) 
                                                        readonly 
                                                        class="w-full px-3 py-2 rounded-lg border border-slate-200 bg-slate-50 text-slate-400 text-xs font-mono cursor-not-allowed outline-none select-none"
                                                    
                                                    {{-- Otherwise, standard editable input --}}
                                                    @else
                                                        class="w-full px-3 py-2 rounded-lg border {{ $errors->has('workflow_steps.'.$index.'.step_key') ? 'border-rose-400 bg-rose-50' : 'border-slate-200 bg-white' }} text-xs font-mono focus:border-primary-500 outline-none"
                                                    @endif
                                                >
                                                
                                                @error('workflow_steps.'.$index.'.step_key') 
                                                    <p class="text-[9px] text-rose-500 mt-1 font-bold absolute">{{ $message }}</p> 
                                                @enderror
                                            </div>
                                            {{-- end step key--}}
                                            <div class="col-span-12 sm:col-span-4">
                                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Display Label <span class="text-rose-500">*</span></label>
                                                <input type="text" wire:model="workflow_steps.{{ $index }}.label" placeholder="e.g. Awaiting Reply" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-xs focus:border-primary-500 outline-none">
                                            </div>
                                            <div class="col-span-6 sm:col-span-2">
                                                {{-- Added Lucide Reference Link --}}
                                                <div class="flex items-center justify-between mb-1">
                                                    <label class="block text-[10px] font-bold text-slate-500 uppercase">Icon</label>
                                                    <a href="https://lucide.dev/icons" target="_blank" title="Browse available icons" class="text-[9px] text-primary-500 hover:text-primary-700 font-bold flex items-center gap-0.5">
                                                        View <i data-lucide="external-link" class="w-2.5 h-2.5"></i>
                                                    </a>
                                                </div>
                                                <input type="text" wire:model="workflow_steps.{{ $index }}.icon" placeholder="e.g. file, user" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-xs focus:border-primary-500 outline-none">
                                            </div>
                                            <div class="col-span-6 sm:col-span-2">
                                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Color</label>
                                                <select wire:model="workflow_steps.{{ $index }}.status_color" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-xs focus:border-primary-500 outline-none">
                                                    <option value="slate">Slate</option><option value="amber">Amber</option>
                                                    <option value="emerald">Emerald</option><option value="blue">Blue</option>
                                                    <option value="purple">Purple</option><option value="rose">Rose</option>
                                                </select>
                                            </div>
                                        </div>

                                        {{-- Row 2: Description --}}
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Description</label>
                                            <input type="text" wire:model="workflow_steps.{{ $index }}.description" placeholder="Describe what happens in this step..." class="w-full px-3 py-2 rounded-lg border border-slate-200 text-xs focus:border-primary-500 outline-none">
                                        </div>

                                        {{-- Row 3: Logic Configuration --}}
                                        <div class="p-4 bg-slate-50 rounded-lg border border-slate-100 grid grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Who are we waiting for?</label>
                                                <input type="text" wire:model="workflow_steps.{{ $index }}.waiting_for" placeholder="e.g. Airline, Bank, User" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-xs focus:border-primary-500 outline-none bg-white">
                                            </div>
                                            <div class="flex items-end pb-2">
                                                <label class="flex items-center gap-2 cursor-pointer">
                                                    <input type="checkbox" wire:model="workflow_steps.{{ $index }}.is_final" class="rounded text-primary-600 focus:ring-primary-500 w-4 h-4 border-slate-300">
                                                    <span class="text-xs font-bold text-slate-700">This is a Final Step (Resolution/Closed)</span>
                                                </label>
                                            </div>
                                        </div>

                                        {{-- Sub-section: Actions --}}
                                        <div>
                                            <div class="flex items-center justify-between mb-2">
                                                <h5 class="text-xs font-bold text-slate-800">Possible Actions from here:</h5>
                                                <button wire:click="addAction({{ $index }})" 
                                                        wire:loading.attr="disabled"
                                                        class="text-[10px] font-bold text-primary-600 hover:text-primary-700 disabled:opacity-50">
                                                    
                                                    {{-- Default State --}}
                                                    <span wire:loading.remove wire:target="addAction({{ $index }})" class="inline-flex items-center gap-1">
                                                        <i data-lucide="plus" class="w-3 h-3"></i> Add Action
                                                    </span>
                                                    
                                                    {{-- Loading State (Notice the .flex modifier here) --}}
                                                    <span wire:loading.flex wire:target="addAction({{ $index }})" class="items-center gap-1">
                                                        <i data-lucide="loader-2" class="w-3 h-3 animate-spin"></i> Adding...
                                                    </span>
                                                    
                                                </button>
                                            </div>
                                            
                                            <div class="space-y-3">
                                                @forelse($step['actions'] as $aIndex => $action)
                                                    {{--  CHANGED: wire:key now uses the unique UUID from the PHP component --}}
                                                    <div wire:key="action-{{ $action['id'] ?? $aIndex }}" class="flex flex-col gap-1">
                                                        <div class="flex gap-2 items-center bg-white p-2 rounded-lg border {{ $errors->has('workflow_steps.'.$index.'.actions.'.$aIndex.'.to_step') ? 'border-rose-400 ring-1 ring-rose-500/20' : 'border-slate-200' }} shadow-sm">
                                                            <i data-lucide="corner-down-right" class="w-4 h-4 text-slate-300 shrink-0"></i>
                                                            
                                                            <input type="text" wire:model="workflow_steps.{{ $index }}.actions.{{ $aIndex }}.label" placeholder="Button Label" class="w-1/3 px-2 py-1.5 rounded border border-slate-200 text-xs outline-none focus:border-primary-500">
                                                            
                                                            <i data-lucide="arrow-right" class="w-3 h-3 text-slate-300 shrink-0"></i>
                                                            
                                                            <select wire:model="workflow_steps.{{ $index }}.actions.{{ $aIndex }}.to_step" class="flex-1 px-2 py-1.5 rounded border border-slate-200 text-xs font-mono outline-none focus:border-primary-500 cursor-pointer">
                                                                <option value="">-- Select Target Step --</option>
                                                                @foreach($workflow_steps as $targetStep)
                                                                    @if(!empty($targetStep['step_key']))
                                                                        <option value="{{ $targetStep['step_key'] }}">{{ $targetStep['step_key'] }}</option>
                                                                    @endif
                                                                @endforeach
                                                            </select>
                                                            
                                                            <button wire:click="removeAction({{ $index }}, {{ $aIndex }})" 
                                                                    wire:loading.attr="disabled"
                                                                    class="p-1 text-slate-400 hover:text-rose-500 disabled:opacity-50 shrink-0">
                                                                <span wire:loading.remove wire:target="removeAction({{ $index }}, {{ $aIndex }})"><i data-lucide="x" class="w-4 h-4"></i></span>
                                                                <span wire:loading wire:target="removeAction({{ $index }}, {{ $aIndex }})"><i data-lucide="loader-2" class="w-4 h-4 animate-spin text-rose-500"></i></span>
                                                            </button>
                                                        </div>
                                                        
                                                        @error('workflow_steps.'.$index.'.actions.'.$aIndex.'.label') <div class="text-[9px] text-rose-500 font-bold px-1">{{ $message }}</div> @enderror
                                                        @error('workflow_steps.'.$index.'.actions.'.$aIndex.'.to_step') <div class="text-[9px] text-rose-500 font-bold px-1">{{ $message }}</div> @enderror
                                                    </div>
                                                @empty
                                                    <div class="text-xs text-slate-400 italic bg-white p-2 border border-dashed border-slate-200 rounded">No actions defined. This step leads nowhere.</div>
                                                @endforelse
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-10 bg-white rounded-xl border border-dashed border-slate-300">
                                <p class="text-sm text-slate-500 mb-2">No workflow steps defined.</p>
                                @error('workflow_steps') <p class="text-rose-500 font-bold text-xs">{{ $message }}</p> @enderror
                            </div>
                        @endforelse

                        <button wire:click="addStep" 
                                wire:loading.attr="disabled"
                                class="w-full py-4 border-2 border-dashed border-slate-300 rounded-xl text-sm font-bold text-slate-500 hover:border-primary-500 hover:text-primary-600 hover:bg-primary-50 transition-all flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-wait">
                            <span wire:loading.remove wire:target="addStep" class="inline-flex items-center gap-2">
                                <i data-lucide="plus-circle" class="w-5 h-5"></i> Add New Step
                            </span>
                            <span wire:loading.flex wire:target="addStep" class="inline-flex items-center gap-2 text-primary-600">
                                <i data-lucide="loader-2" class="w-5 h-5 animate-spin"></i> Appending Step...
                            </span>
                        </button>

                    </div>
                </div>
            </div>

            {{-- Footer --}}
            <div class="px-8 py-5 bg-white border-t flex justify-end gap-3 shrink-0">
                <button x-on:click="modalOpen = false" class="text-sm font-bold text-slate-400 hover:text-slate-600 transition-colors">
                    Cancel
                </button>
                <button wire:click="{{ $isEditMode ? 'update' : 'store' }}" 
                        wire:loading.attr="disabled"
                        class="min-w-[150px] px-8 py-2.5 bg-primary-600 text-white rounded-xl text-sm font-bold shadow-xl shadow-primary-600/20 active:scale-95 transition-all flex items-center justify-center gap-2 disabled:opacity-70 disabled:cursor-not-allowed hover:bg-primary-700">
                    
                    <span wire:loading.remove wire:target="store, update">
                        {{ $isEditMode ? 'Save Category' : 'Create Category' }}
                    </span>
                    <span wire:loading.flex wire:target="store, update" class="inline-flex items-center gap-2">
                        <i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Saving...
                    </span>
                </button>
            </div>
        </div>
    </div>

    {{-- SCRIPTS --}}
    <script>
        document.addEventListener('livewire:init', () => {
           Livewire.hook('morph.updated', ({ el, component }) => {
                if (typeof lucide !== 'undefined') lucide.createIcons();
           });
        });

        function confirmDelete(id) {
            Swal.fire({
                title: 'Move to Trash?',
                text: "This category will be deleted.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#0f172a', 
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
                if (result.isConfirmed) @this.call('deleteConfirmed', id);
            })
        }
    </script>
</div>