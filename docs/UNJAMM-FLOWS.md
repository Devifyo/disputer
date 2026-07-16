# Unjamm - Flight Compensation Flows

Living documentation of the flight-dispute module: how the flows work, where
the code lives, and what changed. **Update this file whenever a flow changes.**

Last updated: 2026-07-16

---

## 1. The two claim entry points

### Scenario 1 - Protected Trip (evaluated before the claim exists)

```
Upload itinerary / add trip manually      TripApiController@store / @upload
        v
Flight monitoring (FlightAware checkpoints T-24h..T+24h)
        v                                  TripMonitoringService, trips:monitor (cron, every 5 min)
Disruption detected (delay / cancellation / diversion / gate / schedule / missed connection)
        v
Eligibility Engine evaluates + stores verdict on the trip
        v                                  EligibilityEngine@evaluate
User notified (email via EmailTemplate system)
        v
User clicks "Start your claim" on the trip
        v                                  TripApiController@createClaim
ONE master claim per booking, verdict INHERITED - engine is NOT re-run
        v
Straight to the Claim Confirmation screen  (route: /claims/:id/confirm)
```

Key rule: `createClaim` copies the trip's stored eligibility fields onto the
claim (`eligibility_details.inherited_from_trip`) and only re-prices amounts
via `ClaimEligibilityService@priceCompensation`. No second engine run.

### Scenario 2 - Direct Claim (never evaluated before)

```
Entry: upload ticket (AddClaim) | manual funnel (AddClaim) | email to claims@unjamm.com
        v                                  ClaimApiController@store / Claim::ensureForItinerary
ONE master claim per booking (all passengers on it)
        v
FlightAware verification (history reaches ~10-11 days back)
        v                                  ClaimEligibilityService@verifyFlight
Eligibility Engine evaluates (APPR / EU261 / UK261 / US_DOT)
        v                                  EligibilityEngine@decide
Compensation calculated (engine-side only, never in the UI)
        v                                  CompensationCalculator
eligible -> Confirmation screen | review -> team queue | rejected -> "Not eligible" result view
```

## 2. Eligibility Engine

- `EligibilityEngine@decide(EligibilityContext)` - provider-pluggable:
  `config('eligibility.evaluator')` selects `ai` (Gemini) or `rules`
  (deterministic); the official rules engine can be dropped in via
  `config('eligibility.providers')` without touching anything else.
- AI verdicts are reconciled against the rules (anti-hallucination guard,
  dedupe, backfill -> decision source `ai+rules`).
- Confidence 0-100 (admin threshold, Setting `eligibility.confidence_threshold`,
  default 70). Above threshold + eligible -> auto-approved. Eligible below
  threshold, low-confidence rejections, "other" reports, and AI
  `manual_review_recommended` -> human review (admin Trip Reviews queue for
  trips). AI flags can only escalate to review, never approve.
- Regulation weight when several apply: EU261 = UK261 (3) > APPR (2) > US_DOT (1).
- Confidence hard-capped at 75 when facts are passenger-reported/unverified.

## 3. Compensation (engine-side only)

`CompensationCalculator@calculate(verdict, context, ticketPrice, ticketCurrency)`:
- EU261 EUR 250/400/600, UK261 GBP 220/350/520 by great-circle distance;
  Article 7(2) 50% reduction; downgrade 30/50/75% of fare (Article 10).
- APPR CAD 400/700/1000 by delay tier; denied boarding 900/1800/2400;
  refund-chosen cancellations flat CAD 400 (s.19(2)) + ticket refund (s.17).
- US DOT: refund-based (Part 260); denied boarding up to 400% of fare (Part 250).
- Output: amount/currency/basis + `breakdown` {tiers, facts, note,
  entitlements}. Entitlements = separate rights: compensation / ticket refund /
  re-routing / expenses, each `included|conditional|none` with plain-language detail.
- Never derived from ticket price except fare-based remedies the law defines.

## 4. Claim Confirmation screen (`/claims/:id/confirm`)

`ClaimConfirm.vue` + `ClaimApiController@confirmation` / `@confirm`.
Presentation-only - displays what the engine returned. Sections: flight
summary, disruption + timeline, why you qualify (regulation/article/
jurisdiction/reason), per-passenger compensation + booking totals + success
fee (Setting `claims.success_fee_percent`, default 25%) + net payout,
estimated timeline, social proof (Settings + published SuccessStories),
Unjamm Plus promo (optional), 4 consent checkboxes gating "Confirm & Continue".
Confirm stores consents (+IP/timestamp), generates legal documents, then
redirects to the Sign step. Only claims with status `eligible` can confirm.

Not-eligible claims show the "Claim evaluation result - Not eligible" view on
the claim's Compensation tab (regulation, reason, flight info, NO amounts).

## 5. E-signatures

- Roster (`claim_signers`): each adult passenger signs their own POA; a
  guardian (lead adult/account holder) signs for minors (`CHD`/`INF`); lead
  also signs the booking-level Assignment of Claims.
- Documents: dompdf, jurisdiction-specific templates (CA/US/EU/UK from the
  winning regulation) - `resources/views/legal/poa.blade.php`,
  `assignment.blade.php`; generated by `ClaimLegalDocumentService`.
- Providers (`ClaimSignatureService@provider`): Dropbox Sign embedded
  (requires DROPBOX_SIGN_API_KEY + DROPBOX_SIGN_CLIENT_ID) with text-tag
  guided signature fields; built-in canvas pad as automatic fallback.
  Provider failures never block the flow.
- Additional adults: emailed individual signing request (template
  `claim-signature-request`) -> tokenised public page `/claim-signature/{token}`.
- Status: webhook `/api/webhooks/dropbox-sign` (HMAC-verified; handles
  signed/all_signed/downloadable/declined) PLUS direct API reconciliation on
  page load (webhook-timing independent). Pending->signed transition is atomic
  (no double-recording). Signed PDFs (with Dropbox audit trail) replace the
  unsigned copies.
- Reminders: `claims:signature-reminders` daily 09:00 - nudges signers
  invited 48h+ ago, re-nudges every 48h.
- Unlock: when the last signature lands -> `claims.signed_at`, events
  "All authorisations signed - unlocked for filing" + "Claim submitted -
  waiting for the airline's response" (pending).
- Where users see documents: claim Documents tab ("Authorisation documents",
  SIGNED/AWAITING badges) and the Sign page.
- Dropbox test mode: DROPBOX_SIGN_TEST_MODE=true -> TEST watermark, not
  legally binding. Production needs a paid API plan + app approval.

## 6. Inbound email claims

customer -> claims@unjamm.com (Hostinger MX) -> forwarder ->
claims@claims.unjamm.com (MX -> SendGrid) -> Inbound Parse ->
`/api/webhooks/sendgrid/claims-inbound` -> itinerary parsed -> master claim.
Display address env: CLAIMS_DISPLAY_ADDRESS. Known gap: transactional/no-reply
senders also create accounts+claims (no sender filter yet).

## 7. Admin

- Settings -> Trip Eligibility: confidence threshold.
- Settings -> Flight Claims: success fee %, social proof stats.
- Settings -> Website: feature toggles (Setting `app.plus_promo_enabled` -
  show/hide the Unjamm Plus upsell on claim confirmation).
- Trip Reviews queue: Review|All tabs, approve/reject (reason required,
  emailed), decision recorded (who + source ai/rules/ai+rules/admin).
- Templates: all customer emails are admin-editable EmailTemplates.

## 8. Key conventions

- Amounts/eligibility NEVER computed in the UI - presentation only.
- One master claim per booking (never one per passenger).
- Statuses: draft -> pending_eligibility_review | eligible | rejected;
  workflow: confirmed_at -> signed_at (unlocked for filing).
- PHP: imports at top, never inline FQCN. Hyphen "-", never em-dash.
- CTA styling: bg-slate-900; pending = amber clock icon; timeline pending
  events always render last.
- Tests: `php artisan test` (DB disputer_testing) - keep green; frontend
  changes need `npm run build`; backend changes need queue worker restart
  (`supervisorctl restart unjamm-queue:`). After running artisan as root:
  `chown -R www-data:www-data storage`.

---

## Changelog

### 2026-07-16
- Passenger management: names editable on the confirmation screen ("Not
  spelled right?") AND a Passengers card in the claim Details tab with name +
  "This person is under 18" checkbox per passenger (the minor flag drives the
  guardian signing flow). Locked once the claim is confirmed - the details are
  on the POAs. Endpoint: POST api/claims/{claim}/passengers (accepts plain
  names or {name, minor} objects).
- Admin Settings -> Website tab (feature toggles): Unjamm Plus upsell on the
  confirmation screen can be enabled/disabled (`app.plus_promo_enabled`).
- Eligible-claim email: when a claim turns eligible, the customer gets
  "You're owed [AMOUNT] - Claim it now" (template `claim-eligible-compensation`,
  admin-editable; sent once per claim on the not-eligible -> eligible
  transition, booking total, links to the claim page).
- Claim detail CTAs redesigned: eligible -> emerald gradient "You're owed X /
  Claim it now" card; awaiting signatures -> violet card with "N of M
  signatures collected" progress bar (workflow payload now carries
  signers_signed/signers_total).
- Trip -> claim now creates ONE master claim for the whole booking and
  navigates directly to the Claim Confirmation screen (verdict inherited,
  engine not re-run).
- Not-eligible claims: Compensation tab shows "Claim evaluation result - Not
  eligible" (regulation, reason, flight info; no amounts).
- Details tab explains when a flight is too old for live tracking (~10 days).
- claims@unjamm.com live via Hostinger forwarder -> claims.unjamm.com ->
  SendGrid Inbound Parse (forwarder confirmed; display address switched).
- Signature events: atomic pending->signed (no duplicates), event names the
  actual signer.

### 2026-07-15
- Claim Confirmation screen (9 sections) + consent + POA/Assignment generation.
- Master claim per booking (Claim::ensureForItinerary consolidated).
- E-signature layer: Dropbox Sign embedded + native pad fallback, per-passenger
  jurisdiction-specific POAs, guardian workflow, invites, webhook + reconciliation,
  reminders, auto-unlock. Dropbox credentials configured (test mode).
- Admin: Flight Claims settings (success fee, social proof).
- Documents tab: Authorisation documents section with signature status.
- Compensation tab: payout step-by-step (per passenger, total, fee, net);
  hero shows booking total.

### Earlier (July 2026)
- Trip Protection: FlightAware monitoring, checkpoints, events, notifications.
- Eligibility Engine: AI (Gemini) + rules, confidence rubric, admin threshold,
  review queue, provider-pluggable for the future official rules engine.
- CompensationCalculator with statutory tiers + entitlements.
- Claims pipeline: verification, evaluation, missing-info loop, report funnel,
  disruption types incl. schedule_change / returned_to_origin / downgrade.
