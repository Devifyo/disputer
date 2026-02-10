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
        
        <div class="px-6 py-4 border-b border-slate-100 flex items-start justify-between bg-slate-50">
            <div class="min-w-0 flex-1 mr-4">
                <h3 class="text-lg font-bold text-slate-900 leading-tight truncate" x-text="viewSubject"></h3>
                <p class="text-xs text-slate-500 mt-1">Archived Message</p>
            </div>
            <button @click="viewModalOpen = false" class="p-2 -mr-2 rounded-lg hover:bg-slate-200 text-slate-400 hover:text-slate-600 transition-colors">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <div class="p-8 overflow-y-auto bg-white flex-1">
            <div class="text-sm text-slate-700 font-mono whitespace-pre-wrap leading-relaxed select-text" x-text="viewBody"></div>
        </div>

        <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex justify-end">
            <button @click="viewModalOpen = false" class="px-4 py-2 bg-white border border-slate-300 rounded-lg text-xs font-bold text-slate-700 hover:bg-slate-100 hover:text-slate-900 shadow-sm transition-all">
                Close
            </button>
        </div>
    </div>
</div>