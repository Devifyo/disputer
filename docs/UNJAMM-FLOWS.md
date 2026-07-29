# Unjamm - Flight Compensation Flows

Living documentation of the flight-dispute module: how the flows work, where
the code lives, and what changed. **Update this file whenever a flow changes.**

Last updated: 2026-07-28

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
- Unlock: when the last signature lands -> `claims.signed_at`, event
  "All authorisations signed - your claim is unlocked for filing"; the
  workflow transition to ready_to_file then writes the stage's own
  customer step. (The old pre-written "Claim submitted - waiting for the
  airline's response" pending step is gone - it claimed a submission that
  had not happened; the two stale rows were relabelled 2026-07-29.)
- Where users see documents: claim Documents tab ("Authorisation documents",
  SIGNED/AWAITING badges) and the Sign page.
- Dropbox test mode: DROPBOX_SIGN_TEST_MODE=true -> TEST watermark, not
  legally binding. Production needs a paid API plan + app approval.

## 5a. Airline directory & email routing

- `airlines` (name, IATA code, active, notes) + `airline_contacts`
  (purpose-based addresses: claims / legal / escalation / customer
  relations). Admin module: Flight Claims -> Airlines (search, CRUD, one
  address per purpose; emptying an address removes it). Seeded with the
  carriers already in the system - admins fill the addresses.
- Resolution: `Airline::match(name, flightNumber)` - the flight number's
  IATA prefix is authoritative (QK8474 -> Air Canada Express even when the
  claim says "Air Canada"), name is the fallback.
- Per-stage routing: lifecycle stages carry `airline_contact_purpose`
  (default: Filed -> claims). The admin claim composer's To field and the
  filing recipient prefill from the claim's airline + the stage's purpose,
  with a directory hint under the field (and an "add it to the directory"
  warning when the airline or contact is missing).

## 5b. Multiple workflows per airline

- `claim_workflows` - named lifecycles. One is the DEFAULT (used by every
  airline without its own); each airline may attach to exactly ONE workflow
  (Airlines form -> Workflow select; deleting a workflow drops its airlines
  back to the default).
- Lifecycle Management has a workflow switcher (chips with DEFAULT badge +
  attached-airline counts), "New workflow" (duplicates the default's stages),
  "Make default" and "Delete". Stages, transitions, timers, ordering and
  routing are all per-workflow (stage keys unique per workflow).
- Resolution: `Claim::resolvedWorkflowId()` = matched airline's workflow ??
  default. The engine, stage badges, timers and manual actions all resolve
  through it - e.g. an "Air Canada process" workflow can set a 60-day
  response window while everyone else keeps 30 (tested).
- Per-step emails: every stage carries `airline_contact_purpose` (Filed ->
  claims, Awaiting Admin Escalation -> escalation) resolved against the
  airline's directory contacts - so each airline gets its own address at
  each step, including a dedicated escalation address.
- The airline form shows all four contact purposes as fixed slots (Claims
  department / Legal / Escalation / Customer relations) plus the Workflow
  select; lifecycle stages route to whichever purpose they declare.

## 5c. Claim workflow state machine (configurable lifecycle)

- Stages live in `claim_lifecycle_stages` (admin-managed: Flight Claims ->
  Lifecycle) - name/key/color/icon/order (drag-and-drop), initial/final/
  system flags, customer visibility + simplified customer label, manual vs
  automatic entry, auto-timer (delay days + target stage), notifications
  (notify_admin -> alert email; notify_customer -> `claim-stage-update`
  email with the simplified label), AI action on entry (drafts the airline
  claim / follow-up / regulator complaint for admin review - never sent),
  required roles for manual entry (enforced in the engine), allowed next
  stages (previous stages shown derived), preview. System stages are locked (key immutable,
  cannot deactivate/delete). Custom stages (e.g. Legal Review between Ready
  To File and Filed) slot in via config only - no code changes.
- Default lifecycle: draft -> awaiting_signature -> ready_to_file -> filed
  -> awaiting_response -> responded -> paid|denied -> awaiting_escalation ->
  escalated -> litigation -> closed.
- Engine: `ClaimWorkflowService` - the ONLY way claim states change
  (controllers/UI never touch workflow_state). Validates transitions against
  the config, runs side effects (cancel/start timers, customer timeline
  entry from customer_label, admin alert email `claim-escalation-alert`),
  writes the immutable audit trail (`claim_audit_logs`: action, from/to,
  via customer/admin/system/airline, actor, notes). Audited actions include:
  claim created (upload/manual/trip), eligibility evaluated, consent
  recorded, each signature, filing, airline response, every stage change.
- Automatic transitions (system events only): confirm -> awaiting_signature;
  all signatures -> ready_to_file; filed -> awaiting_response (instant
  chain); awaiting_response -> awaiting_escalation after 30 days
  (`claim_workflow_timers` + `claims:evaluate-workflow-timers`, daily 08:00,
  admins notified). Never automatic: escalation to regulator, paid, denied -
  always explicit admin actions.
- Admin claim detail: Workflow card shows the current stage, the running
  timer (due date/days left), the filing record, and the configured manual
  next-stage actions - filing captures recipient/reference/attachments;
  "responded" requires the airline's response text (audited via=airline).
  Timeline tab carries the internal audit trail (never customer-visible).
- Customers only ever see the simplified customer_label entries in their
  timeline; airline_letter, filing, drafts and audit logs are excluded from
  the customer API (regression-tested).

## 5d. Landing page live disruption board

- The "We're watching, right now" board serves REAL disrupted flights:
  LiveDisruptionFeedService scans FlightAware departures at 6 hubs
  (LHR/CDG/FRA/AMS/YYZ/JFK, last 12h, 2 pages each), keeps cancellations and
  3h+ delays, prices the "You get" column by regime (US: cancellations only,
  REFUND). Endpoint GET /api/live-disruptions (throttled, 5-min browser
  cache); the landing page swaps rows into the flip animation, falling back
  to a static sample pool.
- Cost control: ONE scan per hour (cache TTL 3590s), warmed by the scheduler
  (warm-live-disruptions, hourly) so visitors never trigger FlightAware
  calls; last good scan kept as a permanent stale backup; quiet windows are
  padded with samples so the board never looks empty.

## 6. Inbound email claims

customer -> claims@unjamm.com (Hostinger MX) -> forwarder ->
claims@claims.unjamm.com (MX -> SendGrid) -> Inbound Parse ->
`/api/webhooks/sendgrid/claims-inbound` -> itinerary parsed -> master claim.
Display address env: CLAIMS_DISPLAY_ADDRESS. Known gap: transactional/no-reply
senders also create accounts+claims (no sender filter yet).

## 6a. Airline correspondence (outbound + replies)

One mailbox, two streams - the webhook splits them before the itinerary
importer runs:

- **Outbound** (admin claim detail -> "Send to airline"): AirlineClaimMail
  goes out From CLAIMS_DISPLAY_ADDRESS ("Unjamm Claims") with
  `Reply-To: claims+{reference-lowercase}@CLAIMS_REPLY_DOMAIN` (the Inbound
  Parse host accepts any local part, so the token survives). The claim
  reference is appended to the subject once (`[Ref: CLM-XXXXXXXX]`) if
  missing. Selected composer attachments (POAs, assignment, itinerary,
  customer docs, admin extras) resolve via `Claim::documentPath($key)` and go
  out as named files. Stored as an immutable `claim_correspondence` row
  (direction=outbound, sent_by) + audit entry. A send while the claim is in
  `ready_to_file` IS the filing: the workflow transitions to `filed` with the
  filing record (recipient/subject/attachments) and the auto chain takes it
  to `awaiting_response` + 30-day timer.
- **Inbound**: `ClaimCorrespondenceService::matchInbound()` checks, in
  order: (1) reply-token in any recipient (`claims+clm-...@`), (2) claim
  reference anywhere in the subject (covers airlines replying to the visible
  From address - the Hostinger forwarder still lands them on the webhook).
  Matched mail becomes a `claim_correspondence` row (direction=inbound,
  matched_by=reply_token|subject_reference), attachments stored under
  `claims/{user}/inbound/`, audit entry via=airline, and every admin gets a
  `claim-airline-reply-alert` email. No account/claim is ever created for an
  airline address. Unmatched mail falls through to the customer itinerary
  flow unchanged. The workflow does NOT auto-advance on inbound mail - the
  admin reads it in the Correspondence tab and records the response
  (`responded`) explicitly.
- **Admin UI**: claim detail work area gained a Correspondence tab (count
  badge): expandable cards per email, direction badges (SENT/AIRLINE),
  matched-by note, inline attachment preview for airline files
  (`inbound-{correspondenceId}-{index}` document keys).
- Customers never see airline correspondence (communication-visibility rule).
- Env: CLAIMS_REPLY_DOMAIN (default claims.unjamm.com).

## 6b. Unjamm Plus subscriptions

Completely independent of claim compensation, success fees and payouts.

- **Master switch** (Setting `subscriptions.enabled`, default OFF): while off
  the platform is fully free - every gate passes, checkout refuses, stored
  subscriptions are kept but ignored. Launch free, enable later, no deploy.
- **Plans** (`subscription_plans`, seeded with "Unjamm Plus" CAD 9.99/mo):
  admin-managed name/description/monthly/annual/currency/trial/sort/active.
  Stripe is managed automatically: saving a plan calls
  `StripeBillingService::syncPlan()` - creates the product on first save,
  renames it with the plan, and (since Stripe prices are immutable) archives
  and re-mints prices whenever the amount/currency changes, storing the IDs
  itself. Unchanged prices are reused. Price changes apply to EXISTING
  subscribers as well: every live subscription on the replaced price is
  moved to the new one (proration none - the new amount bills from the next
  cycle). Currency changes cannot be migrated by Stripe; those subscribers
  stay on the old price and the admin is told. Admins never see or touch a
  Stripe ID. Deactivating
  hides a plan from customers; existing subscribers keep it.
- **Feature gates** (Setting `subscriptions.features`):
  `SubscriptionGate::FEATURES` lists what can require Plus (flight_claims,
  flight_monitoring, ai_claim_drafting, ai_follow_up_drafts,
  ai_regulator_drafts, priority_processing, multi_passenger). Unticked =
  free for everyone. `SubscriptionGate::authorize()` guards endpoints
  (claim creation, trip protection) with a 402 + `subscription_required`
  payload; the SPA intercepts 402 and routes to /flight-disputes/plus.
- **Stripe billing** (`StripeBillingService`, stripe-php): Checkout session
  (customer reused via users.stripe_customer_id, promotion codes allowed,
  trial days honoured), Customer Portal, cancel-at-period-end, reactivate,
  plan/interval change with proration, invoice listing. Pricing in CAD (C$9.99/month; perks: priority filing queue, multi-passenger / family accounts, fully automatic claim filing on consent).
- **Webhook pipeline**: BOTH `/stripe/webhook` (the URL registered in the
  Stripe dashboard - kept so the already-working delivery keeps working) and
  `/api/webhooks/stripe` land on `StripeWebhookDispatcher`: verify the
  signature once, then offer the event to every registered
  `StripeEventHandler` (open/closed - products plug in via
  AppServiceProvider). `LegacyPlanEventHandler` routes exactly what the old
  controller routed to the untouched legacy `SubscriptionService`
  (/admin/plans product); `PlusSubscriptionEventHandler` funnels
  subscription-bearing events into the one `syncFromStripe()` mirror.
  Product separation: Plus checkouts/subscriptions carry
  `metadata.product=unjamm_plus`; the Plus sync refuses anything without the
  marker, a matching plan price, or an existing mirror row, and the legacy
  handler skips marked events. A handler failure returns 500 so Stripe
  retries (all syncs idempotent); one product's failure never blocks the
  other. Access = status in active/trialing/past_due, minus expired
  cancel-at-period-end.
- **Admin module** Flight Claims -> Subscriptions: master switch, stats
  (total/active/cancelled/failed/expired, MRR + ARR from live plan prices),
  plan CRUD modal, feature checkboxes, subscriber list (search, status,
  invoices modal, Stripe dashboard link, cancel/reactivate via styled
  confirm).
- **Customer** `/flight-disputes/plus` (Vue): member card + Manage billing
  (portal), or plan cards with monthly/annual toggle -> Stripe Checkout;
  after success the page polls briefly while the webhook lands. When the
  system is off it says everything is free.

## 6c. Payments & payouts

Airline money in -> success fee -> passenger money out. Fully independent of
subscriptions; permissioned beyond the admin role.

- **Tables**: `payments` (gross / fee% / fee / net per claim+passenger),
  `payouts` (one transfer attempt, Wise or manual), `payout_transactions`
  (append-only ledger incl. currency conversions - model throws on
  update/delete), `payment_logs` (immutable audit: actor, IP, old/new).
- **Flow**: admin records the airline payment (Flight Claims -> Payments ->
  Record) - fee auto-calculated from Setting `claims.success_fee_percent`
  (25%); net = gross - fee. Overriding the fee (at record time or later)
  requires the `payments.override_fee` permission and keeps calculation
  history (ledger row per recalculation + old/new in the audit).
  Status machine: pending -> received -> ready_for_payout -> processing ->
  paid, with failed/cancelled/refunded branches - illegal jumps throw.
- **Wise payouts** (`WisePayoutService`): `WISE_SANDBOX=true` flips the
  whole integration to Wise's sandbox (own URL + own credentials
  WISE_SANDBOX_API_TOKEN / WISE_SANDBOX_PROFILE_ID - sandbox is a separate
  Wise account, live tokens are invalid there). Profile auto-resolves from
  the token (business preferred) when not set; `php artisan wise:setup`
  verifies the active environment and registers the webhook. The admin
  Payments page shows a violet "Wise sandbox" badge while in sandbox.
  Live: WISE_API_TOKEN / WISE_PROFILE_ID.
- **Customer payout bank accounts** (`user_payout_accounts`): the customer
  saves where their money should go - one account per currency
  (EUR IBAN / GBP sort code / CAD institution+transit / USD ACH+address),
  per-currency validation with forgiving input (spaces/dashes stripped).
  Details are ENCRYPTED at rest (encrypted:array cast); only the masked
  tail (····3000) ever leaves the server - including to the owner and to
  admins. SPA: "Payout bank details" card on the claim Compensation tab -
  RED urgent state while missing, neutral once saved. Accounts belong to
  the USER, so they reuse across all their claims.
  **The destination is the CUSTOMER's choice, never the admin's**: the
  first saved account becomes the payout default (`is_default`);
  with multiple accounts the customer switches via "Use for payouts"
  (badge "PAYOUTS GO HERE"), deleting the default promotes the newest
  remaining account. The admin's payout drafter shows the default
  account READ-ONLY (it pins the payout currency); when the customer has
  no account the drafter shows a red "No bank details yet" panel with a
  **Request bank details** button (templated email `payout-details-request`
  + in-app bell + claim timeline step + audit `bank_details_requested`)
  and a secondary "ask via Wise email" fallback.
  **One-click send**: for admins with payouts.send the button is "Send
  Wise payout" - a styled confirm popup (amount, destination, conversion
  note) then draft + queue in one action, so payouts never sit forgotten
  in draft. Admins without the send permission keep the two-step
  "Prepare" flow (drafts for a senior to send). Existing drafts keep
  their separate Send/Cancel actions.
  Recipient priority in WisePayoutService: account pinned at draft time >
  customer's default/currency-matching saved account > sandbox test
  details > Wise email request.
  PSD2 constraint: Wise no longer permits API funding (SCA key signing) on
  PERSONAL accounts - transfers from a personal profile are created as
  drafts and must be funded from the Wise website/app. BUSINESS profiles
  (the live devifyo profile) still support key signing: register the public
  key from storage/app/keys/wise-sca.pub under the business account's API
  settings before live payouts. wise:setup warns when the active profile is
  personal. admin drafts a payout
  (choice of CAD/USD/EUR/GBP), reviews, sends - the transfer runs in the
  queued `ProcessWisePayout` job (3 tries, backoff). Quote fixes the
  exchange rate (stored on the payout AND as a ledger conversion row - a
  re-quote on retry appends a new row, never overwrites). Recipients are
  Wise "email" type: Wise asks the passenger for bank details, Unjamm never
  stores account numbers. Retry failed / cancel draft / refresh from Wise;
  webhook `/api/webhooks/wise` treats the payload as a
  PING only - the state acted on is re-fetched from Wise's API with our own
  token, so a forged webhook can never inject a status (tested). RSA
  signature verification additionally applies when WISE_WEBHOOK_PUBLIC_KEY
  is set (Wise's live PEM is no longer published in their docs; the
  fetch-back design removes the dependency). outgoing_payment_sent
  completes the payout + marks the payment paid.
  Manual payouts (bank/Interac) can be recorded with amount, currency, FX
  rate and reference and settle the payment immediately.
- **Permissions**: payments.view / payments.manage / payments.override_fee /
  payouts.send - seeded to the admin role, revocable per admin for
  four-eyes setups. Customers see only their own payment (read-only block
  in the claim API; internal notes never ship).
- **Notifications**: customer gets queued template emails
  (payment-received, payout-initiated, payout-completed, payout-failed,
  payment-refunded - all admin-editable) + in-app database notifications
  (bell in the user sidebar; endpoints under /flight-disputes/api/
  notifications). Admin alerts (new payment, large payment >= Setting
  payments.large_payment_threshold, Wise transfer failed/retry required) go
  to AdminAlertRecipients type "payments" + in-app bell in the admin
  sidebar. Claim timeline gets customer-visible payout events.
- **Admin UI** Flight Claims -> Payments: dashboard (collected / fees /
  paid out / pending / processing / failed), payments list and full
  transaction history with date/passenger/claim/status/currency filters and
  CSV export, payment detail (split, fee override, payout actions, ledger +
  audit side by side).
- **Customer UI**: claim Compensation tab shows the payout card (gross /
  fee / you-receive, transfer status + reference, event timeline).

## 7. Admin

- Settings -> Trip Eligibility: confidence threshold.
- Settings -> Flight Claims: success fee %, social proof stats.
- Settings -> Website: feature toggles (Setting `app.plus_promo_enabled` -
  show/hide the Unjamm Plus upsell on claim confirmation).
- Sidebar "Flight Claims" group (collapsible submenu): Trip Reviews (pending
  badge), Protected Trips (admin.flight-claims.trips - all monitored trips,
  search + status filters, read-only detail panel) and Claims
  (admin.flight-claims.claims - all claims with workflow stage incl.
  signature progress).
- Admin claim detail page (admin.flight-claims.claims.show): compact header +
  context rail (verdict, FlightAware tracking snapshot with sched/actual
  times + delays + gates, payout, passengers & signatures w/ POA preview,
  timeline) and the outbound claim email composer - AI-drafted via
  ClaimLetterService: Gemini reads the FULL claim record (tracking snapshot,
  verdict, entitlements, signatures, consent) plus every stored document as
  multimodal inline_data (ticket, customer evidence, admin extras; max 6
  files / 8MB each) and writes a jurisdiction-specific demand letter
  (CA: APPR + s.19(4) 30 days + CTA; US: DOT Parts 250/260 + OACP;
  EU: 261/2004 + 14 days + NEB; UK: UK261 + CAA/ADR). Deterministic
  jurisdiction-aware template fallback. Fields To/Subject/Body persisted on
  claims.airline_letter, attachments listed
  (signed POAs, Assignment, ticket, supporting docs - streamable via
  admin.flight-claims.claims.document). Attachments are selectable
  (checkboxes, default = all; stored in airline_letter.attachments) and the
  admin can upload extra external documents (airline_letter.extra, removable).
  "Send to airline" is intentionally disabled until the outbound
  mailbox/sending flow is defined.
- AI Drafting module (client spec): three draft types via ClaimLetterService -
  initial airline claim, follow-ups (reasons: no_response / info_request /
  partial / rejected / manual; admin pastes the airline's response as
  context; prior correspondence fed to the prompt) and regulator complaints
  (CTA / US DOT / CAA / NEB by jurisdiction). Strict rule in every prompt:
  cite the Eligibility Engine's regulation+article EXACTLY, never invent
  legal facts; AI writes correspondence only. Every generation and every
  admin edit is an immutable version in claim_drafts (type, version,
  generated_by ai/template/admin, author, context); Draft history panel
  supports Load / Approve (one approved final per type); AI never sends -
  admins review and send everything. Data fidelity: prompts forbid invented
  dates/amounts/placeholders; today's date + the real original-demand date
  and exact days-elapsed are computed and injected (elapsed-time may only be
  stated from those figures); follow-ups/complaints are blocked until an
  initial claim draft exists.
- Trip Reviews queue: Review|All tabs, approve/reject (reason required,
  emailed), decision recorded (who + source ai/rules/ai+rules/admin).
- Claim review decisions live on the admin claim detail page: claims in
  pending_eligibility_review show a "Your decision is needed" card -
  Approve (prices compensation, closes the review event, sends the
  "you're owed" email) or Reject (reason required min 10 chars, compensation
  cleared, `claim-eligibility-rejected` email). Decision recorded in
  eligibility_details (decided_by_admin, decided_at) + decision_source=admin.
  Sidebar: Claims submenu badge = claims awaiting review; group badge =
  trips + claims combined.
- Templates: all customer emails are admin-editable EmailTemplates.

## 8. Key conventions

- Amounts/eligibility NEVER computed in the UI - presentation only.
- One master claim per booking (never one per passenger).
- Statuses: draft -> pending_eligibility_review | eligible | rejected;
  workflow: confirmed_at -> signed_at (unlocked for filing).
- PHP: imports at top, never inline FQCN. Hyphen "-", never em-dash.
- CTA styling: bg-slate-900; pending = amber clock icon; timeline pending
  events always render last.
- Admin confirmations: never native wire:confirm / js alert - dispatch
  `admin-confirm` to the shared `x-admin.confirm` modal (danger: true for
  destructive actions) and include the component once per page.
- Tests: `php artisan test` (DB disputer_testing) - keep green; frontend
  changes need `npm run build`; backend changes need queue worker restart
  (`supervisorctl restart unjamm-queue:`). After running artisan as root:
  `chown -R www-data:www-data storage`.

---

## Changelog

### 2026-07-27
- Dashboard totals readable: the three money cards (collected / fees /
  paid out) now headline ONE number - the base-currency equivalent
  (WISE_DASHBOARD_CURRENCY - set to USD on this box, default CAD) via
  Wise mid-market rates cached 6h - with the true per-currency figures as
  a small breakdown line. The rates call sends source/target as real
  query params AND matches the returned pair - Wise returns its full rate
  list when it does not recognise the filter, which briefly produced
  garbage totals (caught by the client in sandbox).
  Each money card is clickable and opens a breakdown popup: per-currency
  table (payment count, amount, mid-market rate, converted value), total
  row, rates-are-estimates footnote. The card subline collapses to
  "N currencies - click for the breakdown" beyond two currencies, so the
  cards stay clean at any volume.
  Admin sidebar bell icon inlined as SVG (the lucide JS replacement was
  lost inside the nested Livewire component).
- Retired customer modules (Documents, My Cases): the sidebar links are
  COMMENTED OUT (kept in the blade) and the GET routes are wrapped in a
  new `retired_module` middleware (`BlockRetiredModules`) - hiding a link
  is not access control, a bookmarked URL would still get in. Browsers
  are redirected to the dashboard with "That section has moved -
  everything now lives under Flight Disputes"; JSON clients get a plain
  404. Routes, controllers and views all stay in the codebase: removing
  the middleware from the route group reopens the module.
- Profile -> Billing is now the member's self-service home for Unjamm
  Plus (`App\Livewire\User\PlusMembership`): membership status with what
  happens next in plain words, the card on file (brand/last4/expiry) with
  an "Update card" deep-link into Stripe's payment-method flow,
  PAUSE billing (optionally until a date - Stripe pause_collection,
  mirrored locally on subscriptions.paused_at / resumes_at), resume,
  cancel-at-period-end behind an inline confirmation that offers pausing
  instead, "Keep my membership" to undo a pending cancellation, the full
  Stripe portal, and an invoice list with PDF downloads. Non-members see
  an invitation instead of billing controls.
  The legacy case-management "Billing & Plans" partial is COMMENTED OUT
  in profile/edit.blade.php (kept in the codebase, not deleted).
- Claims list gained the missing "Awaiting confirmation" queue (eligible
  but the customer has not consented yet - the claim cannot move until
  they do) with its own count badge, plus a "★ Plus" toggle beside the
  tabs. Membership is deliberately NOT a tab: it is a separate axis, so
  the toggle narrows whichever lifecycle tab is open (e.g. In review +
  Plus only). Plus-first ORDERING already applies automatically when the
  priority_processing perk is enabled - the toggle is for focusing on
  members, not for turning priority on.
- Airline replies feed the drafts automatically (client caught it): the
  follow-up context box used to be a blank "paste the airline's response"
  field even though inbound replies are already stored on the claim. It
  now prefills from the latest inbound correspondence (new text only -
  the quoted history is stripped) and labels its source, with a Clear
  button and a manual paste path for airlines that answer by phone or
  post. The AI's `history` context also merges inbound replies with our
  own drafts (labelled "Airline reply - X" vs "Unjamm - ..."), so a
  follow-up is written against what the airline actually said rather
  than only against our previous letters.
- Customer claim list follows the JOURNEY, not the verdict (client caught
  a paid claim still badged "Eligible for Compensation"): eligibility is
  decided once and never changes, so it cannot be the badge on its own.
  `Claim::customerStage()` now also returns a tone and checks payments
  first - but only claims "Paid out" when EVERY live payment is paid.
  A claim settled in instalments reads "Partly paid" while any payment is
  still outstanding, and "Payout on the way" when the airline has paid us
  but nothing has reached the customer yet (cancelled/refunded payments
  are ignored). One payout landing must never imply the rest has. The claims API sends `stage_label` + `stage_tone`
  alongside the unchanged `status_label`, and the SPA list uses them for
  the badge, the filter chips and search. Same helper drives the
  dashboard, so both views always agree.
- "Remind customer" on the admin claim header: when a claim waits on the
  customer, one button nudges them by EMAIL (admin-editable templates
  `claim-confirm-reminder` / `claim-sign-reminder`, each with the amount,
  flight and a deep link straight to the confirm or sign step) AND in the
  app bell (`ClaimActionNeeded`, both channels, same wording). The button
  knows which step is outstanding and labels itself accordingly. Rate
  limited to once every 24h (claims.reminded_at) - the button then reads
  "Reminded 3 hours ago" and is disabled, because a reminder that arrives
  twice reads as a fault, not urgency. Every nudge writes an audit entry
  and a customer-visible timeline step.
- Authorisation gate on airline emails (client caught it): the composer
  let an admin send a claim letter while the claim was still "Awaiting
  confirmation" with 0/1 signatures - and our letters state that a signed
  authority is attached, so that would have been a false assertion to the
  airline. `Claim::canContactAirline()` now returns [allowed, reason]:
  blocked unless the claim is eligible, CONFIRMED by the customer and
  every signature is in. Claims already at ready_to_file or beyond pass
  through, so follow-ups and escalations keep working. The Send button is
  disabled with the reason shown above it ("You can draft and save now -
  sending unlocks by itself"), and send() refuses server-side regardless
  of the UI. Regression-tested through all three states.
- Templates reach MANY airlines (client asked): scope moved from a single
  `airline_id` to a pivot with one rule - NO airlines attached means every
  airline (a "house" template), one or more means exactly those. The
  editor has an "All airlines" tick plus a multi-select list; the list
  shows an ALL AIRLINES chip or "Air Canada, Lufthansa +2". Resolution
  (`AirlineEmailTemplate::defaultFor` / `scopeForAirline`): an
  airline-specific template always beats a house one, then the marked
  default, then the most recently edited - so the AI and the composer
  both fall back gracefully for carriers with no template of their own.
  The composer addresses the letter using the CLAIM's airline contact
  (a template may cover several). One default per letter type is now
  enforced in the service across overlapping reach, since the old unique
  index was scoped to a single airline.
- Airline email templates + hybrid composer (Flight Claims -> Claim
  Templates): per-airline letters typed as initial claim / follow up /
  escalation / final notice / custom, each with subject, body,
  is_default, is_active and created_by/updated_by. Exactly ONE default
  per airline+type, enforced by a unique index on
  (airline_id, type, is_default) where "not default" is stored as NULL -
  a race cannot leave two. Admin UI: search + airline/type filters,
  create/edit, duplicate (copy lands inactive and non-default so it
  cannot be sent by accident), make default, enable/disable, delete
  (own permission), and preview rendered against a REAL claim of that
  airline with a warning listing unresolvable variables.
  `TemplateRenderer` owns the 17 documented {{variables}} (passenger,
  flight, times from the tracking snapshot, delay, claim reference,
  compensation, regulation/article, today) - values come from the claim
  and the Eligibility Engine's stored verdict, never from the renderer;
  an unknown placeholder is left VISIBLE rather than silently blanked.
  Composer on the claim: a mode switch with **AI draft as the default**
  and "Use saved template" as the manual route. AI route feeds the
  airline's default template into the prompt as a STYLE base (structure,
  tone, airline-specific wording) with an explicit rule that facts, law
  and amounts still come only from the claim record - the citation guard
  is untouched. Template route renders the template verbatim, fills the
  variables, and addresses it to the airline contact that letter type
  belongs to (initial/follow-up -> claims, escalation -> escalation,
  final notice -> legal). Both routes stay fully editable.
  Sending gained CC/BCC (invalid addresses dropped, never fatal), an
  email preview (from/to/cc/bcc/subject/attachment count/rendered body
  with an unresolved-variable warning) and scheduling: a "scheduled"
  correspondence row appears immediately in the claim's history and
  `SendScheduledClaimEmail` delivers it, flipping the status (failed
  status on terminal failure). History now records provenance -
  AI draft / Template (with its name) / Written by hand - plus cc and
  the scheduled time.
  Airline directory gained ICAO code, country and delete (cascades
  contacts + templates; claims keep the airline name they were filed
  under). New permissions: airlines.manage, claim_templates.manage,
  claim_templates.delete, claim_drafts.generate, claim_emails.send -
  granted to the admin role by the migration. Template and airline
  actions are audited to the new append-only `admin_activity_logs`
  (immutable, morphed subject, actor + IP + old/new values) via the
  `AdminActivity` service; claim-scoped events (AI draft generated,
  email sent/scheduled) keep going to the claim audit log so they show
  on the claim timeline.
- Passenger management (Flight Claims -> Passengers): passengers are not
  a table - a person appears as a signature-roster entry, a name on a
  parsed ticket, a claim's lead passenger and a monitored trip - so
  `PassengerDirectory` merges those records into one profile per human
  and owns the identity rule: a SIGNER's own email address first (name
  variants like "T. Hagyal" merge onto it), then the normalised name. A
  claim's contact email counts as identity only on a single-passenger
  claim, and the account holder's email never does - otherwise every
  passenger on a family booking would fuse into one person (both caught
  by tests). Guardians and the minors they sign for each get their own
  linked profile.
  The screen: counter tiles that double as filters (people / awaiting
  signature / no email on file / minors), search across name, email,
  claim number, reference and flight, and a drawer per person showing
  their signatures, claims (with compensation totals), monitored trips
  and signed POAs. Admin actions where support actually gets stuck:
  correct a signer's email and send in one click, resend the signature
  request, or copy the signing link to share another way - all through
  ClaimSignatureService::invite() and written to the claim's audit log.
  Scale note: the merge runs in PHP over the claim book; materialise it
  into a table if that stops fitting in memory.
- Admin notifications unified (email + in-app for EVERY alert type):
  new `AdminNotifier` service is the single delivery point - services now
  raise an `AdminAlert` value object (type, title, description, url,
  email template + placeholders) and the notifier decides who hears it
  and how: queued template emails to the recipients configured per alert
  type in Admin -> Settings -> Flight Claims (multiple mailboxes, each
  subscribed to what it cares about, falling back to the admin accounts
  when nobody subscribes) PLUS an in-app bell notification
  (`AdminAlertNotification`) for every admin account. Airline replies and
  escalation decisions previously emailed only - they now ring the bell
  too; payments alerts moved off the payment-specific notification onto
  the shared one. Adding a channel (Slack/SMS) is a change to
  AdminNotifier alone; adding an alert type is a new entry in
  AdminAlertRecipients::TYPES, which the settings UI renders
  automatically. Emails are queued, so inbound-webhook handling never
  blocks on SMTP, and a failed send is logged with the mailbox (it used
  to log `$admin->id` on an array).
- Expense reimbursements are fee-free (business rule from the client):
  payments carry expenses_amount - the part of the gross that reimburses
  out-of-pocket receipts. The success fee is computed on
  (gross - expenses) at record time AND on fee override.
  Record modal is built around ADDITION (the client found "gross, of
  which expenses" confusing): "Compensation the airline paid" plus an
  "Airline also paid back the passenger's expenses" TOGGLE. Enabling it
  (auto-enabled when the claim has approved receipts) reveals the claim's
  APPROVED ClaimExpense rows, each pre-ticked with category, date and
  amount - untick anything the airline did not cover; receipts in another
  currency are shown disabled with a hint, and an "Other / unlisted
  expenses" box covers the rest. Optional "Charge a fee on expenses too"
  checkbox + percent (payments.expense_fee_percent, default 0) - expenses
  stay fee-free unless the admin opts in. Live receipt-style preview:
  Compensation + Expenses (NO FEE badge) = Total received, minus success
  fee (% of compensation only), minus expense fee when charged, =
  Customer receives. The gross is the sum, computed on save.
  The whole record form runs CLIENT-SIDE (Alpine + deferred @entangle):
  ticking a receipt or typing an amount never round-trips, and the totals
  recompute instantly; Livewire receives the state with the save request
  and recomputes the amounts server-side from the database, so a tampered
  client cannot inflate a payout. Receipts in a currency other than the
  payment's are shown disabled and are neither counted NOR marked
  reimbursed (regression-tested).
  Recording a payment marks the ticked receipts reimbursed
  (reimbursed_amount / reimbursed_at, ids captured in the audit log);
  the customer's Expenses tab then shows a green PAID BACK badge and
  "Reimbursed to you on {date}". Ledger fee row spells the split out;
  customer card shows "Includes X for your out-of-pocket expenses -
  reimbursed in full, no fee charged"; the PDF receipt breaks out the
  expense row and labels the fee "of the X compensation portion".
- Estimate vs payouts disambiguated: once a claim has real payments, the
  eligibility/estimate block ("Estimated compensation", entitlements,
  how-the-amount-is-set) folds into a collapsed "How this claim was
  assessed" panel with an explicit note that the airline has since paid -
  it can no longer be mistaken for additional money coming. Claims
  without payments still show the full estimate as before.
- Customer payout cards decluttered + customer receipts: the per-payment
  timeline now shows only the steps that moved the money (received /
  payout created / conversion / transfer / completed) - internal retry
  churn (failed/cancelled attempts) never reaches the customer, and
  repeated attempts collapse to the one that counted. Timelines are
  collapsed behind "View history (N)". Each card has a "Receipt" button:
  route /flight-disputes/payments/{payment}/receipt (owner-only), same
  branded PDF minus failed/cancelled ledger rows. Receipt generation
  refactored into PaymentReceiptService (admin + customer share it;
  admin keeps the full ledger).
- Multi-instalment fix (client question exposed it): the customer claim
  API returned only the FIRST payment (->first()) - extra instalments,
  including paid ones, were invisible to the customer. show() now returns
  'payments' (all, newest first) and the SPA Compensation tab renders one
  dark payout card per instalment ("Payout 2 of 3" + airline payment
  date), each with its own split, transfer status and timeline.
- PDF receipt per payment: "PDF receipt" button in the payment detail
  modal downloads RCPT-{claim}-{id}.pdf (dompdf, view
  pdf/payment-receipt): branded dark header with the real logo, passenger
  + claim block, compensation/fee table with net total, payouts table
  with Wise transaction numbers and FX rates, full transaction history,
  reference note and footer. Route
  admin/flight-claims/payments/{payment}/receipt, gated by
  payments.view. UX polish batch: instant tab switching (entangled tab +
  wire:loading veil), tab switch resets pagination, KPI popup closes
  optimistically, loaders on KPI cards.
  "≈" marks converted totals; single-currency totals show exact with no
  marker; no token/rate available falls back to the breakdown alone.
  Rates are display-only - transfers always price from their live quote.
- Race fix (caught in sandbox: the customer got TWO "payout completed"
  emails): the Wise webhook and wise:simulate/refresh could complete the
  same transfer concurrently - each process's stale model passed the
  "not yet completed" check. Completion and failure in WisePayoutService
  now use an atomic conditional UPDATE (status != completed) as the claim;
  only the winning process writes the ledger row and notifies. Also
  removed transition()'s generic no-amount ledger rows (completed/failed/
  refund) - every ledger row is now written once by the business action
  itself with its amount; refund() writes its own row. Duplicate rows and
  bell notifications cleaned from the test data.
- Payout destination is now customer-owned: first saved bank account
  auto-becomes the default, customer switches it with "Use for payouts",
  admin sees it read-only in the payout drafter and gets a
  "Request bank details" nudge button (email + bell + timeline + audit)
  when the customer has none; Wise-email request demoted to an explicit
  fallback.
- Payments & notifications module (see section 6c): airline payments with
  automatic 25% fee split, permissioned fee override with history, Wise
  payouts (queued, retried, webhook-synced, append-only FX history, email
  recipients so no bank details are stored) + manual payouts, immutable
  ledger and audit log, CSV export, admin dashboard + filters, customer
  payout card + timeline, queued template emails and in-app notification
  bells for both admins and customers. Closes the "payouts" gap in
  PROJECT-STATUS.


### 2026-07-22
- Unjamm Plus subscription module (see section 6b): master enable/disable
  switch (off = everything free), admin-managed plans with Stripe IDs,
  per-feature paywall config, Stripe Checkout/Portal/webhook sync, admin
  dashboard + subscriber management under Flight Claims -> Subscriptions,
  customer /plus page. Gates enforced on claim creation and trip protection;
  fully independent of compensation and success fees.

### 2026-07-21
- Smooth scrolling on the marketing site (`resources/js/marketing.js`, Lenis
  via Vite - the layout now loads a bundle where it previously had none).
  Lenis eases the NATIVE scroll position; a transform-based scroller
  (ASScroll / older locomotive) was rejected because a transformed ancestor
  becomes the containing block for `position:fixed`, which would break the
  fixed navbar, the scroll-progress bar and the flight-check modal.
  Disabled for touch (native momentum is better) and for
  prefers-reduced-motion. `html { scroll-behavior: smooth }` is kept as the
  no-Lenis fallback but switched off when Lenis runs - two animators on one
  scroll position fight frame by frame (this was the cause of the
  stuck-then-snap bug in the first hand-rolled attempt). Modal opts out with
  `data-lenis-prevent`, and Lenis stops while `body` is overflow-hidden.
- Public flight check (`App\Services\Marketing\PublicFlightLookupService`,
  `POST /api/flight-lookup`, throttle 10/min + CSRF): a "Search your flight"
  button sits immediately left of the LIVE badge on the landing page and
  opens a modal taking flight number + departure date. Anonymous visitors get
  the flight's real status and a PROVISIONAL eligibility read with an
  estimated amount, then a CTA to create an account. Deliberately scoped:
  rule-based evaluator only - no AI call, no DB writes, no account - and each
  flight+date is cached (30 min found / 15 min not found) so repeat lookups
  and bots cost nothing at FlightAware. Four honest result states: eligible,
  disrupted-but-uncertain, not-disrupted (told plainly, never sold a claim),
  and not-found (outside the ~10-day tracking window - still invites a claim,
  since most claims are older than that). An API failure still shows a CTA
  rather than dead-ending the visitor.
  The result renders as a FlightAware-style card: carrier name (our directory,
  else FlightAware's operators endpoint cached forever, else blank - never
  "XX flight"), IATA codes, city and airport names, scheduled vs actual times
  in each airport's LOCAL zone with an early/late delta, and a progress track.
  A CANCELLED flight suppresses actual times, delta and progress - AeroAPI
  leaves the schedule populated, which would otherwise read as "2m early" on a
  flight that never operated - and shows a dashed route instead. Signed-in
  visitors get "Start this claim in your account" rather than the signup CTA.
  Guests also get a zero-friction second path under the primary CTA: "Email
  my ticket" (mailto to CLAIMS_DISPLAY_ADDRESS, subject prefilled with the
  flight) plus a copy button - the existing inbound pipeline (section 6)
  parses the ticket and creates BOTH the claim and the account, so a visitor
  can convert without filling in any form. Hidden for signed-in users.
- Expense receipts (`claim_expenses`, `App\Models\ClaimExpense`): the
  passenger uploads out-of-pocket receipts (meal / hotel / taxi / transport /
  rebooking / other) with amount, currency, date and description from a new
  Expenses tab on the claim; each receipt is linked to that claim by
  construction. Admins verify each one on the claim detail - approve, or
  reject with a customer-facing reason plus an internal-only note - record
  what the airline actually reimbursed, and see Claimed vs Reimbursed totals.
  Approved receipts become selectable attachments (`expense-{id}` document
  keys, gated on approved status) and are demanded in the AI letter and the
  template as a SEPARATE head of claim with a per-receipt breakdown; nothing
  is mentioned when no receipt is approved. Customers may delete a receipt
  only while it is pending. **No success fee is charged on expense
  reimbursement** - the fee applies to statutory compensation only, stated in
  both the customer and admin UIs.
- Regulator suggestion (`App\Services\Claims\RegulatorDirectory`): the
  competent enforcement body is resolved from the regulation + route.
  APPR -> CTA, UK261 -> CAA, US_DOT -> DOT OACP are single-body regimes;
  EU261 resolves to the National Enforcement Body of the member state where
  the disruption happened (departure state, else arrival state for inbound
  flights) from a 30-country NEB table (LBA, DGAC, AESA, ENAC, ILT, ...).
  Shown on the admin claim detail with the reason and a complaint-portal
  link; fed into the regulator complaint draft so the letter is addressed to
  the named body instead of "the competent NEB". When the route cannot
  settle it (neither airport in the EU/EEA) the card turns amber and asks
  the admin to confirm rather than guessing. Deliberately deterministic -
  naming the wrong authority is the same class of error as citing the wrong
  article, so AI does not pick regulators.
- Alert recipients are configurable per alert type: Setting
  `claims.alert_recipients` holds `[{name, email, alerts[]}]` rows managed in
  Admin -> Settings -> Flight Claims (add/remove rows, tick which alerts each
  person receives). `AdminAlertRecipients::for($type)` resolves the list;
  alert types are `escalation` (claim needs an escalation decision) and
  `airline_reply` (new inbound airline email). When nobody subscribes to a
  type the admin accounts receive it, so alerts are never silently dropped;
  a legacy comma-separated value is still honoured as "everyone gets
  everything". Both admin templates now use the same branded layout + slate
  CTA button as customer emails.
- Canonical legal citations (`App\Services\Eligibility\RegulationCitation`):
  a vetted table maps regime + disruption scenario -> exact article. The AI
  evaluator's citation is now advisory only - `normalise()` replaces it, so
  the engine (not the model) decides the law. Drafting receives a structured
  LEGAL BASIS block (regulation, article, what it covers, amounts, deadline,
  supporting provisions) plus an explicit allow-list; `inventedCitations()`
  rejects any draft citing an unauthorised provision, retries once, then
  falls back to the deterministic template. Backfill command
  `claims:normalise-citations` corrected 13 existing claims (e.g. APPR
  "ss. 20-22" -> "Section 19" on a cancellation; a denied-boarding claim
  citing "Article 7(1)" -> "Article 4").
- New delivery tracker `docs/PROJECT-STATUS.md`, kept current every session.

### 2026-07-18
- Airline correspondence (see section 6a): "Send to airline" is live - claim
  emails go out from CLAIMS_DISPLAY_ADDRESS with a per-claim reply-to token
  (claims+{ref}@CLAIMS_REPLY_DOMAIN) and the reference tagged in the
  subject; sending from ready_to_file files the claim through the workflow.
  Inbound webhook now splits airline replies (token or subject-reference
  match -> claim_correspondence + audit + admin alert email
  claim-airline-reply-alert) from customer ticket submissions (unchanged
  fallback). Admin claim detail gained the Correspondence tab with inline
  preview of airline attachments. New: claim_correspondence table,
  ClaimCorrespondenceService, AirlineClaimMail, Claim::documentPath()
  (shared by the admin document route and outbound attachments).
- Audit trail completed: eligibility approve/reject decisions, draft
  approvals, and admin document add/remove now write audit entries (were
  previously untracked). ClaimAuditLog is enforced append-only at the model
  level - updating or deleting an entry throws.
- Correspondence display: airline replies split into new text vs quoted
  history (collapsed behind a toggle; stored body stays complete). AI letter
  prompt gained strict FORMATTING rules - salutation line, blank-line
  paragraphs, amounts as a list, sign-off block - no more wall-of-text
  drafts.

### 2026-07-17
- Claim workflow state machine (see section 5b): configurable lifecycle
  stages (admin module Flight Claims -> Lifecycle with drag-and-drop
  reorder, transitions, timers, visibility, preview; system stages locked),
  ClaimWorkflowService engine (sole mutator of workflow_state), immutable
  claim_audit_logs, claim_workflow_timers + claims:evaluate-workflow-timers
  (daily 08:00), 30-day airline response deadline -> Awaiting Admin
  Escalation + admin alert email. Filing captures recipient/reference/
  attachments; responded requires the airline's response text. Paid/Denied/
  Escalation are admin-only by engine rule. Claims list stage pills and
  filters config-driven; admin claim detail gained the Workflow action card
  and the internal audit trail view.
- AI Drafting module completed per client spec: follow-up drafts (5 reasons,
  airline-response context, correspondence history), regulator complaints
  (CTA/DOT/CAA/NEB), immutable draft versions with approvals
  (claim_drafts + Draft history panel), strict citation + data-fidelity
  rules (exact engine citations, injected today/original-demand dates and
  days-elapsed; follow-ups blocked until an initial claim draft exists).
- Admin claim detail: workflow-aware; document preview modal; per-signer POA
  view from Passengers & signatures; tabbed work area (Claim email |
  Timeline).

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
