<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 0; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Helvetica', 'DejaVu Sans', sans-serif; font-size: 11px; color: #1e293b; }

    /* ── Header band ─────────────────────────────── */
    .band { background: #0f172a; padding: 30px 48px; color: #ffffff; }
    .band table { width: 100%; }
    .logo-cell { width: 50px; vertical-align: middle; }
    .logo { width: 40px; height: 40px; border-radius: 10px; }
    .brand { font-size: 20px; font-weight: bold; letter-spacing: -0.5px; vertical-align: middle; padding-left: 12px; color: #ffffff; }
    .brand small { display: block; font-size: 8px; font-weight: normal; color: #8ea3c0; letter-spacing: 1.8px; text-transform: uppercase; margin-top: 3px; }
    .doc-meta { text-align: right; vertical-align: middle; }
    .doc-title { font-size: 14px; font-weight: bold; letter-spacing: 3px; text-transform: uppercase; color: #ffffff; }
    .doc-number { font-size: 9.5px; color: #8ea3c0; margin-top: 5px; letter-spacing: 0.4px; }
    a.brand-link { color: #ffffff; text-decoration: none; }
    a.brand-link small { color: #8ea3c0; }
    .footer a { color: #475569; text-decoration: none; font-weight: bold; }
    .footer .f-logo { width: 14px; height: 14px; border-radius: 4px; vertical-align: middle; margin-right: 6px; }
    .accent { height: 4px; background: #3fcb94; }

    .page { padding: 32px 48px 110px 48px; }

    /* ── Hero summary ────────────────────────────── */
    .hero { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin: 0 -8px 26px -8px; }
    .hero td { width: 33.33%; background: #f8fafc; border: 1px solid #e8edf3; border-radius: 12px; padding: 14px 16px; }
    .hero td.net { background: #ecfdf5; border-color: #a7f3d0; }
    .hero .k { font-size: 7.5px; text-transform: uppercase; letter-spacing: 1.5px; color: #94a3b8; font-weight: bold; }
    .hero td.net .k { color: #059669; }
    .hero .v { font-size: 17px; font-weight: bold; color: #0f172a; margin-top: 6px; }
    .hero td.net .v { color: #047857; }
    .hero .s { font-size: 8.5px; color: #94a3b8; margin-top: 4px; }

    /* ── Info grid ───────────────────────────────── */
    .info { width: 100%; margin-bottom: 8px; }
    .info td { vertical-align: top; width: 33.33%; padding-right: 18px; }
    .label { font-size: 7.5px; text-transform: uppercase; letter-spacing: 1.5px; color: #94a3b8; font-weight: bold; margin-bottom: 6px; }
    .info .name { font-size: 12px; font-weight: bold; color: #0f172a; }
    .info .line { font-size: 9.5px; color: #64748b; margin-top: 3px; line-height: 1.5; }
    .chip { display: inline-block; font-size: 8px; font-weight: bold; letter-spacing: 1.2px; text-transform: uppercase; padding: 4px 11px; border-radius: 20px; background: #d1fae5; color: #065f46; }
    .chip.open { background: #e0e7ff; color: #3730a3; }

    .rule { border: 0; border-top: 1px solid #e8edf3; margin: 22px 0; }

    /* ── Sections & tables ───────────────────────── */
    h2 { font-size: 8.5px; text-transform: uppercase; letter-spacing: 2px; color: #0f172a; margin: 0 0 10px 0; }
    h2 span { color: #94a3b8; font-weight: normal; }

    table.data { width: 100%; border-collapse: collapse; }
    table.data th { font-size: 7.5px; text-transform: uppercase; letter-spacing: 1.2px; color: #94a3b8; text-align: left; padding: 0 12px 7px 12px; border-bottom: 1.5px solid #0f172a; }
    table.data th.r, table.data td.r { text-align: right; }
    table.data td { padding: 9px 12px; font-size: 10px; color: #334155; border-bottom: 0.6px solid #eef2f7; }
    table.data tr.alt td { background: #fafbfd; }
    table.data td.strong { font-weight: bold; color: #0f172a; }
    .mono { font-family: 'DejaVu Sans Mono', monospace; font-size: 8.8px; color: #64748b; }
    .neg { color: #be123c; }
    .pos { color: #047857; font-weight: bold; }
    .muted { color: #94a3b8; }

    .total-row td { border-bottom: none; border-top: 1.5px solid #0f172a; padding-top: 10px; font-size: 12.5px; font-weight: bold; color: #0f172a; background: #ffffff !important; }

    .section { margin-bottom: 26px; }

    /* ── Note & footer ───────────────────────────── */
    .note { margin-top: 4px; padding: 12px 16px; background: #f8fafc; border-left: 3px solid #3fcb94; border-radius: 0 8px 8px 0; font-size: 8.5px; color: #64748b; line-height: 1.6; }

    .footer { position: fixed; bottom: 0; left: 0; right: 0; }
    .footer .accent { height: 3px; }
    .footer .inner { padding: 14px 48px 20px 48px; background: #f8fafc; }
    .footer table { width: 100%; }
    .footer td { font-size: 8px; color: #94a3b8; }
    .footer .strong { color: #475569; font-weight: bold; }
</style>
</head>
<body>

<div class="band">
    <table>
        <tr>
            <td class="logo-cell"><a href="{{ config('app.url') }}"><img class="logo" src="{{ public_path('email-logo.png') }}" alt="Unjamm"></a></td>
            <td class="brand"><a href="{{ config('app.url') }}" class="brand-link">Unjamm<small>Get the money airlines owe you</small></a></td>
            <td class="doc-meta">
                <div class="doc-title">Payment receipt</div>
                <div class="doc-number">{{ $receiptNumber }} &nbsp;·&nbsp; {{ $issuedAt->format('d M Y') }}</div>
            </td>
        </tr>
    </table>
</div>
<div class="accent"></div>

<div class="page">

    <table class="hero">
        <tr>
            <td>
                <div class="k">Gross compensation</div>
                <div class="v">{{ $payment->currency }} {{ number_format((float) $payment->gross_amount, 2) }}</div>
                <div class="s">received from {{ $payment->claim?->airline }}</div>
            </td>
            <td>
                <div class="k">Success fee ({{ rtrim(rtrim(number_format((float) $payment->fee_percent, 2), '0'), '.') }}%)</div>
                <div class="v">{{ $payment->currency }} {{ number_format((float) $payment->fee_amount, 2) }}</div>
                <div class="s">Unjamm service fee</div>
            </td>
            <td class="net">
                <div class="k">Net payout</div>
                <div class="v">{{ $payment->currency }} {{ number_format((float) $payment->net_amount, 2) }}</div>
                <div class="s">to the passenger</div>
            </td>
        </tr>
    </table>

    <table class="info">
        <tr>
            <td>
                <div class="label">Passenger</div>
                <div class="name">{{ $payment->claim?->passenger_name ?: $payment->user?->name }}</div>
                <div class="line">{{ $payment->user?->email }}</div>
            </td>
            <td>
                <div class="label">Claim</div>
                <div class="name">#{{ $payment->claim?->number }}</div>
                <div class="line">{{ $payment->claim?->airline }} {{ $payment->claim?->flight_number }} · {{ $payment->claim?->departure_airport }} &rarr; {{ $payment->claim?->arrival_airport }}<br>Flight date {{ $payment->claim?->flight_date?->format('d M Y') }}</div>
            </td>
            <td>
                <div class="label">Payment</div>
                <div class="name">{{ $payment->payment_date?->format('d M Y') }} &nbsp;<span class="chip {{ $payment->status === 'paid' ? '' : 'open' }}">{{ $payment->statusLabel() }}</span></div>
                <div class="line">Airline reference: {{ $payment->reference ?: '-' }}</div>
            </td>
        </tr>
    </table>

    <hr class="rule">

    <div class="section">
        <h2>Settlement</h2>
        <table class="data">
            <tr>
                <th style="width: 44%;">Description</th>
                <th>Reference</th>
                <th>Date</th>
                <th class="r">Amount</th>
            </tr>
            <tr>
                <td class="strong">Airline compensation received</td>
                <td class="mono">{{ $payment->reference ?: '-' }}</td>
                <td>{{ $payment->payment_date?->format('d M Y') }}</td>
                <td class="r strong">{{ $payment->currency }} {{ number_format((float) $payment->gross_amount, 2) }}</td>
            </tr>
            <tr class="alt">
                <td>Unjamm success fee ({{ rtrim(rtrim(number_format((float) $payment->fee_percent, 2), '0'), '.') }}%)</td>
                <td class="mono">-</td>
                <td>{{ $payment->payment_date?->format('d M Y') }}</td>
                <td class="r neg">&minus; {{ $payment->currency }} {{ number_format((float) $payment->fee_amount, 2) }}</td>
            </tr>
            <tr class="total-row">
                <td colspan="3">Net payout to passenger</td>
                <td class="r">{{ $payment->currency }} {{ number_format((float) $payment->net_amount, 2) }}</td>
            </tr>
        </table>
    </div>

    @if ($payouts->isNotEmpty())
        <div class="section">
            <h2>Payout <span>· how the money reached the passenger</span></h2>
            <table class="data">
                <tr>
                    <th>Method</th>
                    <th>Transaction number</th>
                    <th>Reference</th>
                    <th>Exchange rate</th>
                    <th>Date</th>
                    <th class="r">Amount sent</th>
                </tr>
                @foreach ($payouts as $payout)
                    <tr @if ($loop->even) class="alt" @endif>
                        <td class="strong">{{ $payout->method === 'wise' ? 'Wise transfer' : 'Manual transfer' }}</td>
                        <td class="mono">{{ $payout->wise_transfer_id ?: '-' }}</td>
                        <td class="mono">{{ $payout->transfer_reference }}</td>
                        <td class="mono">
                            @if ($payout->exchange_rate && $payout->currency !== $payout->source_currency)
                                {{ $payout->source_currency }}&rarr;{{ $payout->currency }} @ {{ rtrim(rtrim(number_format((float) $payout->exchange_rate, 6), '0'), '.') }}
                            @else
                                <span class="muted">-</span>
                            @endif
                        </td>
                        <td>{{ $payout->transferred_at?->format('d M Y') ?: '-' }}</td>
                        <td class="r pos">{{ $payout->currency }} {{ number_format((float) $payout->amount, 2) }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endif

    @if ($transactions->isNotEmpty())
        <div class="section">
            <h2>Transaction history <span>· complete ledger for this payment</span></h2>
            <table class="data">
                <tr>
                    <th style="width: 20%;">Date</th>
                    <th>Event</th>
                    <th>Reference</th>
                    <th class="r">Amount</th>
                </tr>
                @foreach ($transactions as $tx)
                    <tr @if ($loop->even) class="alt" @endif>
                        <td>{{ $tx->created_at->format('d M Y · H:i') }}</td>
                        <td class="strong">{{ $tx->typeLabel() }}</td>
                        <td class="mono">{{ $tx->reference ? Str::limit($tx->reference, 22) : '-' }}</td>
                        <td class="r {{ (float) $tx->amount < 0 ? 'neg' : '' }}">
                            {{ $tx->amount !== null ? trim(($tx->currency ?? '') . ' ' . number_format((float) $tx->amount, 2)) : '-' }}
                        </td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endif

    <div class="note">
        This receipt documents flight compensation recovered from {{ $payment->claim?->airline }} on the passenger's behalf and the corresponding payout. Amounts in other currencies were converted at the exchange rate shown at the time of transfer. For any questions about this document, please quote <b>{{ $receiptNumber }}</b>.
    </div>
</div>

<div class="footer">
    <div class="accent"></div>
    <div class="inner">
        <table>
            <tr>
                <td><a href="{{ config('app.url') }}"><img class="f-logo" src="{{ public_path('email-logo.png') }}" alt="">{{ config('app.name', 'Unjamm') }}</a></td>
                <td style="text-align: center;"><a href="{{ config('app.url') }}" style="font-weight: normal; color: #94a3b8;">{{ preg_replace('#^https?://#', '', (string) config('app.url')) }}</a></td>
                <td style="text-align: right;">{{ $receiptNumber }} · generated {{ $issuedAt->format('d M Y H:i') }} UTC</td>
            </tr>
        </table>
    </div>
</div>

</body>
</html>
