<section id="why-this-exists">
    <div class="why-inner">
        <div class="why-left">
            <div class="section-eyebrow">Why This Exists</div>
            <h2 style="color:var(--paper)">Most people don't ignore important issues because they don't care.</h2>
        </div>
        <div class="why-right">
            <p>They ignore them because they don't know what to say, who to contact, or how to escalate properly.</p>
            <div class="why-highlight">
                <p>Unjamm was built for that moment. Not just to dispute charges — but to help you get unstuck. Through structured communication, guided escalation, and AI-assisted drafting.</p>
            </div>
            <p>We act as your human copilot when dealing with institutions — bringing clarity where there was only confusion, and momentum where there was only stall.</p>
            <div style="margin-top: 16px;">
                @auth
                    <a href="{{ auth()->user()->role === 'admin' ? route('admin.dashboard') : route('user.dashboard') }}" class="btn-primary" style="background: var(--accent); color: white; display: inline-flex; align-items: center; gap: 8px; padding: 14px 28px; border-radius: 12px; font-weight: 600; text-decoration: none;">
                        Go to Dashboard
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    </a>
                @else
                    <a href="{{ route('register') }}" class="btn-primary" style="background: var(--accent); color: white; display: inline-flex; align-items: center; gap: 8px; padding: 14px 28px; border-radius: 12px; font-weight: 600; text-decoration: none;">
                        Get Started
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    </a>
                @endauth
            </div>
        </div>
    </div>
</section>
