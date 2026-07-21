@extends('layouts.marketing')

@section('title', 'Unjamm — Get the money airlines owe you')
@section('meta_description', 'Forward your flight confirmation to Unjamm. We monitor every flight in real time and file compensation claims automatically under EU 261, UK 261, Canada APPR, US DOT and the Montreal Convention. You confirm, we collect, you get paid.')

@php
    $ctaUrl = auth()->check()
        ? (auth()->user()->role === 'admin' ? route('admin.dashboard') : route('user.dashboard'))
        : route('register');

    $claimsEmail = config('services.inbound.claims_display');

    $mk = function ($id, $extra = '') {
        return 'https://images.unsplash.com/photo-' . $id . '?q=80&w=640&h=430&fit=crop&auto=format' . $extra;
    };
    $A = '1551748629-08d916ed6682';
    $B = '1545132147-d037e6c54cfd';
    $C = '1738682085346-6a6faff97fb1';
    $galleryItems = [
        ['img' => $mk($A, '&crop=top'),          'route' => 'LHR → JFK', 'status' => 'DELAYED 3H', 'amt' => '£520',      'tone' => '#FFC53D'],
        ['img' => $mk($B, '&crop=bottom'),       'route' => 'YYZ → LHR', 'status' => 'CANCELLED',  'amt' => 'CAD $1,000','tone' => '#FF6B6B'],
        ['img' => $mk($C, ''),                    'route' => 'CDG → YUL', 'status' => 'DELAYED 4H', 'amt' => '€600',      'tone' => '#FFC53D'],
        ['img' => $mk($A, '&flip=h&crop=edges'), 'route' => 'SFO → LHR', 'status' => 'DENIED BRD', 'amt' => 'REFUND',    'tone' => '#FF9F45'],
        ['img' => $mk($B, '&flip=h'),            'route' => 'AMS → YYZ', 'status' => 'BAG DELAY',  'amt' => 'CAD $2,350','tone' => '#7CC5FF'],
        ['img' => $mk($C, '&crop=top&flip=h'),   'route' => 'DXB → LHR', 'status' => 'DELAYED 3H', 'amt' => '€600',      'tone' => '#FFC53D'],
        ['img' => $mk($A, '&crop=bottom'),       'route' => 'MAD → JFK', 'status' => 'CANCELLED',  'amt' => '€600',      'tone' => '#FF6B6B'],
        ['img' => $mk($B, '&crop=edges&flip=h'), 'route' => 'FRA → YVR', 'status' => 'DIVERTED',   'amt' => 'CAD $1,000','tone' => '#FFC53D'],
    ];
    $stripLoop = array_merge($galleryItems, $galleryItems);
@endphp

@section('content')

  <!-- HERO -->
  <header id="top" style="position:relative;min-height:100vh;display:flex;flex-direction:column;overflow:hidden;background:#090C11;">
    <div id="ujHeroBg" style="position:absolute;inset:-18% 0;z-index:0;overflow:hidden;will-change:transform;background:#090C11;">
      <img src="https://images.unsplash.com/photo-1551748629-08d916ed6682?q=80&w=2400&auto=format&fit=crop" alt="Aerial view above a sea of clouds at dawn" decoding="async" fetchpriority="high" style="width:100%;height:100%;object-fit:cover;object-position:center 42%;transform-origin:center;animation:ujZoom 24s ease-in-out infinite alternate;will-change:transform;backface-visibility:hidden;"/>
    </div>
    <div style="position:absolute;inset:0;z-index:1;background:linear-gradient(180deg,var(--bg) 0%,rgba(9,12,17,.42) 26%,rgba(9,12,17,.30) 52%,rgba(9,12,17,.72) 82%,var(--bg) 100%);"></div>
    <div style="position:absolute;inset:0;z-index:1;background:linear-gradient(90deg,rgba(9,12,17,.72) 0%,rgba(9,12,17,.30) 42%,rgba(9,12,17,0) 70%);"></div>

    <div id="ujHeroContent" style="position:relative;z-index:2;flex:1;display:flex;align-items:center;will-change:transform;backface-visibility:hidden;">
      <div style="max-width:1220px;margin:0 auto;padding:150px 32px 60px;width:100%;">
        <div style="max-width:560px;">
          <h1 data-reveal style="opacity:0;transform:translateY(20px);font-family:'Bricolage Grotesque',sans-serif;font-weight:700;font-size:clamp(44px,5.2vw,72px);line-height:1.01;letter-spacing:-.03em;margin:0;color:#fff;text-shadow:0 2px 30px rgba(0,0,0,.35);">Get the money<br>airlines owe you.</h1>
          <p data-reveal style="opacity:0;transform:translateY(20px);font-size:18px;line-height:1.6;color:#D3D9E0;max-width:500px;margin:22px 0 0;text-shadow:0 1px 16px rgba(0,0,0,.4);">Forward your flight confirmation to Unjamm. We monitor every flight in real time and file compensation claims automatically under EU 261, UK 261, Canada APPR, US DOT, and the Montreal Convention. You confirm, we collect, you get paid.</p>

          <div data-reveal style="opacity:0;transform:translateY(20px);margin-top:30px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.14);border-radius:20px;padding:22px;max-width:500px;backdrop-filter:blur(14px);box-shadow:0 30px 60px -30px rgba(0,0,0,.7);">
            <div style="font-size:12px;font-weight:700;color:#C6CDD6;letter-spacing:.08em;text-transform:uppercase;">Forward your first itinerary to</div>
            <div style="display:flex;align-items:center;gap:12px;margin-top:12px;">
              <code style="flex:1;font-family:ui-monospace,'SF Mono',Menlo,monospace;font-size:17px;font-weight:600;color:#fff;background:rgba(0,0,0,.32);border:1px solid rgba(255,255,255,.12);border-radius:12px;padding:13px 16px;">{{ $claimsEmail }}</code>
              <button type="button" onclick="ujCopyEmail(this)" data-hover="background:var(--accent2);transform:translateY(-1px)" style="display:inline-flex;align-items:center;gap:7px;background:var(--accent);color:var(--on-accent);border:none;border-radius:12px;padding:13px 18px;font-family:'Hanken Grotesk',sans-serif;font-weight:700;font-size:15px;cursor:pointer;transition:background .2s ease,transform .15s ease;white-space:nowrap;">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="11" height="11" rx="2.2"></rect><path d="M6 15H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v1"></path></svg>
                <span class="uj-copy-label">Copy</span>
              </button>
            </div>
            <a href="{{ $ctaUrl }}" style="display:inline-block;margin-top:14px;font-size:14px;font-weight:600;color:var(--accent);text-decoration:none;">Or create an account first →</a>
          </div>
          <div data-reveal style="opacity:0;transform:translateY(20px);margin-top:16px;font-size:13.5px;color:#B6BDC7;font-weight:500;">Works with any email provider · No card required</div>
        </div>
      </div>
    </div>

    <div style="position:relative;z-index:2;border-top:1px solid rgba(255,255,255,.1);background:rgba(9,12,17,.82);backdrop-filter:blur(14px);">
      <div id="ujStats" style="max-width:1220px;margin:0 auto;padding:34px 32px;display:grid;grid-template-columns:repeat(4,1fr);gap:24px;">
        <div data-reveal style="opacity:0;transform:translateY(16px);">
          <div style="font-family:'Bricolage Grotesque',sans-serif;font-weight:700;font-size:40px;letter-spacing:-.02em;color:var(--accent);"><span data-count-to="680" data-count-prefix="$">$0</span></div>
          <div style="font-size:14px;font-weight:500;color:#D2D8E0;margin-top:4px;">avg. payout per delayed flight</div>
        </div>
        <div data-reveal style="opacity:0;transform:translateY(16px);">
          <div style="font-family:'Bricolage Grotesque',sans-serif;font-weight:700;font-size:40px;letter-spacing:-.02em;color:var(--accent);"><span data-count-to="12" data-count-suffix=" days">0 days</span></div>
          <div style="font-size:14px;font-weight:500;color:#D2D8E0;margin-top:4px;">avg. time to payout</div>
        </div>
        <div data-reveal style="opacity:0;transform:translateY(16px);">
          <div style="font-family:'Bricolage Grotesque',sans-serif;font-weight:700;font-size:40px;letter-spacing:-.02em;color:var(--accent);"><span data-count-to="25" data-count-suffix="%">0%</span></div>
          <div style="font-size:14px;font-weight:500;color:#D2D8E0;margin-top:4px;">success fee — no win, no fee</div>
        </div>
        <div data-reveal style="opacity:0;transform:translateY(16px);">
          <div style="font-family:'Bricolage Grotesque',sans-serif;font-weight:700;font-size:40px;letter-spacing:-.02em;color:var(--accent);"><span data-count-to="108">0</span></div>
          <div style="font-size:14px;font-weight:500;color:#D2D8E0;margin-top:4px;">airlines & OTAs supported</div>
        </div>
      </div>
    </div>
  </header>

  <!-- LIVE BOARD -->
  <section id="live" style="padding:110px 0;background:var(--bg);position:relative;overflow:hidden;">
    <div id="ujAurora" style="position:absolute;top:-20%;left:20%;width:640px;height:640px;border-radius:50%;background:radial-gradient(circle,rgba(63,203,148,.16),rgba(63,203,148,0) 66%);filter:blur(20px);z-index:0;pointer-events:none;animation:ujAurora 18s ease-in-out infinite;"></div>
    <div style="max-width:1220px;margin:0 auto;padding:0 32px;position:relative;z-index:1;">
      <div data-reveal style="opacity:0;transform:translateY(18px);display:flex;align-items:flex-end;justify-content:space-between;gap:24px;flex-wrap:wrap;">
        <div style="max-width:640px;">
          <div style="font-size:13px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--accent);">Live disruption feed</div>
          <h2 style="font-family:'Bricolage Grotesque',sans-serif;font-weight:700;font-size:clamp(32px,3.6vw,48px);letter-spacing:-.025em;line-height:1.05;margin:16px 0 0;">We're watching, right now.</h2>
        </div>
        <div style="display:inline-flex;align-items:center;gap:12px;flex-wrap:wrap;">
          <button type="button" onclick="ujOpenSearch()" aria-haspopup="dialog"
                  style="display:inline-flex;align-items:center;gap:9px;background:var(--accent);color:#04120C;border:none;padding:11px 20px;border-radius:999px;font-size:14px;font-weight:700;letter-spacing:.01em;cursor:pointer;transition:transform .16s ease,box-shadow .16s ease;box-shadow:0 10px 30px -12px rgba(63,203,148,.7);"
                  onmouseover="this.style.transform='translateY(-1px)';this.style.boxShadow='0 16px 36px -12px rgba(63,203,148,.85)'"
                  onmouseout="this.style.transform='';this.style.boxShadow='0 10px 30px -12px rgba(63,203,148,.7)'">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            Search your flight
          </button>
          <div style="display:inline-flex;align-items:center;gap:9px;background:rgba(255,90,90,.12);border:1px solid rgba(255,90,90,.35);color:#FF8A8A;padding:8px 14px;border-radius:999px;font-size:13px;font-weight:700;letter-spacing:.03em;">
            <span style="width:8px;height:8px;border-radius:50%;background:#FF5A5A;display:inline-block;animation:ujBlink 1.4s infinite;"></span>LIVE
          </div>
        </div>
      </div>

      <div id="ujBoard" data-reveal style="opacity:0;transform:translateY(22px);margin-top:32px;background:#090C11;border:1px solid rgba(255,255,255,.08);border-radius:18px;padding:14px;box-shadow:0 40px 90px -50px rgba(0,0,0,.9),inset 0 1px 0 rgba(255,255,255,.04);overflow-x:auto;">
        <div style="display:grid;grid-template-columns:112px 1fr 168px 120px;gap:14px;padding:10px 14px 12px;font-family:ui-monospace,'SF Mono',Menlo,monospace;font-size:11px;font-weight:700;letter-spacing:.12em;color:var(--faint);text-transform:uppercase;border-bottom:1px solid rgba(255,255,255,.07);">
          <div>Flight</div><div>Route</div><div>Status</div><div style="text-align:right;">You get</div>
        </div>
        <div id="ujRows" style="perspective:600px;"></div>
      </div>

      <div style="margin-top:22px;overflow:hidden;-webkit-mask-image:linear-gradient(90deg,transparent,#000 8%,#000 92%,transparent);mask-image:linear-gradient(90deg,transparent,#000 8%,#000 92%,transparent);">
        <div id="ujMarquee" style="display:flex;gap:12px;width:max-content;animation:ujMarquee 34s linear infinite;">
          @foreach(['LHR → JFK','YYZ → LHR','CDG → YUL','SFO → LHR','AMS → YYZ','DXB → LHR','MAD → JFK','BCN → YUL','LHR → JFK','YYZ → LHR','CDG → YUL','SFO → LHR','AMS → YYZ','DXB → LHR','MAD → JFK','BCN → YUL'] as $r)
          <span style="font-family:ui-monospace,monospace;font-size:13px;font-weight:600;color:var(--muted);background:var(--card);border:1px solid var(--border);border-radius:999px;padding:8px 16px;white-space:nowrap;">{{ $r }}</span>
          @endforeach
        </div>
      </div>
    </div>
  </section>

  <!-- HOW IT WORKS -->
  <section id="how" style="padding:120px 0;background:var(--bg);">
    <div style="max-width:1220px;margin:0 auto;padding:0 32px;">
      <div data-reveal style="opacity:0;transform:translateY(18px);max-width:720px;">
        <div style="font-size:13px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--accent);">How it works</div>
        <h2 style="font-family:'Bricolage Grotesque',sans-serif;font-weight:700;font-size:clamp(32px,3.6vw,48px);letter-spacing:-.025em;line-height:1.05;margin:16px 0 14px;">Forward once. We do the rest.</h2>
        <p style="font-size:17px;line-height:1.6;color:var(--muted);margin:0;">No inbox connection, no apps to install. Forward a confirmation, sign once, and Unjamm runs quietly in the background — only reaching out when there's money to collect.</p>
      </div>
      <div id="ujHow" style="display:grid;grid-template-columns:repeat(4,1fr);gap:20px;margin-top:52px;">
        <div data-reveal data-hover="transform:translateY(-4px);border-color:rgba(63,203,148,.35);box-shadow:0 26px 50px -30px rgba(0,0,0,.7)" style="opacity:0;transform:translateY(22px);background:var(--card);border:1px solid var(--border);border-radius:20px;padding:26px;transition:transform .3s ease,box-shadow .3s ease,border-color .3s ease;">
          <div style="width:44px;height:44px;display:flex;align-items:center;justify-content:center;border-radius:12px;background:var(--chip);color:var(--accent);">
            <svg viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2.4"></rect><path d="M3.5 6.5 12 12.5 20.5 6.5"></path></svg>
          </div>
          <div style="font-family:'Bricolage Grotesque',sans-serif;font-weight:700;font-size:13px;color:var(--faint);margin-top:18px;letter-spacing:.04em;">01</div>
          <h3 style="font-family:'Bricolage Grotesque',sans-serif;font-weight:600;font-size:19px;letter-spacing:-.01em;margin:6px 0 8px;">Forward your flight confirmation</h3>
          <p style="font-size:14.5px;line-height:1.55;color:var(--muted);margin:0;">Send it to {{ $claimsEmail }} — works with Gmail, Outlook, iCloud, anything.</p>
        </div>
        <div data-reveal data-hover="transform:translateY(-4px);border-color:rgba(63,203,148,.35);box-shadow:0 26px 50px -30px rgba(0,0,0,.7)" style="opacity:0;transform:translateY(22px);background:var(--card);border:1px solid var(--border);border-radius:20px;padding:26px;transition:transform .3s ease,box-shadow .3s ease,border-color .3s ease;">
          <div style="width:44px;height:44px;display:flex;align-items:center;justify-content:center;border-radius:12px;background:var(--chip);color:var(--accent);">
            <svg viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 20c3-1 4.5-2.5 8-9 1.2-2.2 3-3.4 4.5-2 1.4 1.3.6 3.2-1 4.4-1.4 1-3 .8-3-.6"></path><path d="M15.5 6.5l2-2 2 2-2 2z"></path></svg>
          </div>
          <div style="font-family:'Bricolage Grotesque',sans-serif;font-weight:700;font-size:13px;color:var(--faint);margin-top:18px;letter-spacing:.04em;">02</div>
          <h3 style="font-family:'Bricolage Grotesque',sans-serif;font-weight:600;font-size:19px;letter-spacing:-.01em;margin:6px 0 8px;">Sign your one-time Power of Attorney</h3>
          <p style="font-size:14.5px;line-height:1.55;color:var(--muted);margin:0;">Takes 60 seconds and stays valid for 2 years. Sign once, never again.</p>
        </div>
        <div data-reveal data-hover="transform:translateY(-4px);border-color:rgba(63,203,148,.35);box-shadow:0 26px 50px -30px rgba(0,0,0,.7)" style="opacity:0;transform:translateY(22px);background:var(--card);border:1px solid var(--border);border-radius:20px;padding:26px;transition:transform .3s ease,box-shadow .3s ease,border-color .3s ease;">
          <div style="width:44px;height:44px;display:flex;align-items:center;justify-content:center;border-radius:12px;background:var(--chip);color:var(--accent);">
            <svg viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="2"></circle><path d="M12 3a9 9 0 0 1 9 9M12 6.5a5.5 5.5 0 0 1 5.5 5.5"></path></svg>
          </div>
          <div style="font-family:'Bricolage Grotesque',sans-serif;font-weight:700;font-size:13px;color:var(--faint);margin-top:18px;letter-spacing:.04em;">03</div>
          <h3 style="font-family:'Bricolage Grotesque',sans-serif;font-weight:600;font-size:19px;letter-spacing:-.01em;margin:6px 0 8px;">We monitor every flight 24/7</h3>
          <p style="font-size:14.5px;line-height:1.55;color:var(--muted);margin:0;">Every itinerary is watched in real time via FlightAware, against the same feeds airlines use.</p>
        </div>
        <div data-reveal data-hover="transform:translateY(-4px);box-shadow:0 26px 50px -30px rgba(63,203,148,.4)" style="opacity:0;transform:translateY(22px);background:linear-gradient(160deg,rgba(63,203,148,.16),rgba(63,203,148,.04));border:1px solid rgba(63,203,148,.4);border-radius:20px;padding:26px;transition:transform .3s ease,box-shadow .3s ease;">
          <div style="width:44px;height:44px;display:flex;align-items:center;justify-content:center;border-radius:12px;background:var(--accent);color:var(--on-accent);">
            <svg viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 3 11 13"></path><path d="M21 3 14.5 21 11 13 3 9.5z"></path></svg>
          </div>
          <div style="font-family:'Bricolage Grotesque',sans-serif;font-weight:700;font-size:13px;color:var(--accent);margin-top:18px;letter-spacing:.04em;">04</div>
          <h3 style="font-family:'Bricolage Grotesque',sans-serif;font-weight:600;font-size:19px;letter-spacing:-.01em;margin:6px 0 8px;">You confirm, we collect, you get paid</h3>
          <p style="font-size:14.5px;line-height:1.55;color:var(--muted);margin:0;">On a qualifying disruption you tap confirm — then we file, collect from the airline, and pay you.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- COVERAGE -->
  <section id="coverage" style="padding:110px 0;background:var(--bg2);">
    <div style="max-width:1220px;margin:0 auto;padding:0 32px;">
      <div data-reveal style="opacity:0;transform:translateY(18px);max-width:720px;">
        <div style="font-size:13px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--accent);">Coverage</div>
        <h2 style="font-family:'Bricolage Grotesque',sans-serif;font-weight:700;font-size:clamp(32px,3.6vw,48px);letter-spacing:-.025em;line-height:1.05;margin:16px 0 14px;">Five regulations. One engine.</h2>
        <p style="font-size:17px;line-height:1.6;color:var(--muted);margin:0;">We evaluate every disrupted flight against all of them and file under the rule that pays you the most.</p>
      </div>
      <div id="ujCov" style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-top:48px;">
        <div data-reveal style="opacity:0;transform:translateY(22px);background:var(--card);border:1px solid var(--border);border-radius:18px;padding:24px;display:flex;flex-direction:column;gap:10px;">
          <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;">
            <div style="font-family:'Bricolage Grotesque',sans-serif;font-weight:700;font-size:19px;letter-spacing:-.01em;">EU 261</div>
            <div style="font-size:12.5px;font-weight:700;color:var(--accent);background:var(--chip);padding:5px 10px;border-radius:999px;white-space:nowrap;">up to €600</div>
          </div>
          <p style="font-size:13.5px;line-height:1.5;color:var(--muted);margin:0;">Delays 3h+, cancellations, denied boarding on EU flights.</p>
        </div>
        <div data-reveal style="opacity:0;transform:translateY(22px);background:var(--card);border:1px solid var(--border);border-radius:18px;padding:24px;display:flex;flex-direction:column;gap:10px;">
          <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;">
            <div style="font-family:'Bricolage Grotesque',sans-serif;font-weight:700;font-size:19px;letter-spacing:-.01em;">UK 261</div>
            <div style="font-size:12.5px;font-weight:700;color:var(--accent);background:var(--chip);padding:5px 10px;border-radius:999px;white-space:nowrap;">up to £520</div>
          </div>
          <p style="font-size:13.5px;line-height:1.5;color:var(--muted);margin:0;">The UK's retained version of EU 261, post-Brexit.</p>
        </div>
        <div data-reveal style="opacity:0;transform:translateY(22px);background:var(--card);border:1px solid var(--border);border-radius:18px;padding:24px;display:flex;flex-direction:column;gap:10px;">
          <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;">
            <div style="font-family:'Bricolage Grotesque',sans-serif;font-weight:700;font-size:19px;letter-spacing:-.01em;">Canada APPR</div>
            <div style="font-size:12.5px;font-weight:700;color:var(--accent);background:var(--chip);padding:5px 10px;border-radius:999px;white-space:nowrap;">up to CAD $1,000</div>
          </div>
          <p style="font-size:13.5px;line-height:1.5;color:var(--muted);margin:0;">Delays, cancellations & tarmac waits on flights to/from/within Canada.</p>
        </div>
        <div data-reveal style="opacity:0;transform:translateY(22px);background:var(--card);border:1px solid var(--border);border-radius:18px;padding:24px;display:flex;flex-direction:column;gap:10px;">
          <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;">
            <div style="font-family:'Bricolage Grotesque',sans-serif;font-weight:700;font-size:19px;letter-spacing:-.01em;">US DOT</div>
            <div style="font-size:12.5px;font-weight:700;color:var(--accent);background:var(--chip);padding:5px 10px;border-radius:999px;white-space:nowrap;">full refund</div>
          </div>
          <p style="font-size:13.5px;line-height:1.5;color:var(--muted);margin:0;">Mandatory cash refunds for cancelled or significantly changed US flights.</p>
        </div>
        <div data-reveal style="opacity:0;transform:translateY(22px);background:var(--card);border:1px solid var(--border);border-radius:18px;padding:24px;display:flex;flex-direction:column;gap:10px;">
          <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;">
            <div style="font-family:'Bricolage Grotesque',sans-serif;font-weight:700;font-size:19px;letter-spacing:-.01em;">Montreal Convention</div>
            <div style="font-size:12.5px;font-weight:700;color:var(--accent);background:var(--chip);padding:5px 10px;border-radius:999px;white-space:nowrap;">up to ~CAD $2,350</div>
          </div>
          <p style="font-size:13.5px;line-height:1.5;color:var(--muted);margin:0;">Lost, damaged or delayed baggage on international itineraries.</p>
        </div>
        <div data-reveal style="opacity:0;transform:translateY(22px);background:linear-gradient(160deg,rgba(63,203,148,.14),rgba(63,203,148,.03));border:1px solid rgba(63,203,148,.35);border-radius:18px;padding:24px;display:flex;flex-direction:column;justify-content:center;gap:8px;">
          <div style="font-family:'Bricolage Grotesque',sans-serif;font-weight:600;font-size:17px;letter-spacing:-.01em;color:var(--accent);">We file under the rule that pays most.</div>
          <p style="font-size:13.5px;line-height:1.5;color:var(--muted);margin:0;">One disruption is checked against all five — automatically.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- GALLERY -->
  <section id="gallery" style="padding:110px 0;background:var(--bg);overflow:hidden;">
    <div style="max-width:1220px;margin:0 auto;padding:0 32px;">
      <div data-reveal style="opacity:0;transform:translateY(18px);max-width:720px;">
        <div style="font-size:13px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--accent);">Every sky, watched</div>
        <h2 style="font-family:'Bricolage Grotesque',sans-serif;font-weight:700;font-size:clamp(32px,3.6vw,48px);letter-spacing:-.025em;line-height:1.05;margin:16px 0 14px;">Real routes. Real payouts.</h2>
        <p style="font-size:17px;line-height:1.6;color:var(--muted);margin:0;">A rolling snapshot of the disruptions we monitor around the clock — and what each one puts back in a passenger's pocket.</p>
      </div>
    </div>

    <div data-reveal style="opacity:0;transform:translateY(22px);margin-top:44px;display:flex;flex-direction:column;gap:18px;-webkit-mask-image:linear-gradient(90deg,transparent,#000 5%,#000 95%,transparent);mask-image:linear-gradient(90deg,transparent,#000 5%,#000 95%,transparent);">
      <div style="overflow:visible;">
        <div data-hover="animation-play-state:paused" style="display:flex;gap:18px;width:max-content;animation:ujMarquee 64s linear infinite;padding:6px 9px;">
          @foreach($stripLoop as $card)
            <div style="position:relative;flex:0 0 auto;width:300px;height:198px;border-radius:16px;overflow:hidden;border:1px solid var(--border);box-shadow:0 24px 55px -32px rgba(0,0,0,.85);">
              <img src="{{ $card['img'] }}" alt="Aerial sky" loading="lazy" style="width:100%;height:100%;object-fit:cover;display:block;"/>
              <div style="position:absolute;inset:0;background:linear-gradient(180deg,rgba(6,9,13,0) 38%,rgba(6,9,13,.88) 100%);"></div>
              <div style="position:absolute;left:14px;right:14px;bottom:12px;">
                <div style="font-family:'Bricolage Grotesque',sans-serif;font-weight:700;font-size:18px;color:#fff;">{{ $card['route'] }}</div>
                <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-top:7px;">
                  <span style="display:inline-flex;align-items:center;gap:6px;font-size:11px;font-weight:700;letter-spacing:.03em;color:#EAEEF3;background:rgba(0,0,0,.42);border:1px solid rgba(255,255,255,.16);padding:4px 9px;border-radius:999px;"><span style="width:7px;height:7px;border-radius:50%;background:{{ $card['tone'] }};display:inline-block;"></span>{{ $card['status'] }}</span>
                  <span style="font-family:'Bricolage Grotesque',sans-serif;font-weight:700;font-size:15px;color:var(--accent);">{{ $card['amt'] }}</span>
                </div>
              </div>
            </div>
          @endforeach
        </div>
      </div>
      <div style="overflow:visible;">
        <div data-hover="animation-play-state:paused" style="display:flex;gap:18px;width:max-content;animation:ujMarquee 78s linear infinite reverse;padding:6px 9px;">
          @foreach($stripLoop as $card)
            <div style="position:relative;flex:0 0 auto;width:300px;height:198px;border-radius:16px;overflow:hidden;border:1px solid var(--border);box-shadow:0 24px 55px -32px rgba(0,0,0,.85);">
              <img src="{{ $card['img'] }}" alt="Aerial sky" loading="lazy" style="width:100%;height:100%;object-fit:cover;display:block;"/>
              <div style="position:absolute;inset:0;background:linear-gradient(180deg,rgba(6,9,13,0) 38%,rgba(6,9,13,.88) 100%);"></div>
              <div style="position:absolute;left:14px;right:14px;bottom:12px;">
                <div style="font-family:'Bricolage Grotesque',sans-serif;font-weight:700;font-size:18px;color:#fff;">{{ $card['route'] }}</div>
                <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-top:7px;">
                  <span style="display:inline-flex;align-items:center;gap:6px;font-size:11px;font-weight:700;letter-spacing:.03em;color:#EAEEF3;background:rgba(0,0,0,.42);border:1px solid rgba(255,255,255,.16);padding:4px 9px;border-radius:999px;"><span style="width:7px;height:7px;border-radius:50%;background:{{ $card['tone'] }};display:inline-block;"></span>{{ $card['status'] }}</span>
                  <span style="font-family:'Bricolage Grotesque',sans-serif;font-weight:700;font-size:15px;color:var(--accent);">{{ $card['amt'] }}</span>
                </div>
              </div>
            </div>
          @endforeach
        </div>
      </div>
    </div>
  </section>

  <!-- WHY -->
  <section id="why" style="padding:110px 0;background:var(--bg);">
    <div style="max-width:1220px;margin:0 auto;padding:0 32px;">
      <div data-reveal style="opacity:0;transform:translateY(18px);max-width:720px;">
        <div style="font-size:13px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--accent);">Why Unjamm</div>
        <h2 style="font-family:'Bricolage Grotesque',sans-serif;font-weight:700;font-size:clamp(32px,3.6vw,48px);letter-spacing:-.025em;line-height:1.05;margin:16px 0 0;">Built to actually get you paid.</h2>
      </div>
      <div id="ujWhy" style="display:grid;grid-template-columns:repeat(3,1fr);gap:24px;margin-top:48px;">
        <div data-reveal style="opacity:0;transform:translateY(22px);border-top:2px solid var(--accent);padding-top:22px;">
          <div style="font-family:'Bricolage Grotesque',sans-serif;font-weight:700;font-size:14px;color:var(--accent);">01</div>
          <h3 style="font-family:'Bricolage Grotesque',sans-serif;font-weight:600;font-size:22px;letter-spacing:-.015em;margin:12px 0 10px;">Real-time monitoring</h3>
          <p style="font-size:15px;line-height:1.6;color:var(--muted);margin:0;">Most services wait for you to notice and file. Unjamm watches every flight against live data and starts the claim before you've left the gate.</p>
        </div>
        <div data-reveal style="opacity:0;transform:translateY(22px);border-top:2px solid var(--accent);padding-top:22px;">
          <div style="font-family:'Bricolage Grotesque',sans-serif;font-weight:700;font-size:14px;color:var(--accent);">02</div>
          <h3 style="font-family:'Bricolage Grotesque',sans-serif;font-weight:600;font-size:22px;letter-spacing:-.015em;margin:12px 0 10px;">Transparent 25% fee</h3>
          <p style="font-size:15px;line-height:1.6;color:var(--muted);margin:0;">One flat success fee on what we recover — no hidden cuts, no monthly charge on the free plan, no surprise deductions at payout.</p>
        </div>
        <div data-reveal style="opacity:0;transform:translateY(22px);border-top:2px solid var(--accent);padding-top:22px;">
          <div style="font-family:'Bricolage Grotesque',sans-serif;font-weight:700;font-size:14px;color:var(--accent);">03</div>
          <h3 style="font-family:'Bricolage Grotesque',sans-serif;font-weight:600;font-size:22px;letter-spacing:-.015em;margin:12px 0 10px;">Multi-regulation engine</h3>
          <p style="font-size:15px;line-height:1.6;color:var(--muted);margin:0;">EU 261, UK 261, APPR, US DOT and the Montreal Convention in one engine. We pick the rule that pays you the most and file under it.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- PRICING -->
  <section id="pricing" style="padding:110px 0;background:var(--bg2);">
    <div style="max-width:1220px;margin:0 auto;padding:0 32px;">
      <div data-reveal style="opacity:0;transform:translateY(18px);max-width:720px;">
        <div style="font-size:13px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--accent);">Pricing</div>
        <h2 style="font-family:'Bricolage Grotesque',sans-serif;font-weight:700;font-size:clamp(32px,3.6vw,48px);letter-spacing:-.025em;line-height:1.05;margin:16px 0 0;">You only pay when we win.</h2>
      </div>
      <div id="ujPrice" style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-top:48px;align-items:start;">
        <div data-reveal style="opacity:0;transform:translateY(22px);background:var(--card);border:1px solid var(--border);border-radius:24px;padding:34px;">
          <div style="font-family:'Bricolage Grotesque',sans-serif;font-weight:700;font-size:24px;letter-spacing:-.01em;">Unjamm Free</div>
          <div style="font-size:15px;color:var(--muted);margin-top:4px;">For anyone who flies.</div>
          <div style="display:flex;align-items:baseline;gap:8px;margin:24px 0 4px;">
            <span style="font-family:'Bricolage Grotesque',sans-serif;font-weight:700;font-size:44px;color:var(--text);">25%</span>
            <span style="font-size:15px;color:var(--muted);">success fee — no win, no fee</span>
          </div>
          <div style="height:1px;background:var(--border);margin:24px 0;"></div>
          <ul style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:13px;">
            <li style="display:flex;gap:11px;font-size:15px;color:var(--text);"><span style="color:var(--accent);font-weight:700;flex-shrink:0;">✓</span>Unlimited flight monitoring</li>
            <li style="display:flex;gap:11px;font-size:15px;color:var(--text);"><span style="color:var(--accent);font-weight:700;flex-shrink:0;">✓</span>Automatic claim filing</li>
            <li style="display:flex;gap:11px;font-size:15px;color:var(--text);"><span style="color:var(--accent);font-weight:700;flex-shrink:0;">✓</span>All five regulations</li>
            <li style="display:flex;gap:11px;font-size:15px;color:var(--text);"><span style="color:var(--accent);font-weight:700;flex-shrink:0;">✓</span>Forward from any email provider</li>
          </ul>
          <a href="{{ $ctaUrl }}" data-hover="border-color:var(--accent);color:var(--accent)" style="display:flex;align-items:center;justify-content:center;margin-top:30px;background:transparent;color:var(--text);border:1px solid var(--border2);padding:14px;border-radius:12px;text-decoration:none;font-weight:600;font-size:15px;transition:background .2s,border-color .2s,color .2s;">Start free</a>
        </div>

        <div data-reveal style="opacity:0;transform:translateY(22px);background:var(--card);border:1.5px solid var(--accent);border-radius:24px;padding:34px;position:relative;box-shadow:0 0 0 4px rgba(63,203,148,.08),0 40px 80px -44px rgba(63,203,148,.35);">
          <div style="position:absolute;top:26px;right:26px;background:var(--accent);color:var(--on-accent);font-size:12px;font-weight:700;padding:6px 12px;border-radius:999px;">Most popular</div>
          <div style="font-family:'Bricolage Grotesque',sans-serif;font-weight:700;font-size:24px;letter-spacing:-.01em;">Unjamm Plus</div>
          <div style="font-size:15px;color:var(--muted);margin-top:4px;">For frequent flyers & families.</div>
          <div style="display:flex;align-items:baseline;gap:8px;margin:24px 0 4px;">
            <span style="font-family:'Bricolage Grotesque',sans-serif;font-weight:700;font-size:44px;color:var(--accent);">25%</span>
            <span style="font-size:15px;color:var(--muted);">success fee — no win, no fee</span>
          </div>
          <div style="font-size:14px;color:var(--accent);font-weight:600;">Same fee. More benefits.</div>
          <div style="height:1px;background:var(--border);margin:24px 0;"></div>
          <ul style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:13px;">
            <li style="display:flex;gap:11px;font-size:15px;color:var(--text);"><span style="color:var(--accent);font-weight:700;flex-shrink:0;">✓</span>Priority filing queue</li>
            <li style="display:flex;gap:11px;font-size:15px;color:var(--text);"><span style="color:var(--accent);font-weight:700;flex-shrink:0;">✓</span>Multi-passenger / family accounts</li>
            <li style="display:flex;gap:11px;font-size:15px;color:var(--text);"><span style="color:var(--accent);font-weight:700;flex-shrink:0;">✓</span>Next-business-day payout via Wise</li>
            <li style="display:flex;gap:11px;font-size:15px;color:var(--text);"><span style="color:var(--accent);font-weight:700;flex-shrink:0;">✓</span>Lounge access on delays <span style="color:var(--faint);">(coming Year 2 via Priority Pass)</span></li>
          </ul>
          <a href="{{ $ctaUrl }}" data-hover="transform:translateY(-1px);background:var(--accent2)" style="display:flex;align-items:center;justify-content:center;margin-top:30px;background:var(--accent);color:var(--on-accent);padding:14px;border-radius:12px;text-decoration:none;font-weight:700;font-size:15px;transition:transform .2s,background .2s;">Get Plus — $9/month</a>
        </div>
      </div>
    </div>
  </section>

  <!-- PROVIDER STRIP -->
  <section style="padding:0 0 96px;background:var(--bg2);">
    <div style="max-width:1220px;margin:0 auto;padding:0 32px;">
      <div data-reveal style="opacity:0;transform:translateY(18px);display:flex;flex-wrap:wrap;gap:16px 40px;align-items:center;justify-content:center;border-top:1px solid var(--border);padding-top:36px;color:var(--muted);font-size:14.5px;font-weight:500;">
        <div style="display:flex;align-items:center;gap:9px;"><span style="color:var(--accent);display:flex;"><svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2.2"></rect><path d="M4 6.5 12 12l8-5.5"></path></svg></span>Works with any email provider</div>
        <div style="display:flex;align-items:center;gap:9px;"><span style="color:var(--accent);display:flex;"><svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7 3v5c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6z"></path></svg></span>Provider-agnostic</div>
        <div style="display:flex;align-items:center;gap:9px;"><span style="color:var(--accent);display:flex;"><svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10" width="16" height="10" rx="2"></rect><path d="M8 10V7a4 4 0 0 1 8 0v3"></path></svg></span>No inbox access required</div>
        <div style="display:flex;align-items:center;gap:9px;"><span style="color:var(--accent);display:flex;"><svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s7-5.5 7-11a7 7 0 0 0-14 0c0 5.5 7 11 7 11z"></path><circle cx="12" cy="10" r="2.4"></circle></svg></span>Founded in Toronto</div>
      </div>
    </div>
  </section>

  <!-- PHOTO BAND -->
  <section style="position:relative;overflow:hidden;background:#06090D;">
    <div id="ujBandBg" style="position:absolute;inset:-12% 0;z-index:0;will-change:transform;">
      <img src="https://images.unsplash.com/photo-1545132147-d037e6c54cfd?q=80&w=2400&auto=format&fit=crop" alt="Sea of clouds from a plane window at sunset" style="width:100%;height:100%;object-fit:cover;object-position:center 50%;"/>
    </div>
    <div style="position:absolute;inset:0;z-index:1;background:linear-gradient(90deg,rgba(6,9,13,.9) 0%,rgba(6,9,13,.62) 50%,rgba(6,9,13,.4) 100%);"></div>
    <div style="position:relative;z-index:2;max-width:1220px;margin:0 auto;padding:130px 32px;">
      <div data-reveal style="opacity:0;transform:translateY(22px);max-width:620px;">
        <div style="font-size:13px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--accent);">The gap</div>
        <h2 style="font-family:'Bricolage Grotesque',sans-serif;font-weight:700;font-size:clamp(34px,4.2vw,56px);letter-spacing:-.03em;line-height:1.03;margin:16px 0 16px;color:#fff;">Airlines count on you not asking.</h2>
        <p style="font-size:18px;line-height:1.6;color:#CBD2DA;margin:0;">Most eligible passengers never file — the process is slow, opaque, and easy to give up on. Unjamm asks on your behalf, automatically, every single time a flight goes wrong.</p>
      </div>
    </div>
  </section>

  <!-- FINAL CTA -->
  <section id="cta" style="padding:96px 0 96px;background:var(--bg);">
    <div style="max-width:1220px;margin:0 auto;padding:0 32px;">
      <div data-reveal style="opacity:0;transform:translateY(22px);background:linear-gradient(135deg,var(--accent) 0%,var(--accent2) 100%);border-radius:28px;padding:72px 40px;text-align:center;">
        <h2 style="font-family:'Bricolage Grotesque',sans-serif;font-weight:700;font-size:clamp(32px,4vw,52px);letter-spacing:-.03em;line-height:1.04;margin:0 auto;max-width:760px;color:#06231A;">There's likely money waiting for you right now.</h2>
        <p style="font-size:18px;line-height:1.6;color:#0A3A2A;max-width:600px;margin:20px auto 0;font-weight:500;">Forward your next flight confirmation to {{ $claimsEmail }} and we'll start monitoring it the moment it lands.</p>
        <div style="display:flex;align-items:center;justify-content:center;gap:14px;margin-top:34px;flex-wrap:wrap;">
          <button type="button" onclick="ujCopyEmail(this)" data-hover="transform:translateY(-2px);background:rgba(6,35,26,.14)" style="display:inline-flex;align-items:center;gap:9px;background:rgba(6,35,26,.08);color:#06231A;border:1.5px solid rgba(6,35,26,.35);border-radius:999px;padding:15px 26px;font-family:'Hanken Grotesk',sans-serif;font-weight:700;font-size:16px;cursor:pointer;transition:transform .2s,background .2s;">{{ $claimsEmail }} · <span class="uj-copy-label">Copy</span></button>
          <a href="{{ $ctaUrl }}" data-hover="transform:translateY(-2px);background:#0A3A2A" style="display:inline-flex;align-items:center;gap:8px;background:#06231A;color:#EAF7F0;border-radius:999px;padding:15px 26px;text-decoration:none;font-weight:700;font-size:16px;transition:transform .2s,background .2s;">Create an account →</a>
        </div>
      </div>
    </div>
  </section>

  <!-- FLIGHT CHECK: value before signup -->
  <div id="ujSearch" role="dialog" aria-modal="true" aria-labelledby="ujSearchTitle"
       style="display:none;position:fixed;inset:0;z-index:120;align-items:center;justify-content:center;padding:20px;background:rgba(4,8,12,.78);backdrop-filter:blur(6px);">
    <div class="uj-scroll" style="width:100%;max-width:520px;background:#0B0F15;border:1px solid rgba(255,255,255,.1);border-radius:20px;padding:28px;box-shadow:0 50px 120px -40px rgba(0,0,0,.9);max-height:90vh;overflow-y:auto;overscroll-behavior:contain;">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:6px;">
        <h3 id="ujSearchTitle" style="margin:0;font-family:'Bricolage Grotesque',sans-serif;font-size:24px;font-weight:700;letter-spacing:-.02em;color:#fff;">Check your flight</h3>
        <button type="button" onclick="ujCloseSearch()" aria-label="Close"
                style="background:none;border:none;color:#5A6B7D;font-size:26px;line-height:1;cursor:pointer;padding:0 2px;">&times;</button>
      </div>
      <p style="margin:0 0 20px;font-size:14px;line-height:1.6;color:#8FA3B8;">
        No account needed. Enter your flight and we will tell you what happened to it - and whether it looks like the airline owes you money.
      </p>

      <form id="ujSearchForm" novalidate>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div>
            <label for="ujFlightNo" style="display:block;font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#5A6B7D;margin-bottom:7px;">Flight number</label>
            <input id="ujFlightNo" type="text" placeholder="AC123" autocomplete="off" maxlength="10" required
                   style="width:100%;box-sizing:border-box;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.12);border-radius:12px;padding:13px 15px;color:#fff;font-size:15px;font-family:ui-monospace,Menlo,monospace;text-transform:uppercase;outline:none;"
                   onfocus="this.style.borderColor='#3FCB94'" onblur="this.style.borderColor='rgba(255,255,255,.12)'">
          </div>
          <div>
            <label for="ujFlightDate" style="display:block;font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#5A6B7D;margin-bottom:7px;">Departure date</label>
            <input id="ujFlightDate" type="date" required
                   style="width:100%;box-sizing:border-box;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.12);border-radius:12px;padding:13px 15px;color:#fff;font-size:15px;outline:none;color-scheme:dark;"
                   onfocus="this.style.borderColor='#3FCB94'" onblur="this.style.borderColor='rgba(255,255,255,.12)'">
          </div>
        </div>
        <button id="ujSearchBtn" type="submit"
                style="width:100%;margin-top:16px;background:#3FCB94;color:#04120C;border:none;border-radius:12px;padding:15px 22px;font-size:15px;font-weight:700;cursor:pointer;transition:opacity .16s;">
          Check this flight
        </button>
      </form>

      <div id="ujSearchResult" style="display:none;margin-top:22px;padding-top:22px;border-top:1px solid rgba(255,255,255,.08);"></div>
    </div>
  </div>

@endsection

@push('scripts')
<script>
(function () {
    /* Copy claims email */
    window.ujCopyEmail = function (btn) {
        var email = @json($claimsEmail);
        try { navigator.clipboard.writeText(email); }
        catch (e) {
            var t = document.createElement('textarea');
            t.value = email; document.body.appendChild(t); t.select();
            try { document.execCommand('copy'); } catch (_) {}
            document.body.removeChild(t);
        }
        var label = btn.querySelector('.uj-copy-label');
        document.querySelectorAll('.uj-copy-label').forEach(function (l) {
            l.textContent = 'Copied';
            clearTimeout(l._t);
            l._t = setTimeout(function () { l.textContent = 'Copy'; }, 1800);
        });
    };

    /* Hero + band parallax (runs inside the layout's single scroll handler) */
    var heroBg = document.getElementById('ujHeroBg');
    var heroContent = document.getElementById('ujHeroContent');
    var heroEl = document.getElementById('top');
    window.ujOnScrollExtra = function (y) {
        var hh = (heroEl ? heroEl.offsetHeight : window.innerHeight) || 1;
        var hp = Math.max(0, Math.min(1, y / hh));
        /* Parallax displacement is clamped to 12% of the hero height so it always
           stays inside the layer's 18% top/bottom bleed — the background can never
           slide far enough to expose the page behind it (the "black band" bug). */
        if (heroBg) heroBg.style.transform = 'translateY(' + (Math.min(y, hh) * 0.12).toFixed(1) + 'px) scale(' + (1 + hp * 0.14).toFixed(4) + ')';
        if (heroContent) {
            heroContent.style.transform = 'translateY(' + (y * -0.16).toFixed(1) + 'px)';
            heroContent.style.opacity = String(Math.max(0, 1 - hp * 1.35));
        }
        var bandBg = document.getElementById('ujBandBg');
        if (bandBg) {
            var r = bandBg.parentElement.getBoundingClientRect();
            var vh2 = window.innerHeight;
            if (r.bottom > 0 && r.top < vh2) {
                var prog = (vh2 - r.top) / (vh2 + r.height);
                bandBg.style.transform = 'translateY(' + ((prog - 0.5) * 60).toFixed(1) + 'px)';
            }
        }
    };

    /* Responsive grids (inline styles can't hold media queries) */
    var g = {
        stats: document.getElementById('ujStats'),
        how:   document.getElementById('ujHow'),
        cov:   document.getElementById('ujCov'),
        why:   document.getElementById('ujWhy'),
        price: document.getElementById('ujPrice'),
    };
    var cols = function (el, n) { if (el) el.style.gridTemplateColumns = 'repeat(' + n + ',minmax(0,1fr))'; };
    function responsive() {
        var w = window.innerWidth;
        cols(g.stats, w < 560 ? 2 : 4);
        cols(g.how,   w < 640 ? 1 : (w < 1000 ? 2 : 4));
        cols(g.cov,   w < 640 ? 1 : (w < 1000 ? 2 : 3));
        cols(g.why,   w < 800 ? 1 : 3);
        cols(g.price, w < 760 ? 1 : 2);
    }
    responsive();
    window.addEventListener('resize', responsive, { passive: true });

    /* Split-flap live board */
    (function initBoard() {
        var host = document.getElementById('ujRows');
        if (!host) return;
        var boardCols = [
            { key: 'flight', w: '112px', len: 7, align: 'left', color: '#E7ECF2' },
            { key: 'route',  w: '1fr',  len: 10, align: 'left', color: '#E7ECF2' },
            { key: 'status', w: '168px', len: 10, align: 'left', color: 'status' },
            { key: 'pay',    w: '120px',  len: 7, align: 'right', color: 'var(--accent)' },
        ];
        var statusColor = { 'DELAYED 3H': '#FFC53D', 'DELAYED 4H': '#FFC53D', 'DELAYED 5H': '#FFC53D', 'DELAYED 6H': '#FFC53D', 'DELAYED 7H': '#FFC53D', 'DELAYED 8H': '#FFC53D', 'DELAYED 9H': '#FFC53D', 'CANCELLED': '#FF6B6B', 'DENIED BRD': '#FF9F45', 'BAG DELAY': '#7CC5FF', 'DIVERTED': '#FFC53D' };
        var pool = [
            { flight: 'BA 249', route: 'LHR -> GRU', status: 'DELAYED 4H', pay: '600EUR' },
            { flight: 'AC 856', route: 'YYZ -> LHR', status: 'CANCELLED',  pay: '1000CAD' },
            { flight: 'AF 348', route: 'CDG -> YUL', status: 'DELAYED 3H', pay: '600EUR' },
            { flight: 'UA 930', route: 'SFO -> LHR', status: 'DENIED BRD', pay: 'REFUND' },
            { flight: 'KL 691', route: 'AMS -> YYZ', status: 'BAG DELAY',  pay: '2350CAD' },
            { flight: 'EK 001', route: 'DXB -> LHR', status: 'DELAYED 3H', pay: '520GBP' },
            { flight: 'IB 342', route: 'MAD -> JFK', status: 'CANCELLED',  pay: '600EUR' },
            { flight: 'VY 8410',route: 'BCN -> YUL', status: 'DELAYED 4H', pay: '1000CAD' },
            { flight: 'LH 400', route: 'FRA -> JFK', status: 'DIVERTED',   pay: '600EUR' },
            { flight: 'WS 001', route: 'YVR -> LHR', status: 'DELAYED 3H', pay: '1000CAD' },
            { flight: 'DL 084', route: 'JFK -> CDG', status: 'BAG DELAY',  pay: '2350CAD' },
            { flight: 'BA 117', route: 'LHR -> JFK', status: 'DENIED BRD', pay: '520GBP' },
        ];

        /* Real disruptions, scanned via FlightAware and cached server-side -
           swapped into the rotation in place; the samples above are only the
           fallback while loading or when the feed is unavailable. */
        fetch('{{ route('live-disruptions') }}')
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res && res.data && res.data.length >= 4) {
                    pool.length = 0;
                    res.data.forEach(function (row) { pool.push(row); });
                }
            })
            .catch(function () {});
        var CH = ' ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789->€£$';
        var pad = function (s, n, align) {
            s = (s || '').toUpperCase().slice(0, n);
            return align === 'right' ? s.padStart(n, ' ') : s.padEnd(n, ' ');
        };
        var rowCount = 5;
        var rows = [];
        var mkCell = function (ch, color) {
            var c = document.createElement('span');
            c.textContent = ch;
            c.style.cssText = 'display:inline-flex;align-items:center;justify-content:center;width:14px;height:26px;margin-right:2px;border-radius:3px;font-family:ui-monospace,\'SF Mono\',Menlo,monospace;font-size:13px;font-weight:700;color:' + color + ';background:linear-gradient(180deg,#20262F 0%,#20262F 49.4%,#05070A 49.5%,#05070A 50.5%,#161B22 50.6%,#161B22 100%);box-shadow:inset 0 0 0 1px rgba(0,0,0,.5);transform-origin:center;will-change:transform;';
            return c;
        };
        for (var r = 0; r < rowCount; r++) {
            var row = document.createElement('div');
            row.style.cssText = 'display:grid;grid-template-columns:112px 1fr 168px 120px;gap:14px;padding:7px 14px;align-items:center;';
            var cellGroups = [];
            boardCols.forEach(function (col) {
                var wrap = document.createElement('div');
                wrap.style.cssText = 'display:flex;' + (col.align === 'right' ? 'justify-content:flex-end;' : '');
                var chars = [];
                for (var i = 0; i < col.len; i++) {
                    var cell = mkCell(' ', col.color === 'status' ? '#FFC53D' : col.color);
                    wrap.appendChild(cell);
                    chars.push(cell);
                }
                row.appendChild(wrap);
                cellGroups.push({ col: col, chars: chars });
            });
            host.appendChild(row);
            rows.push({ el: row, groups: cellGroups, data: null });
        }
        var flipCell = function (cell, target, color, delay) {
            if (cell.textContent === target && cell._col === color) { cell._col = color; return; }
            clearTimeout(cell._ft);
            var steps = 3 + Math.floor(Math.random() * 3);
            var step = function () {
                if (steps <= 0) { cell.textContent = target; if (color) { cell.style.color = color; cell._col = color; } return; }
                cell.textContent = CH[Math.floor(Math.random() * CH.length)];
                try { cell.animate([{ transform: 'rotateX(-72deg)', filter: 'brightness(1.5)' }, { transform: 'rotateX(0deg)', filter: 'brightness(1)' }], { duration: 80, easing: 'ease-out' }); } catch (e) {}
                steps--;
                cell._ft = setTimeout(step, 62);
            };
            cell._ft = setTimeout(step, delay);
        };
        var accent = getComputedStyle(document.documentElement).getPropertyValue('--accent').trim() || '#3FCB94';
        var setRow = function (row, rec) {
            row.data = rec;
            row.groups.forEach(function (grp) {
                var raw = pad(rec[grp.col.key], grp.col.len, grp.col.align);
                var color = grp.col.color;
                if (color === 'status') color = statusColor[rec.status] || '#FFC53D';
                else if (color === 'var(--accent)') color = accent;
                grp.chars.forEach(function (cell, i) {
                    flipCell(cell, raw[i] || ' ', color, i * 34 + Math.random() * 40);
                });
            });
        };
        var shuffled = pool.slice().sort(function () { return Math.random() - 0.5; });
        rows.forEach(function (row, i) { setTimeout(function () { setRow(row, shuffled[i % shuffled.length]); }, 250 + i * 260); });
        var ptr = rowCount;
        setInterval(function () {
            var row = rows[Math.floor(Math.random() * rows.length)];
            var shown = {};
            rows.forEach(function (r2) { if (r2.data) shown[r2.data.flight] = true; });
            var rec = null;
            for (var k = 0; k < pool.length; k++) { var cand = pool[(ptr + k) % pool.length]; if (!shown[cand.flight]) { rec = cand; ptr += k + 1; break; } }
            if (!rec) { rec = pool[ptr % pool.length]; ptr++; }
            setRow(row, rec);
        }, 2600);
    })();
})();

/* ── Public flight check: value before signup ───────────── */
(function () {
    var modal = document.getElementById('ujSearch');
    var form  = document.getElementById('ujSearchForm');
    var out   = document.getElementById('ujSearchResult');
    var btn   = document.getElementById('ujSearchBtn');
    if (!modal || !form) { return; }

    var ident = document.getElementById('ujFlightNo');
    var when  = document.getElementById('ujFlightDate');

    window.ujOpenSearch = function () {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        setTimeout(function () { ident.focus(); }, 60);
    };
    window.ujCloseSearch = function () {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    };

    modal.addEventListener('click', function (e) { if (e.target === modal) { window.ujCloseSearch(); } });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && modal.style.display === 'flex') { window.ujCloseSearch(); } });

    // Default to yesterday - most people check a flight that just happened.
    (function () {
        var d = new Date(); d.setDate(d.getDate() - 1);
        when.value = d.toISOString().slice(0, 10);
        when.max = new Date().toISOString().slice(0, 10);
    })();

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    /* One end of the journey: code, city, scheduled vs actual local time. */
    function endpoint(p, align) {
        if (!p) { return ''; }
        var right = align === 'right';
        var times = '';
        if (p.scheduled || p.actual) {
            var shown = p.actual || p.scheduled;
            times = '<div style="margin-top:6px;font-family:ui-monospace,Menlo,monospace;font-size:15px;font-weight:700;color:#fff;">'
                  + esc(shown) + (p.timezone ? ' <span style="font-size:11px;font-weight:600;color:#5A6B7D;">' + esc(p.timezone) + '</span>' : '')
                  + '</div>';
            if (p.actual && p.scheduled && p.actual !== p.scheduled) {
                times += '<div style="font-family:ui-monospace,Menlo,monospace;font-size:11px;color:#5A6B7D;text-decoration:line-through;">' + esc(p.scheduled) + '</div>';
            }
            if (p.delta) {
                times += '<div style="margin-top:3px;font-size:11px;font-weight:700;color:' + (p.late ? '#FF8A8A' : '#3FCB94') + ';">' + esc(p.delta) + '</div>';
            }
        }
        return '<div style="flex:1;min-width:0;text-align:' + (right ? 'right' : 'left') + ';">'
             + '<div style="font-family:ui-monospace,Menlo,monospace;font-size:26px;font-weight:700;color:#fff;line-height:1;">' + esc(p.code || '?') + '</div>'
             + (p.city ? '<div style="margin-top:4px;font-size:12px;font-weight:700;color:#8FA3B8;text-transform:uppercase;letter-spacing:.04em;">' + esc(p.city) + '</div>' : '')
             + (p.airport ? '<div style="margin-top:2px;font-size:11px;color:#5A6B7D;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + esc(p.airport) + '</div>' : '')
             + times
             + '</div>';
    }

    function flightCard(f) {
        var bad    = f.cancelled || f.delay_min >= 180;
        var accent = f.cancelled ? '#FF5A5A' : (bad ? '#F5C26B' : '#3FCB94');
        var status = f.cancelled ? 'CANCELLED' : (f.delay_min >= 15 ? 'DELAYED ' + f.status : (f.status_text || 'ON TIME'));
        var pct    = f.cancelled ? 0 : Math.max(4, Math.min(100, f.progress || 100));

        // A cancelled flight never flew: show the route as a broken line
        // rather than a completed journey.
        var track = f.cancelled
            ? '<div style="height:2px;margin:16px 4px 0;background:repeating-linear-gradient(90deg,rgba(255,90,90,.55) 0 8px,transparent 8px 15px);border-radius:2px;"></div>'
            : '<div style="position:relative;height:2px;margin:16px 4px 0;background:rgba(255,255,255,.1);border-radius:2px;">'
              + '<div style="position:absolute;left:0;top:0;height:2px;width:' + pct + '%;background:' + accent + ';border-radius:2px;"></div>'
              + '<div style="position:absolute;top:-5px;left:calc(' + pct + '% - 5px);width:10px;height:10px;border-radius:50%;background:' + accent + ';box-shadow:0 0 0 3px rgba(9,12,17,1);"></div>'
              + '</div>';

        return '<div style="margin:0 0 18px;padding:16px;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.08);border-radius:14px;">'
             + '<div style="display:flex;align-items:baseline;gap:10px;flex-wrap:wrap;margin-bottom:2px;">'
             +   '<span style="font-family:\'Bricolage Grotesque\',sans-serif;font-size:19px;font-weight:700;color:#fff;">' + esc(f.airline || f.ident) + '</span>'
             +   '<span style="font-family:ui-monospace,Menlo,monospace;font-size:13px;color:#5A6B7D;">' + esc(f.ident) + '</span>'
             + '</div>'
             + '<div style="font-size:12px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:' + accent + ';margin-bottom:16px;">'
             +   esc(status) + '<span style="color:#5A6B7D;font-weight:600;letter-spacing:.02em;text-transform:none;"> · ' + esc(f.date) + '</span>'
             + '</div>'
             + '<div style="display:flex;align-items:flex-start;gap:16px;">' + endpoint(f.from, 'left') + endpoint(f.to, 'right') + '</div>'
             + track
             + (f.cancelled ? '<div style="margin-top:8px;font-size:11px;color:#5A6B7D;text-align:center;">This flight did not operate - times shown were the schedule.</div>' : '')
             + '</div>';
    }

    /* Signed-in visitors already have an account - send them to their claims. */
    var authed = @json(auth()->check());

    function ctaLabel(r) {
        if (!authed) { return r.cta; }

        return r.eligible ? 'Start this claim in your account' : 'Go to my claims';
    }

    function card(r) {
        var accent = r.eligible ? '#3FCB94' : (r.found ? '#8FA3B8' : '#F5C26B');

        return (r.flight ? flightCard(r.flight) : '')
            + '<p style="margin:0 0 8px;font-family:\'Bricolage Grotesque\',sans-serif;font-size:20px;font-weight:700;line-height:1.25;color:' + accent + ';">' + esc(r.headline) + '</p>'
            + '<p style="margin:0 0 18px;font-size:14px;line-height:1.65;color:#8FA3B8;">' + esc(r.detail) + '</p>'
            + '<a href="{{ $ctaUrl }}" style="display:block;text-align:center;background:' + (r.eligible ? '#3FCB94' : '#ffffff') + ';color:#04120C;padding:14px 22px;border-radius:12px;font-size:15px;font-weight:700;text-decoration:none;">' + esc(ctaLabel(r)) + '</a>'
            + emailRoute(r)
            + '<p style="margin:12px 0 0;font-size:12px;color:#5A6B7D;text-align:center;">'
            + (authed ? 'No win, no fee. This is a provisional read - we confirm everything on the claim itself.'
                      : 'Free to check. No win, no fee. This is a provisional read - we confirm everything once you start a claim.')
            + '</p>';
    }

    /* Zero-friction alternative: forward the ticket and we build the claim
       (and the account) from it - no form to fill in at all. */
    function emailRoute(r) {
        if (authed) { return ''; }

        var subject = encodeURIComponent('My flight claim' + (r.flight ? ' - ' + r.flight.ident + ' ' + r.flight.date : ''));
        var body    = encodeURIComponent('My ticket is attached / forwarded below.');

        return '<div style="margin-top:14px;padding-top:14px;border-top:1px solid rgba(255,255,255,.08);text-align:center;">'
             + '<p style="margin:0 0 10px;font-size:13px;color:#8FA3B8;">Or skip the form entirely - forward your ticket and we will build the claim for you.</p>'
             + '<div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">'
             +   '<a href="mailto:{{ $claimsEmail }}?subject=' + subject + '&body=' + body + '" '
             +      'style="display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.14);color:#fff;padding:11px 18px;border-radius:10px;font-size:14px;font-weight:700;text-decoration:none;">'
             +      '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 7 10 6 10-6"/></svg>'
             +      'Email ticket</a>'
             +   '<button type="button" onclick="ujCopyEmail(this)" '
             +      'style="display:inline-flex;align-items:center;gap:7px;background:none;border:1px solid rgba(255,255,255,.14);color:#8FA3B8;padding:11px 16px;border-radius:10px;font-family:ui-monospace,Menlo,monospace;font-size:13px;font-weight:600;cursor:pointer;">'
             +      '{{ $claimsEmail }} · <span class="uj-copy-label">Copy</span></button>'
             + '</div>'
             + '<p style="margin:9px 0 0;font-size:11px;color:#5A6B7D;">We read the ticket, create the claim and set up your account automatically.</p>'
             + '</div>';
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!ident.value.trim()) { ident.focus(); return; }

        btn.disabled = true;
        btn.textContent = 'Checking…';
        out.style.display = 'block';
        out.innerHTML = '<p style="margin:0;font-size:14px;color:#8FA3B8;">Checking live flight records…</p>';

        fetch('{{ route('flight-lookup') }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({ flight: ident.value.trim(), date: when.value })
        })
        .then(function (res) { return res.json().then(function (b) { return { ok: res.ok, body: b }; }); })
        .then(function (r) {
            if (!r.ok) {
                var msg = r.body && r.body.errors
                    ? Object.values(r.body.errors)[0][0]
                    : 'That did not work - please check the flight number and try again.';
                out.innerHTML = '<p style="margin:0;font-size:14px;color:#F5C26B;">' + msg + '</p>';
                return;
            }
            out.innerHTML = card(r.body.data);
        })
        .catch(function () {
            out.innerHTML = '<p style="margin:0 0 14px;font-size:14px;color:#F5C26B;">We could not reach the flight database just now.</p>'
                + '<a href="{{ $ctaUrl }}" style="display:block;text-align:center;background:#ffffff;color:#04120C;padding:14px 22px;border-radius:12px;font-size:15px;font-weight:700;text-decoration:none;">Start a claim anyway - it is free</a>';
        })
        .finally(function () {
            btn.disabled = false;
            btn.textContent = 'Check this flight';
        });
    });
})();
</script>
@endpush
