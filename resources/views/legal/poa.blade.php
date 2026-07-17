@php
    $regimes = [
        'EU' => ['law' => 'Regulation (EC) No 261/2004', 'body' => 'the competent National Enforcement Body or an approved alternative dispute resolution scheme'],
        'UK' => ['law' => 'UK261 (Regulation (EC) No 261/2004 as retained in UK law)', 'body' => 'the UK Civil Aviation Authority or an approved alternative dispute resolution scheme'],
        'CA' => ['law' => "Canada's Air Passenger Protection Regulations (SOR/2019-150)", 'body' => 'the Canadian Transportation Agency'],
        'US' => ['law' => 'US Department of Transportation rules (14 CFR Parts 250 and 260)', 'body' => 'the US Department of Transportation'],
    ];
    $regime  = $regimes[$jurisdiction] ?? $regimes['EU'];
    $covers  = $signer->signs_for ?: $signer->name;
    $isGuard = $signer->role === 'guardian';
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1e293b; line-height: 1.5; margin: 28px 44px; }
    h1 { font-size: 19px; margin: 0 0 2px; }
    .muted { color: #64748b; font-size: 11px; }
    .box { border: 1px solid #cbd5e1; border-radius: 6px; padding: 12px 16px; margin: 16px 0; }
    table { width: 100%; border-collapse: collapse; }
    td { padding: 3px 0; vertical-align: top; }
    td.k { color: #64748b; width: 170px; }
    .sig { margin-top: 24px; }
    .sig-box { border: 1px solid #cbd5e1; border-radius: 8px; background: #ffffff; padding: 14px 18px; margin-top: 10px; }
    .sig-label { font-size: 13px; font-weight: bold; color: #0f172a; }
    .sig-area { height: 90px; margin-top: 8px; border-bottom: 2px solid #1e293b; }
    .sig-area img { max-height: 84px; }
    ol li { margin-bottom: 4px; }
    .footer { margin-top: 20px; padding-top: 8px; border-top: 1px solid #e2e8f0; font-size: 10px; color: #94a3b8; }
</style>
</head>
<body>
    <h1>Power of Attorney</h1>
    <div class="muted">Claim reference {{ $claim->reference }} - Claim No. {{ $claim->number }} - Jurisdiction: {{ $jurisdiction }}</div>

    <div class="box">
        <table>
            <tr><td class="k">Passenger</td><td>{{ $covers }}</td></tr>
            @if ($isGuard)
            <tr><td class="k">Guardian (signing)</td><td>{{ $signer->name }}</td></tr>
            @endif
            <tr><td class="k">Airline</td><td>{{ $claim->airline ?: '-' }}</td></tr>
            <tr><td class="k">Flight</td><td>{{ $claim->flight_number ?: '-' }} on {{ $claim->flight_date?->format('d F Y') ?: '-' }}</td></tr>
            <tr><td class="k">Route</td><td>{{ $claim->departure_airport }} to {{ $claim->arrival_airport }}</td></tr>
            @if ($claim->booking_reference)
            <tr><td class="k">Booking reference</td><td>{{ $claim->booking_reference }}</td></tr>
            @endif
        </table>
    </div>

    @if ($isGuard)
    <p>I, {{ $signer->name }}, as parent or legal guardian of the minor passenger {{ $covers }}, hereby grant Unjamm ("the Representative") power of attorney to act on the minor's behalf in connection with the air passenger rights claim identified above.</p>
    @else
    <p>I, {{ $covers }}, the passenger named above, hereby grant Unjamm ("the Representative") power of attorney to act on my behalf in connection with the air passenger rights claim identified above.</p>
    @endif

    <p>The Representative is authorised to:</p>
    <ol>
        <li>contact and correspond with the operating air carrier, its agents and representatives regarding the claim;</li>
        <li>submit, pursue and negotiate the claim for compensation, refunds and expense reimbursement arising from the disruption of the flight identified above under {{ $regime['law'] }};</li>
        <li>receive information and documents relating to the booking and the flight for the purpose of the claim;</li>
        <li>escalate the claim to {{ $regime['body'] }} where the carrier does not respond or rejects the claim.</li>
    </ol>

    <p>This power of attorney is limited to the claim identified above and remains valid until the claim is settled or withdrawn.</p>

    <div class="sig">
        <div class="sig-box">
            <div class="sig-label" style="text-transform: uppercase; letter-spacing: .05em; color: #64748b; font-size: 10px;">Authorised signature</div>
            <div class="sig-area">
                @if ($signature)
                    <img src="{{ $signature }}" alt="Signature">
                @else
                    {{-- Dropbox Sign text tag: becomes the guided signature field; white so it never shows in print --}}
                    <span style="color:#ffffff; font-size:34px;">[sig|req|signer1]</span>
                @endif
            </div>
            <div class="muted" style="margin-top:6px;">
                {{ $signer->name }}@if ($isGuard) (guardian of {{ $covers }})@endif
                @if ($signer->signed_at) - signed {{ $signer->signed_at->format('d F Y H:i') }} (UTC)@endif
            </div>
        </div>
    </div>

    <div class="footer">Generated by Unjamm on {{ now()->format('d F Y') }} - claim {{ $claim->reference }}</div>
</body>
</html>
