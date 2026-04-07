<footer style="background:#0f172a; border-top:1px solid rgba(248,250,252,0.08); padding:32px 48px;">
    <div style="max-width:1200px; margin:0 auto; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px;">

        <a href="{{ route('home') }}" style="display:flex; align-items:center; gap:10px; text-decoration:none;">
            <div style="width:30px; height:30px; background:#2563eb; border-radius:8px; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <img src="/icon.svg" alt="Unjamm" style="width:14px; height:14px; filter:brightness(0) invert(1);" />
            </div>
            <span style="font-weight:800; font-size:1rem; color:#f8fafc; letter-spacing:-0.02em;">{{ config('app.name') }}</span>
        </a>

        <p style="font-size:0.75rem; color:rgba(248,250,252,0.35); margin:0;">&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>

        <div style="display:flex; gap:20px; align-items:center; flex-wrap:wrap;">
            <a href="{{ route('home') }}"    style="font-size:0.75rem; color:rgba(248,250,252,0.5); text-decoration:none; transition:color 0.2s;" onmouseover="this.style.color='#f8fafc'" onmouseout="this.style.color='rgba(248,250,252,0.5)'">Home</a>
            <a href="{{ route('support') }}" style="font-size:0.75rem; color:rgba(248,250,252,0.5); text-decoration:none; transition:color 0.2s;" onmouseover="this.style.color='#f8fafc'" onmouseout="this.style.color='rgba(248,250,252,0.5)'">Support</a>
            <a href="{{ route('terms') }}"   style="font-size:0.75rem; color:rgba(248,250,252,0.5); text-decoration:none; transition:color 0.2s;" onmouseover="this.style.color='#f8fafc'" onmouseout="this.style.color='rgba(248,250,252,0.5)'">Terms</a>
            <a href="{{ route('privacy') }}" style="font-size:0.75rem; color:rgba(248,250,252,0.5); text-decoration:none; transition:color 0.2s;" onmouseover="this.style.color='#f8fafc'" onmouseout="this.style.color='rgba(248,250,252,0.5)'">Privacy Policy</a>
        </div>

    </div>
</footer>
