# NetWatch progress tracker

Milestones follow the 8-week plan. Update this file as part of each PR/commit that moves a milestone.

Legend: ✅ done · 🟡 in progress · ⬜ not started

| Week | Milestone | Status | Done when |
|---|---|---|---|
| 1 | Foundation | ✅ | Login works, CI green |
| 2 | IPAM & inventory | ✅ | Import 500-row CSV, bad rows reported clearly |
| 3 | Check engine | ✅ | 50 monitors checked every 30s without backlog |
| 4 | Status & dashboard | ✅ | Killing a test host flips it to Down live |
| 5 | Circuits & maintenance | ✅ | SLA % excludes maintenance windows correctly |
| 6 | Incidents & alerts | ✅ | Outage → Telegram alert → ack from phone → RFO PDF |
| 7 | Simulator & hardening | 🟡 | Public read-only demo live |
| 8 | Launch package | 🟡 | Linked from aliimranrohaizi.xyz |

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

Weeks 7–8 need things only the owner can do: deploy to a VPS with a domain, record the demo video, take screenshots, publish the posts, link from the portfolio site. Everything they need is prepared (see the checklists in Weeks 7 and 8 below).

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

## Week 4 — Status & dashboard

Decisions (2026-09-25): 3 consecutive failures → Down, 2 consecutive passes → Up (per-monitor `down_after` / `up_after`); Reverb + Echo for live updates; 5-minute and hourly rollups with a per-monitor latency chart.

- [x] `StatusEvaluator`: flap-protected state machine (unknown/up/down), row-locked, records latest result and `state_changed_at`; a monitor with no history goes Up on its first pass
- [x] `MonitorChecked` event on a private `monitors` channel after every check (`state_changed` flags a flip); broadcast auth at `/api/broadcasting/auth` behind Sanctum
- [x] `check_rollups` + `checks:rollup` (every 5 min, closed buckets only, idempotent, `--hours` to backfill): checks, failures, avg, nearest-rank p95
- [x] `GET /api/monitors/{id}/history?range=1h|24h|7d`: raw results, 5-minute buckets, hourly buckets
- [x] Frontend: live Status board (Down first, Up/Down/Unknown counts, connection indicator) and per-monitor latency chart with range switch
- [x] Tests: 53/53 Pest, 4/4 Vitest
- [x] Done when: stopped a test container → its monitor went Down after the 3rd failed check (~90s at 30s interval), came back Up after 2 passes; 0 failed jobs

### Notes

- Server-side Reverb host is `reverb` (set in `compose.yaml`); the browser uses `localhost` via `frontend/.env.local` (`VITE_REVERB_APP_KEY`, `VITE_REVERB_HOST`, `VITE_REVERB_PORT`; not committed). Without the compose override, broadcasts fail with "connect to localhost:8080".
- Down detection takes `down_after × interval` (90s at the 30s minimum). Trade-off for flap protection.

## Week 5 — Circuits & maintenance

- [x] `providers`, `circuits` (provider ref, A/Z sites, type, bandwidth, SLA target), `maintenance_windows` (a circuit *or* one monitor, provider change number); monitors can now link to a circuit or a device (not both)
- [x] Deleting a circuit unlinks its monitors instead of leaving dangling ids; deleting a provider with circuits is a 409
- [x] `SlaCalculator`: availability from 5-minute rollups of every monitor on the circuit, dropping buckets that overlap a covering maintenance window; `GET /api/circuits/{id}/sla?from&to` and a 30-day figure in the circuit list
- [x] `Monitor::inMaintenance()` (one preloaded query per list); shown on the Status board as "Maintenance" and carried on the live event
- [x] Frontend: Carriers section (Providers, Circuits with SLA column, Maintenance with local-time pickers)
- [x] Done when: SLA % excludes maintenance windows correctly (tested: a planned full outage drops out; an unplanned loss and another circuit's window are handled; an edge that only clips a bucket excludes the whole bucket, documented)
- Not built: a calendar *grid* (the Maintenance page is a list with Scheduled / Active / Done status).

## Week 6 — Incidents & alerts

- [x] `incidents`, `incident_events`, `alert_channels`; `IncidentState` enum owns the legal moves ([diagram](incident-states.md)); `IncidentService::transition()` is the only writer, row-locked, one event row per move (source of MTTA/MTTR)
- [x] Auto-open when a monitor goes Down (not under maintenance; one open incident per monitor; circuit incidents are critical); auto-resolve on recovery from Detected/Acknowledged/Investigating; Escalated and Monitoring wait for a person
- [x] Closing needs the RFO summary; closed incidents are frozen
- [x] Alerts: one queued job per (channel, incident), 3 tries with backoff; Telegram with an Acknowledge button on open, plain message on resolve; email; per-channel "Send test"; bot token redacted from errors
- [x] Telegram webhook (`POST /api/telegram/webhook`): secret header required (fails closed), press honoured only from a configured enabled channel, "already resolved" reply instead of an error
- [x] RFO PDF (dompdf): summary, root cause, corrective action, timeline; DRAFT watermark text until closed
- [x] Alert channels visible to engineers, changeable by admins only (`AlertChannelPolicy`)
- [x] Frontend: Incidents list with open/all filter and MTTA/MTTR tiles, detail page with timeline, only-legal-next-state buttons, RFO form and PDF download, Alert channels page, open-incident banner on the Status board
- [x] Verified live on the Docker stack: stopped a target → incident opened (email alert in the mail log) → restarted → auto-resolved after 117 s; RFO PDF rendered; 0 failed jobs
- Not verified: a real Telegram bot (no token available here). The client, button flow and webhook are covered by tests against a faked Telegram API; go-live steps are in [DEPLOY.md](DEPLOY.md).

## Week 7 — Simulator & hardening

- [x] `simulator` monitor type: `stable`, `flapping`, `latency_spike`, `outage_cycle` scenarios, pure functions of clock and monitor id; tested through the real evaluator (flapping never goes Down; outage_cycle opens and auto-resolves an incident)
- [x] `DemoSeeder`: fictional carrier "Straits Link Networks" (4 sites, 12 devices, 6 subnets, 3 providers, 5 circuits, 17 simulator monitors + 2 real HTTP/DNS checks, maintenance windows) and a read-only demo viewer; idempotent
- [x] Rate limiting: sign-in (5/min per account+address, 20/min per address), API 240/min, heavy actions 20/min (run-now, RFO, alert test, CSV import), webhook 60/min
- [x] Authorization matrix tests over every resource (401 unauthenticated, viewers read-only, alert channels hidden from viewers); unauthenticated API calls answer 401 JSON instead of 500
- [x] `netwatch:user` command (12+ character passwords) because production has no default accounts
- [x] Deploy kit: `deploy/Dockerfile` (SPA + composer + FrankenPHP), `Caddyfile` (automatic HTTPS, security headers, SPA fallback, WebSocket proxy, Horizon not exposed), `compose.prod.yaml`, `.env.production.example`, [DEPLOY.md](DEPLOY.md)
- [x] Production stack smoke-tested locally end to end: sign-in, 17 monitors checking, incident opened by the simulator, viewer write = 403, WebSocket handshake through Caddy (allowed origin connects, foreign origin rejected), Horizon path returns the SPA not the dashboard
- [x] Static analysis: Larastan level 6 in CI with a baseline of 141 pre-existing typing findings (missing array shapes, enum casts it does not resolve). New findings fail the build; the baseline is not zero and is worth paying down.
- [x] Performance: 203 monitors at 30 s on a laptop Docker stack (Horizon local: 10 workers): mean gap 30.3 s, 99.7% of gaps ≤ 40 s (4 of 1,212 skipped a tick after one slow 11 s dispatch), queue empty, 0 failed jobs. The plan's target is 200 monitors on a 2 vCPU VPS; that machine has not been tested.
- **Owner to do:** provision the VPS and DNS, follow DEPLOY.md, run `DemoSeeder` on a separate demo instance, confirm HTTPS and the "Live" indicator.

## Week 8 — Launch package

- [x] README rewritten (features, stack, quick start, tests, deploy, clean-room note); [architecture.md](architecture.md) with system and sequence diagrams and the design decisions
- [x] Drafts in `docs/launch/`: case study, two LinkedIn post options, 2-minute demo video script, screenshots checklist
- **Owner to do:** record the video, take the screenshots (replace the README comment), fill `[LIVE_DEMO_URL]` in the drafts, publish the post, add the case study and links to aliimranrohaizi.xyz.

## Session log — 2026-09-25

Done today (all uncommitted at the time of writing; commit is waiting on the owner's go-ahead):

- Weeks 4–6 built and verified on the Docker stack; Weeks 5–8 code and docs completed (see the sections above).
- Production stack (`compose.prod.yaml`) built and smoke-tested locally, then torn down; its throwaway secrets were deleted.
- Load test: 203 monitors at 30 s, no backlog, 0 failed jobs.
- Larastan level 6 added to CI with a baseline of 141 existing typing findings.
- Fix from owner feedback: deleting a monitor that has incidents now returns a clear 409 ("N incident(s) on record… untick Enabled") instead of the generic message. Suite: 128 Pest, 6 Vitest, all green.
- Product framing written for a client and for a non-technical reader (kept out of the repo; the README and `docs/launch/case-study.md` carry the public version).

## Backlog / decisions pending

- **Commit and push** the day's work (proposed split: Week 4, 5, 6, then 7+8).
- **Owner-only launch tasks:** VPS + DNS + first deploy, real Telegram bot and webhook, screenshots, demo video, fill `[LIVE_DEMO_URL]`, publish the post, link from aliimranrohaizi.xyz.
- **Phase 2 candidate: probe agent for client-side servers.** Push monitors with per-monitor tokens (`POST /api/agent/results`), a small Linux `netwatch-agent` run by cron/systemd, and a "no heartbeat for N minutes" rule. Needs token issuing/rotation and rate limits. Design first, then build (about a week). Not started.
- **Optional:** let a monitor be deleted while keeping its incidents (nullable `incidents.monitor_id`, plus fallbacks in the RFO and alert text).
- **Optional:** pay down the Larastan baseline; a calendar grid for maintenance windows.
- **Housekeeping:** remove the `nw-victim` test container and monitor and the `demo-XX` dev monitors when no longer needed.
