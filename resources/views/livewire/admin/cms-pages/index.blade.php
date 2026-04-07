<div class="p-6 pb-24 h-full overflow-y-auto custom-scrollbar bg-slate-50/50">
    <x-flash />

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">CMS Pages</h1>
            <p class="text-sm text-slate-500 mt-1">Manage the content of your public legal pages.</p>
        </div>
    </div>

    @if($editingId)
        {{-- EDITOR --}}
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6" wire:key="editor-view">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-lg font-bold text-slate-800">Editing: {{ $title }}</h2>
                <button wire:click="cancel" class="text-sm text-slate-500 hover:text-slate-700 flex items-center gap-1.5">
                    <i data-lucide="x" class="w-4 h-4"></i> Cancel
                </button>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Page Title</label>
                <input wire:model="title" type="text"
                    class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <div class="mb-6">
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Page Content</label>
                
                {{-- Alpine.js wrapper handles the TinyMCE lifecycle safely --}}
                <div wire:ignore x-data="{
                    editorInstance: null,
                    initEditor() {
                        if (typeof tinymce === 'undefined') {
                            setTimeout(() => this.initEditor(), 200);
                            return;
                        }
                        tinymce.remove('#' + this.$refs.editor.id);
                        tinymce.init({
                            target: this.$refs.editor,
                            plugins: 'anchor autolink charmap codesample emoticons fullscreen help image link lists advlist media preview searchreplace table visualblocks visualchars wordcount accordion directionality',
                            toolbar: [
                                'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | forecolor backcolor',
                                'link image media table | bullist numlist outdent indent | alignleft aligncenter alignright alignjustify | codesample | emoticons charmap',
                                'searchreplace | visualblocks fullscreen preview | removeformat | accordion | help'
                            ],
                            toolbar_mode: 'wrap',
                            menubar: 'file edit view insert format tools table help',
                            height: 600,
                            branding: false,
                            promotion: false,
                            resize: true,
                            image_advtab: true,
                            codesample_languages: [
                                { text: 'HTML/XML', value: 'markup' },
                                { text: 'JavaScript', value: 'javascript' },
                                { text: 'CSS', value: 'css' },
                                { text: 'PHP', value: 'php' },
                                { text: 'Python', value: 'python' },
                                { text: 'Bash', value: 'bash' },
                            ],
                            content_style: 'body { font-family: Inter, sans-serif; font-size: 15px; line-height: 1.75; color: #334155; } h1,h2,h3 { color: #0f172a; } code { background:#f1f5f9; padding:2px 6px; border-radius:4px; font-size:0.875em; }',
                            setup: (editor) => {
                                this.editorInstance = editor;
                                
                                // Sync data to Livewire ONLY on blur (clicking away)
                                // This prevents the browser from freezing due to network spam
                                editor.on('blur', () => {
                                    $wire.content = editor.getContent();
                                });
                            }
                        });

                        // Official Alpine v3 cleanup hook: Runs safely when Livewire removes this block
                    },
                    init() {
                        this.$nextTick(() => this.initEditor());
                        this.$cleanup(() => {
                            if (this.editorInstance) {
                                this.editorInstance.remove();
                            }
                        });
                    }
                }">
                    {{-- x-ref guarantees TinyMCE binds to the right element without ID conflicts --}}
                    <textarea x-ref="editor" id="cms-editor-{{ $editingId }}">{{ $content }}</textarea>
                </div>
                
                @error('content') <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="flex justify-end gap-3">
                <button wire:click="cancel"
                    class="px-5 py-2.5 text-sm font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors">
                    Cancel
                </button>
                <button
                    wire:click="save"
                    class="px-5 py-2.5 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-xl transition-colors flex items-center gap-2">
                    <i data-lucide="save" class="w-4 h-4"></i> Save Changes
                </button>
            </div>
        </div>
    @else
        {{-- PAGE LIST --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5" wire:key="list-view">
            @forelse($pages as $page)
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <i data-lucide="file-text" class="w-4 h-4 text-blue-600 shrink-0"></i>
                            <h3 class="font-bold text-slate-900 text-base truncate">{{ $page->title }}</h3>
                        </div>
                        <p class="text-xs text-slate-400 font-mono">/{{ $page->slug }}</p>
                        @if($page->last_updated_at)
                            <p class="text-xs text-slate-400 mt-2">Last updated: {{ $page->last_updated_at->format('M d, Y') }}</p>
                        @endif
                    </div>
                    <button wire:click="edit({{ $page->id }})" wire:target="edit({{ $page->id }})"
                        class="shrink-0 inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold text-blue-700 bg-blue-50 hover:bg-blue-100 rounded-xl transition-colors">
                        <i data-lucide="loader-circle" wire:loading wire:target="edit({{ $page->id }})" class="w-3.5 h-3.5 animate-spin"></i>
                        <i data-lucide="pencil" class="w-3.5 h-3.5" wire:loading.remove wire:target="edit({{ $page->id }})"></i>
                        <span wire:loading.remove wire:target="edit({{ $page->id }})">Edit</span>
                        <span wire:loading wire:target="edit({{ $page->id }})">Loading...</span>
                    </button>
                </div>
            @empty
                <div class="col-span-2 text-center py-16 text-slate-400">
                    <i data-lucide="file-x" class="w-10 h-10 mx-auto mb-3 opacity-40"></i>
                    <p class="text-sm">No CMS pages found. Run the seeder to create default pages.</p>
                    <code class="text-xs mt-2 block opacity-60">php artisan db:seed --class=CmsPagesSeeder</code>
                </div>
            @endforelse
        </div>
    @endif
</div>