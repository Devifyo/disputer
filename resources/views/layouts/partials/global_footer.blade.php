<footer style="background:var(--bg);border-top:1px solid var(--border);">
    <div style="max-width:1220px;margin:0 auto;padding:40px 32px;display:flex;align-items:center;justify-content:space-between;gap:24px;flex-wrap:wrap;">
        <div style="display:flex;align-items:center;gap:11px;flex-wrap:wrap;">
            <span style="display:flex;color:var(--accent);">
                <svg viewBox="0 0 32 32" width="24" height="24" fill="none" aria-hidden="true"><path d="M5 23.5 L16 6 L27 23.5" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"></path><path d="M11 23.5 L16 15.5 L21 23.5" stroke="currentColor" stroke-opacity="0.4" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"></path><circle cx="16" cy="26" r="2.1" fill="currentColor"></circle></svg>
            </span>
            <span style="font-family:'Bricolage Grotesque',sans-serif;font-weight:700;font-size:18px;">Unjamm</span>
            <span style="font-size:13.5px;color:var(--faint);margin-left:8px;">&copy; {{ date('Y') }} Unjamm Inc. · Toronto, Canada</span>
        </div>
        <div style="display:flex;align-items:center;gap:26px;flex-wrap:wrap;">
            <a href="{{ route('home') }}" data-hover="color:var(--accent)" style="text-decoration:none;color:var(--muted);font-size:14px;font-weight:500;transition:color .2s;">Home</a>
            <a href="{{ route('support') }}" data-hover="color:var(--accent)" style="text-decoration:none;color:var(--muted);font-size:14px;font-weight:500;transition:color .2s;">Support</a>
            <a href="{{ route('privacy') }}" data-hover="color:var(--accent)" style="text-decoration:none;color:var(--muted);font-size:14px;font-weight:500;transition:color .2s;">Privacy</a>
            <a href="{{ route('terms') }}" data-hover="color:var(--accent)" style="text-decoration:none;color:var(--muted);font-size:14px;font-weight:500;transition:color .2s;">Terms</a>
        </div>
    </div>
</footer>
