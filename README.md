# Avalan SmartPay — Public Architecture Demo

## What is Avalan?

Avalan is a personal finance / SmartPay platform (a Telegram Mini App
plus a PHP REST backend) that connects to a user's real bank cards
(via the Paylov open-banking API), tracks upcoming obligations (loans,
utilities, subscriptions), and runs a set of deterministic financial
engines on top of that data to answer three questions every day:

- **How much money do I actually have, right now, everywhere?** (Balance Engine)
- **What do I owe, and when?** (Liability Engine)
- **What is safe to spend today without missing a payment tomorrow?**
  (SmartPay / Payment Allocation Engine + Daily Limit Engine)

On top of that it layers a **Risk Engine** (Crisis Mode detection,
stress score, debt-to-income ratio) and a **Financial Score Engine**
(a 0–1000 rating and E–S rank blended from income stability, financial
health, payment discipline, and resilience).

This repository is **not** the production codebase. It is a curated,
public demo built specifically so a reviewer can read real,
runnable financial logic and see the frontend and backend actually
talk to each other — without shipping any production credential,
user data, or infrastructure detail.

## Project structure

```
git/
├── backend/     PHP 8.1+ REST API — real financial engines, fixture data
└── frontend/    PHP pages + vanilla JS — calls the backend, renders real numbers
```

## Frontend

`frontend/` is a small set of PHP pages (no framework, matching
production's own "plain PHP + vanilla JS" approach) covering the seven
surfaces asked for in a review: login, dashboard, balance, loans,
SmartPay (payment allocation), financial profile (score + risk), and
user profile.

- `assets/api-client.js` — the single fetch() wrapper every page uses.
  Structurally the same shape as production's real API client
  (normalized `ApiError`, automatic `Authorization: Bearer` header,
  redirect-to-login on 401) — see that file's own comments for exactly
  what was simplified and why.
- `assets/main.css` — reuses production's actual design tokens (colors,
  radii, shadows) so the demo looks like the real product.
- `assets/nav.php` — the same static bottom-navigation partial pattern
  production uses.

These pages are new, purpose-written files, not trimmed copies of
production's pages — the real production pages run 600–2,600 lines
each and are wired into Telegram Mini App bootstrapping, a dozen
feature flags, and endpoints this demo intentionally does not include.
Writing focused pages against the demo API was the honest way to keep
this reviewable while still exercising every engine below for real.

## Backend

`backend/` is a PHP 8.1+, framework-free REST API (`public/index.php`
is a single front controller, exactly like production's own
`public/index.php`). It ships four kinds of file:

1. **Ported unchanged** — `src/Utilities/Money.php`, `SafeMath.php`,
   `src/DTO/LiabilityItem.php`, `src/Services/LiabilityEngine.php`,
   `PaymentAllocationEngine.php`, `DailyLimitEngine.php`,
   `src/RiskEngine/RiskEngine.php`. These are byte-for-byte the same
   classes production runs — they have no database, network, or secret
   dependency, so there was no reason to alter them.
2. **Adapted** — `src/Services/BalanceEngine.php` and `ScoreEngine.php`
   keep production's real aggregation rules and scoring formulas
   (identical component weights: 35% income stability / 30% financial
   health / 20% payment discipline / 15% resilience) but read from the
   demo's fixture store instead of Paylov's live API / five MySQL
   repositories / a daily-recompute rate gate.
3. **Demo-only** — `src/Repositories/DemoDataStore.php` (an in-memory
   store seeded from `database/seed_demo.json`, implementing the exact
   same narrow repository interfaces the real engines depend on),
   `src/Services/DemoAuthService.php` (a single fixed demo token
   instead of real JWT/Telegram auth), and the five HTTP controllers
   under `src/Http/Controllers/`.
4. **Reference only** — `database/schema_demo.sql`, a trimmed
   illustration of the real table shapes. It is not required to run
   the demo (the demo API never touches MySQL).

## Core financial engines

| Engine | File | What it computes |
|---|---|---|
| Balance Engine | `src/Services/BalanceEngine.php` | Total balance = every card + cash, with no double-counting |
| Liability Engine | `src/Services/LiabilityEngine.php` | Every obligation due in the next 30 days, bucketed today/tomorrow/7d/30d, plus the total amount that must be reserved and can never be reported as spendable |
| Daily Limit Engine | `src/Services/DailyLimitEngine.php` | `available money ÷ days until next income` — never a flat 1/30th |
| Payment Allocation Engine (SmartPay) | `src/Services/PaymentAllocationEngine.php` | Turns the liability list + daily limit into a concrete "pay this today, reserve this, here's your order" action plan |
| Risk Engine | `src/RiskEngine/RiskEngine.php` | Debt ratio, reserve ratio, liquidity ratio, emergency days, debt-to-income ratio, a weighted stress score, a confidence/doubt score, and Crisis Mode detection — pure deterministic math, no AI |
| Score Engine | `src/Services/ScoreEngine.php` | The E–S rank and 0–1000 rating blended from income stability, financial health, payment discipline, and resilience |

Every one of these enforces the same invariant via `Money` and
`SafeMath`: never divide by zero, never return negative money, never
let a ratio produce NaN/Infinity.

## How frontend communicates with backend

This is real, not simulated:

1. A page loads and calls `AvalanDemoApi.smartpayCompute()` (or
   `.balance()`, `.loans()`, `.profile()`).
2. `assets/api-client.js` sends an actual `fetch()` to the PHP backend
   (`GET /api/demo/smartpay/compute`, etc.) with an `Authorization:
   Bearer` header obtained from the demo login endpoint.
3. `public/index.php` routes the request to a controller
   (`SmartPayController::compute()`, for example), which calls
   `BalanceEngine` → `LiabilityEngine` → `DailyLimitEngine` →
   `RiskEngine` → `PaymentAllocationEngine` in sequence — the same
   pipeline order production's real `/api/smartpay/compute` uses.
4. The controller returns JSON built entirely from those engines'
   real return values (`Money::toArray()`, the risk block, the payment
   plan) — nothing in the JSON is hand-faked by the controller.
5. The page renders that JSON directly (`fmtSom()`, badges, progress
   bars) — no mock numbers live in any `.php` or `.js` file.

You can verify this yourself: change a due date or an amount in
`backend/database/seed_demo.json`, reload `smartpay.php`, and watch
the payment plan, daily limit, and risk numbers change accordingly.

## How to run the demo

Requires PHP 8.1+ with the `json` extension (both are part of a
default PHP install — no Composer or database required).

```bash
# Terminal 1 — backend, port 8091
cd backend
php -S 127.0.0.1:8091 -t public

# Terminal 2 — frontend, port 8080
cd frontend
php -S 127.0.0.1:8080
```

Then open `http://127.0.0.1:8080/index.php` and click **"Demo hisobga
kirish"**. If you serve the frontend on a different port, add it to
`$allowedOrigins` in `backend/public/index.php` and set
`window.AVALAN_DEMO_API_BASE` before `assets/api-client.js` loads.

## Demo limitations

- **One fixture user, no persistence.** `DemoDataStore` reads
  `database/seed_demo.json` fresh on every request; nothing you do in
  the UI is saved. Production persists to MySQL across 90+ migrations.
- **No live bank connectivity.** Card balances are fixture values, not
  a live Paylov call. Production's `BalanceEngine` calls Paylov in
  parallel with a MySQL cache layer — see that class's own docblock in
  the production project.
- **No real authentication.** Login returns one fixed token; there is
  no password, no Telegram HMAC verification, no JWT signing.
- **Read-only.** There are no create/update/delete endpoints (no "add
  a loan," no "pay a bill") — this demo is about the calculation
  pipeline, not the full product surface.
- **A fraction of the real feature set.** Production also includes
  Goals, Debt Strategy simulation, Wallets, Subscription billing, Card
  Monitoring, Gamification, an internal credit system ("Avalan
  Balans"), AI-assisted command routing, and more — none of that is
  in scope for an architecture-and-core-math demo.

## Security note

No production secret of any kind is present in this repository:

- No database host, username, or password.
- No Paylov API key, no SMS gateway credential.
- No JWT signing secret, no field-level encryption key.
- No payment webhook secret, no admin API key.
- No real user data — every name, phone number, card, loan, and
  transaction in `database/seed_demo.json` is invented for this demo.

`backend/.env.example` documents the one non-secret placeholder value
this demo actually uses (a public "app key" header, mirroring the
*shape* of production's real app-key gate without being a real
secret). If you fork this demo for your own use, change that value.
