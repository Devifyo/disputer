<div x-data="{ isSubmitted: false }" 
     x-show="showSuccessModal" 
     style="display: none;" 
     class="relative z-[200]" 
     aria-labelledby="modal-title" 
     role="dialog" 
     aria-modal="true"
     x-on:story-submitted.window="isSubmitted = true">
    
    <div x-show="showSuccessModal" 
         x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" 
         x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" 
         class="fixed inset-0 backdrop-blur-md transition-opacity"
         style="background-color: rgba(15, 23, 42, 0.6);"></div>

    <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            
            <div x-show="showSuccessModal" @click.away="showSuccessModal = false; setTimeout(() => isSubmitted = false, 500)"
                 x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-8 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" 
                 x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-8 sm:translate-y-0 sm:scale-95" 
                 style="background-color: var(--white); border: 1px solid var(--border); border-radius: 28px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);"
                 class="relative transform text-left transition-all sm:my-8 sm:w-full sm:max-w-2xl">
                
                <div class="px-6 pb-6 pt-8 sm:p-10 relative">
                    
                    <div class="absolute right-6 top-6 z-10">
                        <button type="button" @click="showSuccessModal = false; setTimeout(() => isSubmitted = false, 500)" 
                                style="color: var(--muted); background-color: var(--white); border: 1px solid var(--border); box-shadow: 0 2px 8px rgba(15,23,42,0.05);" 
                                class="flex items-center justify-center w-10 h-10 rounded-full transition-all duration-200 hover:scale-105 focus:outline-none"
                                onmouseover="this.style.backgroundColor='var(--paper)'; this.style.color='var(--ink)';"
                                onmouseout="this.style.backgroundColor='var(--white)'; this.style.color='var(--muted)';">
                            <span class="sr-only">Close</span>
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    
                    <div x-show="!isSubmitted" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0 hidden" class="w-full">
                        
                        <div style="font-size: 0.72rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: var(--accent); margin-bottom: 16px; display: flex; align-items: center; gap: 12px;">
                            <span style="width: 32px; height: 2px; background: var(--accent); border-radius: 2px; display: block;"></span>
                            Community
                        </div>

                        <h3 style="color: var(--ink); font-family: 'Inter', sans-serif; letter-spacing: -0.035em; line-height: 1.05;" class="text-3xl sm:text-4xl font-extrabold pr-12" id="modal-title">
                            Share your success story.
                        </h3>
                        
                        <p style="color: var(--muted); font-size: 1.05rem;" class="mt-4 leading-relaxed font-light">
                            Did Unjamm help you resolve a frustrating issue? We'd love to hear about it. Your story could inspire others to take action and finally get unstuck.
                        </p>

                        <form wire:submit.prevent="submit" class="mt-8 space-y-6">
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                <div>
                                    <label class="block text-sm font-semibold mb-2" style="color: var(--ink);">First Name</label>
                                    <input type="text" wire:model="first_name" required 
                                           style="border: 1px solid var(--border); background-color: var(--paper); border-radius: 12px; color: var(--ink); transition: all 0.2s ease;" 
                                           class="w-full px-4 py-3.5 text-[0.95rem] focus:outline-none placeholder-slate-400" 
                                           onfocus="this.style.borderColor='var(--accent)'; this.style.boxShadow='0 0 0 4px rgba(37, 99, 235, 0.1)';" 
                                           onblur="this.style.borderColor='var(--border)'; this.style.boxShadow='none';">
                                    @error('first_name') <span class="text-red-500 text-xs mt-1 block font-medium">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold mb-2" style="color: var(--ink);">Email <span style="color: var(--muted); font-weight: 400;">(Optional)</span></label>
                                    <input type="email" wire:model="email" 
                                           style="border: 1px solid var(--border); background-color: var(--paper); border-radius: 12px; color: var(--ink); transition: all 0.2s ease;" 
                                           class="w-full px-4 py-3.5 text-[0.95rem] focus:outline-none placeholder-slate-400" 
                                           onfocus="this.style.borderColor='var(--accent)'; this.style.boxShadow='0 0 0 4px rgba(37, 99, 235, 0.1)';" 
                                           onblur="this.style.borderColor='var(--border)'; this.style.boxShadow='none';">
                                    @error('email') <span class="text-red-500 text-xs mt-1 block font-medium">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold mb-2" style="color: var(--ink);">What were you stuck on, and how did it get resolved?</label>
                                <textarea wire:model="story" rows="4" required 
                                          style="border: 1px solid var(--border); background-color: var(--paper); border-radius: 12px; color: var(--ink); transition: all 0.2s ease;" 
                                          class="w-full px-4 py-3.5 text-[0.95rem] focus:outline-none placeholder-slate-400 leading-relaxed" 
                                          onfocus="this.style.borderColor='var(--accent)'; this.style.boxShadow='0 0 0 4px rgba(37, 99, 235, 0.1)';" 
                                          onblur="this.style.borderColor='var(--border)'; this.style.boxShadow='none';" 
                                          placeholder="I was trying to get a refund from an airline for 6 months..."></textarea>
                                @error('story') <span class="text-red-500 text-xs mt-1 block font-medium">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-semibold mb-2" style="color: var(--ink);">Attach Media <span style="color: var(--muted); font-weight: 400;">(Optional)</span></label>
                                
                                <input id="media_upload" wire:model="media_files" type="file" multiple class="sr-only">

                                <div class="mt-1 flex justify-center px-6 pt-7 pb-8 border-2 border-dashed transition-all duration-200 cursor-pointer" 
                                     style="border-color: rgba(15, 23, 42, 0.12); background-color: var(--paper); border-radius: 14px;" 
                                     onmouseover="this.style.borderColor='var(--accent)'; this.style.backgroundColor='rgba(37, 99, 235, 0.02)';" 
                                     onmouseout="this.style.borderColor='rgba(15, 23, 42, 0.12)'; this.style.backgroundColor='var(--paper)';"
                                     onclick="document.getElementById('media_upload').click()">
                                     
                                    <div class="space-y-2 text-center">
                                        <div class="mx-auto w-12 h-12 rounded-full bg-white flex items-center justify-center shadow-sm border border-slate-100 mb-3">
                                            <svg class="h-6 w-6" style="color: var(--muted);" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                            </svg>
                                        </div>
                                        <div class="flex text-[0.95rem] justify-center" style="color: var(--muted);">
                                            <span class="font-semibold" style="color: var(--accent);">Select multiple files</span>
                                            <p class="pl-1">or drag and drop</p>
                                        </div>
                                        <p class="text-xs" style="color: var(--muted); opacity: 0.8;">PNG, JPG, PDF up to 10MB</p>
                                    </div>
                                </div>

                                <div wire:loading wire:target="media_files" class="mt-3 text-sm font-medium flex items-center" style="color: var(--accent);">
                                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-accent" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Uploading files...
                                </div>
                                @error('media_files.*') <span class="text-red-500 text-xs mt-1 block font-medium">{{ $message }}</span> @enderror

                                @if($media_files && count($media_files) > 0)
                                    <div class="mt-4 space-y-2">
                                        @foreach($media_files as $index => $file)
                                            <div class="flex items-center justify-between px-4 py-3 transition-all" 
                                                 style="border: 1px solid rgba(37, 99, 235, 0.2); background-color: rgba(37, 99, 235, 0.03); border-radius: 12px;">
                                                <div class="flex items-center space-x-3 overflow-hidden">
                                                    <div style="background-color: var(--white); padding: 8px; border-radius: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                                                        <svg class="h-4 w-4" style="color: var(--accent);" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                        </svg>
                                                    </div>
                                                    <span class="text-[0.9rem] font-semibold truncate" style="color: var(--ink);">{{ $file->getClientOriginalName() }}</span>
                                                </div>
                                                
                                                <button type="button" wire:click="removeFile({{ $index }})" class="p-2 ml-2 rounded-lg hover:bg-white hover:text-red-600 transition-colors focus:outline-none shadow-sm border border-transparent hover:border-red-100" style="color: var(--muted);">
                                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            <div class="mt-8 pt-6 sm:flex sm:flex-row-reverse" style="border-top: 1px solid var(--border);">
                                <button type="submit" wire:loading.attr="disabled" style="background-color: var(--ink); color: var(--white); border-radius: 14px; letter-spacing: -0.01em;" class="inline-flex w-full justify-center items-center px-8 py-3.5 text-[0.95rem] font-semibold shadow-md sm:ml-4 sm:w-auto transition-transform hover:-translate-y-0.5 hover:shadow-lg disabled:opacity-50 disabled:cursor-not-allowed">
                                    <span wire:loading.remove wire:target="submit">Submit Story</span>
                                    <span wire:loading wire:target="submit">Submitting...</span>
                                    <svg wire:loading.remove wire:target="submit" class="ml-2 w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                                </button>
                                <button type="button" @click="showSuccessModal = false; setTimeout(() => isSubmitted = false, 500)" style="background-color: var(--white); color: var(--ink); border: 1px solid var(--border); border-radius: 14px; letter-spacing: -0.01em;" class="mt-3 inline-flex w-full justify-center px-8 py-3.5 text-[0.95rem] font-semibold shadow-sm sm:mt-0 sm:w-auto transition-all hover:bg-slate-50">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>

                    <div x-show="isSubmitted" style="display: none;" 
                         x-transition:enter="ease-out duration-500 delay-150" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0"
                         class="w-full text-center py-12 px-4">
                        
                        <div class="mx-auto flex items-center justify-center h-24 w-24 rounded-full mb-8 shadow-inner" style="background-color: rgba(37, 99, 235, 0.08);">
                            <div class="flex items-center justify-center h-16 w-16 rounded-full" style="background-color: rgba(37, 99, 235, 0.15);">
                                <svg class="h-8 w-8" style="color: var(--accent);" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                        </div>
                        
                        <h3 style="color: var(--ink); font-family: 'Inter', sans-serif; letter-spacing: -0.035em;" class="text-3xl font-extrabold mb-4">
                            Thank you!
                        </h3>
                        
                        <p style="color: var(--muted); font-size: 1.05rem;" class="mb-10 font-light leading-relaxed max-w-sm mx-auto">
                            Your story has been successfully received. We appreciate you taking the time to share your experience to help others get unstuck.
                        </p>
                        
                        <button type="button" @click="showSuccessModal = false; setTimeout(() => isSubmitted = false, 500)" style="background-color: var(--ink); color: var(--white); border-radius: 14px; letter-spacing: -0.01em;" class="inline-flex w-full sm:w-auto justify-center px-12 py-3.5 text-[0.95rem] font-semibold shadow-md transition-transform hover:-translate-y-0.5 hover:shadow-lg">
                            Close Window
                        </button>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>