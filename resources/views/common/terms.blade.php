@php $page = \App\Models\CmsPage::findBySlug('terms-of-service'); @endphp
<!DOCTYPE html>
<html lang="en" class="bg-slate-50 antialiased selection:bg-blue-200 selection:text-blue-900">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms of Service - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .prose h2 { font-size: 1.2rem; font-weight: 700; color: #0f172a; margin-top: 2rem; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 8px; }
        .prose h2::before { content: ''; display: inline-block; width: 4px; height: 1.1em; background: #2563eb; border-radius: 2px; flex-shrink: 0; }
        .prose h3 { font-size: 1rem; font-weight: 700; color: #0f172a; margin-top: 1.25rem; margin-bottom: 0.5rem; }
        .prose p { margin-bottom: 1rem; color: #475569; line-height: 1.75; }
        .prose ul { list-style: disc; padding-left: 1.5rem; margin-bottom: 1rem; color: #475569; }
        .prose ul li { margin-bottom: 0.5rem; line-height: 1.7; }
        .prose strong { color: #1e293b; }
        .prose a { color: #2563eb; text-decoration: underline; }
        .prose hr { border: none; border-top: 1px solid #f1f5f9; margin: 2rem 0; }
    </style>
</head>
<body class="text-slate-600 bg-slate-50">

    <nav class="sticky top-0 z-50 w-full bg-white/80 backdrop-blur-md border-b border-slate-200 shadow-sm">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-blue-600 flex items-center justify-center">
                    <i data-lucide="scale" class="w-4 h-4 text-white"></i>
                </div>
                <span class="font-bold text-slate-900 tracking-tight">{{ config('app.name') }}</span>
            </div>
            <button onclick="history.back()" class="inline-flex items-center gap-2 text-sm font-bold text-slate-600 hover:text-slate-900 bg-slate-100 hover:bg-slate-200 px-4 py-2 rounded-lg transition-colors">
                <i data-lucide="arrow-left" class="w-4 h-4"></i> Back
            </button>
        </div>
    </nav>

    <header class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 pt-16 pb-8">
        <h1 class="text-3xl sm:text-4xl font-bold text-slate-900 tracking-tight mb-3">
            {{ $page?->title ?? 'Terms of Service' }}
        </h1>
        <p class="text-sm text-slate-500 font-medium">
            Last Updated: {{ $page?->last_updated_at?->format('F d, Y') ?? date('F d, Y') }}
        </p>
    </header>

    <main class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 pb-24">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8 sm:p-12 text-[15px] sm:text-base leading-relaxed prose">
            {!! $page?->content ?? '<p>Content not available.</p>' !!}
        </div>
    </main>

    <script>lucide.createIcons();</script>
</body>
</html>
