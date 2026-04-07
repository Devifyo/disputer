@extends('layouts.marketing')

@section('title', 'Unjamm - Resolve complaints with companies faster')
@section('meta_description', 'Unjamm helps you escalate disputes with banks, airlines, telecom companies, and other institutions using structured escalation workflows.')

@push('styles')
    @include('marketing.partials._styles')
    @include('marketing.partials._skeleton')
@endpush

@section('content')

    {{-- ═══ PAGE SKELETON OVERLAY ═══ --}}
    <div id="page-skeleton">

        {{-- Nav --}}
        <div class="sk-nav">
            <div class="sk-nav-logo">
                <div class="sk-block" style="width:32px;height:32px;border-radius:8px;"></div>
                <div class="sk-block" style="width:90px;height:16px;border-radius:6px;"></div>
            </div>
            <div style="display:flex;gap:12px;">
                <div class="sk-block" style="width:80px;height:36px;border-radius:8px;"></div>
                <div class="sk-block" style="width:110px;height:36px;border-radius:8px;"></div>
            </div>
        </div>

        {{-- Hero --}}
        <div class="sk-hero">
            <div class="sk-hero-left">
                <div class="sk-block" style="width:140px;height:24px;border-radius:100px;"></div>
                <div class="sk-block" style="width:100%;height:52px;border-radius:10px;"></div>
                <div class="sk-block" style="width:85%;height:52px;border-radius:10px;"></div>
                <div class="sk-block" style="width:90%;height:20px;border-radius:6px;margin-top:8px;"></div>
                <div class="sk-block" style="width:75%;height:20px;border-radius:6px;"></div>
                <div class="sk-block" style="width:60%;height:20px;border-radius:6px;"></div>
                <div style="display:flex;gap:12px;margin-top:12px;">
                    <div class="sk-block" style="width:160px;height:48px;border-radius:12px;"></div>
                    <div class="sk-block" style="width:130px;height:48px;border-radius:12px;"></div>
                </div>
            </div>
            <div class="sk-hero-right">
                @for($i = 0; $i < 3; $i++)
                <div class="sk-card">
                    <div class="sk-block" style="width:50%;height:12px;border-radius:4px;"></div>
                    <div class="sk-block" style="width:80%;height:18px;border-radius:6px;"></div>
                    <div class="sk-block" style="width:100%;height:14px;border-radius:4px;"></div>
                    <div class="sk-block" style="width:90%;height:14px;border-radius:4px;"></div>
                    <div class="sk-block" style="width:90px;height:26px;border-radius:100px;margin-top:4px;"></div>
                </div>
                @endfor
            </div>
        </div>

        {{-- How It Works --}}
        <div style="background:#fff;padding:80px 48px;border-bottom:1px solid #e2e8f0;">
            <div style="max-width:1200px;margin:0 auto;">
                <div style="text-align:center;margin-bottom:40px;">
                    <div class="sk-block" style="width:100px;height:12px;border-radius:4px;margin:0 auto 12px;"></div>
                    <div class="sk-block" style="width:220px;height:32px;border-radius:8px;margin:0 auto;"></div>
                </div>
                <div class="sk-grid-4">
                    @for($i = 0; $i < 4; $i++)
                    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:24px;padding:32px 24px;">
                        <div class="sk-block" style="width:48px;height:48px;border-radius:14px;margin-bottom:20px;"></div>
                        <div class="sk-block" style="width:70%;height:18px;border-radius:6px;margin-bottom:12px;"></div>
                        <div class="sk-block" style="width:100%;height:14px;border-radius:4px;margin-bottom:6px;"></div>
                        <div class="sk-block" style="width:80%;height:14px;border-radius:4px;"></div>
                    </div>
                    @endfor
                </div>
            </div>
        </div>

        {{-- Why This Exists --}}
        <div class="sk-section-dark">
            <div style="max-width:1200px;margin:0 auto;" class="sk-grid-2">
                <div style="display:flex;flex-direction:column;gap:16px;">
                    <div class="sk-block" style="width:120px;height:12px;border-radius:4px;"></div>
                    <div class="sk-block" style="width:100%;height:36px;border-radius:8px;"></div>
                    <div class="sk-block" style="width:85%;height:36px;border-radius:8px;"></div>
                </div>
                <div style="display:flex;flex-direction:column;gap:16px;">
                    <div class="sk-block" style="width:100%;height:16px;border-radius:4px;"></div>
                    <div class="sk-block" style="width:90%;height:16px;border-radius:4px;"></div>
                    <div style="border-left:3px solid #2563eb;padding:20px 24px;background:rgba(37,99,235,0.1);border-radius:0 12px 12px 0;">
                        <div class="sk-block" style="width:100%;height:14px;border-radius:4px;margin-bottom:8px;"></div>
                        <div class="sk-block" style="width:90%;height:14px;border-radius:4px;"></div>
                    </div>
                    <div class="sk-block" style="width:140px;height:44px;border-radius:12px;margin-top:8px;"></div>
                </div>
            </div>
        </div>

        {{-- Real Results --}}
        <div style="background:#fff;padding:80px 48px;">
            <div style="max-width:1200px;margin:0 auto;">
                <div class="sk-grid-2" style="margin-bottom:40px;">
                    <div>
                        <div class="sk-block" style="width:120px;height:12px;border-radius:4px;margin-bottom:12px;"></div>
                        <div class="sk-block" style="width:80%;height:36px;border-radius:8px;"></div>
                    </div>
                    <div>
                        <div class="sk-block" style="width:100%;height:16px;border-radius:4px;margin-bottom:8px;"></div>
                        <div class="sk-block" style="width:90%;height:16px;border-radius:4px;"></div>
                    </div>
                </div>
                <div class="sk-grid-3">
                    @for($i = 0; $i < 3; $i++)
                    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:24px;padding:32px;">
                        <div class="sk-block" style="width:100px;height:22px;border-radius:100px;margin-bottom:16px;"></div>
                        <div class="sk-block" style="width:80%;height:20px;border-radius:6px;margin-bottom:12px;"></div>
                        <div class="sk-block" style="width:100%;height:14px;border-radius:4px;margin-bottom:6px;"></div>
                        <div class="sk-block" style="width:90%;height:14px;border-radius:4px;margin-bottom:6px;"></div>
                        <div class="sk-block" style="width:75%;height:14px;border-radius:4px;margin-bottom:24px;"></div>
                        <div style="border-top:1px solid #e2e8f0;padding-top:16px;">
                            <div class="sk-block" style="width:60%;height:18px;border-radius:6px;"></div>
                        </div>
                    </div>
                    @endfor
                </div>
            </div>
        </div>

        {{-- FAQ --}}
        <div class="sk-section-cream">
            <div style="max-width:800px;margin:0 auto;">
                <div style="text-align:center;margin-bottom:40px;">
                    <div class="sk-block" style="width:130px;height:12px;border-radius:4px;margin:0 auto 12px;"></div>
                    <div class="sk-block" style="width:260px;height:32px;border-radius:8px;margin:0 auto;"></div>
                </div>
                @for($i = 0; $i < 5; $i++)
                <div style="padding:20px 0;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;">
                    <div class="sk-block" style="width:{{ $i % 2 === 0 ? '55' : '70' }}%;height:18px;border-radius:6px;"></div>
                    <div class="sk-block" style="width:20px;height:20px;border-radius:4px;flex-shrink:0;"></div>
                </div>
                @endfor
            </div>
        </div>

        {{-- CTA --}}
        <div style="background:#0f172a;padding:60px 48px 100px;">
            <div style="max-width:1200px;margin:0 auto;">
                <div style="background:#2563eb;border-radius:32px;padding:60px;display:flex;justify-content:space-between;align-items:center;gap:40px;flex-wrap:wrap;">
                    <div style="flex:1;display:flex;flex-direction:column;gap:14px;">
                        <div class="sk-block" style="width:70%;height:36px;border-radius:8px;background:#3b82f6;"></div>
                        <div class="sk-block" style="width:55%;height:36px;border-radius:8px;background:#3b82f6;"></div>
                        <div class="sk-block" style="width:80%;height:16px;border-radius:4px;background:#3b82f6;margin-top:4px;"></div>
                    </div>
                    <div class="sk-block" style="width:160px;height:52px;border-radius:12px;background:#3b82f6;flex-shrink:0;"></div>
                </div>
            </div>
        </div>

    </div>
    {{-- ═══ END SKELETON ═══ --}}

    {{-- Real page content --}}
    <div id="page-content">
        @include('marketing.partials._hero')
        @include('marketing.partials._how-it-works')
        @include('marketing.partials._why')
        @include('marketing.partials._story')
        @include('marketing.partials._outcomes')
        @include('marketing.partials._situations')
        @include('marketing.partials._faq')
        @include('marketing.partials._cta')
    </div>

@endsection

@push('scripts')
<script>
    window.addEventListener('load', function () {
        const skeleton = document.getElementById('page-skeleton');
        const content  = document.getElementById('page-content');

        skeleton.classList.add('sk-hidden');
        content.classList.add('sk-ready');

        setTimeout(() => skeleton.remove(), 600);
    });
</script>
@endpush
