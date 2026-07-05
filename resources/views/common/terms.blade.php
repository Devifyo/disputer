@php $page = \App\Models\CmsPage::findBySlug('terms-of-service'); @endphp

@extends('layouts.marketing')

@section('title', 'Terms of Service — Unjamm')
@section('meta_description', 'The terms governing your use of Unjamm, the automated flight compensation service.')

@push('styles')
<style>
    .uj-legal-header { max-width: 820px; margin: 0 auto; padding: 150px 32px 8px; }
    .uj-legal-header h1 { font-family: 'Bricolage Grotesque', sans-serif; font-size: clamp(2.2rem, 3.6vw, 3rem); font-weight: 700; letter-spacing: -.03em; color: var(--text); margin: 0 0 10px; }
    .uj-legal-header p { font-size: .9rem; color: var(--faint); font-weight: 500; margin: 0; }
    .uj-legal-main { max-width: 820px; margin: 0 auto; padding: 24px 32px 120px; }
    .uj-legal-card { background: var(--card); border: 1px solid var(--border); border-radius: 24px; padding: 40px; box-shadow: 0 40px 90px -55px rgba(0,0,0,.8); }
    .uj-prose h2 { font-family: 'Bricolage Grotesque', sans-serif; font-size: 1.2rem; font-weight: 700; color: var(--text); margin: 2rem 0 .75rem; display: flex; align-items: center; gap: 10px; }
    .uj-prose h2::before { content: ''; display: inline-block; width: 4px; height: 1.05em; background: var(--accent); border-radius: 2px; flex-shrink: 0; }
    .uj-prose h3 { font-family: 'Bricolage Grotesque', sans-serif; font-size: 1rem; font-weight: 600; color: var(--text); margin: 1.25rem 0 .5rem; }
    .uj-prose p { margin: 0 0 1rem; color: var(--muted); line-height: 1.75; }
    .uj-prose ul { list-style: disc; padding-left: 1.4rem; margin: 0 0 1rem; color: var(--muted); }
    .uj-prose ul li { margin-bottom: .5rem; line-height: 1.7; }
    .uj-prose strong { color: var(--text); }
    .uj-prose a { color: var(--accent); text-decoration: underline; }
    .uj-prose hr { border: none; border-top: 1px solid var(--border); margin: 2rem 0; }
    .uj-prose:first-child > :first-child { margin-top: 0; }
    @media (max-width: 640px) {
        .uj-legal-header { padding: 130px 24px 8px; }
        .uj-legal-main { padding: 20px 24px 80px; }
        .uj-legal-card { padding: 28px 22px; }
    }
</style>
@endpush

@section('content')
    <header class="uj-legal-header">
        <h1>{{ $page?->title ?? 'Terms of Service' }}</h1>
        <p>Last updated: {{ $page?->last_updated_at?->format('F d, Y') ?? date('F d, Y') }}</p>
    </header>

    <main class="uj-legal-main">
        <div class="uj-legal-card uj-prose">
            {!! $page?->content ?? '<p>Content not available.</p>' !!}
        </div>
    </main>
@endsection
