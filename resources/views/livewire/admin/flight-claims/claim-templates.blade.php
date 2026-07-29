<div class="h-full overflow-y-auto bg-slate-50/50">
    <div class="max-w-[1320px] mx-auto p-6 pb-24">
        <x-flash />

        <div class="flex items-start justify-between gap-4 flex-wrap mb-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Claim Templates</h1>
                <p class="text-sm text-slate-500 mt-1">Airline letters admins can send as-is - and the base the AI drafts from, so each airline keeps its own wording.</p>
            </div>
            <button wire:click="create" class="inline-flex items-center gap-2 bg-slate-900 hover:bg-slate-800 text-white text-sm font-bold px-5 py-2.5 rounded-xl transition-colors">
                <i data-lucide="file-plus-2" class="w-4 h-4"></i> New template
            </button>
        </div>

        <div class="flex items-center gap-2 flex-wrap mb-4">
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Template, subject or airline…"
                   class="w-64 px-3.5 py-2 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none">
            <select wire:model.live="airlineFilter" class="px-3 py-2 rounded-xl border border-slate-200 text-sm outline-none">
                <option value="all">All airlines</option>
                @foreach ($airlines as $airline) <option value="{{ $airline->id }}">{{ $airline->name }}</option> @endforeach
            </select>
            <select wire:model.live="typeFilter" class="px-3 py-2 rounded-xl border border-slate-200 text-sm outline-none">
                <option value="all">All types</option>
                @foreach ($types as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
            </select>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            @if ($templates->isEmpty())
                <p class="px-6 py-12 text-sm text-slate-400 text-center">No templates yet. Create one per airline and letter type - the AI will use it as its base.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-[11px] uppercase tracking-wider text-slate-400 border-b border-slate-100">
                                <th class="px-4 py-3 font-bold">Template</th>
                                <th class="px-4 py-3 font-bold">Applies to</th>
                                <th class="px-4 py-3 font-bold">Type</th>
                                <th class="px-4 py-3 font-bold">Subject</th>
                                <th class="px-4 py-3 font-bold">Status</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            @foreach ($templates as $template)
                                <tr class="hover:bg-slate-50/70 transition-colors">
                                    <td class="px-4 py-3.5">
                                        <span class="font-bold text-slate-800">{{ $template->name }}</span>
                                        @if ($template->is_default)
                                            <span class="ml-1.5 text-[9px] font-black bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded-full">DEFAULT</span>
                                        @endif
                                        <p class="text-[11px] text-slate-400">{{ $template->author?->name ?? 'system' }} · {{ $template->updated_at->format('d M Y') }}</p>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        @if ($template->appliesToAll())
                                            <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-black bg-sky-100 text-sky-700">ALL AIRLINES</span>
                                        @else
                                            <span class="text-slate-600 text-[13px]" title="{{ $template->airlines->pluck('name')->implode(', ') }}">{{ $template->reachLabel() }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-black bg-slate-100 text-slate-600">{{ strtoupper($template->typeLabel()) }}</span>
                                    </td>
                                    <td class="px-4 py-3.5 text-[12px] text-slate-500 max-w-xs truncate">{{ $template->subject }}</td>
                                    <td class="px-4 py-3.5">
                                        <button wire:click="toggleActive({{ $template->id }})"
                                                class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-black transition-colors {{ $template->is_active ? 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200' : 'bg-slate-100 text-slate-500 hover:bg-slate-200' }}">
                                            {{ $template->is_active ? 'ACTIVE' : 'DISABLED' }}
                                        </button>
                                    </td>
                                    <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                        {{-- Icon actions: inline SVG so they render regardless of script timing --}}
                                        <span class="inline-flex items-center gap-0.5">
                                            <button wire:click="$set('previewId', {{ $template->id }})" title="Preview"
                                                    class="p-2 rounded-lg text-slate-400 hover:text-slate-800 hover:bg-slate-100 transition-colors">
                                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                                            </button>
                                            <button wire:click="edit({{ $template->id }})" title="Edit"
                                                    class="p-2 rounded-lg text-slate-400 hover:text-primary-600 hover:bg-primary-50 transition-colors">
                                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                                            </button>
                                            <button wire:click="duplicate({{ $template->id }})" title="Duplicate"
                                                    class="p-2 rounded-lg text-slate-400 hover:text-slate-800 hover:bg-slate-100 transition-colors">
                                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
                                            </button>
                                            @unless ($template->is_default)
                                                <button wire:click="setDefault({{ $template->id }})" title="Make this the default {{ strtolower($template->typeLabel()) }} letter"
                                                        class="p-2 rounded-lg text-slate-400 hover:text-amber-500 hover:bg-amber-50 transition-colors">
                                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01Z"/></svg>
                                                </button>
                                            @else
                                                <span class="p-2 text-amber-400" title="Default for this airline and type">
                                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor" stroke="none"><path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01Z"/></svg>
                                                </span>
                                            @endunless
                                            @if ($canDelete)
                                                <button @click="$dispatch('admin-confirm', { title: 'Delete template', message: 'Delete {{ addslashes($template->name) }}? Emails already sent keep their history.', confirmLabel: 'Delete', danger: true, method: 'delete', params: [{{ $template->id }}] })"
                                                        title="Delete"
                                                        class="p-2 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors">
                                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                                                </button>
                                            @endif
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-3 border-t border-slate-100">{{ $templates->links() }}</div>
            @endif
        </div>
    </div>

    {{-- Editor --}}
    @if ($showEditor)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" wire:click="$set('showEditor', false)"></div>
            <div class="relative bg-white w-full max-w-3xl rounded-2xl shadow-2xl max-h-[92vh] overflow-y-auto p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-bold text-slate-900">{{ $editingId ? 'Edit template' : 'New template' }}</h2>
                    <button wire:click="$set('showEditor', false)" class="p-2 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100"><i data-lucide="x" class="w-5 h-5"></i></button>
                </div>

                <div class="grid sm:grid-cols-2 gap-3">
                    <div class="sm:col-span-2">
                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Template name</label>
                        <input type="text" wire:model="form.name" placeholder="e.g. Air Canada - initial claim"
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary-500 outline-none">
                        @error('form.name') <span class="text-rose-500 text-[10px] font-bold">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Template type</label>
                        <select wire:model="form.type" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm outline-none">
                            @foreach ($types as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
                        </select>
                    </div>
                    {{-- Reach: a clear choice first, then a searchable picker with
                         the selection visible as chips - a bare scroll list is
                         unusable once there are fifty airlines. --}}
                    <div class="sm:col-span-2" x-data="{ q: '' }">
                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Applies to</label>

                        <div class="inline-flex items-center gap-1 bg-slate-100 rounded-xl p-1 mb-2">
                            <button type="button" wire:click="$set('form.all', true)"
                                    class="px-3.5 py-1.5 rounded-lg text-[12px] font-bold transition-all {{ ($form['all'] ?? false) ? 'bg-white shadow text-slate-900' : 'text-slate-500 hover:text-slate-800' }}">
                                All airlines
                            </button>
                            <button type="button" wire:click="$set('form.all', false)"
                                    class="px-3.5 py-1.5 rounded-lg text-[12px] font-bold transition-all {{ ($form['all'] ?? false) ? 'text-slate-500 hover:text-slate-800' : 'bg-white shadow text-slate-900' }}">
                                Specific airlines
                                @if (count($form['airlines'] ?? []) && !($form['all'] ?? false))
                                    <span class="ml-1 px-1.5 py-0.5 rounded-full bg-slate-900 text-white text-[10px] font-black">{{ count($form['airlines']) }}</span>
                                @endif
                            </button>
                        </div>

                        @if ($form['all'] ?? false)
                            <p class="text-[12px] text-slate-500">This house letter is offered for every airline - an airline-specific template still wins where one exists.</p>
                        @else
                            @php $chosen = $airlines->whereIn('id', $form['airlines'] ?? []); @endphp

                            @if ($chosen->isNotEmpty())
                                <div class="flex flex-wrap gap-1.5 mb-2">
                                    @foreach ($chosen as $picked)
                                        <button type="button" wire:click="removeAirline({{ $picked->id }})"
                                                class="inline-flex items-center gap-1.5 pl-2.5 pr-1.5 py-1 rounded-lg bg-slate-900 text-white text-[11px] font-bold">
                                            {{ $picked->name }}
                                            <svg class="w-3 h-3 opacity-60 hover:opacity-100" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                                        </button>
                                    @endforeach
                                    <button type="button" wire:click="$set('form.airlines', [])"
                                            class="text-[11px] font-bold text-slate-400 hover:text-rose-600 px-2">Clear</button>
                                </div>
                            @endif

                            <div class="rounded-xl border border-slate-200 overflow-hidden">
                                <input type="search" x-model="q" placeholder="Search airlines…"
                                       class="w-full px-3.5 py-2.5 text-sm border-b border-slate-100 outline-none focus:bg-slate-50/50">
                                <div class="max-h-48 overflow-y-auto divide-y divide-slate-50">
                                    @foreach ($airlines as $airline)
                                        <label x-show="q === '' || @js(strtolower($airline->name . ' ' . $airline->iata_code)).includes(q.toLowerCase())"
                                               class="flex items-center gap-2.5 px-3.5 py-2 text-sm cursor-pointer hover:bg-slate-50">
                                            <input type="checkbox" value="{{ $airline->id }}" wire:model.live="form.airlines"
                                                   class="rounded border-slate-300 text-slate-900 focus:ring-slate-900">
                                            <span class="text-slate-700">{{ $airline->name }}</span>
                                            @if ($airline->iata_code)<span class="text-[11px] font-mono text-slate-400">{{ $airline->iata_code }}</span>@endif
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                            @error('form.airlines') <span class="text-rose-500 text-[10px] font-bold mt-1 block">{{ $message }}</span> @enderror
                        @endif
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Subject</label>
                        <input type="text" wire:model="form.subject"
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm font-mono focus:border-primary-500 outline-none">
                        @error('form.subject') <span class="text-rose-500 text-[10px] font-bold">{{ $message }}</span> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Body</label>
                        <textarea wire:model="form.body" rows="14"
                                  class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm font-mono leading-relaxed focus:border-primary-500 outline-none"></textarea>
                        @error('form.body') <span class="text-rose-500 text-[10px] font-bold">{{ $message }}</span> @enderror
                    </div>
                </div>

                {{-- Variables: click to copy, so nobody has to remember the list --}}
                <div class="mt-3 rounded-xl bg-slate-50 border border-slate-100 p-3.5">
                    <p class="text-[11px] font-bold text-slate-400 uppercase mb-2">Variables - click to copy</p>
                    @php $mustache = fn ($name) => '{' . '{' . $name . '}' . '}'; @endphp
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($variables as $name => $description)
                            <button type="button" x-data
                                    @click="navigator.clipboard.writeText(@js($mustache($name))); $dispatch('toast', { type: 'success', message: @js($mustache($name) . ' copied') })"
                                    title="{{ $description }}"
                                    class="px-2 py-1 rounded-lg bg-white border border-slate-200 text-[11px] font-mono text-slate-600 hover:border-slate-300 hover:text-slate-900 transition-colors">
                                {{ $mustache($name) }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="flex items-center gap-4 mt-4 flex-wrap">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-600 cursor-pointer">
                        <input type="checkbox" wire:model="form.is_default" class="rounded border-slate-300 text-slate-900 focus:ring-slate-900">
                        Default for this airline and type
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm text-slate-600 cursor-pointer">
                        <input type="checkbox" wire:model="form.is_active" class="rounded border-slate-300 text-slate-900 focus:ring-slate-900">
                        Active
                    </label>
                    <button wire:click="save" wire:loading.attr="disabled" class="ml-auto px-5 py-2.5 rounded-xl bg-slate-900 text-white text-sm font-bold disabled:opacity-60">
                        <span wire:loading.remove wire:target="save">{{ $editingId ? 'Save changes' : 'Create template' }}</span>
                        <span wire:loading wire:target="save">Saving…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Preview against a real claim --}}
    @if ($preview)
        <div class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" wire:click="$set('previewId', null)"></div>
            <div class="relative bg-white w-full max-w-2xl rounded-2xl shadow-2xl max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
                    <div>
                        <h3 class="font-bold text-slate-900">{{ $preview['template']->name }}</h3>
                        <p class="text-[11px] text-slate-400">
                            @if ($preview['claim'])
                                {{ $preview['template']->reachLabel() }} · variables filled from claim #{{ $preview['claim']->number }}
                            @else
                                No claim to preview against - variables stay unresolved
                            @endif
                        </p>
                    </div>
                    <button wire:click="$set('previewId', null)" class="p-2 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100"><i data-lucide="x" class="w-4 h-4"></i></button>
                </div>
                <div class="p-6">
                    @if ($preview['unknown'])
                        <div class="rounded-xl bg-amber-50 border border-amber-200 px-4 py-2.5 mb-4 text-[12px] text-amber-800">
                            Unknown variable{{ count($preview['unknown']) === 1 ? '' : 's' }}:
                            <span class="font-mono font-bold">{{ collect($preview['unknown'])->map(fn ($v) => '{' . '{' . $v . '}' . '}')->implode(', ') }}</span> - these will be sent as written.
                        </div>
                    @endif
                    <p class="text-[10px] uppercase tracking-wider font-bold text-slate-400">Subject</p>
                    <p class="text-sm font-bold text-slate-900 mb-4">{{ $preview['rendered']['subject'] ?? $preview['template']->subject }}</p>
                    <p class="text-[10px] uppercase tracking-wider font-bold text-slate-400">Body</p>
                    <div class="mt-1 rounded-xl border border-slate-200 bg-slate-50/50 p-4 text-sm text-slate-700 whitespace-pre-wrap leading-relaxed">{{ $preview['rendered']['body'] ?? $preview['template']->body }}</div>
                </div>
            </div>
        </div>
    @endif

    <x-admin.confirm />
</div>
