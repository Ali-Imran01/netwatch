# NetWatch

Self-hosted network asset & uptime monitoring for people who run networks: an IPAM/inventory, a check engine (ping, TCP, HTTP, DNS), provider circuits with maintenance windows and SLA, and an incident workflow that goes from detection to a written reason-for-outage (RFO) PDF, with Telegram and email alerts.

[![CI](https://github.com/Ali-Imran01/netwatch/actions/workflows/ci.yml/badge.svg)](https://github.com/Ali-Imran01/netwatch/actions/workflows/ci.yml)

<!-- Screenshots: see docs/launch/screenshots-checklist.md, then replace this comment with the images.
![Status board](docs/screenshots/status-board.png) -->

## What it does

- **Inventory (IPAM):** sites, VLANs, subnets, IP addresses and devices, with subnet utilisation and per-entity CSV import that reports bad rows by row number instead of failing the file.
- **Check engine:** monitors probe hosts and services every 30 s or slower through queued workers. Ping, TCP, HTTP and DNS probes; raw results kept 14 days, 5-minute and hourly rollups kept for good.
- **Flap-protected status:** a monitor goes Down after 3 consecutive failures and recovers after 2 passes (both configurable). A live Status board updates over WebSockets the moment a state flips.
- **Circuits & maintenance:** providers, circuits (IPLC / IEPL / DIA / MPLS) with A–Z sites and an SLA target, monitors linked to circuits, provider maintenance windows, and an availability figure that **leaves planned maintenance out**.
- **Incidents:** opened automatically when a monitor goes Down (unless it is under maintenance), a strict state machine (Detected → Acknowledged → Investigating → Escalated → Monitoring → Resolved → Closed), a timeline of every move, MTTA/MTTR, and an RFO you write and export as a PDF.
- **Alerts:** Telegram (with an **Acknowledge** button that works from your phone) and email, with a "send test" button per channel.
- **Access control:** admin / engineer / viewer roles enforced by policies, an audit log on every write, rate-limited sign-in.
- **Public demo mode:** a simulator probe plays scripted scenarios (a recurring self-healing circuit outage, a flapping link, a latency spike) so a demo instance is alive without touching a real network.

## Tech stack

| | |
|---|---|
| Backend | Laravel 13 (PHP 8.3), API-first, Sanctum SPA session auth, policies |
| Frontend | React + Vite + TypeScript + Tailwind v4 + Recharts |
| Data | MySQL 8 · Redis |
| Background work | Laravel Horizon (queues) · scheduler (`schedule:work`) |
| Real-time | Laravel Reverb + Echo |
| PDF | dompdf |
| Tests & quality | Pest (backend) · Vitest (frontend) · Larastan level 6 · oxlint · GitHub Actions CI |
| Deploy | Docker Compose · FrankenPHP/Caddy with automatic HTTPS |

Architecture, sequence diagram and the reasoning behind the design: [`docs/architecture.md`](docs/architecture.md).

## Quick start (development)

Requires Docker Desktop and Node 24.

```bash
cp .env.example .env
docker compose up -d --build                      # app, MySQL, Redis, Reverb, Horizon, scheduler
docker compose exec laravel.test php artisan key:generate
docker compose exec laravel.test php artisan migrate:fresh --seed
cd frontend && npm install && npm run dev         # http://localhost:5173
```

The API is on `http://localhost:8000`. Dev accounts (seeded, **development only**): `admin@netwatch.test`, `engineer@netwatch.test`, `viewer@netwatch.test`, all with password `password`.

Load something to look at:

```bash
docker compose exec laravel.test php artisan db:seed --class=DemoSeeder      # the fictional carrier, simulator monitors
docker compose exec laravel.test php artisan db:seed --class=MonitorSeeder   # 50 real probes against the dev stack, for load testing
```

Live updates need the Reverb key in `frontend/.env.local`:

```
VITE_API_URL=http://localhost:8000
VITE_REVERB_APP_KEY=<REVERB_APP_KEY from .env>
VITE_REVERB_HOST=localhost
VITE_REVERB_PORT=8080
```

## Tests

```bash
DB_CONNECTION=sqlite DB_DATABASE=:memory: CACHE_STORE=array php artisan test     # 128 Pest tests
cd frontend && npm test                                                          # 6 Vitest tests
```

The suite covers the probes (including a real local TCP socket), the flap-protection state machine, dispatcher cadence, rollups, SLA with maintenance exclusion, every incident transition (legal and illegal), alert delivery and token redaction, the Telegram webhook's authentication, PDF export, rate limiting, and an authorisation matrix over every resource.

The check engine was also load-tested on the Docker stack: 50 monitors at a 30 s interval ran with check gaps of 26–35 s (mean 30 s), an empty queue and no failed jobs.

## Deploying

A single VPS with a domain is enough: [`docs/DEPLOY.md`](docs/DEPLOY.md) covers first deploy, the read-only public demo, Telegram setup, backups and a security checklist. The production stack has been built and smoke-tested end to end locally (login, live checks, incident opening, WebSocket handshake through the proxy, viewer write protection).

## Clean-room note

This project is an original, clean-room design; see [`DESIGN.md`](DESIGN.md) for the statement, data model and scope. No code, schema or naming is derived from any prior employer's system. All seed and demo data is fictional or from RFC 5737 documentation ranges.

## Project docs

[`DESIGN.md`](DESIGN.md) · [`docs/architecture.md`](docs/architecture.md) · [`docs/ERD.md`](docs/ERD.md) · [`docs/incident-states.md`](docs/incident-states.md) · [`docs/DEPLOY.md`](docs/DEPLOY.md) · [`docs/PROGRESS.md`](docs/PROGRESS.md)

## License

[MIT](LICENSE)
