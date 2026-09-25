# NetWatch progress tracker

Milestones follow the 8-week plan. Update this file as part of each PR/commit that moves a milestone.

Legend: ✅ done · 🟡 in progress · ⬜ not started

| Week | Milestone | Status | Done when |
|---|---|---|---|
| 1 | Foundation | ✅ | Login works, CI green |
| 2 | IPAM & inventory | ✅ | Import 500-row CSV, bad rows reported clearly |
| 3 | Check engine | ✅ | 50 monitors checked every 30s without backlog |
| 4 | Status & dashboard | ⬜ | Killing a test host flips it to Down live |
| 5 | Circuits & maintenance | ⬜ | SLA % excludes maintenance windows correctly |
| 6 | Incidents & alerts | ⬜ | Outage → Telegram alert → ack from phone → RFO PDF |
| 7 | Simulator & hardening | ⬜ | Public read-only demo live |
| 8 | Launch package | ⬜ | Linked from aliimranrohaizi.xyz |

## Week 1 — Foundation

- [x] Repo (MIT), `DESIGN.md`, `docs/ERD.md`, `docs/incident-states.md`
- [x] Laravel 13 API scaffold (plan says 12; installer default is 13)
- [x] React + Vite + TS + Tailwind v4 frontend (oxlint instead of ESLint — Vite's current default)
- [x] Sanctum SPA auth, roles (admin/engineer/viewer), audit log, `UserPolicy`
- [x] CI green (Pest on SQLite + oxlint/Vitest)
- [x] WSL2 + Ubuntu + Docker Desktop working
- [x] Docker Compose stack up: `laravel.test`, `mysql`, `redis`, `reverb`, `horizon`
- [x] `migrate:fresh --seed` on MySQL; `artisan test` 6/6; curl csrf → login → user = 204/200/200
- [x] Browser check of login flow at http://localhost:5173 (confirmed by user 2026-09-24)

### Notes

- Windows: Sail's shell wrapper sets `WWWUSER`/`WWWGROUP`; running `docker compose` directly needs them in `.env` (added).
- Host port map avoids Laragon: app 8000, MySQL 33060, Redis 63790, Reverb 8080.
- The container's own Vite port is remapped to 5180 (`VITE_PORT`) so the host-run frontend can use 5173, which CORS/Sanctum expect.


### Week 2 decisions (2026-09-24)

- IPv4 only; IPv6 deferred.
- CSV import is synchronous (no queue); good rows are imported and bad rows are reported with row number and reason.
- Frontend: full CRUD screens for sites, VLANs, subnets, IPs and devices, plus the import page and utilization bars.

## Week 2 — IPAM & inventory

- [x] Migrations, models, factories: sites, vlans, subnets, ip_addresses, devices (all audited)
- [x] Generic `InventoryController` + 5 thin controllers, `InventoryPolicy` (viewer read-only; admin/engineer write)
- [x] Validation: VLAN id unique per site, CIDR normalisation + duplicate check, VLAN must belong to subnet's site, gateway inside subnet, IP inside subnet and not network/broadcast
- [x] Subnet utilization % (non-free addresses / usable hosts; /31 and /32 handled)
- [x] Delete of a referenced record returns 409
- [x] Feature tests: 13 new, suite 19/19 green
- [x] CSV import, one CSV per entity (`POST /api/{entity}/import`): valid rows imported, bad rows reported by file row number; 500-row test green (suite 22/22)
- [x] Demo seeder (`InventorySeeder`): fictional carrier, 6 POPs, 36 devices, ~470 IPs, subnet utilization from ~12% to ~94% (suite 23/23)
- [x] Frontend: sidebar layout, one config-driven CRUD page for all 5 entities (create/edit/delete, paging, viewer read-only), CSV import panel with row errors, utilization bars (Vitest 3/3, tsc + build clean). Browser check confirmed by user 2026-09-24.
- [x] Week 2 done: 500-row CSV imports and bad rows are reported clearly

### CSV import format

Headers are required (any order, extra columns ignored). Rows reference parents by natural key. Import in this order: sites → vlans → subnets → ip-addresses → devices.

| Entity | Columns |
|---|---|
| sites | `name, code, city, country, lat, lng` |
| vlans | `site_code, vid, name` |
| subnets | `site_code, vlan_vid, cidr, description, gateway` |
| ip-addresses | `site_code, subnet_cidr, address, status, device_name, dns_name` |
| devices | `site_code, name, type, vendor, model, serial, mgmt_ip` |

Response: `{imported, failed, errors: [{row, errors: {column: [messages]}}]}`. Max 2 MB / 5000 rows.

## Next up

Week 4 — Status & dashboard: flap-protected evaluator, live status board (Reverb), latency charts, rollup job.

### Week 3 decisions (2026-09-24)

- Ping shells out to the system `ping` (iputils-ping added to the app image).
- Scheduler: Laravel scheduler + a due-monitor dispatcher that queues one Horizon job per monitor whose `next_check_at` has passed; sub-minute cadence via `everyThirtySeconds()`.
- Store raw `check_results` now, with a prune command (14 days); rollups and charts are Week 4.
- UI: monitor CRUD with last-check time, success/fail and latency, plus a "Run now" button. Live status board is Week 4.

## Week 3 — Check engine

- [x] `monitors` + `check_results` tables; `Monitor` (audited, polymorphic `monitorable`, devices only until Week 5) and `CheckResult`
- [x] Probes behind one `Probe` interface: ping (system `ping`, argv only), TCP, HTTP (2xx/3xx = up, redirects not followed), DNS (A/AAAA)
- [x] `CheckRunner` stores the raw result and refreshes `last_*` columns on the monitor; a probe exception becomes a failed result
- [x] Scheduler: `schedule:work` (new `scheduler` compose service) runs `monitors:dispatch` every 30s; it claims due monitors with a compare-and-set on `next_check_at` and queues one `RunCheck` job each on the `checks` queue
- [x] Horizon: `checks` queue served first, 10 workers locally
- [x] `checks:prune` (14 days, daily 03:00)
- [x] API: `/api/monitors` CRUD (same policy as inventory), `POST /api/monitors/{id}/run` for "Run now"; targets validated as host/IP (or http(s) URL) so nothing option-like reaches `ping`
- [x] Frontend: Monitoring → Monitors page with Up/Down, latency, last-checked time, Run now; enabled checkbox
- [x] Sail runtime published to `docker/8.5` so the image includes `iputils-ping`
- [x] Tests: 44/44 Pest (probes, validation, dispatcher cadence, 50-monitor tick, prune), 4/4 Vitest
- [x] Done when: `db:seed --class=MonitorSeeder` (50 monitors, 30s) on the Sail stack → 400 checks in 4.5 min, gaps 26–35s (avg 30s), queue depth 0, 0 failed jobs

### Notes

- Dispatch times use one tick timestamp per run. Claiming each monitor at its own `now()` pushed later monitors past the next tick's 3s grace window and doubled their gap to ~60s.
- The demo seeder targets hosts inside the stack (`redis`, `mysql`, `reverb`, `laravel.test`). HTTP goes to Reverb because `laravel.test` is a single-threaded dev server and timed out under 8 concurrent probes.
- DNS timeouts are not enforced (PHP has no per-query timeout). `retries`/`thresholds` are left to the Week 4 evaluator.
- Scheduling `monitors:dispatch` as an in-process `Schedule::call` hung the scheduler; the plain `Schedule::command` is used.
