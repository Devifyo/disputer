<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Sign your claim authorisation - Unjamm</title>
<style>
    * { box-sizing: border-box; margin: 0; }
    body { font-family: ui-sans-serif, system-ui, -apple-system, sans-serif; background: #f1f5f9; color: #0f172a; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 16px; }
    .card { background: #fff; border-radius: 20px; box-shadow: 0 10px 30px rgba(15,23,42,.08); max-width: 560px; width: 100%; padding: 28px; }
    h1 { font-size: 20px; margin-bottom: 4px; }
    .muted { color: #64748b; font-size: 13px; }
    .row { display: flex; justify-content: space-between; gap: 12px; padding: 8px 0; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
    .row .k { color: #64748b; }
    .pill { display: inline-block; background: #ecfdf5; color: #047857; font-weight: 700; font-size: 12px; padding: 3px 10px; border-radius: 999px; }
    canvas { width: 100%; height: 180px; border: 2px dashed #cbd5e1; border-radius: 14px; touch-action: none; background: #fff; cursor: crosshair; }
    .actions { display: flex; gap: 10px; margin-top: 14px; }
    button { font: inherit; font-weight: 700; border: 0; border-radius: 12px; padding: 12px 18px; cursor: pointer; }
    .primary { background: #0f172a; color: #fff; flex: 1; }
    .primary:disabled { opacity: .4; cursor: not-allowed; }
    .ghost { background: #f1f5f9; color: #334155; }
    .docs a { color: #2563eb; font-size: 13px; }
    .done { text-align: center; padding: 24px 0; }
    .done .tick { width: 56px; height: 56px; border-radius: 50%; background: #10b981; color: #fff; font-size: 28px; line-height: 56px; margin: 0 auto 12px; }
    section { margin-top: 18px; }
    label.sec { display: block; font-size: 11px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: #94a3b8; margin-bottom: 8px; }
</style>
</head>
<body>
<div class="card">
    @if ($signer->status === 'signed')
        <div class="done">
            <div class="tick">✓</div>
            <h1>Already signed</h1>
            <p class="muted">Thank you, {{ $signer->name }} - nothing more is needed from you.</p>
        </div>
    @else
        <h1>Sign your claim authorisation</h1>
        <p class="muted">Flight compensation claim {{ $claim->reference }}</p>

        <section>
            <div class="row"><span class="k">Passenger</span><span>{{ $signer->signs_for ?: $signer->name }}@if($signer->role === 'guardian') (signed by guardian)@endif</span></div>
            <div class="row"><span class="k">Flight</span><span>{{ trim(($claim->airline ?? '') . ' ' . ($claim->flight_number ?? '')) }}</span></div>
            <div class="row"><span class="k">Route</span><span>{{ $claim->departure_airport }} → {{ $claim->arrival_airport }}</span></div>
            <div class="row"><span class="k">Date</span><span>{{ $claim->flight_date?->format('d M Y') }}</span></div>
        </section>

        <section class="docs">
            <label class="sec">You are signing</label>
            <p style="font-size:14px;">A Power of Attorney authorising Unjamm to pursue this compensation claim on {{ $signer->role === 'guardian' ? "the minor's" : 'your' }} behalf. <span class="pill">No win, no fee</span></p>
        </section>

        @if ($mode === 'dropbox_sign' && $signUrl && !request()->boolean('pad'))
            <section>
                <div id="dropbox-sign-container"></div>
                <p class="muted" id="ds-hint">Loading the secure signing window…</p>
            </section>
            <script>
                // If the embedded library can't load or open, fall back to the
                // built-in drawing pad instead of leaving the signer stuck.
                function dsFallback() {
                    location.replace(location.pathname + '?pad=1');
                }
                const dsScript = document.createElement('script');
                dsScript.src = 'https://cdn.hellosign.com/public/js/embedded/v2.12.0/embedded.production.min.js';
                dsScript.onerror = dsFallback;
                dsScript.onload = () => {
                    try {
                        const dsClient = new window.HelloSign({ clientId: @json($clientId) });
                        dsClient.open(@json($signUrl), { allowCancel: true, skipDomainVerification: {{ config('services.dropbox_sign.test_mode') ? 'true' : 'false' }} });
                        dsClient.on('sign', () => {
                            // Show a clear "done" state; the server reconciles
                            // with Dropbox on reload, so no second signature is
                            // ever requested.
                            document.querySelector('.card').innerHTML =
                                '<div class="done"><div class="tick">✓</div><h1>Signature received</h1><p class="muted">Finalising your document…</p></div>';
                            setTimeout(() => location.reload(), 2000);
                        });
                        dsClient.on('error', dsFallback);
                        document.getElementById('ds-hint').textContent = '';
                    } catch (e) {
                        dsFallback();
                    }
                };
                document.head.appendChild(dsScript);
            </script>
        @else
            <section>
                <label class="sec">Draw your signature</label>
                <canvas id="pad"></canvas>
                <div class="actions">
                    <button type="button" class="ghost" id="clear">Clear</button>
                    <button type="button" class="primary" id="submit" disabled>Sign &amp; finish</button>
                </div>
            </section>
            <script>
                const canvas = document.getElementById('pad');
                const ctx = canvas.getContext('2d');
                const submit = document.getElementById('submit');
                let drawing = false, drawn = false;

                function scale() {
                    const r = canvas.getBoundingClientRect();
                    canvas.width = r.width * 2; canvas.height = r.height * 2;
                    ctx.scale(2, 2); ctx.lineWidth = 2.2; ctx.lineCap = 'round'; ctx.strokeStyle = '#0f172a';
                }
                scale();

                function pos(e) {
                    const r = canvas.getBoundingClientRect();
                    const t = e.touches ? e.touches[0] : e;
                    return [t.clientX - r.left, t.clientY - r.top];
                }
                function start(e) { drawing = true; const [x, y] = pos(e); ctx.beginPath(); ctx.moveTo(x, y); e.preventDefault(); }
                function move(e) {
                    if (!drawing) return;
                    const [x, y] = pos(e); ctx.lineTo(x, y); ctx.stroke();
                    drawn = true; submit.disabled = false; e.preventDefault();
                }
                function end() { drawing = false; }

                canvas.addEventListener('pointerdown', start);
                canvas.addEventListener('pointermove', move);
                window.addEventListener('pointerup', end);

                document.getElementById('clear').onclick = () => {
                    ctx.setTransform(1, 0, 0, 1, 0, 0); ctx.clearRect(0, 0, canvas.width, canvas.height);
                    scale(); drawn = false; submit.disabled = true;
                };

                submit.onclick = async () => {
                    if (!drawn) return;
                    submit.disabled = true; submit.textContent = 'Saving…';
                    try {
                        const res = await fetch(@json(route('claim-signature.store', $signer->sign_token)), {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ signature: canvas.toDataURL('image/png') }),
                        });
                        if (!res.ok) throw new Error();
                        location.reload();
                    } catch {
                        submit.disabled = false; submit.textContent = 'Sign & finish';
                        alert('Could not save your signature. Please try again.');
                    }
                };
            </script>
        @endif
    @endif
</div>
</body>
</html>
