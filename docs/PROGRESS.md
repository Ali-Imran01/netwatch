# NetWatch progress tracker

Milestones follow the 8-week plan. Update this file as part of each PR/commit that moves a milestone.

Legend: ✅ done · 🟡 in progress · ⬜ not started

| Week | Milestone | Status | Done when |
|---|---|---|---|
| 1 | Foundation | 🟡 | Login works, CI green |
| 2 | IPAM & inventory | ⬜ | Import 500-row CSV, bad rows reported clearly |
| 3 | Check engine | ⬜ | 50 monitors checked every 30s without backlog |
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
- [ ] Browser check of login flow at http://localhost:5173 (not yet confirmed)

### Notes

- Windows: Sail's shell wrapper sets `WWWUSER`/`WWWGROUP`; running `docker compose` directly needs them in `.env` (added).
- Host port map avoids Laragon: app 8000, MySQL 33060, Redis 63790, Reverb 8080.
- The container's own Vite port is remapped to 5180 (`VITE_PORT`) so the host-run frontend can use 5173, which CORS/Sanctum expect.

## Next up

Week 2 — IPAM & inventory: sites, VLANs, subnets, IPs, devices CRUD; utilization %; CSV import with row-level errors.

### Week 2 decisions (2026-09-24)

- IPv4 only; IPv6 deferred.
- CSV import is synchronous (no queue); good rows are imported and bad rows are reported with row number and reason.
- Frontend: full CRUD screens for sites, VLANs, subnets, IPs and devices, plus the import page and utilization bars.
