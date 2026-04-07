<header>
    <div class="hero-bg-shape"></div>
    <div class="hero-left">
        <div class="hero-tag"><span></span> Get Unstuck Today</div>
        <h1>Resolve complaints<br>with companies <em>faster.</em></h1>
        <p class="hero-sub">Unjamm helps you escalate disputes with banks, airlines, telecom companies, insurance companies and other institutions using structured escalation workflows and AI-generated complaint letters.</p>
        <div class="hero-actions">
            @auth
                @if(auth()->user()->role === 'admin')
                    <a href="{{ route('admin.dashboard') }}" class="btn-primary">
                        Go to Admin Panel
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    </a>
                @else
                    <a href="{{ route('user.dashboard') }}" class="btn-primary">
                        Go to Dashboard
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    </a>
                @endif
            @else
                <a href="{{ route('register') }}" class="btn-primary">
                    Start a Dispute
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </a>
            @endauth
            <a href="#how-it-works" class="btn-secondary">How It Works</a>
        </div>
    </div>

    <div class="hero-visual">
        <div class="hero-card">
            <div class="hero-card-label">Case #0034 · Municipal Office</div>
            <div class="hero-card-title">Pending Information Request</div>
            <div class="hero-card-body">Structured AI-drafted follow-up sent in Portuguese with formal escalation path.</div>
            <span class="hero-card-badge badge-resolved">
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                Resolved 8w
            </span>
        </div>
        <div class="hero-card">
            <div class="hero-card-label">Case #0041 · E-Commerce</div>
            <div class="hero-card-title">Amazon Refund Escalation</div>
            <div class="hero-card-body">Three-stage escalation culminating in Better Business Bureau complaint.</div>
            <span class="hero-card-badge badge-resolved">
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                Refund Obtained
            </span>
        </div>
        <div class="hero-card">
            <div class="hero-card-label">Case #0055 · Healthcare</div>
            <div class="hero-card-title">Public Health Waitlist</div>
            <div class="hero-card-body">Written request drafted to place parents on the correct waiting list.</div>
            <span class="hero-card-badge badge-inprogress">
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3" fill="currentColor"/></svg>
                Access secured
            </span>
        </div>
    </div>
</header>
