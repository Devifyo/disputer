<div class="h-full overflow-y-auto p-6 pb-24 bg-slate-50/50">
    <x-flash />

    <div class="mb-8 flex flex-wrap items-center gap-3">
        <div class="flex-1 min-w-0">
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Airlines</h1>
            <p class="text-sm text-slate-500">The carrier directory - each airline's contact addresses per purpose, used to route claim emails at every lifecycle stage.</p>
        </div>
        <div class="relative w-72">
            <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2"></i>
            <input type="search" wire:model.live.debounce.400ms="search" placeholder="Name, IATA code or email…"
                   class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-200 bg-white shadow-sm text-sm focus:border-primary-500 outline-none">
        </div>
        <button wire:click="create" wire:loading.attr="disabled" class="px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-sm font-bold transition-colors disabled:opacity-60">
            <span wire:loading.remove wire:target="create">Add airline</span>
            <span wire:loading wire:target="create" class="inline-flex items-center gap-2"><svg class="inline w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg> Opening…</span>
        </button>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-[11px] uppercase tracking-wider text-slate-400 border-b border-slate-100">
                        <th class="px-5 py-3 font-bold">Airline</th>
                        <th class="px-5 py-3 font-bold">Contacts</th>
                        <th class="px-5 py-3 font-bold">Notes</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($airlines as $airline)
                        <tr class="border-b border-slate-50 hover:bg-slate-50/60 transition-colors {{ $airline->is_active ? '' : 'opacity-50' }}">
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-3">
                                    <span class="w-9 h-9 rounded-xl bg-slate-900 text-white flex items-center justify-center font-black text-xs shrink-0">
                                        {{ $airline->iata_code ?: strtoupper(substr($airline->name, 0, 2)) }}
                                    </span>
                                    <div>
                                        <div class="font-bold text-slate-800">{{ $airline->name }}</div>
                                        <div class="text-xs text-slate-400">
                                            {{ $airline->iata_code ? 'IATA ' . $airline->iata_code : 'no IATA code' }}
                                            · {{ $airline->workflow?->name ?? 'Default workflow' }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex flex-wrap gap-1.5">
                                    @forelse ($airline->contacts as $contact)
                                        <span class="inline-flex items-center gap-1 bg-slate-50 ring-1 ring-slate-200 text-slate-600 text-[11px] font-bold px-2 py-0.5 rounded-full" title="{{ $contact->email }}">
                                            <i data-lucide="mail" class="w-3 h-3"></i> {{ $contact->purposeLabel() }}
                                        </span>
                                    @empty
                                        <span class="text-[11px] font-bold text-amber-600 bg-amber-50 ring-1 ring-amber-200 px-2 py-0.5 rounded-full">No contacts yet</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="px-5 py-3.5 text-xs text-slate-400 max-w-[240px] truncate">{{ $airline->notes ?: '-' }}</td>
                            <td class="px-5 py-3.5 text-right whitespace-nowrap">
                                <button wire:click="edit({{ $airline->id }})" wire:loading.attr="disabled" class="text-xs font-bold text-primary-600 hover:underline mr-2">
                                    <span wire:loading.remove wire:target="edit({{ $airline->id }})">Edit</span>
                                    <span wire:loading wire:target="edit({{ $airline->id }})"><svg class="inline w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg></span>
                                </button>
                                <button @click="$dispatch('admin-confirm', { title: 'Remove airline', message: 'Delete {{ addslashes($airline->name) }}? Its contacts and templates go too. Existing claims keep the airline name they were filed under.', confirmLabel: 'Delete', danger: true, method: 'delete', params: [{{ $airline->id }}] })"
                                        class="text-xs font-bold text-rose-600 hover:underline">Delete</button>
                                <button wire:click="toggleActive({{ $airline->id }})" wire:loading.attr="disabled" class="text-xs font-bold text-amber-600 hover:underline">
                                    <span wire:loading.remove wire:target="toggleActive({{ $airline->id }})">{{ $airline->is_active ? 'Deactivate' : 'Activate' }}</span>
                                    <span wire:loading wire:target="toggleActive({{ $airline->id }})"><svg class="inline w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg></span>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-14 text-center text-sm text-slate-400">No airlines match.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($airlines->hasPages())
            <div class="px-5 py-4 border-t border-slate-100">{{ $airlines->links() }}</div>
        @endif
    </div>

    {{-- Airline form --}}
    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" wire:click="$set('showForm', false)"></div>
            <div class="relative bg-white w-full max-w-xl rounded-2xl shadow-2xl max-h-[90vh] overflow-y-auto">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between sticky top-0 bg-white z-10">
                    <h2 class="font-bold text-slate-900">{{ $editingId ? 'Edit airline' : 'New airline' }}</h2>
                    <button wire:click="$set('showForm', false)" class="p-2 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100"><i data-lucide="x" class="w-5 h-5"></i></button>
                </div>
                <div class="p-6 space-y-4">
                    <div class="grid grid-cols-[1fr_120px] gap-3">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Name *</label>
                            <input type="text" wire:model="form.name" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none">
                            @error('form.name') <p class="text-xs font-bold text-rose-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">IATA code</label>
                                <input type="text" wire:model="form.iata_code" placeholder="AC" maxlength="3" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm uppercase focus:border-primary-500 outline-none">
                                @error('form.iata_code') <p class="text-xs font-bold text-rose-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">ICAO code</label>
                                <input type="text" wire:model="form.icao_code" placeholder="ACA" maxlength="4" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm uppercase focus:border-primary-500 outline-none">
                                @error('form.icao_code') <p class="text-xs font-bold text-rose-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Country</label>
                        <input type="text" wire:model="form.country" placeholder="Canada" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none">
                        @error('form.country') <p class="text-xs font-bold text-rose-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Workflow (lifecycle)</label>
                        <select wire:model="form.claim_workflow_id" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none">
                            <option value="">Default workflow</option>
                            @foreach ($workflows as $wf)
                                <option value="{{ $wf->id }}">{{ $wf->name }}{{ $wf->is_default ? ' (default)' : '' }}</option>
                            @endforeach
                        </select>
                        <p class="text-[11px] text-slate-400 mt-1">Claims for this airline follow this lifecycle - manage lifecycles under Flight Claims → Lifecycle.</p>
                    </div>

                    <div>
                        <p class="text-[11px] font-bold text-slate-400 uppercase mb-2">Contact addresses</p>
                        <div class="space-y-2">
                            @foreach ($form['contacts'] ?? [] as $i => $contact)
                                <div class="rounded-xl border border-slate-100 bg-slate-50/50 p-3 min-w-0" wire:key="contact-{{ $i }}">
                                    @if ($contact['custom'] ?? false)
                                        <div class="flex items-center gap-2 mb-1.5">
                                            <input type="text" wire:model="form.contacts.{{ $i }}.purpose"
                                                   placeholder="Contact type, e.g. Refunds desk"
                                                   class="flex-1 min-w-0 px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-xs font-bold text-slate-600 focus:border-primary-500 outline-none">
                                            <button type="button" wire:click="removeContact({{ $i }})" title="Remove this contact"
                                                    class="p-1.5 rounded-lg text-slate-300 hover:text-rose-500 hover:bg-rose-50 transition-colors shrink-0">
                                                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                            </button>
                                        </div>
                                    @else
                                        <span class="block text-xs font-bold text-slate-600 mb-1.5">{{ $purposes[$contact['purpose']] ?? ucfirst(str_replace('_', ' ', $contact['purpose'])) }}</span>
                                    @endif
                                    <div class="grid sm:grid-cols-2 gap-2 min-w-0">
                                        <input type="email" wire:model="form.contacts.{{ $i }}.email" placeholder="email address"
                                               class="w-full min-w-0 px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm focus:border-primary-500 outline-none">
                                        <input type="text" wire:model="form.contacts.{{ $i }}.label" placeholder="label (optional)"
                                               class="w-full min-w-0 px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm focus:border-primary-500 outline-none">
                                    </div>
                                    @error('form.contacts.' . $i . '.email') <p class="text-xs font-bold text-rose-600 mt-1">{{ $message }}</p> @enderror
                                </div>
                            @endforeach
                        </div>
                        <button type="button" wire:click="addContact" wire:loading.attr="disabled"
                                class="mt-2 w-full flex items-center justify-center gap-2 border-2 border-dashed border-slate-200 hover:border-primary-300 rounded-xl px-4 py-2.5 text-xs font-bold text-slate-500 hover:text-slate-700 transition-colors">
                            <span wire:loading.remove wire:target="addContact" class="inline-flex items-center gap-2"><i data-lucide="plus" class="w-3.5 h-3.5"></i> Add another contact</span>
                            <span wire:loading wire:target="addContact"><svg class="inline w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg></span>
                        </button>
                        <p class="text-[11px] text-slate-400 mt-2">Leave an address empty to remove that contact. Lifecycle stages pick the purpose they route to (e.g. Filed → Claims department).</p>
                    </div>

                    <label class="inline-flex items-center gap-2 text-sm text-slate-700 select-none cursor-pointer">
                        <input type="checkbox" wire:model="form.is_active" class="w-4 h-4 rounded border-slate-300 text-slate-900 focus:ring-slate-900">
                        Active
                    </label>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Notes</label>
                        <textarea wire:model="form.notes" rows="2" placeholder="e.g. accepts claims via web portal only"
                                  class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none"></textarea>
                    </div>
                </div>
                <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex justify-end gap-2 sticky bottom-0">
                    <button wire:click="$set('showForm', false)" class="px-5 py-2.5 rounded-xl border border-slate-200 bg-white text-slate-700 text-sm font-bold">Cancel</button>
                    <button wire:click="save" wire:loading.attr="disabled" class="px-6 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-sm font-bold transition-colors disabled:opacity-60">
                        <span wire:loading.remove wire:target="save">Save airline</span>
                        <span wire:loading wire:target="save" class="inline-flex items-center gap-2"><svg class="inline w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg> Saving…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <x-admin.confirm />
</div>
