<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden mb-8 relative"
     x-data="{ 
        aiOpen: @entangle('showAiModal'),
        
        // --- COMPOSE MODAL STATE (Matches Timeline) ---
        composeModalOpen: false,
        replyTo: @entangle('recipient'),
        replySubject: @entangle('subject'),
        replyBody: @entangle('body'),
        replyParentId: null,

        // Open Escalation Helper
        openEscalation(targetName, targetEmail) {
            this.replyTo = targetEmail || '';
            this.replySubject = 'Formal Escalation: Case #{{ $case->case_reference_id }}';
            // Simple pre-fill body
            this.replyBody = 'To ' + targetName + ',\n\nI am writing to formally escalate my dispute regarding Case #{{ $case->case_reference_id }}. \n\nDetails of the issue:\n...';
            
            this.composeModalOpen = true;
        }
     }" 
     @email-sent.window="composeModalOpen = false"
     @keydown.escape.window="aiOpen = false; composeModalOpen = false">
    
    <div class="grid grid-cols-1 lg:grid-cols-12 min-h-[400px]">
        
        <div class="lg:col-span-4 bg-slate-50 border-r border-slate-100 p-6 flex flex-col">
            <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-6 pl-1">Case Progress</h4>
             <div class="relative flex-1">
                @php
                    $steps = $workflowConfig['steps'] ?? [];
                    $stepKeys = array_keys($steps);
                    $currentIndex = array_search($currentStepKey, $stepKeys);
                    if ($currentIndex === false) $currentIndex = 0;
                @endphp
                <div class="absolute left-[7px] top-2 bottom-0 w-0.5 bg-slate-200"></div>
                @foreach($steps as $stepKey => $stepConfig)
                    @php
                        $index = array_search($stepKey, $stepKeys);
                        $status = $index < $currentIndex ? 'completed' : ($index === $currentIndex ? 'current' : 'future');
                        $isLast = $index === count($stepKeys) - 1;
                    @endphp
                    <div class="relative pl-8 {{ $isLast ? '' : 'pb-8' }} group">
                        <div class="absolute left-0 top-1 w-4 h-4 rounded-full border-2 z-10 box-border transition-all duration-300 flex items-center justify-center
                            {{ $status === 'completed' ? 'bg-blue-600 border-blue-600' : '' }}
                            {{ $status === 'current'   ? 'bg-white border-blue-600 ring-4 ring-blue-50' : '' }}
                            {{ $status === 'future'    ? 'bg-white border-slate-300' : '' }}
                        ">
                            @if($status === 'completed') <svg class="w-2.5 h-2.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                            @elseif($status === 'current') <div class="w-1.5 h-1.5 bg-blue-600 rounded-full"></div> @endif
                        </div>
                        <div class="transition-opacity duration-300 {{ $status === 'current' ? 'opacity-100' : 'opacity-60 group-hover:opacity-100' }}">
                            <p class="text-sm font-bold leading-tight {{ $status === 'current' ? 'text-blue-700' : 'text-slate-700' }}">{{ $stepConfig['label'] ?? $stepKey }}</p>
                            @if($status === 'current') <span class="inline-block mt-1 text-[10px] font-bold text-blue-600 uppercase tracking-wide bg-blue-50 px-1.5 py-0.5 rounded">Active Stage</span> @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="lg:col-span-8 p-6 lg:p-8 flex flex-col bg-white">
            
            <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 mb-4">
                <div>
                     <div class="flex items-center gap-2 mb-2">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wide border bg-blue-50 text-blue-700 border-blue-100">Current Status</span>
                        <span class="text-xs text-slate-400 font-medium flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            @php $days = (int) $case->updated_at->diffInDays(now()); @endphp
                            {{ $days < 1 ? 'Started today' : ($days == 1 ? '1 day in stage' : "$days days in stage") }}
                        </span>
                    </div>
                    <h2 class="text-2xl font-bold text-slate-900 tracking-tight">{{ $currentStepConfig['label'] ?? 'Unknown Stage' }}</h2>
                </div>

                <button wire:click="askAiForHelp" wire:loading.attr="disabled" class="group flex items-center gap-2 px-3 py-2 bg-white border border-slate-200 rounded-lg hover:bg-indigo-50 hover:border-indigo-200 hover:text-indigo-600 transition-all text-slate-500 text-xs font-bold shadow-sm">
                    <svg class="w-4 h-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.384-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" /></svg>
                    <span>Ask AI Copilot</span>
                </button>
            </div>
            
            <p class="text-slate-500 text-sm leading-relaxed max-w-2xl mb-8">{{ $currentStepConfig['description'] ?? '' }}</p>

            @if(isset($currentStepConfig['timeouts']) && count($currentStepConfig['timeouts']) > 0)
                @php
                    $timeout = $currentStepConfig['timeouts'][0];
                    $maxDays = (int) $timeout['days'];
                    $daysElapsed = (int) $case->updated_at->diffInDays(now());
                    $daysRemaining = max(0, $maxDays - $daysElapsed);
                    $percentage = min(100, ($daysElapsed / $maxDays) * 100);
                    
                    $waitingFor = $currentStepConfig['waiting_for'] ?? 'Action';
                    $escalationTarget = $currentStepConfig['escalation_target'] ?? null;
                    $escalationEmail = $currentStepConfig['escalation_email'] ?? null;
                @endphp

                <div class="bg-slate-50 rounded-xl border border-slate-200 p-5 mb-8">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <span class="relative flex h-2.5 w-2.5">
                              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                              <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-amber-500"></span>
                            </span>
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-wide">Waiting for {{ $waitingFor }}</span>
                        </div>
                        <span class="text-xs font-bold {{ $daysRemaining < 3 ? 'text-rose-600' : 'text-slate-600' }}">
                            {{ (int)$daysRemaining }} days remaining
                        </span>
                    </div>
                    
                    <div class="w-full bg-slate-200 rounded-full h-2 mb-4 overflow-hidden">
                        <div class="{{ $daysRemaining < 3 ? 'bg-rose-500' : 'bg-blue-600' }} h-2 rounded-full transition-all duration-1000" style="width: {{ $percentage }}%"></div>
                    </div>

                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-3 rounded-lg border border-slate-100 shadow-sm">
                        <div class="flex items-start gap-3">
                            <div class="mt-0.5 text-slate-400"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg></div>
                            <div class="text-xs text-slate-600 leading-relaxed">
                                <p class="mb-1">
                                    If no response by <strong class="text-slate-900">{{ $case->updated_at->addDays($maxDays)->format('M d, Y') }}</strong>, 
                                    we recommend escalating this case.
                                </p>
                            </div>
                        </div>

                        @if($escalationTarget)
                            <button 
                                @click="openEscalation('{{ addslashes($escalationTarget) }}', '{{ addslashes($escalationEmail) }}')"
                                class="shrink-0 flex items-center gap-2 px-4 py-2 bg-rose-50 border border-rose-100 text-rose-700 text-xs font-bold rounded-lg hover:bg-rose-100 hover:border-rose-200 transition-colors shadow-sm"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                                Escalate to {{ $escalationTarget }}
                            </button>
                        @endif
                    </div>
                </div>
            @endif

            @if(isset($currentStepConfig['actions']) && count($currentStepConfig['actions']) > 0)
                <div class="space-y-3 mt-auto">
                    <h4 class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Available Actions</h4>
                    <div class="flex flex-wrap gap-3">
                        @foreach($currentStepConfig['actions'] as $action)
                            <button wire:click="triggerAction('{{ $action['key'] }}')" wire:loading.attr="disabled" class="inline-flex items-center gap-2 px-5 py-2.5 bg-white border border-slate-200 rounded-lg text-sm font-bold text-slate-700 hover:border-blue-500 hover:text-blue-600 hover:shadow-md transition-all active:scale-95 group">
                                <span>{{ $action['label'] ?? 'Action' }}</span>
                                <svg class="w-4 h-4 text-slate-300 group-hover:text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

    @if($showAiModal)
    <div class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/50 backdrop-blur-sm transition-all duration-300 px-4">
        <div class="bg-white rounded-2xl shadow-2xl border border-slate-100 w-full max-w-lg p-8 relative animate-in fade-in zoom-in-95 duration-200">
            <button wire:click="closeAiModal" class="absolute top-4 right-4 text-slate-400 hover:text-slate-600 transition-colors"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg></button>
            @if($isAnalyzing)
                <div class="text-center py-8"><h3 class="text-xl font-bold text-slate-900 mb-2">Analyzing Case...</h3></div>
            @elseif($aiResponse)
                <div class="text-left"><h3 class="text-lg font-bold text-slate-900">Copilot Recommendation</h3><div class="bg-indigo-50/50 rounded-xl p-6 border border-indigo-100 text-sm text-indigo-900">{{ $aiResponse }}</div><div class="mt-8 flex justify-end"><button wire:click="closeAiModal" class="px-5 py-2.5 bg-slate-900 text-white text-sm font-bold rounded-lg">Got it</button></div></div>
            @endif
        </div>
    </div>
    @endif

    @include('user.cases.partials.modals.compose_email')

</div>