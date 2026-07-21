# Unjamm - working notes for Claude

- **Living documentation - update BOTH without being asked**, in the same
  session as the change:
  - `docs/PROJECT-STATUS.md` - the delivery tracker (Done / Partial / Not
    started / Blocked per feature, known gaps, session log). Never mark
    something Done that has not been verified running. Add an entry to the
    session log and refresh "Last updated".
  - `docs/UNJAMM-FLOWS.md` - how every flight-dispute flow actually works
    (trips, claims, eligibility, compensation, e-signatures, correspondence,
    inbound email) plus its changelog and "Last updated" date.
- The AI never decides the law and never sends anything: legal citations come
  from `RegulationCitation` (canonical table), the Eligibility Engine decides,
  the model only drafts, an admin reviews and sends.
- Coding standards: PHP imports at top (never inline `\FQCN`), minimal
  comments, hyphen "-" never em-dash, CTA/tab styling bg-slate-900,
  mobile-responsive.
- Amounts and eligibility are computed ONLY in the Eligibility Engine /
  CompensationCalculator - never in the UI or controllers.
- One master claim per booking - never one claim per passenger.
- After backend changes: `supervisorctl restart unjamm-queue:`. After frontend
  changes: `npm run build`. Tests: `php artisan test` (dedicated DB
  disputer_testing). After running artisan as root:
  `chown -R www-data:www-data storage`.
- Ops: APP_ENV=production, APP_DEBUG=false, CACHE_STORE=file. After .env
  changes: `systemctl reload php8.2-fpm` + restart the queue. NEVER run
  `php artisan config:cache` on this box - cached config would make the test
  suite (RefreshDatabase) hit the production DB instead of disputer_testing.
  `route:cache` is blocked by a duplicate route name (password.update).
