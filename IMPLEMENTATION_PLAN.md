# Laundry Management System — Application Implementation Plan
### Turning `MASTER_SPECIFICATION.md` into a running Laravel application

**Status:** Planning document only. Nothing in this plan has been implemented.

**Relationship to `MASTER_SPECIFICATION.md`:** that document is the database layer's
spec — schema, triggers, security, and a 7-phase path from "reviewed SQL" to
"SQL running in production" (its own §9). It does not cover building the Laravel
application that sits on top of that database. This plan covers exactly that gap:
turning the current repo (an unmodified `laravel/laravel` skeleton — one commit,
default welcome page, MySQL configured, no auth/RBAC/broadcasting/frontend
packages installed) into the working system described in `MASTER_SPECIFICATION.md`
§2–§3.

Where work overlaps with `MASTER_SPECIFICATION.md` §5–§9 (security hardening,
backup/monitoring, deployment, staging/production cutover), this plan **references**
those phases rather than repeating them — do both plans in parallel, this one
feeding staging, theirs governing the database server itself.

---

## 0. Key Technical Decisions (recommended defaults — flag before Phase 1 if you disagree)

| Decision | Recommendation | Why |
|---|---|---|
| **Frontend architecture** | **Livewire 3 + Volt + Alpine.js**, not Inertia+Vue/React | Everything stays in PHP/Blade — one language, one test suite, no separate SPA build/state-management layer. Livewire's `wire:poll`/Echo integration pairs cleanly with Reverb for the real-time requirements (§3.4/3.5/3.10). The one place this is a real trade-off is the **POS-style laundry terminal** (§3.11, two-pane cart UI) — Livewire can do it, but a bit more client-side plumbing (Alpine) is needed than Inertia+Vue would take. Re-evaluate only if the terminal's interactivity proves painful in Phase 6. |
| **Auth scaffolding** | **Laravel Fortify** (headless), not Breeze/Jetstream | The `users` table already has bespoke columns (`failed_login_attempts`, `locked_until`, soft-delete-aware email uniqueness) that don't match Breeze's default migration/controllers. Fortify gives the auth *actions* (login, lockout, password reset) without opinionated scaffolding to rip out. |
| **RBAC implementation** | **Custom**, not `spatie/laravel-permission` | The schema already fully specifies multi-role users, `is_primary`, role deactivation via `status` (enforced by `trg_user_roles_require_active_role`), and a `permission_group` catalogue. Bolting a generic package on top would mean maintaining two parallel role models. A thin custom `AuthorizesViaRole` layer over the existing tables is less code, not more. |
| **Testing framework** | **PHPUnit** (already scaffolded: `phpunit.xml`, `phpunit/phpunit` in `composer.json`) | No reason to introduce Pest on top of an already-configured PHPUnit setup. |
| **Broadcasting** | **Laravel Reverb** | Explicitly named throughout the spec (§3.2–3.5, §3.10). Currently `BROADCAST_CONNECTION=log` — needs installing and wiring in Phase 3. |
| **Queue driver** | Keep **`database`** (already set) through staging; revisit Redis only if `monitoring.sql`'s `vw_health_queue` shows contention | `jobs`/`failed_jobs`/`job_batches` tables are already in the schema for exactly this. |
| **PDF/receipt generation** | `barryvdh/laravel-dompdf` (or `spatie/laravel-pdf` if a headless-Chrome render is preferred for complex receipt layouts) | Needed for §3.9 receipt printing and §9 report exports. Decide in Phase 8. |

If any of these should go the other way (Inertia+Vue in particular, since it's the highest-blast-radius choice), say so now — everything below assumes Livewire.

---

## 1. Build Sequence Overview

Modules are sequenced by **foreign-key dependency order** — the same order `schema.sql` itself uses — so nothing is built against a table that doesn't exist yet, and each phase's tests can use real (not stubbed) parent records.

```
Phase 1  Foundations & tooling
Phase 2  Database layer port (schema → migrations, triggers, seeders)
Phase 3  Platform shell: auth, RBAC, layout, theming, Reverb wiring
Phase 4  Catalogue & configuration (clothing types, services, packages, machines, settings, departments, employees)
Phase 5  Customers
Phase 6  Laundry orders & POS terminal (the transactional core)
Phase 7  Subscriptions & collections
Phase 8  Payments, store credit, refunds, receipts
Phase 9  Damage management
Phase 10 Delivery
Phase 11 Expenses
Phase 12 Notifications (real-time + queued external channels)
Phase 13 Reporting & dashboards
Phase 14 Cross-cutting UI polish (tables, responsiveness, accessibility)
Phase 15 Test hardening (concurrency, trigger rejection paths, end-to-end flows)
Phase 16 → hands off to MASTER_SPECIFICATION.md §9 Phases 0–6 (env provisioning through production cutover)
```

Phases 4–13 each follow the same internal shape: **migrations/models for that
domain → policies/RBAC wiring → Livewire components → tests → manual smoke
test against seeded data** — so each phase below only calls out what's
*specific* to that domain, not the repeated mechanics.

---

## Phase 1 — Foundations & Tooling (0.5–1 day)

**Goal:** the repo can run `composer dev`, has the packages this plan depends on, and has its conventions fixed before any domain code is written.

| Task | Detail |
|---|---|
| Install core packages | `laravel/reverb`, `laravel/fortify`, a PDF package (§0), `laravel/pint` config review (already present) |
| Install frontend packages | `livewire/livewire`, `livewire/volt`; confirm Tailwind 4 (already present) is wired to Livewire components, not just `welcome.blade.php` |
| Decide and document folder conventions | `app/Livewire/<Module>/...` per domain; `app/Services/<Module>Service.php` for anything with a DB transaction boundary (payments, store credit, collection conversion) so trigger-adjacent logic isn't scattered across Livewire components |
| `.env` / `.env.example` alignment | Add `REVERB_*`, confirm `BROADCAST_CONNECTION=reverb`, confirm `DB_CONNECTION=mysql` stays consistent with `MASTER_SPECIFICATION.md` §7 (never sqlite past local scratch use) |
| CI baseline | GitHub Actions (or equivalent) running `phpunit`, `pint --test`, and a migration-fresh check on every push — cheap to add now, expensive to retrofit after 50 tables exist |
| Git conventions | Confirm branch/PR strategy with the user before Phase 2 generates the first large commit (50 tables' worth of migrations is a big diff) |

**Exit criteria:** `php artisan serve` + `npm run dev` run concurrently; CI passes on an empty diff; `.env.example` documents every new variable this plan will introduce.

---

## Phase 2 — Database Layer Port (2–3 days)

**Goal:** every table, trigger, and seed row in `MASTER_SPECIFICATION.md` §10 exists as Laravel-native artifacts, not as a hand-run SQL file — this is the single highest-risk phase, since it's translating 50 tables + 24 triggers from raw SQL into Laravel's migration system for the first time.

| Task | Detail |
|---|---|
| **Migrations** | One migration file per logical group (mirrors `schema.sql`'s own 21 numbered sections: branches, auth/RBAC, employees, customers, catalogue, machines, subscriptions, laundry orders, collections, discounts, damage, delivery, payments, receipts, expenses, notifications, settings, activity log, reporting, queue infra, schema version). Preserve every constraint name (`fk_`/`uq_`/`chk_`/`idx_` prefixes) exactly as written in `schema.sql` — the spec's `ROLLBACK_current.sql` and `MIGRATION_round1_to_current.sql` (if those files exist alongside this repo) depend on exact name matches. |
| **CHECK constraints** | Laravel's schema builder doesn't have first-class CHECK support pre-11; use `DB::statement()` raw SQL inside the migration for every `CONSTRAINT chk_*` clause, copied verbatim from `schema.sql`. |
| **Triggers** | Cannot be expressed via the schema builder at all — each of the 24 triggers in §10.2 becomes a raw `DB::unprepared()` block in a dedicated `create_business_rule_triggers` migration, run after all 50 tables exist. Preserve the trigger execution-order notes from the spec's own header comment. |
| **Composite PKs** | `role_permissions`, `package_services` — Laravel supports this but verify generated DDL matches the spec's `PRIMARY KEY (a, b)` exactly. |
| **Seeders** | Convert `seed.sql` §1–7 into `RoleSeeder`, `PermissionSeeder`, `DepartmentSeeder`, `DamageTypeSeeder`, `ExpenseCategorySeeder`, `SettingSeeder`, run from `DatabaseSeeder`. **Do not** port §8 (bootstrap admin with the public placeholder hash) into a seeder that could run against production — keep that as a separate `php artisan make:command SeedProductionAdmin` following `seed_production_admin.md`'s random-password procedure, gated so it can't be accidentally included in `php artisan migrate --seed`. |
| **Models** | One Eloquent model per table, `$fillable`/casts matching column types (`DECIMAL` columns cast to a decimal-safe type, not float — this matters given the spec's explicit "never floating point" money rule), relationships matching §4.3's key-relationship diagram. Models for append-only tables (`activity_log`, `*_history`, `store_credit_transactions`) should **not** define `update()`/`delete()`-friendly scopes — make the ORM-level intent match the DB-level privilege revocation in `permissions.sql`. |
| **Verification** | `php artisan migrate:fresh --seed` on a real local MySQL instance (matching the version floor in `schema.sql`'s header warning — **8.0.16+**, not just "8.0"); manually trigger each of the 24 triggers' rejection paths once, the same exercise `MASTER_SPECIFICATION.md` §9 Phase 1 describes for the raw-SQL deployment — doing it here too, against the Eloquent-facing surface, is what actually proves the app can't route around the DB-level protections. |

**Exit criteria:** `migrate:fresh --seed` succeeds repeatedly from empty; every table/trigger/constraint name matches §10 verbatim (spot-check with `SHOW CREATE TABLE` against the appendix); each Eloquent model has at least one factory + one relationship test.

---

## Phase 3 — Platform Shell: Auth, RBAC, Layout, Reverb (2–4 days)

**Goal:** a logged-in user of any role sees the role-adaptive shell described in §3.1–3.6, with real-time working end-to-end on at least one trivial event, before any business module is built on top of it.

| Task | Detail |
|---|---|
| Fortify wiring | Login, logout, password reset, account lockout using `users.failed_login_attempts`/`locked_until` and the `security.max_failed_login_attempts`/`security.account_lockout_minutes` settings seeded in Phase 2 |
| Multi-role authorization layer | A `User::hasPermission(string $slug): bool` that unions permissions across every row in `user_roles` (not just the primary role) per §4.3/§2.1; Laravel `Gate`/Policy classes call into this, not into a hardcoded role check, everywhere in the app |
| Sidebar / top-nav shell | Navy-blue sidebar, entity-icon nav, permission-based module hiding (§3.2) — build as a Livewire component driven by the current user's permission set, not by role name string-matching |
| Theming | Light/dark toggle persisted per-user (a `users` preference column or a `settings`-style per-user table — decide and document which), applied via Tailwind's `dark:` variant across the shell before any module adds its own screens |
| Reverb end-to-end smoke test | One trivial broadcast (e.g. "branding updated" from §3.2) proven to update the sidebar with no page refresh — this de-risks Reverb configuration before Phase 12 depends on it being reliable |
| Notification center shell | Empty-state UI wired to the `notifications` table's schema, populated for real in Phase 12 |

**Exit criteria:** each of the 5 seeded roles can log in and see a distinctly-scoped sidebar matching §3.1's table; a manual settings change is reflected in the UI within the same session via Reverb with no refresh; failed-login lockout is manually verified.

---

## Phase 4 — Catalogue & Configuration (2–3 days)

Clothing types, services, packages (+ `package_services`), machines, departments, employees, settings management UI.

| Task | Detail |
|---|---|
| CRUD via slide-in drawers | Per §3.8 — Packages, Employees, Expenses (categories only here), Settings all use the same drawer pattern; build the drawer as a reusable Livewire/Alpine component now since 6+ later modules reuse it |
| Package business rule | `maximum_clothes` and `priority` (express) surfaced in the package form; the DB-level enforcement (`trg_loi_check_package_limit_*`) is already in place from Phase 2 — the UI only needs to *display* the limit clearly and handle the SQLSTATE 45000 rejection gracefully if the client-side check is ever bypassed |
| Settings screen | Grouped by `setting_group`, real-time propagation via Reverb (§3.2/§16) — reuse the Phase 3 broadcast pattern |
| Employees vs. Users | UI must make the §2.1 distinction visible — an employee record with no linked `users.id` is valid and common (laundry/delivery staff without login access) |

**Exit criteria:** an Administrator can create a package with services attached, set its clothing limit, and see it appear correctly in the (not-yet-built) POS terminal's package list once Phase 6 lands.

---

## Phase 5 — Customers (1–2 days)

Customer CRUD, addresses, notes (instruction vs. internal), timeline, profile tabs shell (§3.9 — tabs for Laundry/Packages/Collections/etc. render empty until their owning module lands in later phases).

| Task | Detail |
|---|---|
| Active-only phone uniqueness | Client-side validation should mirror `trg_customers_phone_unique_active_ins/upd`'s intent (friendly inline error) but the trigger remains the actual enforcement — don't duplicate the uniqueness check as an app-level `unique:customers,phone` rule, since that would incorrectly count soft-deleted rows unless scoped identically to the trigger |
| Search | FULLTEXT (`ftx_customers_name`) + phone prefix search per §3.7 |
| Customer type | Walk-in vs subscription toggle; subscription-specific tabs stay disabled/hidden until Phase 7 |

**Exit criteria:** customer list search/filter/sort/pagination works per §3.7; profile page renders all 9 tabs (most still empty) without error.

---

## Phase 6 — Laundry Orders & POS Terminal (4–6 days, the largest single phase)

**Goal:** the walk-in order flow (§3.11) end-to-end — package → clothes → cart → customer → payment → receipt — as one atomic transaction (T1 per the spec's design doc), plus the processing queue and stage timeline.

| Task | Detail |
|---|---|
| POS terminal UI | Two-pane layout (§3.11); **package-first enforcement** at the UI layer, backed by the FK structure (`laundry_order_packages` → `laundry_order_items`) already making the reverse structurally impossible |
| Order creation transaction | Wrap customer-resolve → order → packages → items → (payment, if paid at creation) → receipt in a single DB transaction in a dedicated `LaundryOrderService`, not spread across Livewire lifecycle hooks — mirrors the spec's own T1 transaction-boundary language |
| Stage sequence UI | Laundry timeline component (§3.10) driven by `laundry_order_stage_history`; advancing a stage calls a service method that lets `trg_laundry_orders_stage_sequence` be the actual authority — the UI should only *offer* the legal next stage, never attempt a skip client-side |
| Queue view | Sorted per `idx_laundry_orders_queue_sort` (status, priority, created_at) — confirm the Livewire query actually uses that composite index (`EXPLAIN`), not just returns correct results |
| Discounts | `discount_templates` + `order_discounts`, cashier-vs-manager approval threshold (BR-041, app-layer only per the spec's own trigger-design notes) |
| Order/employee/machine assignment | Simple assignment UI; capacity throughput limits (BR-029/030/031) enforced app-side only, per the spec's explicit "don't push this into a trigger" reasoning — implement as a service-layer check, not a hard block, matching the "please wait" UX the spec calls for |

**Exit criteria:** a full walk-in order can be created, paid, and receipted from the terminal; every stage transition works and skip-attempts are rejected with a user-legible error (not a raw SQLSTATE dump); the queue view's `EXPLAIN` shows the composite index in use.

---

## Phase 7 — Subscriptions & Collections (2–3 days)

| Task | Detail |
|---|---|
| Subscription CRUD | Frequency types incl. `custom_frequency_config` (JSON) editor; pause/resume |
| Collection generation | Scheduled-job (queue-backed) that creates `collections` rows per subscription's frequency — this is new application logic, not something `schema.sql`/`triggers.sql` does for you |
| Collection → order conversion | Service method taking the `SELECT ... FOR UPDATE` lock the spec calls for (§6.3, §9 Phase 4) — this is the other of the two explicit deadlock-risk transaction boundaries, build the retry-on-1213 wrapper here (see Phase "Deadlock retry" note below) rather than deferring it |
| `trg_collections_sync_order_subscription` integration | Conversion service must handle the trigger's rejection path (mismatched subscription_id) as a real, tested error case, not just the happy path |

**Exit criteria:** a subscription generates its scheduled collections correctly for each frequency type; a collection converts to an order exactly once (`uq_collections_order` respected) even under a simulated concurrent double-click.

---

## Phase 8 — Payments, Store Credit, Refunds, Receipts (3–4 days)

| Task | Detail |
|---|---|
| Payment recording | Multiple methods incl. store-credit blending, partial payments against `chk_lo_credit_ceiling`/`trg_payments_check_not_exceed_total_*` |
| Store credit service | The **other** `SELECT ... FOR UPDATE` boundary (§6.3) — build the deadlock-retry wrapper here first (this is the piece the spec's §8 "What remains honestly open" flags as never having been implemented in application code; this plan is where that debt actually gets paid) |
| Refunds | Against `trg_refunds_check_total`; UI must surface "exceeds remaining refundable amount" clearly |
| Receipts | Auto-generated on completed payment, immutable snapshot columns populated at creation (never re-derived later), PDF render (package chosen in §0), cancel-only lifecycle |
| Immutability UX | `trg_payments_prevent_paid_tamper` rejection must surface as "this payment is settled — issue a refund instead," not a raw DB error — this is the first of several "correction procedure" UX moments the spec's §9 Phase 3 flags for support-staff training; write the copy now while the context is fresh |

**Exit criteria:** the deadlock-retry wrapper is demonstrated under a simulated concurrent-write test (this closes the specific gap `MASTER_SPECIFICATION.md` §8 calls out as unclosed); a receipt's snapshot values are provably frozen against a later settings change.

---

## Phase 9 — Damage Management (1–2 days)

Report creation (any non-cancelled order status), evidence upload, resolution workflow (repair/refund/rewash/store_credit/replacement/other with the cash+store-credit split), approval gate.

**Exit criteria:** a damage report can be resolved with a blended cash/store-credit compensation, driving a real `store_credit_transactions` credit row through the Phase 8 service (not a separate ad-hoc write path).

---

## Phase 10 — Delivery (1–2 days)

Pickup/delivery type, staff assignment, status lifecycle with failure/reschedule, `delivery_status_history`.

**Exit criteria:** `trg_deliveries_check_order_type`'s rejection path (delivery row created against a `pickup`-type order) is handled gracefully in the UI.

---

## Phase 11 — Expenses (1–2 days)

Categories (built in Phase 4), recurring schedules, approval-workflow-gated expense creation.

**Exit criteria:** a recurring schedule's queue job correctly creates a linked `expenses` row on its `next_run_date`.

---

## Phase 12 — Notifications: Real-Time + Queued External (2–3 days)

| Task | Detail |
|---|---|
| Event → notification wiring | Every module built in Phases 6–11 fires the relevant notification type (§3.5's categorized list) — this is retrofitted onto already-built modules, so budget time to touch each one rather than treating this as fully greenfield |
| In-app real-time | Reverb broadcast → unread badge, reusing the Phase 3 smoke-test pattern at scale |
| Queued external channels | WhatsApp/SMS/email per `notification_preferences`, using the `channel`/`delivery_status`/`failed_reason` columns already in the schema |
| Purge job | Wire up `evt_purge_old_notifications`'s *application-side* equivalent if the MySQL `EVENT` scheduler route from `maintenance.sql` isn't being used in this environment — decide which owns this (DB event vs. Laravel scheduled command) and don't implement both |

**Exit criteria:** every event type listed in §3.5 fires at least once in a manual end-to-end test; the notification center's unread count matches reality after a page refresh (i.e., it's not just Echo-session-local state).

---

## Phase 13 — Reporting & Dashboards (2–3 days)

| Task | Detail |
|---|---|
| `daily_statistics` aggregation job | Scheduled (not live-computed), per the SRS's explicit architectural directive quoted in §2.1 — this is a real gap to close, since nothing in Phase 2's migrations creates the job itself, only the table it writes to |
| Role-based dashboards | Per §3.4's card set, scoped per role per §3.1 |
| Report exports | PDF/Excel/CSV via `report_exports` tracking, reusing the Phase 8 PDF package where applicable |
| Index-usage verification | Re-run the `EXPLAIN` check from `MASTER_SPECIFICATION.md` §9 Phase 4 against the real report queries this phase writes, not just the queue view from Phase 6 |

**Exit criteria:** a full day's seeded activity produces correct `daily_statistics` rows via the scheduled job (not a live query); each dashboard card matches a manually-computed control value.

---

## Phase 14 — Cross-Cutting UI Polish (2–3 days, can overlap with 12–13)

Responsive collapse behavior (§3.13), table search/filter/sort/pagination/export consistency across every index page built in Phases 4–11 (§3.7/3.12), accessibility pass (§3.15), consistent feedback toasts (§3.14).

**Exit criteria:** every index page listed in §3.7 passes the same manual checklist; a keyboard-only pass through the POS terminal and one CRUD drawer succeeds.

---

## Phase 15 — Test Hardening (2–4 days)

This is where `MASTER_SPECIFICATION.md` §9 Phase 4's "Load & User Acceptance Testing" concurrency scenarios get their actual test code, at the application layer (not just the database-level manual exercises from Phase 2 here).

| Task | Detail |
|---|---|
| Concurrent store-credit writes | Automated test hitting the Phase 8 service from parallel processes/requests, confirming the `FOR UPDATE` lock + retry wrapper actually serializes correctly |
| Concurrent collection conversion | Same pattern against Phase 7's service |
| Full walk-in flow, forced mid-transaction failure | Confirms the T1 transaction boundary from Phase 6 actually rolls back atomically |
| Every trigger rejection path | Feature-tested through the application layer (not just manually verified in Phase 2) — 24 triggers, each with at least one "this should fail" test |
| RBAC boundary tests | A Cashier session attempting an Administrator-only action is blocked at the policy layer, for every permission in the seeded catalogue |

**Exit criteria:** matches `MASTER_SPECIFICATION.md` §9 Phase 4's exit criteria, but now backed by an automated suite that can be re-run in CI on every future change, not a one-time manual pass.

---

## Phase 16 — Handoff to Database Ops Track

From here, follow `MASTER_SPECIFICATION.md` §9 **Phase 0 through Phase 6** as written (environment provisioning → staging deploy → security hardening → operational readiness → load/UAT → production cutover → stabilization). The only adjustment: wherever that plan says "run `deploy.sh fresh`," it is now deploying the Phase 2 migrations/seeders built here, not the standalone `schema.sql`/`seed.sql` files — reconcile the two before Phase 1 of that track begins (either keep `deploy.sh` orchestrating `php artisan migrate --force` + the seeders, or retire it in favor of Laravel's own deployment tooling; decide once, don't let both paths exist silently).

---

## 2. Cross-Phase Risks Worth Naming Now

| Risk | Mitigation |
|---|---|
| Phase 2's trigger port is the single highest-risk step in this entire plan — 24 triggers, hand-translated into `DB::unprepared()`, have never run anywhere (per `MASTER_SPECIFICATION.md` §8's own admission) | Don't let Phase 2 "look done" from a clean `migrate:fresh` alone — the rejection-path exercise in Phase 2's own task list is not optional |
| Livewire + Reverb interaction under real concurrent users is unproven at this repo's current state (zero packages installed) | The Phase 3 smoke test exists specifically to surface integration issues before 8 later phases build on top of it |
| The deadlock-retry wrapper (Phase 7 & 8) is the one piece `MASTER_SPECIFICATION.md` §8 explicitly flags as never having existed in any form | Treat it as a first-class deliverable of Phase 8, not an afterthought — it's shared code, build it once and reuse in Phase 7 |
| Scope creep risk: this plan's 15 phases (excl. handoff) total roughly 6–8 weeks at the stated per-phase estimates for a small team — re-baseline the estimate once real velocity from Phases 1–3 is known | Treat Phase 1–3 as the calibration point; revisit the whole plan's timeline after Phase 3's exit criteria are met, don't hold the later estimates as fixed |

---

*This plan is a proposal, not a commitment — sequencing, phase boundaries, and the Phase 0 technical decisions are all open for the user to redirect before Phase 1 starts.*
