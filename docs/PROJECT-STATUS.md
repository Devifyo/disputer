# Unjamm - project status

**Last updated: 2026-07-22**

Living delivery tracker: what is built, what is partial, what is not started,
and what is blocked on someone else. Claude Code updates this file at the end
of every working session without being asked - see "Maintenance rules" at the
bottom.

Status keys: **Done** (built, tested, verified in production) ·
**Partial** (works but a named piece is missing) · **Not started** ·
**Blocked** (waiting on the client or a third party).

---

## 1. Claim intake

| Item | Status | Notes |
|---|---|---|
| Manual claim funnel | Done | Report flow -> master claim per booking |
| Inbound email claims | Done | SendGrid Inbound Parse -> itinerary parsed -> claim |
| Trip Protection monitoring | Done | FlightAware polling on checkpoints, `trips:monitor` every 5 min |
| Itinerary parsing (AI, multimodal) | Done | Documents read directly by the model |
| Sender filtering on inbound | Partial | Transactional/no-reply senders can still create accounts+claims |

## 2. Eligibility & compensation

| Item | Status | Notes |
|---|---|---|
| Eligibility Engine (EU261/UK261/APPR/US DOT) | Done | AI evaluator + rule-based fallback, confidence scored |
| Compensation calculation | Done | `CompensationCalculator` only - never in UI/controllers |
| Canonical legal citation table | Done | `RegulationCitation` - engine decides the law, AI never picks a citation |
| Admin eligibility approve/reject | Done | Prices the claim, emails the customer, audited |

## 2a. Expense receipts

| Item | Status | Notes |
|---|---|---|
| Customer uploads receipts | Done | Expenses tab: category, amount, currency, date, description + file |
| Receipts linked to their claim | Done | Created on the claim; ownership enforced on every endpoint |
| Admin verify / approve / reject | Done | Customer-facing reason + internal-only note, full audit trail |
| Attach approved receipts to emails | Done | `expense-{id}` keys, gated on approved status |
| AI claims reimbursement when receipts exist | Done | Separate head of claim with per-receipt breakdown |
| Track claimed vs reimbursed | Done | Per receipt + per-currency totals on the claim detail |
| No success fee on expense reimbursement | Done | Fee applies to statutory compensation only |

## 2b. Unjamm Plus subscriptions

| Item | Status | Notes |
|---|---|---|
| Master enable/disable switch | Done | Off (default) = everything free; stored subscriptions kept |
| Plan management (prices, trials) | Done | Admin CRUD; Stripe product/prices auto-created and re-minted on save - no IDs to manage |
| Per-feature paywall | Done | 7 gateable features; unticked = free |
| Stripe Checkout / Portal / webhooks | Done | Unified pipeline on the dashboard-registered `/stripe/webhook` URL; signature-verified; serves BOTH billing products |
| Admin dashboard + subscriber management | Done | Stats, MRR/ARR, cancel/reactivate, invoices |
| Customer upgrade page (/plus) | Done | Monthly/annual toggle, post-checkout polling |
| Gate enforcement | Done | 7 of 8 gates enforced: claim paths, monitoring, multi-passenger (at confirmation), priority queue (admin list ordering + badge), AI drafting (template fallback). auto_filing labelled config-only until auto-filing ships |
| Coupons / promo codes | Partial | Stripe promotion codes allowed at checkout; no admin UI for creating them (use Stripe dashboard) |
| Live Stripe keys | Blocked | Test keys configured; client flips STRIPE_* for launch |

## 3. E-signatures

| Item | Status | Notes |
|---|---|---|
| Dropbox Sign embedded signing | Done | Text-tag guided fields, POA + Assignment per signer |
| Signed PDF storage + backfill | Done | Webhook + `reconcile()` fallback (webhook-independent) |
| Multi-passenger signing | Done | One master claim, a signer row per passenger |
| Production go-live | Blocked | Client: paid API plan, app approval, `DROPBOX_SIGN_TEST_MODE=false` |

## 4. AI Drafting

| Item | Status | Notes |
|---|---|---|
| Initial airline claim drafts | Done | Full claim record + all documents read multimodally |
| Follow-up drafts | Done | 5 reasons, airline-response context, elapsed-days injected |
| Regulator complaint drafts | Done | CTA / US DOT / CAA / NEB |
| Automatic regulator suggestion | Done | `RegulatorDirectory` - route-resolved, per-country EU NEBs, admin confirms |
| Structured regulation data fed to AI | Done | `LEGAL BASIS` block: regulation, article, what it covers, amounts, deadline |
| Citation guard (no invented articles) | Done | Draft citing unauthorised provisions is rejected, redrafted, then template |
| Immutable draft versions + approvals | Done | `claim_drafts`, admin approves the final version |
| Business-letter formatting | Done | Salutation, paragraphs, amount list, sign-off enforced in the prompt |

## 5. Workflow engine

| Item | Status | Notes |
|---|---|---|
| 12-stage lifecycle | Done | Draft -> ... -> Closed, DB-configured |
| Configurable lifecycle module | Done | Admin CRUD, drag-drop reorder, transitions, preview; system stages locked |
| Multiple workflows per airline | Done | `ClaimWorkflow`, default + per-airline attachment |
| Automatic status transitions | Done | Zero-delay chaining, customer events, signature completion |
| Automatic workflow timers | Done | 30-day airline deadline; `claims:evaluate-workflow-timers` daily 08:00 |
| Manual admin status management | Done | Server-side enforced - forged jumps rejected, not just hidden |
| Complete audit trail | Done | Append-only (`ClaimAuditLog` throws on update/delete) |
| Per-stage role permissions | Partial | Mechanism works and is tested; no stage configures roles yet |

## 6. Airline correspondence

| Item | Status | Notes |
|---|---|---|
| Airline directory + per-purpose contacts | Done | Claims/legal/escalation/customer relations + custom rows |
| Outbound claim email | Done | From the public claims address, per-claim reply-to token |
| Inbound airline replies -> claim | Done | Reply-token match, subject-reference fallback |
| Correspondence tab | Done | Quoted history collapsed, attachments previewable |
| Admin alert on airline reply | Done | `claim-airline-reply-alert` |
| Configurable alert recipients | Done | Unlimited rows, per-recipient alert types; falls back to admin accounts |
| Unreviewed-reply badge on the workflow card | Not started | Offered; admins currently rely on the alert email |

## 7. Admin panel

| Item | Status | Notes |
|---|---|---|
| Flight Claims group (trips, claims, lifecycle, airlines) | Done | |
| Claim detail page | Done | Composer, drafts, workflow actions, timeline, audit, correspondence |
| Styled confirm modal (no native dialogs) | Done | `x-admin.confirm` |
| Per-button loading spinners | Done | Parameterised `wire:target` |

## 8. Marketing site

| Item | Status | Notes |
|---|---|---|
| Live disruption board | Done | Real FlightAware data, hourly warmed cache, sample fallback |
| Public flight check (no signup) | Done | Modal beside the LIVE badge; provisional verdict + signup CTA, cached & throttled |

## 9. Needs human verification

- **The NEB table's factual accuracy** (`RegulatorDirectory::NEBS`, 30
  countries). Tests prove the lookup, the route rules and the completeness of
  the table - they cannot prove that "Hungary -> BFKH" is the right authority
  today. Enforcement bodies and their portals change. Before the first EU
  complaint is filed, someone with legal standing should review the list.
  Same applies to `RegulationCitation` article mappings.

## 9a. Not yet started

- **Payouts to customers** - success fee is configured and displayed, but there
  is no payment-out flow (how the client pays customers after an airline pays).
- **Customer-facing correspondence summary** - customers deliberately never see
  airline emails; no decision yet on whether they should see a summary.
- **Success-fee freeze at confirmation time** - the percentage is read live from
  settings, so changing it would re-price historic claims.
- **Duplicate route name `password.update`** - blocks `route:cache`.

---

## Known constraints (do not break these)

- **Never run `php artisan config:cache`** on this box - cached config makes the
  test suite (RefreshDatabase) hit the production DB instead of `disputer_testing`.
- Amounts and eligibility are computed ONLY in the Eligibility Engine /
  `CompensationCalculator`.
- The AI never decides the law and never sends anything - it drafts, an admin
  reviews and sends.
- One master claim per booking, never one claim per passenger.
- After backend changes: `supervisorctl restart unjamm-queue:`. After frontend:
  `npm run build`. After running artisan as root: `chown -R www-data:www-data storage`.

---

## Maintenance rules (for Claude Code)

1. Update this file at the end of **every** session that changes behaviour -
   without being asked.
2. Move rows between Done / Partial / Not started as reality changes; never
   mark something Done that has not been verified running.
3. Add newly discovered gaps to the right table or to "Not yet started" - an
   honest Partial is more useful than an optimistic Done.
4. Keep "Last updated" current.
5. Detailed flow documentation lives in `docs/UNJAMM-FLOWS.md`; this file is
   the status view. Update both.

---

## Session log

Newest first. One line per session: what changed, what it unblocked.

- **2026-07-26** - Gate bypass fixed: a free user created a claim by ticket
  upload while flight claims were Plus-only - only the manual funnel was
  gated. The gate now lives in the ensureForItinerary chokepoint (covers
  upload, itinerary view, inbound email) plus the upload and trip->claim
  endpoints. Regression test walks all three paths. NOTE: inbound EMAIL from
  a gated free user now stores the itinerary but silently creates no claim -
  needs a product decision on an upgrade-reply email.
- **2026-07-22** - Subscription hardening: plan pricing switched to USD, then to the final product definition - CAD C$9.99/month (real prices created in Stripe test mode each time), webhook unified onto the
  dashboard-registered `/stripe/webhook` URL - one verified pipeline
  dispatching to both the legacy /admin/plans product and Unjamm Plus
  (handler-per-product, metadata-discriminated so neither claims the
  other's events; handler failure returns 500 for Stripe retry, idempotent
  syncs). 14 pipeline tests covering replays, out-of-order delivery,
  cross-product isolation and signatures.
- **2026-07-22** - Unjamm Plus subscription module: admin-configurable plans
  (Stripe IDs included), master switch, per-feature paywall, Stripe
  Checkout/Portal/webhook sync, subscriber management + revenue stats under
  Flight Claims -> Subscriptions, customer /plus upgrade page. 15 tests.

- **2026-07-21** - Public flight check on the landing page: visitors search a
  flight without an account and see status + provisional eligibility + an
  estimate before being asked to sign up. Verified live (BA112 JFK-LHR,
  5h35m late -> GBP 520).
- **2026-07-21** - Expense receipts end to end: customer Expenses tab
  (upload with amount/currency/date/description, remove while pending), admin
  verification panel (approve / reject with reason, internal notes, receipt
  preview, Claimed vs Reimbursed totals), approved receipts attachable to
  airline emails and demanded in the letter as a separate head of claim.
  Success fee explicitly excluded from expense reimbursement.
- **2026-07-21** - Regulator suggestion: `RegulatorDirectory` resolves the
  competent enforcement body from regulation + route (APPR -> CTA, UK261 ->
  CAA, US DOT -> DOT, EU261 -> the right member-state NEB out of 30). Shown
  on the claim detail with its rationale and portal link, and the complaint
  draft is addressed to the named body. Unresolvable routes are flagged for
  the admin instead of guessed.

- **2026-07-21** - Alert routing: unlimited alert recipients configured in
  Settings -> Flight Claims, each subscribed to the alert types they want
  (escalation decisions / airline replies), replacing delivery to admin
  accounts - the box had a single placeholder admin (admin@admin.com), so
  alerts were effectively going nowhere. Admin alert emails restyled to match
  the customer email template (slate CTA button).
- **2026-07-21** - Canonical legal citation table (`RegulationCitation`): the
  Eligibility Engine now resolves every citation from a vetted table instead of
  free-text AI output, drafting receives a structured `LEGAL BASIS` block, and a
  citation guard rejects any draft that invents a provision (retry once, then
  deterministic template). Backfilled 13 existing claims - including
  CLM-N84APPBO "ss. 20-22" -> "Section 19" and a denied-boarding claim wrongly
  citing Article 7(1) -> Article 4.
- **2026-07-21** - Audit trail completed: eligibility approve/reject, draft
  approvals and document add/remove now audited; `ClaimAuditLog` made truly
  append-only (throws on update/delete). Workflow preview redesigned; native
  `confirm()` dialogs replaced with a styled modal.
- **2026-07-18** - Airline correspondence: outbound "Send to airline" with
  per-claim reply-to token, inbound reply routing (token + subject reference),
  Correspondence tab, admin reply alerts. AI letter formatting rules.
- **2026-07-17** - Workflow state machine, configurable lifecycle module,
  multiple workflows per airline, airline directory, workflow timers.
- **2026-07-16** - AI Drafting module (follow-ups, regulator complaints, draft
  versioning), admin claim detail page.
- **2026-07-15** - Claim confirmation screen, e-signatures, master claims.
