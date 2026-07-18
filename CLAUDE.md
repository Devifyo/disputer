# Unjamm - working notes for Claude

- **Living documentation**: `docs/UNJAMM-FLOWS.md` describes every
  flight-dispute flow (trips, claims, eligibility, compensation,
  e-signatures, inbound email) plus a changelog. **After any change to these
  flows, update that file in the same session** - keep the "Last updated"
  date and the changelog current.
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
