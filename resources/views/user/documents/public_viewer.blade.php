<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $attachment->file_name }} | Secure Viewer</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@0.344.0/dist/umd/lucide.min.js"></script>
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        mono: ['JetBrains Mono', 'monospace'],
                    }
                }
            }
        }
    </script>
    
    <style>
        /* 1. HIDE CONTENT BY DEFAULT */
        #viewer-content { visibility: hidden; opacity: 0; transition: opacity 0.5s; }
        
        /* 2. SECURITY OVERLAY */
        #js-warning {
            position: fixed; inset: 0; z-index: 9999;
            background: #ffffff; display: flex; flex-direction: column;
            align-items: center; justify-content: center; text-align: center;
        }

        /* 3. PATTERN BACKGROUND */
        .bg-grid-slate-100 {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32' width='32' height='32' fill='none' stroke='rgb(241 245 249)'%3e%3cpath d='M0 .5H31.5V32'/%3e%3c/svg%3e");
        }

        /* Prevent selection */
        body.secure-mode {
            user-select: none;
            -webkit-user-select: none;
        }
        .protected-img {
            pointer-events: none;
            -webkit-user-drag: none;
        }
    </style>
</head>

<body class="bg-slate-50 font-sans h-screen w-screen overflow-hidden secure-mode text-slate-900" oncontextmenu="return false;">

    <div id="js-warning">
        <div class="w-20 h-20 bg-red-50 text-red-600 rounded-2xl flex items-center justify-center mb-6 shadow-sm border border-red-100">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v4"/><path d="m16.2 7.8 2.9-2.9"/><path d="M18 12h4"/><path d="m16.2 16.2 2.9 2.9"/><path d="M12 18v4"/><path d="m4.9 19.1 2.9-2.9"/><path d="M2 12h4"/><path d="m4.9 4.9 2.9 2.9"/></svg>
        </div>
        <h2 class="text-2xl font-bold text-slate-900 tracking-tight">Security Check Failed</h2>
        <p class="text-slate-500 mt-3 max-w-md px-4 leading-relaxed">
            This document is protected by {{ config('app.name') }} Secure View.<br>
            Please <strong>enable JavaScript</strong> to decrypt and view this file.
        </p>
    </div>

    <div id="app-container" style="display:none;" class="flex flex-col h-full w-full bg-grid-slate-100">
        
        <header class="bg-white/80 backdrop-blur-md border-b border-slate-200 h-16 shrink-0 flex items-center justify-between px-4 sm:px-6 z-50 sticky top-0">
            <div class="flex items-center gap-4 min-w-0">
                <div class="w-9 h-9 bg-slate-900 rounded-lg flex items-center justify-center text-white font-bold shadow-lg shadow-slate-900/20 shrink-0">
                    D
                </div>
                
                <div class="min-w-0 flex flex-col justify-center">
                    <h1 class="font-bold text-slate-900 text-sm truncate pr-4 leading-tight">
                        {{ Str::limit($attachment->file_name, 40) }}
                    </h1>
                    <div class="flex items-center gap-3 mt-0.5">
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-emerald-50 border border-emerald-100 text-[10px] font-bold text-emerald-700 uppercase tracking-wide">
                            <i data-lucide="lock" class="w-3 h-3"></i> Encrypted View
                        </span>
                        <span class="text-[10px] text-slate-400 font-mono hidden sm:inline-block">
                            {{ strtoupper(Str::afterLast($attachment->file_name, '.')) }} • {{ isset($attachment->file_size) ? round($attachment->file_size / 1024) . ' KB' : 'SECURE' }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ Storage::url($attachment->file_path) }}" download class="group flex items-center gap-2 bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 px-4 py-2 rounded-lg text-xs font-bold transition-all shadow-sm hover:shadow active:scale-95">
                    <i data-lucide="download" class="w-4 h-4 text-slate-400 group-hover:text-slate-600 transition-colors"></i>
                    <span class="hidden sm:inline">Download</span>
                </a>
            </div>
        </header>

        <main class="flex-1 flex items-center justify-center p-4 sm:p-8 overflow-hidden relative">
            <div class="relative w-full h-full flex items-center justify-center" id="viewer-mount">
                </div>

            <div class="absolute bottom-4 left-0 right-0 text-center pointer-events-none opacity-40">
                <p class="text-[10px] font-mono text-slate-400 uppercase tracking-widest">
                    Confidential • Do Not Distribute
                </p>
            </div>
        </main>
    </div>

    <script>
        const FILE_TYPE = "{{ Str::contains($attachment->mime_type, 'image') ? 'image' : (Str::contains($attachment->mime_type, 'pdf') ? 'pdf' : 'other') }}";
        const FILE_URL  = "{{ Storage::url($attachment->file_path) }}";

        document.addEventListener("DOMContentLoaded", function() {
            
            if (typeof lucide !== 'undefined') lucide.createIcons();
            
            const warning = document.getElementById('js-warning');
            const app = document.getElementById('app-container');
            const mount = document.getElementById('viewer-mount');

            if(warning && app && mount) {
                warning.remove(); 
                app.style.display = 'flex'; 
                
                // Inject Content dynamically
                if(FILE_TYPE === 'image') {
                    mount.innerHTML = `
                        <div class="relative shadow-2xl shadow-slate-200/50 rounded-xl overflow-hidden bg-white p-2 ring-1 ring-slate-900/5 transition-transform hover:scale-[1.005] duration-500 ease-out">
                            <img src="${FILE_URL}" class="protected-img max-h-[80vh] max-w-full object-contain block rounded-lg" alt="Secure Doc">
                        </div>`;
                } else if(FILE_TYPE === 'pdf') {
                    mount.innerHTML = `
                        <div class="w-full h-full max-w-5xl shadow-2xl shadow-slate-200/50 rounded-xl overflow-hidden bg-white ring-1 ring-slate-900/5">
                            <iframe src="${FILE_URL}#toolbar=0&navpanes=0" class="w-full h-full border-none"></iframe>
                        </div>`;
                } else {
                    mount.innerHTML = `
                        <div class="bg-white p-10 rounded-2xl shadow-xl shadow-slate-200/50 text-center border border-slate-100 max-w-sm">
                            <div class="w-16 h-16 bg-slate-50 rounded-2xl flex items-center justify-center mx-auto mb-5 text-slate-400">
                                <i data-lucide="file-question" class="w-8 h-8"></i>
                            </div>
                            <h3 class="font-bold text-lg text-slate-900 mb-2">Preview Unavailable</h3>
                            <p class="text-sm text-slate-500 mb-6 leading-relaxed">This file format cannot be securely rendered in the browser.</p>
                            <a href="${FILE_URL}" class="block w-full bg-slate-900 hover:bg-slate-800 text-white py-2.5 rounded-lg text-sm font-bold transition-all shadow-lg shadow-slate-900/20">Download File</a>
                        </div>`;
                    if (typeof lucide !== 'undefined') lucide.createIcons();
                }
            }
            enableSecurity();
        });

        function enableSecurity() {
            document.addEventListener('contextmenu', e => e.preventDefault());
            document.onkeydown = function(e) {
                if (e.keyCode == 123) return false; 
                if (e.ctrlKey && e.shiftKey && (e.keyCode == 'I'.charCodeAt(0) || e.keyCode == 'C'.charCodeAt(0) || e.keyCode == 'J'.charCodeAt(0))) return false;
                if (e.ctrlKey && (e.keyCode == 'U'.charCodeAt(0) || e.keyCode == 'S'.charCodeAt(0) || e.keyCode == 'P'.charCodeAt(0))) return false;
            };
            setInterval(() => { (function(){})["constructor"]("debugger")(); }, 500);
        }
    </script>
</body>
</html>