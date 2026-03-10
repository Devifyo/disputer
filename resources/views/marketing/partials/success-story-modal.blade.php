<div x-show="showSuccessModal" style="display: none;" class="relative z-[200]" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    
    <div x-show="showSuccessModal" 
         x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" 
         x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" 
         class="fixed inset-0 backdrop-blur-sm transition-opacity"
         style="background-color: rgba(15, 23, 42, 0.5);"></div>

    <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            
            <div x-show="showSuccessModal" @click.away="showSuccessModal = false"
                 x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-8 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" 
                 x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-8 sm:translate-y-0 sm:scale-95" 
                 style="background-color: var(--white); border: 1px solid var(--border); border-radius: 28px;"
                 class="relative transform overflow-hidden text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-2xl">
                
                <div class="px-6 pb-6 pt-8 sm:p-10">
                    
                    <div class="absolute right-0 top-0 pr-6 pt-6 z-10">
                        <button type="button" @click="showSuccessModal = false" style="color: var(--muted); background-color: var(--paper);" class="rounded-full p-2 hover:opacity-75 focus:outline-none transition-opacity">
                            <span class="sr-only">Close</span>
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                    
                    <div class="w-full">
                        <div style="font-size: 0.72rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: var(--accent); margin-bottom: 16px; display: flex; align-items: center; gap: 12px;">
                            <span style="width: 32px; height: 2px; background: var(--accent); border-radius: 2px; display: block;"></span>
                            Community
                        </div>

                        <h3 style="color: var(--ink); font-family: 'Inter', sans-serif; letter-spacing: -0.035em; line-height: 1.05;" class="text-3xl sm:text-4xl font-extrabold" id="modal-title">
                            Share your success story.
                        </h3>
                        
                        <p style="color: var(--muted); font-size: 1.05rem;" class="mt-4 leading-relaxed font-light">
                            Did Unjamm help you resolve a frustrating issue? We'd love to hear about it. Your story could inspire others to take action and finally get unstuck.
                        </p>

                        <form class="mt-8 space-y-6" action="#" method="POST" enctype="multipart/form-data">
                            @csrf
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                <div>
                                    <label class="block text-sm font-semibold mb-2" style="color: var(--ink);">First Name</label>
                                    <input type="text" name="first_name" required style="border: 1px solid var(--border); background-color: var(--paper); border-radius: 14px; color: var(--ink);" class="w-full px-4 py-3.5 text-sm focus:outline-none transition-shadow" onfocus="this.style.boxShadow='0 0 0 2px var(--accent)'" onblur="this.style.boxShadow='none'">
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold mb-2" style="color: var(--ink);">Email <span style="color: var(--muted); font-weight: 400;">(Optional)</span></label>
                                    <input type="email" name="email" style="border: 1px solid var(--border); background-color: var(--paper); border-radius: 14px; color: var(--ink);" class="w-full px-4 py-3.5 text-sm focus:outline-none transition-shadow" onfocus="this.style.boxShadow='0 0 0 2px var(--accent)'" onblur="this.style.boxShadow='none'">
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold mb-2" style="color: var(--ink);">What were you stuck on, and how did it get resolved?</label>
                                <textarea name="story" rows="4" required style="border: 1px solid var(--border); background-color: var(--paper); border-radius: 14px; color: var(--ink);" class="w-full px-4 py-3.5 text-sm focus:outline-none transition-shadow" onfocus="this.style.boxShadow='0 0 0 2px var(--accent)'" onblur="this.style.boxShadow='none'" placeholder="I was trying to get a refund from an airline for 6 months..."></textarea>
                            </div>

                            <div x-data="{ fileName: '' }">
                                <label class="block text-sm font-semibold mb-2" style="color: var(--ink);">Attach Media <span style="color: var(--muted); font-weight: 400;">(Optional - Screenshots, letters, etc.)</span></label>
                                
                                <input id="media_upload" name="media_file" type="file" class="sr-only" 
                                       @change="fileName = $event.target.files.length > 0 ? $event.target.files[0].name : ''">

                                <div x-show="!fileName"
                                     class="mt-1 flex justify-center px-6 pt-6 pb-7 border-2 border-dashed transition-colors cursor-pointer" 
                                     style="border-color: rgba(15, 23, 42, 0.15); background-color: var(--paper); border-radius: 14px;" 
                                     onmouseover="this.style.borderColor='var(--accent)'" 
                                     onmouseout="this.style.borderColor='rgba(15, 23, 42, 0.15)'"
                                     onclick="document.getElementById('media_upload').click()">
                                     
                                    <div class="space-y-2 text-center">
                                        <svg class="mx-auto h-10 w-10" style="color: var(--muted);" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                                            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                        <div class="flex text-sm justify-center" style="color: var(--muted);">
                                            <span class="relative font-medium focus-within:outline-none" style="color: var(--accent);">Upload a file</span>
                                            <p class="pl-1">or drag and drop</p>
                                        </div>
                                        <p class="text-xs" style="color: var(--muted);">PNG, JPG, PDF up to 10MB</p>
                                    </div>
                                </div>

                                <div x-show="fileName" style="display: none; border: 1px solid var(--accent); background-color: rgba(37, 99, 235, 0.05); border-radius: 14px;" 
                                     class="mt-1 flex items-center justify-between px-5 py-4 transition-all">
                                    <div class="flex items-center space-x-4 overflow-hidden">
                                        <div style="background-color: var(--white); padding: 8px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                                            <svg class="h-6 w-6" style="color: var(--accent);" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                        </div>
                                        <span class="text-sm font-semibold truncate" style="color: var(--ink);" x-text="fileName"></span>
                                    </div>
                                    
                                    <button type="button" 
                                            @click="fileName = ''; document.getElementById('media_upload').value = '';" 
                                            class="p-2 ml-2 rounded-lg hover:bg-white hover:text-red-600 transition-colors focus:outline-none" 
                                            style="color: var(--muted);">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>

                            </div>

                            <div class="mt-8 pt-6 sm:flex sm:flex-row-reverse" style="border-top: 1px solid var(--border);">
                                <button type="submit" style="background-color: var(--ink); color: var(--paper); border-radius: 14px; letter-spacing: -0.01em;" class="inline-flex w-full justify-center items-center px-8 py-3.5 text-[0.95rem] font-semibold shadow-sm sm:ml-4 sm:w-auto transition-transform hover:-translate-y-0.5 hover:shadow-lg">
                                    Submit Story
                                    <svg class="ml-2 w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                                </button>
                                <button type="button" @click="showSuccessModal = false" style="background-color: var(--white); color: var(--ink); border: 1px solid var(--border); border-radius: 14px; letter-spacing: -0.01em;" class="mt-3 inline-flex w-full justify-center px-8 py-3.5 text-[0.95rem] font-semibold shadow-sm sm:mt-0 sm:w-auto transition-transform hover:-translate-y-0.5 hover:shadow-md">
                                    Cancel
                                </button>
                            </div>
                        </form>
                        
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>