<div>
    @if($isOpen)
    @teleport('body')
    <div class="fixed inset-0 z-[999999] sm:flex sm:items-center sm:justify-center sm:p-6" x-cloak>
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" wire:click="close"></div>

        <div class="absolute sm:relative inset-0 sm:inset-auto bg-white sm:rounded-2xl shadow-2xl w-full h-full sm:h-[90vh] sm:max-w-4xl flex flex-col overflow-hidden animate-in zoom-in-95 duration-200 z-10" style="padding: 0 1rem;" x-data x-init="$nextTick(() => { if (typeof lucide !== 'undefined') lucide.createIcons(); })">
            
            <div class="px-4 sm:px-6 py-3 border-b border-slate-100 flex items-center justify-between bg-white">
                <div class="flex items-center gap-2">
                    <div class="w-2 h-2 rounded-full bg-blue-600"></div>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest hidden sm:inline">Message Archive</span>
                </div>

                <div class="flex items-center gap-2">
                    <button wire:click="analyze" wire:loading.attr="disabled" 
                            class="flex items-center gap-1.5 px-3 py-1.5 bg-indigo-50 text-indigo-600 rounded-lg text-xs font-bold hover:bg-indigo-100 transition-all border border-indigo-100 shadow-sm">
                        <div wire:loading wire:target="analyze" class="animate-spin w-3 h-3 border-2 border-indigo-600 border-t-transparent rounded-full"></div>
                        <i data-lucide="sparkles" class="w-3.5 h-3.5" wire:loading.remove wire:target="analyze"></i>
                        <span wire:loading.remove wire:target="analyze" class="hidden sm:inline">Smart Analysis</span>
                        <span wire:loading wire:target="analyze" class="hidden sm:inline">Processing...</span>
                    </button>

                    <button wire:click="close" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 transition-colors">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
            </div>
 
            <div class="px-4 sm:px-8 py-4 sm:py-5 bg-slate-50/50 border-b border-slate-100">
                <h2 class="text-lg font-bold text-slate-900 mb-3 leading-tight">{{ $subject }}</h2>
                <div class="flex flex-col sm:flex-row sm:items-center gap-4 text-xs">
                    <div class="flex items-center gap-2">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                            {{ $direction === 'inbound' ? 'From' : 'To' }}
                        </span>
                        
                        <span class="text-xs font-semibold text-blue-600 bg-blue-50 px-2 py-0.5 rounded border border-blue-100 break-all">
                            {{ $recipient_email }}
                        </span>
                    </div>
                    @if(count($attachments) > 0)
                    <div class="flex items-center gap-2">
                        <i data-lucide="paperclip" class="w-3 h-3 text-slate-400"></i>
                        <span class="text-slate-500 font-medium">{{ count($attachments) }} attachments</span>
                    </div>
                    @endif
                </div>
            </div>

            <div class="p-0 overflow-y-auto overflow-x-hidden bg-white flex-1 w-full">
                
                @if($isAnalyzing)
                    <div class="p-4 sm:p-6 bg-indigo-50/30 border-b border-indigo-100">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="animate-spin w-4 h-4 border-2 border-indigo-500 border-t-transparent rounded-full"></div>
                            <span class="text-xs font-bold text-indigo-500 uppercase">Analyzing Content & Attachments...</span>
                        </div>
                        <div class="space-y-2 max-w-lg">
                            <div class="h-2 bg-indigo-100 rounded-full w-full animate-pulse"></div>
                            <div class="h-2 bg-indigo-100 rounded-full w-2/3 animate-pulse"></div>
                            <div class="h-2 bg-indigo-100 rounded-full w-3/4 animate-pulse"></div>
                        </div>
                    </div>
                    {{-- analysis --}}
                    @elseif(is_array($analysis))
                        <div class="bg-indigo-50/40 border-b border-indigo-100 p-4 sm:p-6 animate-in slide-in-from-top duration-500">
                            <div class="flex items-start gap-4">
                                <div class="shrink-0 p-2 bg-white rounded-xl shadow-sm border border-indigo-100 text-indigo-600">
                                    <i data-lucide="brain-circuit" class="w-5 h-5"></i>
                                </div>
                                
                                <div class="flex-1 space-y-5">
                                    
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2 mb-2">
                                            <h4 class="text-[10px] font-bold text-indigo-400 uppercase tracking-widest">Executive Summary</h4>
                                            
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-100 text-indigo-700 uppercase border border-indigo-200">
                                                {{ $analysis['email_type'] ?? 'Analysis' }}
                                            </span>

                                            @if($analysis['action_flags']['response_required'] ?? false)
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-700 uppercase border border-amber-200 flex items-center gap-1">
                                                    <i data-lucide="alert-circle" class="w-3 h-3"></i> Action Required
                                                </span>
                                            @endif
                                            @if($analysis['action_flags']['deadline_mentioned'] ?? false)
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-700 uppercase border border-rose-200 flex items-center gap-1">
                                                    <i data-lucide="clock" class="w-3 h-3"></i> Deadline Detected
                                                </span>
                                            @endif
                                        </div>
                                        <p class="text-sm font-medium text-slate-800 leading-relaxed">
                                            {{ $analysis['summary'] ?? 'No summary available.' }}
                                        </p>
                                    </div>

                                    @if(!empty($analysis['key_entities']))
                                        <div class="flex flex-wrap gap-2">
                                            @foreach($analysis['key_entities']['amounts'] ?? [] as $amt)
                                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-md bg-white border border-slate-200 text-xs font-mono text-emerald-600 font-bold shadow-sm">
                                                    <i data-lucide="dollar-sign" class="w-3 h-3"></i> {{ $amt }}
                                                </span>
                                            @endforeach
                                            
                                            @foreach($analysis['key_entities']['dates'] ?? [] as $date)
                                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-md bg-white border border-slate-200 text-xs font-mono text-blue-600 font-bold shadow-sm">
                                                    <i data-lucide="calendar" class="w-3 h-3"></i> {{ $date }}
                                                </span>
                                            @endforeach

                                            @foreach($analysis['key_entities']['reference_numbers'] ?? [] as $ref)
                                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-md bg-white border border-slate-200 text-xs font-mono text-slate-500 font-bold shadow-sm">
                                                    <i data-lucide="hash" class="w-3 h-3"></i> {{ $ref }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                                        
                                        <div class="bg-white p-3 rounded-lg border border-indigo-100 shadow-sm flex flex-col h-full">
                                            <h5 class="text-[10px] font-bold text-slate-400 uppercase mb-2 flex items-center gap-1">
                                                <i data-lucide="arrow-right-circle" class="w-3 h-3 text-emerald-500"></i> Recommended Next Steps
                                            </h5>
                                            
                                            @if(!empty($analysis['suggested_next_steps']))
                                                <ul class="space-y-2">
                                                    @foreach($analysis['suggested_next_steps'] as $step)
                                                        <li class="text-xs text-slate-700 flex items-start gap-2">
                                                            <span class="mt-1.5 w-1 h-1 rounded-full bg-emerald-400 shrink-0"></span>
                                                            <span class="leading-relaxed">{{ $step }}</span>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @else
                                                <p class="text-xs text-slate-400 italic">No specific actions recommended.</p>
                                            @endif
                                        </div>

                                        <div class="bg-slate-50 p-3 rounded-lg border border-slate-200 shadow-sm flex flex-col h-full">
                                            <h5 class="text-[10px] font-bold text-slate-400 uppercase mb-2 flex items-center gap-1">
                                                <i data-lucide="file-search" class="w-3 h-3 text-blue-500"></i> Document Analysis
                                            </h5>

                                            <div class="space-y-3">
                                                @if(!empty($analysis['attachment_analysis']['document_types_detected']))
                                                    <div class="flex flex-wrap gap-1.5">
                                                        @foreach($analysis['attachment_analysis']['document_types_detected'] as $docType)
                                                            <span class="px-1.5 py-0.5 bg-blue-100 text-blue-700 rounded text-[10px] font-bold">{{ $docType }}</span>
                                                        @endforeach
                                                    </div>
                                                @endif

                                                @if(!empty($analysis['attachment_analysis']['important_details_extracted']))
                                                    <ul class="space-y-1">
                                                        @foreach($analysis['attachment_analysis']['important_details_extracted'] as $detail)
                                                            <li class="text-[10px] text-slate-600 flex items-start gap-1">
                                                                <i data-lucide="check" class="w-3 h-3 text-slate-400 mt-0.5"></i> {{ $detail }}
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                @endif

                                                @if(!empty($analysis['attachment_analysis']['missing_or_unclear_items']))
                                                    <div class="pt-2 mt-2 border-t border-slate-200">
                                                        <p class="text-[10px] font-bold text-rose-500 uppercase mb-1">Missing / Unclear</p>
                                                        <ul class="space-y-1">
                                                            @foreach($analysis['attachment_analysis']['missing_or_unclear_items'] as $missing)
                                                                <li class="text-xs text-rose-700 flex items-start gap-1.5">
                                                                    <i data-lucide="alert-triangle" class="w-3 h-3 shrink-0 mt-0.5"></i>
                                                                    <span class="leading-tight">{{ $missing }}</span>
                                                                </li>
                                                            @endforeach
                                                        </ul>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                {{--  --}}

                <div class="w-full max-w-full box-border overflow-x-hidden">
                    <div class="text-sm text-slate-800 leading-relaxed max-w-none w-full" x-data="{
                        initShadow() {
                            let shadow = this.$refs.shadowRoot.attachShadow({mode: 'open'});
                            let content = '';
                            try {
                                content = JSON.parse(this.$refs.emailTemplate.textContent);
                            } catch (e) { console.error('Error parsing email content JSON', e); }
                            
                            try {
                                let attachments = JSON.parse(this.$refs.attachmentsData.textContent);
                                content = content.replace(/src=['\x22]cid:([^'\x22]+)['\x22]/gi, (match, cid) => {
                                    let matchedAttachment = attachments.find(a => {
                                        let cleanCid = cid.split('@')[0];
                                        return cleanCid.toLowerCase() === a.name.toLowerCase() || 
                                               cid.toLowerCase().includes(a.name.toLowerCase());
                                    });
                                    if (matchedAttachment) {
                                        return `src='${matchedAttachment.url}'`;
                                    }
                                    return `style='display:none;' src='data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs='`;
                                });
                            } catch (e) { console.error('Error parsing CID attachments', e); }
                            
                            shadow.innerHTML = `
                                <style>
                                    :host { display: block; width: 100%; overflow-x: hidden !important; }
                                    /* Aggressive resets to prevent wide elements from causing horizontal scroll */
                                    * { 
                                        max-width: 100% !important; 
                                        min-width: 0 !important; 
                                        box-sizing: border-box !important; 
                                        word-wrap: break-word !important; 
                                        overflow-wrap: anywhere !important; 
                                        word-break: break-word !important;
                                        white-space: normal !important;
                                    }
                                    body, html { margin: 0; padding: 0; width: 100%; overflow-x: hidden !important; }
                                    img { height: auto !important; border-radius: 0.5rem; max-width: 100% !important; display: inline-block; }
                                    a { color: #2563eb; text-decoration: underline; word-break: break-all !important; }
                                    blockquote { border-left: 3px solid #e2e8f0; padding-left: 1rem; margin-top: 1rem; color: #64748b; overflow: hidden; }
                                    div[dir='ltr'] { margin-bottom: 0.75em; }
                                    
                                    /* Mobile Email Reflow Engine: Stacks tables vertically */
                                    @media (max-width: 640px) {
                                        table, thead, tbody, th, td, tr { 
                                            display: block !important; 
                                            width: 100% !important; 
                                            height: auto !important;
                                        }
                                    }
                                </style>
                                <div style='font-family: inherit; font-size: inherit; color: inherit; word-wrap: break-word;'>
                                    ${content}
                                </div>
                            `;
                        }
                    }" x-init="initShadow()">
                        <script type="application/json" x-ref="attachmentsData">
                            {!! json_encode($attachments ?? []) !!}
                        </script>
                        <script type="application/json" x-ref="emailTemplate">
                            @if(preg_match('/<(br|p|div|html|body|table)[^>]*>/i', $body))
                                {!! json_encode($body) !!}
                            @else
                                {!! json_encode(nl2br(e($body))) !!}
                            @endif
                        </script>
                        <div x-ref="shadowRoot" class="w-full overflow-hidden px-4 sm:px-8 py-4 sm:py-6 box-border"></div>
                    </div>
                    {{-- attachment of email --}}
                    @if(count($attachments) > 0)
                        <div class="mt-8 pt-6 border-t border-slate-100 px-4 sm:px-8 pb-6">
                            <h4 class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-4">Attached Files</h4>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                @foreach($attachments as $file)
                                    @php
                                        // Call the helper function we created
                                        $visuals = $this->getFileVisuals($file['name']);
                                    @endphp

                                    <a href="{{ $file['url'] }}" target="_blank" class="group flex items-center gap-3 p-3 rounded-xl border border-slate-200 hover:border-blue-400 hover:bg-blue-50/30 transition-all bg-white shadow-sm">
                                        
                                        <div class="w-10 h-10 rounded-lg flex items-center justify-center border transition-colors group-hover:bg-white {{ $visuals['bg'] }} {{ $visuals['color'] }} {{ $visuals['border'] }}">
                                            <i wire:ignore data-lucide="{{ $visuals['icon'] }}" class="w-5 h-5"></i>
                                        </div>

                                        <div class="flex-1 min-w-0">
                                            <p class="text-xs font-bold text-slate-700 truncate group-hover:text-blue-700">
                                                {{ $file['name'] }}
                                            </p>
                                            <p class="text-[10px] text-slate-400 uppercase">Click to view</p>
                                        </div>
                                        
                                        <i data-lucide="external-link" class="w-3.5 h-3.5 text-slate-300 group-hover:text-blue-500"></i>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    {{-- end of attachment of pdf --}}
                </div>
            </div>
            
            <div class="px-4 sm:px-6 py-4 border-t border-slate-100 bg-white flex justify-end">
                <button wire:click="close" class="px-6 py-2 bg-slate-900 text-white rounded-lg text-xs font-bold hover:bg-slate-800 transition-all shadow-md active:scale-95">
                    Close Viewer
                </button>
            </div>
        </div>
    </div>
    @endteleport
    @endif
</div>