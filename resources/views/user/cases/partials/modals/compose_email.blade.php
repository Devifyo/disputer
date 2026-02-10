<div x-show="composeModalOpen" 
     style="display: none;" 
     class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6" 
     x-cloak>
    
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" 
         @click="composeModalOpen = false"></div>
    
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl flex flex-col overflow-hidden max-h-[90vh]"
         x-data="fileManager()">
         
        <form action="{{ route('user.cases.send_email', encrypt_id($case->id)) }}" method="POST" enctype="multipart/form-data" class="flex flex-col h-full">
            @csrf
            
            <div class="px-6 py-4 bg-slate-900 flex justify-between items-center text-white shrink-0">
                <h3 class="font-bold text-sm flex items-center gap-2">New Message</h3>
                <button type="button" @click="composeModalOpen = false" class="text-slate-400 hover:text-white transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto">
                <div class="px-6 pt-4 pb-2 space-y-1">
                    <div class="flex items-center border-b border-slate-100 focus-within:border-blue-500 transition-colors">
                        <label class="text-xs font-semibold text-slate-500 w-12 shrink-0">To</label>
                        <input type="email" name="recipient" x-model="replyTo" class="w-full py-3 text-sm font-medium text-slate-900 border-0 focus:ring-0 placeholder:text-slate-300" required>
                    </div>
                    <div class="flex items-center border-b border-slate-100 focus-within:border-blue-500 transition-colors">
                        <label class="text-xs font-semibold text-slate-500 w-12 shrink-0">Subject</label>
                        <input type="text" name="subject" x-model="replySubject" class="w-full py-3 text-sm font-bold text-slate-900 border-0 focus:ring-0 placeholder:text-slate-300" required>
                    </div>
                </div>

                <div class="px-6 py-2 h-full min-h-[200px]">
                    <textarea name="body" x-model="replyBody" class="w-full h-full text-sm text-slate-700 leading-relaxed border-0 focus:ring-0 resize-none placeholder:text-slate-300 outline-none" placeholder="Type your message here..."></textarea>
                </div>
                
                <div class="px-6 pb-6 pt-2 bg-slate-50 border-t border-slate-100">
                    
                    <div class="flex items-center justify-between mb-3">
                        <label class="inline-flex items-center gap-2 px-3 py-1.5 bg-white border border-slate-300 rounded-md shadow-sm text-xs font-bold text-slate-700 hover:bg-slate-100 cursor-pointer transition-all">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-slate-500"><path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                            Attach Files
                            <input type="file" multiple class="hidden" @change="addFiles($event)">
                        </label>
                        <span class="text-[10px] text-slate-400" x-text="files.length + ' files selected'"></span>
                    </div>

                    <input type="file" name="attachments[]" multiple class="hidden" x-ref="hiddenInput">

                    <div class="space-y-2">
                        <template x-for="(file, index) in files" :key="index">
                            <div class="flex items-center justify-between p-2 bg-white border border-slate-200 rounded-lg shadow-sm group hover:border-blue-300 transition-colors">
                                
                                <div class="flex items-center gap-3 overflow-hidden">
                                    <div class="w-8 h-8 rounded bg-slate-100 border border-slate-200 flex items-center justify-center shrink-0 text-slate-500 font-bold text-[10px] uppercase">
                                        <span x-text="file.name.split('.').pop()"></span>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-xs font-bold text-slate-700 truncate max-w-[200px]" x-text="file.name"></p>
                                        <p class="text-[10px] text-slate-400" x-text="formatSize(file.size)"></p>
                                    </div>
                                </div>
                                
                                <button type="button" 
                                        @click="removeFile(index)" 
                                        class="p-1.5 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-md transition-all cursor-pointer"
                                        title="Remove file">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></svg>
                                </button>
                            </div>
                        </template>

                        <div x-show="files.length === 0" class="text-[10px] text-slate-400 italic py-2">
                            No files attached yet.
                        </div>
                    </div>
                </div>
            </div>

            <div class="px-6 py-4 bg-white border-t border-slate-100 flex justify-between items-center shrink-0 z-20">
                <div class="flex items-center gap-1.5 text-[10px] font-medium text-slate-400">
                    <div class="w-1.5 h-1.5 rounded-full bg-emerald-500"></div>
                    Secure SMTP
                </div>
                
                <div class="flex gap-3">
                    <button type="button" @click="composeModalOpen = false" class="px-4 py-2 text-xs font-bold text-slate-500 hover:text-slate-700 transition-colors">
                        Discard
                    </button>
                    <button type="submit" class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-bold shadow-lg shadow-blue-600/20 transition-all flex items-center gap-2 group">
                        <span>Send Email</span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="group-hover:translate-x-0.5 transition-transform"><line x1="22" x2="11" y1="2" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <script>
        function fileManager() {
            return {
                files: [],
                addFiles(e) {
                    // Convert FileList to Array and append
                    const newFiles = Array.from(e.target.files);
                    this.files = [...this.files, ...newFiles];
                    
                    // Reset the trigger input so selecting the same file works again
                    e.target.value = ''; 
                    
                    this.syncInput();
                },
                removeFile(index) {
                    // Remove from visual array
                    this.files.splice(index, 1);
                    this.syncInput();
                },
                syncInput() {
                    // Create a new DataTransfer object to hold the files
                    const dt = new DataTransfer();
                    this.files.forEach(file => dt.items.add(file));
                    
                    // Assign to the actual hidden input that gets submitted
                    this.$refs.hiddenInput.files = dt.files;
                },
                formatSize(bytes) {
                    if(bytes === 0) return '0 B';
                    const k = 1024;
                    const sizes = ['B', 'KB', 'MB', 'GB'];
                    const i = Math.floor(Math.log(bytes) / Math.log(k));
                    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
                }
            }
        }
    </script>
</div>