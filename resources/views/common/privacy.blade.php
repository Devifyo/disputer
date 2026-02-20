<!DOCTYPE html>
<html lang="en" class="bg-slate-50 antialiased selection:bg-blue-200 selection:text-blue-900">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy - {{ config('app.name') }}</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="text-slate-600 bg-slate-50">

    {{-- Sticky Navigation Bar --}}
    <nav class="sticky top-0 z-50 w-full bg-white/80 backdrop-blur-md border-b border-slate-200 shadow-sm">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-blue-600 flex items-center justify-center">
                    <i data-lucide="shield" class="w-4 h-4 text-white"></i>
                </div>
                <span class="font-bold text-slate-900 tracking-tight">{{ config('app.name') }}</span>
            </div>
            
            <a href="{{ url()->previous() }}" class="inline-flex items-center gap-2 text-sm font-bold text-slate-600 hover:text-slate-900 bg-slate-100 hover:bg-slate-200 px-4 py-2 rounded-lg transition-colors">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                Back
            </a>
        </div>
    </nav>

    {{-- Header Section --}}
    <header class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 pt-16 pb-8 text-center">
        <h1 class="text-3xl sm:text-4xl font-bold text-slate-900 tracking-tight mb-4">Privacy Policy</h1>
        <p class="text-sm text-slate-500 font-medium bg-slate-200/50 inline-block px-3 py-1 rounded-full">
            Effective Date: {{ date('F d, Y') }}
        </p>
    </header>

    {{-- Reading Container --}}
    <main class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 pb-24">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8 sm:p-12 text-[15px] sm:text-base leading-relaxed space-y-6">
            
            <p class="text-lg text-slate-700 font-medium">
                At {{ config('app.name', 'Disputer') }}, we take your privacy and the security of your financial data incredibly seriously. This policy outlines how we handle the sensitive information required to generate your disputes.
            </p>

            <hr class="border-slate-100 my-8">

            <h2 class="text-xl font-bold text-slate-900 mt-8 mb-4 flex items-center gap-2">
                <i data-lucide="database" class="w-5 h-5 text-blue-600"></i> 1. Information We Collect
            </h2>
            <p>
                To provide our dispute resolution services, we collect personal information including your name, email address, and specific details regarding your financial disputes. This includes transaction dates, disputed amounts, reference numbers, and the names of involved institutions.
            </p>

            <h2 class="text-xl font-bold text-slate-900 mt-8 mb-4 flex items-center gap-2">
                <i data-lucide="cpu" class="w-5 h-5 text-blue-600"></i> 2. How We Use Your Data & AI
            </h2>
            <p>
                Your data is strictly used to facilitate the dispute process:
            </p>
            <ul class="list-disc pl-5 space-y-2 mt-2">
                <li><strong class="text-slate-800">AI Generation:</strong> We use your context to generate accurate legal templates. Your specific personal identifiers are anonymized where possible before being processed by our language models.</li>
                <li><strong class="text-slate-800">Workflow Tracking:</strong> Updating your case status and organizing your timeline.</li>
                <li><strong class="text-slate-800">Communication:</strong> Sending you critical system notifications.</li>
            </ul>

            <h2 class="text-xl font-bold text-slate-900 mt-8 mb-4 flex items-center gap-2">
                <i data-lucide="lock" class="w-5 h-5 text-blue-600"></i> 3. Bank-Grade Security
            </h2>
            <p>
                We employ bank-grade encryption to protect your sensitive financial records and dispute attachments. Your data is encrypted both in transit (via SSL/TLS) and at rest within our secure databases.
            </p>

            <h2 class="text-xl font-bold text-slate-900 mt-8 mb-4 flex items-center gap-2">
                <i data-lucide="trash-2" class="w-5 h-5 text-blue-600"></i> 4. Data Retention
            </h2>
            <p>
                We retain your case files and attachments only for as long as necessary to resolve your dispute. Once a case is permanently closed, you maintain the right to request a complete deletion of your records from our active servers.
            </p>
           
            <div class="mt-12 p-6 bg-blue-50 rounded-xl border border-blue-100">
                <h3 class="font-bold text-blue-900 mb-2">Contact our Privacy Team</h3>
                <p class="text-sm text-blue-800">
                    If you have questions about this policy or wish to exercise your data rights, please contact us at <a href="mailto:{{ config('app.admin_email') }}" class="font-bold underline hover:text-blue-600 transition-colors">{{ config('app.admin_email') }}</a>.
                </p>
            </div>

        </div>
    </main>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>