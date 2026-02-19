<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden mb-8 relative"
     x-data="{ 
        aiOpen: @entangle('showAiModal'),
        composeModalOpen: false,
        replyTo: @entangle('recipient'),
        replySubject: @entangle('subject'),
        replyBody: @entangle('body'),

        init() {
            Livewire.on('open-compose-modal', (data) => {
                this.composeModalOpen = true;
                // If specific data passed from PHP
                if(data && data[0]) {
                   // Livewire sometimes wraps args in array
                   // handled via entangle usually, but safety check
                }
            });
        },

        confirmAction(actionKey, actionLabel) {
            Swal.fire({
                title: 'Confirm Action',
                text: `Are you sure you want to proceed with '${actionLabel}'?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#2563eb', 
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, proceed',
                cancelButtonText: 'Cancel',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    $wire.triggerAction(actionKey);
                }
            })
        },

        confirmManualJump(stepKey, stepLabel) {
            Swal.fire({
                title: 'Manual Override',
                text: `You are manually jumping to '${stepLabel}'. This bypasses standard timers. Continue?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e11d48', 
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, change stage',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    $wire.jumpToStep(stepKey);
                }
            })
        }
     }" 
     @email-sent.window="composeModalOpen = false"
     @keydown.escape.window="aiOpen = false; composeModalOpen = false">
    
    <div class="grid grid-cols-1 lg:grid-cols-12 min-h-[400px]">
        
        <div class="lg:col-span-4 bg-slate-50 border-r border-slate-100 p-6 flex flex-col">
            <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-6 pl-1">Progress Map</h4>
             <div class="relative flex-1">
                @php
                    $steps = $workflowConfig['steps'] ?? [];
                    $stepKeys = array_keys($steps);
                    $currentIndex = array_search($currentStepKey, $stepKeys);
                @endphp
                <div class="absolute left-[7px] top-2 bottom-0 w-0.5 bg-slate-200"></div>
                @foreach($steps as $stepKey => $stepConfig)
                    @php
                        $index = array_search($stepKey, $stepKeys);
                        $status = $index < $currentIndex ? 'completed' : ($index === $currentIndex ? 'current' : 'future');
                        $isLast = $loop->last;
                    @endphp
                    <div class="relative pl-8 {{ $isLast ? '' : 'pb-8' }} group">
                        <div class="absolute left-0 top-1 w-4 h-4 rounded-full border-2 z-10 box-border transition-all duration-300 flex items-center justify-center
                            {{ $status === 'completed' ? 'bg-blue-600 border-blue-600' : ($status === 'current' ? 'bg-white border-blue-600 ring-4 ring-blue-50' : 'bg-white border-slate-300') }}">
                            @if($status === 'completed') <svg class="w-2.5 h-2.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                            @elseif($status === 'current') <div class="w-1.5 h-1.5 bg-blue-600 rounded-full"></div> @endif
                        </div>
                        <div class="transition-opacity duration-300 {{ $status === 'current' ? 'opacity-100' : 'opacity-60 group-hover:opacity-100' }}">
                            <p class="text-sm font-bold leading-tight {{ $status === 'current' ? 'text-blue-700' : 'text-slate-700' }}">{{ $stepConfig['label'] ?? 'N\A' }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="lg:col-span-8 p-6 lg:p-8 flex flex-col bg-white">
            
            <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 mb-4">
                <div>
                     <div class="flex items-center gap-2 mb-2">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wide border bg-blue-50 text-blue-700 border-blue-100">Live Status</span>
                        <span class="text-xs text-slate-400 font-medium">
                            @php $daysInStage = (int) $case->updated_at->diffInDays(now()); @endphp
                            {{ $daysInStage < 1 ? 'Updated today' : "$daysInStage days in stage" }}
                        </span>
                    </div>
                    <h2 class="text-2xl font-bold text-slate-900 tracking-tight">{{ $currentStepConfig['label'] ?? 'N/A' }}</h2>
                </div>

                <button wire:click="askAiForHelp" 
                        wire:loading.attr="disabled" 
                        class="group flex items-center gap-2 px-3 py-2 bg-indigo-50 border border-indigo-100 rounded-lg hover:bg-indigo-100 text-indigo-600 text-xs font-bold transition-all shadow-sm disabled:opacity-70 disabled:cursor-not-allowed">
                    
                    <svg wire:loading.remove wire:target="askAiForHelp" class="w-4 h-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>

                    <svg wire:loading wire:target="askAiForHelp" class="animate-spin w-4 h-4 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>

                    <span wire:loading.remove wire:target="askAiForHelp">Ask AI Copilot</span>
                    <span wire:loading wire:target="askAiForHelp">Analyzing Case...</span>
                </button>
            </div>
            
            <p class="text-slate-500 text-sm leading-relaxed max-w-2xl mb-8">{{ $currentStepConfig['description'] ?? 'No additional description available.' }}</p>

            @if(isset($currentStepConfig['timeouts']) && count($currentStepConfig['timeouts']) > 0)
                @php
                    $timeout = $currentStepConfig['timeouts'][0];
                    $maxDays = (int) $timeout['days'];
                    $daysElapsed = (int) $case->updated_at->diffInDays(now());
                    $daysRemaining = max(0, $maxDays - $daysElapsed);
                    $percentage = min(100, ($daysElapsed / $maxDays) * 100);
                    
                    $waitingFor = $currentStepConfig['waiting_for'] ?? 'Response';
                @endphp

                <div class="rounded-xl border p-5 mb-8 transition-all duration-300 {{ $daysRemaining <= 0 ? 'bg-rose-50 border-rose-200 shadow-sm' : 'bg-slate-50 border-slate-200' }}">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <span class="relative flex h-2.5 w-2.5">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-75 {{ $daysRemaining <= 0 ? 'bg-rose-400' : 'bg-amber-400' }}"></span>
                            <span class="relative inline-flex rounded-full h-2.5 w-2.5 {{ $daysRemaining <= 0 ? 'bg-rose-500' : 'bg-amber-500' }}"></span>
                            </span>
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-wide">Waiting for {{ $waitingFor }}</span>
                        </div>
                        <span class="text-xs font-bold {{ $daysRemaining <= 0 ? 'text-rose-600' : 'text-slate-600' }}">
                            {{ $daysRemaining <= 0 ? 'Deadline Passed' : "$daysRemaining days remaining" }}
                        </span>
                    </div>
                    
                    <div class="w-full bg-slate-200 rounded-full h-2 mb-4 overflow-hidden">
                        <div class="{{ $daysRemaining <= 0 ? 'bg-rose-500' : 'bg-blue-600' }} h-2 rounded-full transition-all duration-1000" style="width: {{ $percentage }}%"></div>
                    </div>

                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-4 rounded-lg border {{ $daysRemaining <= 0 ? 'border-rose-100 shadow-rose-100' : 'border-slate-100 shadow-sm' }}">
                        <div class="flex items-start gap-3">
                            <div class="mt-0.5 {{ $daysRemaining <= 0 ? 'text-rose-500' : 'text-slate-400' }}">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div class="text-xs text-slate-600 leading-relaxed">
                                @if($daysRemaining <= 0)
                                    @if($case->escalation_level === 0)
                                        <p class="font-bold text-rose-800 mb-0.5">Escalation Recommended</p>
                                        <p>The response deadline of <strong>{{ $maxDays }} days</strong> has passed. Please escalate to the authority.</p>
                                    @else
                                        <p class="font-bold text-purple-800 mb-0.5">Escalation Active (Level {{ $case->escalation_level }})</p>
                                        <p>Case escalated {{ $case->last_escalated_at->diffForHumans() }}. Awaiting authority response.</p>
                                    @endif
                                @else
                                    <p>Response expected by <strong class="text-slate-900">{{ $case->updated_at->addDays($maxDays)->format('M d, Y') }}</strong>.</p>
                                @endif
                            </div>
                        </div>

                        @if($daysRemaining <= 0)
                            <div class="flex items-center gap-2">
                                @if($case->escalation_level === 0)
                                    <button 
                                        wire:click="initiateEscalation"
                                        class="shrink-0 flex items-center gap-2 px-6 py-3 bg-rose-600 text-white text-xs font-bold rounded-lg hover:bg-rose-700 transition-all shadow-lg shadow-rose-200 active:scale-95"
                                    >
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                        </svg>
                                        Escalate Now
                                    </button>
                                @else
                                    <button 
                                        type="button"
                                        @click="$dispatch('open-compose-modal', { 
                                            recipient: '{{ $case->institution->escalation_email ?? '' }}', 
                                            subject: 'Follow Up: Case #{{ $case->case_reference_id }}',
                                            body: `To {{ $case->institution->escalation_contact_name ?? 'Authority' }},\n\nI am following up on the escalation sent previously regarding Case #{{ $case->case_reference_id }}. I have not yet received a resolution.`,
                                            isEscalation: false,
                                            isFollowUp: true
                                        })"
                                        class="px-4 py-2 bg-white border border-slate-300 text-slate-700 text-xs font-bold rounded-lg hover:bg-slate-50 transition-all shadow-sm active:scale-95"
                                    >
                                        <i data-lucide="refresh-cw" class="w-3.5 h-3.5 mr-1 inline-block"></i>
                                        <span>Send Follow-Up</span>
                                    </button>
                                    <button 
                                        wire:click="initiateEscalation" 
                                        class="px-4 py-2 bg-slate-900 text-white text-xs font-bold rounded-lg hover:bg-slate-800 flex items-center gap-2"
                                    >
                                        Escalate Further
                                        <i data-lucide="arrow-right" class="w-3 h-3"></i>
                                    </button>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            @if(isset($currentStepConfig['actions']) && count($currentStepConfig['actions']) > 0)
                <div class="space-y-3 mt-auto">
                    <h4 class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Available Actions</h4>
                    <div class="flex flex-wrap gap-3">
                        @foreach($currentStepConfig['actions'] as $action)
                            <button 
                                @click="confirmAction('{{ $action['key'] }}', '{{ $action['label'] }}')"
                                class="inline-flex items-center gap-2 px-5 py-2.5 bg-white border border-slate-200 rounded-lg text-sm font-bold text-slate-700 hover:border-blue-500 hover:text-blue-600 hover:shadow-md transition-all active:scale-95 group">
                                <span>{{ $action['label'] }}</span>
                                <svg class="w-4 h-4 text-slate-300 group-hover:text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="mt-8 pt-6 border-t border-slate-100 flex justify-end" x-data="{ open: false }">
                <div class="relative">
                    <button @click="open = !open" class="text-[10px] font-bold text-slate-400 hover:text-slate-600 uppercase tracking-widest flex items-center gap-1.5 transition-colors">
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" /></svg>
                        Manual Override
                    </button>
                    <div x-show="open" @click.away="open = false" style="display: none;" class="absolute right-0 bottom-full mb-2 w-56 bg-white rounded-lg shadow-xl border border-slate-100 py-1 z-50">
                        @foreach($steps as $key => $step)
                            @if($key !== $currentStepKey)
                                <button @click="confirmManualJump('{{ $key }}', '{{ $step['label'] }}'); open = false" class="block w-full text-left px-4 py-2.5 text-xs text-slate-600 hover:bg-slate-50 hover:text-blue-600 transition-colors">{{ $step['label'] ?? 'N\A' }}</button>
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($showAiModal)
    <div class="fixed inset-0 z-[110] flex items-center justify-center bg-slate-900/50 backdrop-blur-sm px-4">
        <div class="bg-white rounded-2xl shadow-2xl border border-slate-100 w-full max-w-lg p-8 relative animate-in fade-in zoom-in-95 duration-200">
            <button wire:click="closeAiModal" class="absolute top-4 right-4 text-slate-400 hover:text-slate-600 transition-colors">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>

            @if($isAnalyzing)
                <div class="text-center py-8">
                    <div class="w-16 h-16 rounded-full bg-indigo-50 flex items-center justify-center animate-pulse mx-auto mb-6">
                        <svg class="w-8 h-8 text-indigo-500 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    </div>
                    <h3 class="text-xl font-bold text-slate-900 mb-2">Analyzing Case...</h3>
                    <p class="text-xs text-slate-500">Checking timeline & requirements...</p>
                </div>
            @elseif($aiResponse)
                <div class="text-left">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="p-2.5 bg-indigo-100 rounded-xl text-indigo-600">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                        </div>
                        <h3 class="text-lg font-bold text-slate-900">AI Advice</h3>
                    </div>
                    <div class="bg-indigo-50/50 rounded-xl p-6 border border-indigo-100 text-sm text-indigo-900 leading-relaxed font-medium">
                        {{ $aiResponse }}
                    </div>
                    <div class="mt-8 flex justify-end">
                        <button wire:click="closeAiModal" class="px-5 py-2.5 bg-slate-900 text-white text-sm font-bold rounded-lg hover:bg-slate-800 transition-all">Got it, thanks</button>
                    </div>
                </div>
            @endif
        </div>
    </div>
    @endif

    @include('user.cases.partials.modals.compose_email')

</div>