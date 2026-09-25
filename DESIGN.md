# NetWatch — Design Document

## 1. Overview

NetWatch is a self-hosted platform that tracks network assets (sites, devices, IP space), monitors the uptime and latency of hosts and provider circuits, and manages incidents from detection through a written resolution report (RFO). It exists to demonstrate backend engineering depth — a check engine, background queues, time-series data handling, state machines, and real-time updates — relevant to NOC and telecom operations roles.

## 2. Clean-Room Statement

This project is built from an empty repository. No code, database schema, naming convention, UI layout, or configuration is copied or adapted from any prior employer's internal system. The design draws only on public sources: RFCs (e.g. RFC 5737 for documentation IP ranges), the public documentation of open-source tools in this space (NetBox, Uptime Kuma), and general, publicly-known NOC operational practice. All seed and demo data — provider names, IP ranges, site names, device inventories — is fictional or drawn from RFC 5737 reserved ranges (`192.0.2.0/24`, `198.51.100.0/24`, `203.0.113.0/24`). No former employer, client, real IP range, or real hostname is referenced anywhere in code, seed data, commit history, or documentation.

## 3. Goals & Non-Goals (this milestone)

**In scope for Week 1 ("Foundation"):**
- Repository, this design document, Docker Compose dev environment.
- Laravel API + React SPA skeletons.
- Sanctum-based SPA session auth, role field (admin/engineer/viewer), a minimal audit log, and base authorization policies.
- CI running backend and frontend test suites.
- A draft ERD covering the full v1 data model (schema itself is implemented incrementally in later weeks).

**Explicitly deferred:**
- IPAM, device inventory, and circuit CRUD (Week 2).
- The check engine (ping/TCP/HTTP/DNS probes, scheduler, flap protection) (Week 3).
- Live status dashboard and rollups (Week 4).
- Maintenance windows and SLA calculation (Week 5).
- Incident state machine implementation and alerting (Week 6).
- Demo simulator and production hardening (Week 7).
- VPS deployment, HTTPS, and launch materials (Week 8).

## 4. Architecture Overview

A React SPA talks to a Laravel API over REST for CRUD and over a WebSocket (Laravel Reverb + Echo) for live status updates. The API layer follows a Controller → Service → Resource pattern with Policies for authorization and an audit trail on write operations. A scheduler dispatches due monitor checks into Redis-backed queues; Horizon workers execute the checks and store results; a status evaluator applies flap-protection rules and, on a state change, broadcasts an event and opens/updates an incident (unless the target is in a maintenance window). A rollup job periodically aggregates raw check results into 5-minute and hourly buckets.

```
React SPA  <--REST-->  Laravel API  <-->  MySQL
    ^                       |
    | WebSocket (Reverb)    v
    +------------------  Redis (queues) --> Horizon workers --> Notifier (Telegram/Email)
```

**Data retention:** raw check results kept 7 days → 5-minute rollups kept 90 days → hourly rollups kept 1 year.

## 5. Tech Stack & Rationale

| Layer | Choice | Why |
|---|---|---|
| Backend | Laravel 12 (PHP 8.3) | Strongest framework for the author; clean Controller–Service–Resource separation |
| Frontend | React + Vite + TypeScript + Tailwind + Recharts | Typed UI, fast dev server, charting for latency/uptime |
| Database | MySQL 8 | Familiar; supports partitioning check results by month later |
| Queue / cache | Redis + Horizon | Parallel check execution with visible queue metrics |
| Real-time | Laravel Reverb + Echo | Live status board without polling |
| Auth | Sanctum (SPA) + Policies | Cookie-based SPA auth with fine-grained per-resource authorization |
| Testing | Pest / Vitest | Expressive backend/frontend test syntax |
| DevOps | Docker Compose (Sail), GitHub Actions | Reproducible local environment, CI gate on every push |

## 6. Data Model (v1)

Full entity-relationship draft: [`docs/ERD.md`](docs/ERD.md).

Entities group into five clusters:
- **Identity & audit:** `users`, `audit_logs`.
- **Inventory:** `sites`, `vlans`, `subnets`, `ip_addresses`, `devices`.
- **Circuits:** `providers`, `circuits`.
- **Monitoring:** `monitors`, `check_results`, `check_rollups`, `maintenance_windows`.
- **Incident management & alerting:** `incidents`, `incident_events`, `alert_channels`.

Only the identity cluster (`users`, `audit_logs`, plus a `role` column) is implemented this week; the rest are modeled in the ERD for planning purposes and built out in Weeks 2–6.

## 7. Auth & RBAC

Authentication is Sanctum's SPA cookie-session flow: the frontend first requests `GET /sanctum/csrf-cookie`, then `POST /api/login` with credentials, then reads `GET /api/user`. Sessions are stateful for the SPA's origin only (`SANCTUM_STATEFUL_DOMAINS`). Authorization is role-based with three roles — `admin`, `engineer`, `viewer` — stored as a column on `users` and enforced per-resource via Laravel Policies (an `admin` bypass plus explicit per-role checks on the rest). Every create/update/delete on audited models is recorded to `audit_logs` with the acting user, action, model, and before/after state.

## 8. Incident State Machine (implemented in Week 6)

Diagram: [`docs/incident-states.md`](docs/incident-states.md).

```
Detected → Acknowledged → Investigating → Escalated (to provider) → Monitoring → Resolved → Closed (RFO written)
                  ↘────────────── Resolved (auto, if service recovers) ──────────↗
```

Illegal transitions are rejected in the service layer; every transition writes an `incident_events` row for MTTA/MTTR metrics. See [`docs/architecture.md`](docs/architecture.md) for how it fits with the check engine, alerts and maintenance windows.

## 9. Environments & Deployment

Local development runs entirely via Docker Compose (Laravel Sail): app, MySQL, Redis, Reverb, Horizon. The frontend Vite dev server runs on the host for simplicity. Production deployment (a small Ubuntu VPS behind Nginx with Let's Encrypt, long-running workers under Supervisor) is out of scope until Week 7–8 and is not addressed by this document.

## 10. Testing & CI Strategy

Backend: Pest, targeting the Services layer, run against SQLite in-memory for CI speed. Frontend: ESLint (strict) and a Vitest smoke suite. GitHub Actions runs both on every push/PR to `main`; a merge should never land with CI red.

## 11. Roadmap

| Week | Milestone |
|---|---|
| 1 | Foundation — repo, this document, Docker Compose, Laravel + React skeleton, Sanctum auth, roles, audit log, CI |
| 2 | IPAM & inventory |
| 3 | Check engine |
| 4 | Status & dashboard |
| 5 | Circuits & maintenance |
| 6 | Incidents & alerts |
| 7 | Simulator & hardening |
| 8 | Launch package |
