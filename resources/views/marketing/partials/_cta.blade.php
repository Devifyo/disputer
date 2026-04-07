<section id="cta">
    <div class="cta-inner">
        <div class="cta-box">
            <div class="cta-left">
                <h2 style="color: var(--white);">When something important stalls, Unjamm helps you take action.</h2>
                <p style="color: rgba(255,255,255,0.75);">Don't let bureaucracy, language barriers, or unclear processes hold you back any longer. Start your first case today - it's free to begin.</p>
            </div>
            <div class="cta-right">
                @auth
                    @if(auth()->user()->role === 'admin')
                        <a href="{{ route('admin.dashboard') }}" class="btn-primary" style="background: white; color: var(--accent) !important;">
                            Go to Admin Panel
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                        </a>
                    @else
                        <a href="{{ route('user.dashboard') }}" class="btn-primary" style="background: white; color: var(--accent) !important;">
                            Go to Dashboard
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                        </a>
                    @endif
                @else
                    <a href="{{ route('register') }}" class="btn-primary" style="background: white; color: var(--accent) !important;">
                        Get Started Today
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    </a>
                @endauth
            </div>
        </div>
    </div>
</section>
