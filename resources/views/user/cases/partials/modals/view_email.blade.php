<div x-show="viewModalOpen" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6" x-cloak>
    
    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" 
         x-show="viewModalOpen"
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="viewModalOpen = false"></div>

    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[85vh] flex flex-col overflow-hidden"
         x-show="viewModalOpen"
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
        
        {{-- <div class="px-6 py-4 border-b border-slate-100 flex items-start justify-between bg-slate-50">
            <div class="min-w-0 flex-1 mr-4">
                <h3 class="text-lg font-bold text-slate-900 leading-tight truncate" x-text="viewSubject"></h3>
                <p class="text-xs text-slate-500 mt-1">Archived Message</p>
            </div>
            <button @click="viewModalOpen = false" class="p-2 -mr-2 rounded-lg hover:bg-slate-200 text-slate-400 hover:text-slate-600 transition-colors">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div> --}}
        <div class="px-6 py-4 border-b border-slate-100 flex items-start justify-between bg-slate-50">
            <div class="min-w-0 flex-1 mr-4">
                <h3 class="text-lg font-bold text-slate-900 leading-tight truncate" x-text="viewSubject"></h3>
                
                <div class="flex items-center gap-3 mt-1.5">
                    <div class="flex items-center gap-1.5">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest" 
                            x-text="viewDirection === 'inbound' ? 'From' : 'To'"></span>
                        <span class="text-xs font-semibold text-blue-600 bg-blue-50 px-2 py-0.5 rounded border border-blue-100" 
                            x-text="viewRecipient"></span>
                    </div>
                </div>
            </div>
            <button @click="viewModalOpen = false" class="p-2 -mr-2 rounded-lg hover:bg-slate-200 text-slate-400 hover:text-slate-600 transition-colors">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        {{--  --}}
        <div class="p-8 overflow-y-auto bg-white flex-1 flex flex-col">
            <div class="text-sm text-slate-700 leading-relaxed select-text prose prose-sm max-w-none mb-6" 
                 x-html="viewBody">
            </div>

            <template x-if="viewAttachments.length > 0">
                <div class="mt-auto border-t border-slate-100 pt-4">
                    <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg> 
                        Attachments (<span x-text="viewAttachments.length"></span>)
                    </h4>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <template x-for="file in viewAttachments" :key="file.name">
                            <a :href="file.url" target="_blank" class="group flex items-center gap-3 p-3 rounded-lg border border-slate-200 hover:border-blue-300 hover:bg-blue-50 transition-all bg-slate-50">
                                
                                <div class="w-10 h-10 rounded bg-white border border-slate-200 flex-shrink-0 flex items-center justify-center text-slate-400 overflow-hidden relative">
                                    
                                    <template x-if="['jpg','jpeg','png','gif','webp'].includes(file.name.split('.').pop().toLowerCase())">
                                        <img :src="file.url" class="w-full h-full object-cover">
                                    </template>

                                    <template x-if="!['jpg','jpeg','png','gif','webp'].includes(file.name.split('.').pop().toLowerCase())">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-slate-300 group-hover:text-blue-500"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/></svg>
                                    </template>
                                </div>
                                
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-medium text-slate-700 truncate group-hover:text-blue-700" x-text="file.name"></p>
                                    <p class="text-[10px] text-slate-400 uppercase" x-text="file.name.split('.').pop()"></p>
                                </div>
                                
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-slate-300 group-hover:text-blue-500"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
                            </a>
                        </template>
                    </div>
                </div>
            </template>
        </div>

        <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex justify-end">
            <button @click="viewModalOpen = false" class="px-4 py-2 bg-white border border-slate-300 rounded-lg text-xs font-bold text-slate-700 hover:bg-slate-100 hover:text-slate-900 shadow-sm transition-all">
                Close
            </button>
        </div>
    </div>
</div>