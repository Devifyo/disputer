<div class="h-full overflow-y-auto p-6 pb-24 bg-slate-50/50"
     x-data="{
        dragging: null,
        drop(target) {
            if (this.dragging === null || this.dragging === target) return;
            const rows = Array.from(document.querySelectorAll('[data-stage-id]'));
            const ids = rows.map(r => r.dataset.stageId);
            const from = ids.indexOf(String(this.dragging));
            const to = ids.indexOf(String(target));
            ids.splice(to, 0, ids.splice(from, 1)[0]);
            this.dragging = null;
            $wire.reorder(ids);
        }
     }">
    <x-flash />

    <div class="mb-8 flex flex-wrap items-center gap-3">
        <div class="flex-1 min-w-0">
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Lifecycle Management</h1>
            <p class="text-sm text-slate-500">The stages every claim can pass through - reorder by dragging, configure transitions, timers, visibility and automation. New stages need no code changes.</p>
        </div>
        <button wire:click="$set('showPreview', true)" class="px-4 py-2.5 rounded-xl border border-slate-200 bg-white text-slate-700 text-sm font-bold hover:border-slate-300 transition-colors">Preview workflow</button>
        <button wire:click="create" wire:loading.attr="disabled" class="px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-sm font-bold transition-colors disabled:opacity-60">
            <span wire:loading.remove wire:target="create">Add stage</span>
            <span wire:loading wire:target="create" class="inline-flex items-center gap-2"><svg class="inline w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg> Opening…</span>
        </button>
    </div>

    {{-- Workflow switcher: one lifecycle per airline, default for the rest --}}
    <div class="flex flex-wrap items-center gap-2 mb-4">
        @foreach ($workflows as $wf)
            <button wire:click="switchWorkflow({{ $wf->id }})"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-bold border transition-all {{ $workflowId === $wf->id ? 'bg-slate-900 text-white border-slate-900 shadow' : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300' }}">
                {{ $wf->name }}
                @if ($wf->is_default)
                    <span class="text-[9px] font-black px-1.5 py-0.5 rounded {{ $workflowId === $wf->id ? 'bg-white/20' : 'bg-blue-100 text-blue-700' }}">DEFAULT</span>
                @endif
                @if ($wf->airlines_count)
                    <span class="text-[10px] font-black px-1.5 py-0.5 rounded-full {{ $workflowId === $wf->id ? 'bg-white/20' : 'bg-slate-100 text-slate-500' }}">{{ $wf->airlines_count }} airline{{ $wf->airlines_count > 1 ? 's' : '' }}</span>
                @endif
            </button>
        @endforeach
        <button wire:click="$set('showWorkflowForm', true)"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-sm font-bold border border-dashed border-slate-300 text-slate-500 hover:border-slate-400 hover:text-slate-700 transition-colors">
            <i data-lucide="plus" class="w-4 h-4"></i> New workflow
        </button>
        @if ($workflow && !$workflow->is_default)
            <span class="ml-auto flex items-center gap-2">
                <button wire:click="setDefaultWorkflow({{ $workflowId }})" class="text-xs font-bold text-blue-600 hover:underline">Make default</button>
                <button @click="$dispatch('admin-confirm', {
                            title: 'Delete workflow',
                            message: 'Delete this workflow? Attached airlines fall back to the default lifecycle.',
                            confirmLabel: 'Delete',
                            danger: true,
                            method: 'deleteWorkflow',
                            params: [{{ $workflowId }}],
                        })" class="text-xs font-bold text-rose-600 hover:underline">Delete</button>
            </span>
        @endif
    </div>
    @if ($workflow?->description)
        <p class="text-xs text-slate-400 mb-4 -mt-2">{{ $workflow->description }}</p>
    @endif

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-[11px] uppercase tracking-wider text-slate-400 border-b border-slate-100">
                        <th class="px-5 py-3 font-bold w-8"></th>
                        <th class="px-5 py-3 font-bold">Stage</th>
                        <th class="px-5 py-3 font-bold">Customer sees</th>
                        <th class="px-5 py-3 font-bold">Next stages</th>
                        <th class="px-5 py-3 font-bold">Automation</th>
                        <th class="px-5 py-3 font-bold">Flags</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($stages as $stage)
                        <tr data-stage-id="{{ $stage->id }}" draggable="true"
                            @dragstart="dragging = {{ $stage->id }}" @dragover.prevent @drop="drop({{ $stage->id }})"
                            class="border-b border-slate-50 hover:bg-slate-50/60 transition-colors {{ $stage->is_active ? '' : 'opacity-50' }}"
                            :class="dragging === {{ $stage->id }} ? 'opacity-40' : ''">
                            <td class="px-5 py-3.5 cursor-grab text-slate-300"><i data-lucide="grip-vertical" class="w-4 h-4"></i></td>
                            <td class="px-5 py-3.5">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold ring-1 {{ $stage->badgeClasses() }}">
                                    <i data-lucide="{{ $stage->icon }}" class="w-3.5 h-3.5"></i> {{ $stage->name }}
                                </span>
                                <div class="text-[11px] text-slate-400 mt-1">{{ $stage->key }}</div>
                            </td>
                            <td class="px-5 py-3.5 text-xs text-slate-500 max-w-[220px]">
                                {{ $stage->customer_visible ? ($stage->customer_label ?: $stage->name) : '- hidden -' }}
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex flex-wrap gap-1">
                                    @forelse ($stage->next_stages ?? [] as $next)
                                        <span class="text-[10px] font-bold bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded">{{ \App\Models\ClaimLifecycleStage::byKey($next, $workflowId)?->name ?? $next }}</span>
                                    @empty
                                        <span class="text-[10px] text-slate-300 font-bold">{{ $stage->is_final ? 'FINAL' : '-' }}</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="px-5 py-3.5 text-xs text-slate-500">
                                @if ($stage->auto_next_stage)
                                    {{ $stage->auto_delay_days === 0 ? 'Instant' : $stage->auto_delay_days . 'd timer' }} → {{ \App\Models\ClaimLifecycleStage::byKey($stage->auto_next_stage, $workflowId)?->name }}
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex flex-wrap gap-1">
                                    @if ($stage->is_system)<span class="text-[9px] font-black bg-slate-900 text-white px-1.5 py-0.5 rounded">SYSTEM</span>@endif
                                    @if ($stage->is_initial)<span class="text-[9px] font-black bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded">INITIAL</span>@endif
                                    @if ($stage->is_final)<span class="text-[9px] font-black bg-slate-200 text-slate-600 px-1.5 py-0.5 rounded">FINAL</span>@endif
                                    @if ($stage->allow_manual)<span class="text-[9px] font-black bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded">MANUAL</span>@endif
                                    @if ($stage->allow_auto)<span class="text-[9px] font-black bg-violet-100 text-violet-700 px-1.5 py-0.5 rounded">AUTO</span>@endif
                                    @if ($stage->notify_admin)<span class="text-[9px] font-black bg-rose-100 text-rose-700 px-1.5 py-0.5 rounded">ALERTS ADMIN</span>@endif
                                </div>
                            </td>
                            <td class="px-5 py-3.5 text-right whitespace-nowrap">
                                <button wire:click="edit({{ $stage->id }})" wire:loading.attr="disabled" class="text-xs font-bold text-primary-600 hover:underline mr-2">
                                    <span wire:loading.remove wire:target="edit({{ $stage->id }})">Edit</span>
                                    <span wire:loading wire:target="edit({{ $stage->id }})"><svg class="inline w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg></span>
                                </button>
                                @unless ($stage->is_system)
                                    <button wire:click="toggleActive({{ $stage->id }})" wire:loading.attr="disabled" class="text-xs font-bold text-amber-600 hover:underline mr-2">
                                        <span wire:loading.remove wire:target="toggleActive({{ $stage->id }})">{{ $stage->is_active ? 'Deactivate' : 'Activate' }}</span>
                                        <span wire:loading wire:target="toggleActive({{ $stage->id }})"><svg class="inline w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg></span>
                                    </button>
                                    <button @click="$dispatch('admin-confirm', {
                                                title: 'Delete stage',
                                                message: 'Delete this stage? Claims currently in it keep the raw key until moved.',
                                                confirmLabel: 'Delete',
                                                danger: true,
                                                method: 'delete',
                                                params: [{{ $stage->id }}],
                                            })" class="text-xs font-bold text-rose-600 hover:underline">Delete</button>
                                @endunless
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Stage form --}}
    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" wire:click="$set('showForm', false)"></div>
            <div class="relative bg-white w-full max-w-2xl rounded-2xl shadow-2xl max-h-[90vh] overflow-y-auto">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between sticky top-0 bg-white z-10">
                    <h2 class="font-bold text-slate-900">{{ $editingId ? 'Edit stage' : 'New stage' }}</h2>
                    <button wire:click="$set('showForm', false)" class="p-2 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100"><i data-lucide="x" class="w-5 h-5"></i></button>
                </div>
                <div class="p-6 grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Display name *</label>
                        <input type="text" wire:model="form.name" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none">
                        @error('form.name') <p class="text-xs font-bold text-rose-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Internal name * <span class="normal-case font-medium">(a-z, 0-9, _)</span></label>
                        <input type="text" wire:model="form.key" @if ($editingId && \App\Models\ClaimLifecycleStage::find($editingId)?->is_system) disabled @endif
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none disabled:bg-slate-50 disabled:text-slate-400">
                        @error('form.key') <p class="text-xs font-bold text-rose-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Description</label>
                        <textarea wire:model="form.description" rows="2" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none"></textarea>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Color</label>
                        <div class="flex gap-1.5">
                            @foreach (array_keys($colors) as $color)
                                <button type="button" wire:click="$set('form.color', '{{ $color }}')"
                                        class="w-8 h-8 rounded-full ring-2 transition-all {{ $colors[$color] }} {{ ($form['color'] ?? '') === $color ? 'ring-slate-900 scale-110' : 'ring-transparent' }}"></button>
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Icon (lucide name)</label>
                        <input type="text" wire:model="form.icon" placeholder="e.g. send, scale, gavel" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none">
                    </div>
                    <div class="sm:col-span-2 grid sm:grid-cols-2 gap-x-6 gap-y-2">
                        @foreach ([
                            'is_active' => 'Active', 'is_initial' => 'Initial stage', 'is_final' => 'Final stage',
                            'customer_visible' => 'Customer visible', 'admin_visible' => 'Admin visible',
                            'allow_manual' => 'Allow manual transition (admin)', 'allow_auto' => 'Allow automatic transition (system)',
                            'notify_admin' => 'Notify administrators on entry', 'notify_customer' => 'Notify customer on entry',
                        ] as $flag => $label)
                            <label class="inline-flex items-center gap-2 text-sm text-slate-700 select-none cursor-pointer">
                                <input type="checkbox" wire:model="form.{{ $flag }}" class="w-4 h-4 rounded border-slate-300 text-slate-900 focus:ring-slate-900">
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Customer label (simplified)</label>
                        <input type="text" wire:model="form.customer_label" placeholder="What the customer's timeline shows" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Auto delay (days)</label>
                            <input type="number" min="0" max="365" wire:model="form.auto_delay_days" placeholder="e.g. 30" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Auto move to</label>
                            <select wire:model="form.auto_next_stage" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none">
                                <option value="">- none -</option>
                                @foreach ($stages as $s)
                                    @if ($s->id !== $editingId)<option value="{{ $s->key }}">{{ $s->name }}</option>@endif
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">AI action on entry (draft only)</label>
                        <select wire:model="form.ai_action" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none">
                            <option value="">- none -</option>
                            <option value="airline_claim">Draft the airline claim</option>
                            <option value="follow_up">Draft a follow-up</option>
                            <option value="regulator_complaint">Draft a regulator complaint</option>
                        </select>
                        <p class="text-[11px] text-slate-400 mt-1">Prepares a draft for admin review - nothing is ever sent automatically.</p>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Airline contact for emails</label>
                        <select wire:model="form.airline_contact_purpose" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none">
                            <option value="">- none -</option>
                            @foreach (\App\Models\AirlineContact::PURPOSES as $purposeKey => $purposeLabel)
                                <option value="{{ $purposeKey }}">{{ $purposeLabel }}</option>
                            @endforeach
                        </select>
                        <p class="text-[11px] text-slate-400 mt-1">Outbound emails at this stage route to this contact from the Airline Directory.</p>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Required roles (manual entry)</label>
                        <input type="text" wire:model="form.permissions" placeholder="e.g. admin (comma-separated, empty = any admin)"
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none">
                    </div>
                    @if ($editingId)
                        @php
                            $editKey  = \App\Models\ClaimLifecycleStage::find($editingId)?->key;
                            $previous = $stages->filter(fn ($s) => in_array($editKey, $s->next_stages ?? [], true))->pluck('name');
                        @endphp
                        <div class="sm:col-span-2">
                            <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Allowed previous stages (derived)</label>
                            <p class="text-sm text-slate-500">{{ $previous->isNotEmpty() ? $previous->implode(', ') : 'None - nothing currently transitions here.' }}</p>
                        </div>
                    @endif
                    <div class="sm:col-span-2">
                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Allowed next stages</label>
                        <div class="grid sm:grid-cols-3 gap-1.5">
                            @foreach ($stages as $s)
                                @if ($s->id !== $editingId)
                                    <label class="inline-flex items-center gap-2 text-sm text-slate-700 select-none cursor-pointer">
                                        <input type="checkbox" wire:model="form.next_stages" value="{{ $s->key }}" class="w-4 h-4 rounded border-slate-300 text-slate-900 focus:ring-slate-900">
                                        {{ $s->name }}
                                    </label>
                                @endif
                            @endforeach
                        </div>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Notes</label>
                        <textarea wire:model="form.notes" rows="2" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none"></textarea>
                    </div>
                </div>
                <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex justify-end gap-2 sticky bottom-0">
                    <button wire:click="$set('showForm', false)" class="px-5 py-2.5 rounded-xl border border-slate-200 bg-white text-slate-700 text-sm font-bold">Cancel</button>
                    <button wire:click="save" wire:loading.attr="disabled" class="px-6 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-sm font-bold transition-colors disabled:opacity-60">
                        <span wire:loading.remove wire:target="save">Save stage</span>
                        <span wire:loading wire:target="save" class="inline-flex items-center gap-2"><svg class="inline w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg> Saving…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- New workflow form --}}
    @if ($showWorkflowForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" wire:click="$set('showWorkflowForm', false)"></div>
            <div class="relative bg-white w-full max-w-md rounded-2xl shadow-2xl p-6">
                <h2 class="font-bold text-slate-900 mb-1">New workflow</h2>
                <p class="text-xs text-slate-400 mb-4">Starts as a copy of the default lifecycle - then customise its stages and attach airlines to it.</p>
                <div class="space-y-3">
                    <input type="text" wire:model="workflowForm.name" placeholder="Workflow name, e.g. Air Canada process *"
                           class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none">
                    @error('workflowForm.name') <p class="text-xs font-bold text-rose-600">{{ $message }}</p> @enderror
                    <textarea wire:model="workflowForm.description" rows="2" placeholder="Description (optional)"
                              class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none"></textarea>
                </div>
                <div class="flex justify-end gap-2 mt-4">
                    <button wire:click="$set('showWorkflowForm', false)" class="px-5 py-2.5 rounded-xl border border-slate-200 bg-white text-slate-700 text-sm font-bold">Cancel</button>
                    <button wire:click="createWorkflow" wire:loading.attr="disabled" class="px-5 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-sm font-bold transition-colors disabled:opacity-60">
                        <span wire:loading.remove wire:target="createWorkflow">Create workflow</span>
                        <span wire:loading wire:target="createWorkflow" class="inline-flex items-center gap-2"><svg class="inline w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg> Creating…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Workflow preview --}}
    @if ($showPreview)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" wire:click="$set('showPreview', false)"></div>
            <div class="relative bg-white w-full max-w-2xl rounded-2xl shadow-2xl max-h-[90vh] flex flex-col overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between gap-3 shrink-0">
                    <div>
                        <h2 class="font-bold text-slate-900">Workflow preview</h2>
                        <p class="text-xs text-slate-400 mt-0.5">
                            {{ $workflows->firstWhere('id', $workflowId)?->name }} ·
                            {{ $stages->where('is_active', true)->count() }} active stages · a claim travels top to bottom
                        </p>
                    </div>
                    <button wire:click="$set('showPreview', false)" class="p-2 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors"><i data-lucide="x" class="w-5 h-5"></i></button>
                </div>
                <div class="p-6 overflow-y-auto">
                    <ol class="relative">
                        <span class="absolute left-5 top-3 bottom-3 w-px bg-slate-200" aria-hidden="true"></span>
                        @foreach ($stages->where('is_active', true) as $stage)
                            <li class="relative pl-14 {{ $loop->last ? '' : 'pb-4' }}">
                                <span class="absolute left-0 top-0 w-10 h-10 rounded-xl flex items-center justify-center ring-1 {{ $stage->badgeClasses() }}">
                                    <i data-lucide="{{ $stage->icon }}" class="w-[18px] h-[18px]"></i>
                                </span>
                                <div class="rounded-xl border border-slate-200 px-4 py-3 hover:border-slate-300 transition-colors">
                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                        <span class="font-bold text-slate-900 text-sm">{{ $stage->name }}</span>
                                        @if ($stage->is_initial)
                                            <span class="text-[9px] font-black uppercase tracking-wider px-1.5 py-0.5 rounded bg-slate-900 text-white">Start</span>
                                        @endif
                                        @if ($stage->is_final)
                                            <span class="text-[9px] font-black uppercase tracking-wider px-1.5 py-0.5 rounded bg-slate-100 text-slate-500 ring-1 ring-slate-200">End</span>
                                        @endif
                                        @if ($stage->is_system)
                                            <i data-lucide="lock" class="w-3 h-3 text-slate-300" title="System stage"></i>
                                        @endif
                                        @if ($stage->auto_next_stage)
                                            <span class="inline-flex items-center gap-1 text-[10px] font-bold text-amber-700 bg-amber-50 ring-1 ring-amber-200 px-2 py-0.5 rounded-full">
                                                <i data-lucide="timer" class="w-3 h-3"></i>
                                                {{ $stage->auto_delay_days === 0 ? 'auto-advances immediately' : "auto-advances after {$stage->auto_delay_days} days" }}
                                            </span>
                                        @endif
                                    </div>
                                    @if ($stage->description)
                                        <p class="text-xs text-slate-500 mt-1 leading-relaxed">{{ $stage->description }}</p>
                                    @endif
                                    @php
                                        $meta = collect([
                                            $stage->customer_visible
                                                ? ['eye', 'Customer sees: "' . ($stage->customer_label ?: $stage->name) . '"']
                                                : ['eye-off', 'Hidden from customer'],
                                            $stage->notify_admin ? ['bell-ring', 'Alerts admins'] : null,
                                            $stage->notify_customer ? ['mail', 'Emails customer'] : null,
                                            $stage->ai_action ? ['sparkles', 'AI drafts: ' . str_replace('_', ' ', $stage->ai_action)] : null,
                                            $stage->airline_contact_purpose ? ['at-sign', 'Routes to airline "' . $stage->airline_contact_purpose . '" contact'] : null,
                                        ])->filter();
                                    @endphp
                                    <div class="flex flex-wrap gap-1.5 mt-2">
                                        @foreach ($meta as [$icon, $label])
                                            <span class="inline-flex items-center gap-1 text-[10px] font-bold text-slate-500 bg-slate-50 ring-1 ring-slate-200 px-2 py-0.5 rounded-full">
                                                <i data-lucide="{{ $icon }}" class="w-3 h-3"></i> {{ $label }}
                                            </span>
                                        @endforeach
                                    </div>
                                    @if (!empty($stage->next_stages))
                                        <div class="flex flex-wrap items-center gap-1.5 mt-2.5 pt-2.5 border-t border-slate-100">
                                            <span class="text-[9px] uppercase tracking-wider font-black text-slate-400">Next</span>
                                            @foreach ($stage->next_stages as $nextKey)
                                                @php $next = \App\Models\ClaimLifecycleStage::byKey($nextKey, $workflowId); @endphp
                                                @if ($next)
                                                    <span class="inline-flex items-center gap-1 text-[11px] font-bold px-2 py-0.5 rounded-full ring-1
                                                        {{ $stage->auto_next_stage === $nextKey ? 'bg-amber-50 text-amber-700 ring-amber-200' : 'bg-white text-slate-600 ring-slate-200' }}">
                                                        <i data-lucide="corner-down-right" class="w-3 h-3"></i>
                                                        {{ $next->name }}
                                                        @if ($stage->auto_next_stage === $nextKey)
                                                            <i data-lucide="timer" class="w-3 h-3"></i>
                                                        @endif
                                                    </span>
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </div>
        </div>
    @endif

    <x-admin.confirm />
</div>
