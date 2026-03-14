@if($showModal)
    <div id="modal-overlay" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
        <div onclick="closeModalFast()" class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm animate-in fade-in"></div>
        
        <div class="relative bg-white rounded-[2rem] shadow-2xl w-full max-w-2xl flex flex-col overflow-hidden max-h-[90vh] animate-in zoom-in-95 duration-200">
            
            {{-- Header --}}
            <div class="px-8 py-6 border-b flex items-center justify-between bg-white shrink-0">
                <h2 class="text-xl font-bold text-slate-900">
                    {{ $isEditMode ? 'Edit Institute' : 'Register Institute' }}
                </h2>
                <button wire:click="closeModal" onclick="closeModalFast()" class="text-slate-400 hover:text-slate-600 transition-colors p-1">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            {{-- Body --}}
            <div class="p-8 overflow-y-auto custom-scrollbar">
                <div class="space-y-6">
                    {{-- Basic Details --}}
                    <div class="grid grid-cols-2 gap-6">
                        <div class="col-span-2">
                            <input type="text" wire:model="name" placeholder="Institute Name" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-violet-500 outline-none">
                        </div>
                        <div>
                            <select wire:model.live="institution_category_id" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-violet-500 outline-none">
                                <option value="">Select Category</option>
                                @foreach($this->categories as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <input type="email" wire:model="contact_email" placeholder="Primary Contact Email" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-violet-500 outline-none">
                        </div>
                        {{-- Popular Toggle --}}
                        <div class="col-span-2">
                            <label class="flex items-center gap-4 cursor-pointer p-4 border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors">
                                <div class="relative flex items-center">
                                    <input type="checkbox" wire:model="is_popular" class="peer sr-only">
                                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-violet-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-violet-600"></div>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-sm font-bold text-slate-800">Mark as Popular</span>
                                    <span class="text-[11px] text-slate-500">Highlight this institution in front-end user interfaces and top lists.</span>
                                </div>
                            </label>
                        </div>
                    </div>

                    {{-- Escalation Details --}}
                    <div class="bg-slate-50 p-5 rounded-xl border border-slate-100 grid grid-cols-2 gap-4">
                        <div class="col-span-2 flex items-center gap-2 mb-1">
                            <i data-lucide="shield-alert" class="w-4 h-4 text-violet-500"></i>
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">Escalation Details (Global Fallback)</span>
                        </div>
                        <div>
                            <input type="text" wire:model="escalation_contact_name" placeholder="Contact Name (e.g. Director)" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-xs focus:border-violet-500 outline-none bg-white">
                        </div>
                        <div>
                            <input type="email" wire:model="escalation_email" placeholder="Email (e.g. director@bank.com)" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-xs focus:border-violet-500 outline-none bg-white">
                        </div>
                    </div>

                    {{-- Dynamic Step Routing --}}
                    @if($institution_category_id)
                        <div class="pt-4 border-t border-slate-100">
                            <div class="flex items-center justify-between mb-4 pb-2">
                                <div class="flex items-center gap-2">
                                    <div class="bg-violet-100 p-1.5 rounded-lg text-violet-600"><i data-lucide="git-merge" class="w-4 h-4"></i></div>
                                    <h3 class="text-sm font-black text-slate-800 uppercase tracking-wider">Workflow Step Routing</h3>
                                </div>
                                
                                <button type="button" 
                                        wire:click="addContact" 
                                        wire:loading.attr="disabled"
                                        class="text-xs font-bold text-violet-600 hover:text-violet-700 bg-violet-50 hover:bg-violet-100 px-3 py-1.5 rounded-lg transition-colors flex items-center gap-1.5 shadow-sm disabled:opacity-50 disabled:cursor-wait">
                                    <span wire:loading.remove wire:target="addContact" class="flex items-center gap-1.5">
                                        <i data-lucide="plus" class="w-3 h-3"></i> Add Routing
                                    </span>
                                    <span wire:loading.flex wire:target="addContact" class="flex items-center gap-1.5">
                                        <i data-lucide="loader-2" class="w-3 h-3 animate-spin"></i> Adding...
                                    </span>
                                </button>
                            </div>

                            <div class="space-y-4">
                                @forelse($contacts as $index => $contact)
                                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-5 relative group shadow-sm transition-all hover:border-violet-200">
                                        <button type="button" 
                                                wire:click="removeContact({{ $index }})" 
                                                wire:loading.attr="disabled"
                                                class="absolute top-3 right-3 p-1.5 text-slate-300 hover:text-rose-500 hover:bg-rose-50 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-wait" 
                                                title="Remove routing">
                                            <div wire:loading.remove wire:target="removeContact({{ $index }})"><i data-lucide="trash-2" class="w-4 h-4"></i></div>
                                            <div wire:loading.flex wire:target="removeContact({{ $index }})"><i data-lucide="loader-2" class="w-4 h-4 animate-spin text-rose-500"></i></div>
                                        </button>

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 pr-6">
                                            <div>
                                                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Workflow Step</label>
                                                <select wire:model="contacts.{{ $index }}.step_key" class="w-full px-3 py-2.5 rounded-lg border border-slate-200 text-sm focus:border-violet-500 outline-none bg-white {{ $errors->has('contacts.'.$index.'.step_key') ? 'border-rose-400 bg-rose-50' : 'border-slate-200' }}">
                                                    <option value="">Select Workflow Step</option>
                                                    @foreach($this->availableSteps as $key => $label)
                                                        <option value="{{ $key }}">{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                                @error('contacts.'.$index.'.step_key') <span class="text-rose-500 text-[10px] font-bold">{{ $message }}</span> @enderror
                                            </div>

                                            <div>
                                                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Department Name</label>
                                                <input type="text" wire:model="contacts.{{ $index }}.department_name" placeholder="e.g. Appeals, BBB" class="w-full px-3 py-2.5 rounded-lg border border-slate-200 text-sm focus:border-violet-500 outline-none bg-white {{ $errors->has('contacts.'.$index.'.department_name') ? 'border-rose-400 bg-rose-50' : 'border-slate-200' }}">
                                                @error('contacts.'.$index.'.department_name') <span class="text-rose-500 text-[10px] font-bold">{{ $message }}</span> @enderror
                                            </div>

                                            <div>
                                                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Channel Type</label>
                                                <select wire:model="contacts.{{ $index }}.channel" class="w-full px-3 py-2.5 rounded-lg border border-slate-200 text-sm focus:border-violet-500 outline-none bg-white">
                                                    <option value="email">Email</option>
                                                    <option value="url">URL Link</option>
                                                </select>
                                                @error('contacts.'.$index.'.channel') <span class="text-rose-500 text-[10px] font-bold">{{ $message }}</span> @enderror
                                            </div>

                                            <div>
                                                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Email / URL Value</label>
                                                <input type="text" 
                                                    wire:model="contacts.{{ $index }}.contact_value" 
                                                    placeholder="{{ $contact['channel'] === 'email' ? 'support@bank.com' : 'https://...' }}" 
                                                    class="w-full px-3 py-2.5 rounded-lg border {{ $errors->has('contacts.'.$index.'.contact_value') ? 'border-rose-400 bg-rose-50' : 'border-slate-200' }} text-sm focus:border-violet-500 outline-none bg-white">
                                                @error('contacts.'.$index.'.contact_value') <span class="text-rose-500 text-[10px] font-bold mt-1 block">{{ $message }}</span> @enderror
                                            </div>

                                            <div class="col-span-1 md:col-span-2 pt-2">
                                                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5 flex items-center gap-1.5">
                                                    Communication Tone <span class="text-rose-500">*</span>
                                                </label>
                                                <select wire:model="contacts.{{ $index }}.tone" class="w-full px-3 py-2.5 rounded-lg border border-slate-200 text-sm focus:border-violet-500 outline-none bg-white {{ $errors->has('contacts.'.$index.'.tone') ? 'border-rose-400 bg-rose-50' : 'border-slate-200' }}">
                                                    <option value="">Select Required Tone</option>
                                                    <option value="polite">Polite</option>
                                                    <option value="firm">Firm</option>
                                                    <option value="escalation">Escalation</option>
                                                </select>
                                                @error('contacts.'.$index.'.tone') <span class="text-rose-500 text-[10px] font-bold mt-1 block">{{ $message }}</span> @enderror
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="text-center p-8 border-2 border-dashed border-slate-200 rounded-xl text-slate-500 text-sm bg-slate-50/50">
                                        <i data-lucide="split" class="w-6 h-6 mx-auto mb-2 text-slate-300"></i>
                                        No custom step routing added.<br>
                                        <span class="text-xs text-slate-400">Will default to primary/escalation emails if left empty.</span>
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    @else
                        <div class="p-4 bg-amber-50 rounded-xl border border-amber-100 text-xs text-amber-700 flex items-center gap-2">
                            <i data-lucide="info" class="w-4 h-4"></i>
                            Select an Institution Category first to configure specific workflow step routing.
                        </div>
                    @endif
                </div>
            </div>

            {{-- Footer --}}
            <div class="px-8 py-6 bg-slate-50 border-t flex justify-end gap-3 shrink-0">
                <button wire:click="closeModal" onclick="closeModalFast()" class="text-sm font-bold text-slate-400 hover:text-slate-600 transition-colors">
                    Cancel
                </button>

                <button wire:click="{{ $isEditMode ? 'update' : 'store' }}" 
                        wire:loading.attr="disabled"
                        class="min-w-[120px] px-8 py-2.5 bg-slate-900 text-white rounded-xl text-sm font-bold shadow-xl active:scale-95 transition-all flex items-center justify-center gap-2 disabled:opacity-70 disabled:cursor-not-allowed">
                    <span wire:loading.remove wire:target="{{ $isEditMode ? 'update' : 'store' }}">
                        {{ $isEditMode ? 'Save Changes' : 'Register' }}
                    </span>
                    <span wire:loading.flex wire:target="{{ $isEditMode ? 'update' : 'store' }}" class="inline-flex items-center gap-2">
                        <i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Saving...
                    </span>
                </button>
            </div>
        </div>
    </div>
@endif