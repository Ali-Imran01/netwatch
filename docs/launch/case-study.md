# NetWatch: case study (draft for the portfolio site)

> Format follows the earlier case studies: Challenge / Solution / Role / Tech / Result. Replace the bracketed placeholders once the demo is deployed. Every number below was measured on this project; the "Result" section says how.

**Live demo:** [LIVE_DEMO_URL] (read-only) · **Source:** https://github.com/Ali-Imran01/netwatch

## Challenge

Network operations teams live in three places at once: an inventory (what exists, which IPs, which provider circuit), a monitoring tool (is it up, how fast), and an incident process (who knows, who owns it, what do we tell the customer). Most tools are good at one. Getting a real answer to "what was our availability on that circuit last month, excluding the provider's planned work?" usually means a spreadsheet.

I wanted to build the whole loop, from asset to alert to written reason-for-outage, as a clean-room project I could publish: no code, schema or data from any employer, fictional carriers and RFC 5737 documentation IP ranges only.

## Solution

**NetWatch** is a self-hosted platform with four parts that share one data model:

1. **Inventory:** sites, VLANs, subnets, IPs, devices, with utilisation and a CSV importer that reports bad rows by row number.
2. **A check engine:** ping, TCP, HTTP and DNS probes run through Redis queues and Horizon workers, driven by a 30-second scheduler.
3. **Circuits and maintenance:** provider circuits with an SLA target, monitors linked to them, planned maintenance windows, and availability that excludes those windows.
4. **Incidents:** opened when a monitor goes Down, a strict state machine with a full timeline, Telegram and email alerts (acknowledge from your phone), and a PDF RFO.

Decisions I would defend in an interview:

- **Flap protection as a state machine.** A monitor goes Down only after N consecutive failures and comes back after M passes, tracked per monitor under a row lock. A single dropped probe never pages anyone. The demo's `flapping` scenario fails two checks in every three and provably never opens an incident.
- **Compare-and-set work claiming.** The dispatcher advances `next_check_at` with `UPDATE … WHERE next_check_at = <what it read>` and queues a job only if a row changed, so overlapping scheduler ticks cannot double-queue a check.
- **Rollups outlive raw data.** Raw results are pruned at 14 days; 5-minute and hourly rollups stay, and both charts and SLA read from them. A monthly SLA number does not depend on data that has been deleted.
- **One place enforces incident rules.** `IncidentService::transition()` is the only writer of state; illegal moves are rejected with a 422 and every legal one writes a timeline row that MTTA/MTTR are computed from.
- **The Telegram button is authenticated twice** (webhook secret header, and the chat must be a configured channel), because anyone can message a public bot. The bot token is redacted from exceptions, since HTTP client errors embed the request URL.
- **A simulator probe for an honest public demo.** Scripted scenarios are pure functions of time and monitor id, so a demo needs no real network and every visitor sees the same story, while still exercising the real evaluator, incident and alert code.

## Role

Sole designer and developer: requirements, data model, backend, frontend, tests, Docker and deployment setup, documentation.

## Tech

Laravel 13 (PHP 8.3), MySQL 8, Redis, Horizon, Reverb + Echo (WebSockets), Sanctum, React + TypeScript + Vite + Tailwind + Recharts, dompdf, Pest, Vitest, GitHub Actions, Docker Compose, FrankenPHP/Caddy (automatic HTTPS).

## Result

- **Load test:** 50 monitors at a 30-second interval on a laptop Docker stack ran 400 checks in 4.5 minutes with gaps of 26–35 s (mean 30 s), an empty queue and zero failed jobs. [Add the 200-monitor figure from `docs/PROGRESS.md` once measured on the VPS.]
- **Tests:** 128 backend (Pest) and 6 frontend (Vitest) tests, green in CI. They include a real local TCP socket for the probe, every legal and illegal incident transition, the SLA maths with and without a maintenance window, and an authorisation matrix over every resource.
- **Bugs the testing found, and I fixed:** checks running at 60 s instead of 30 s (claiming monitors at slightly different moments pushed some past the next tick), 565 failed broadcasts (the workers were told to reach the WebSocket server at `localhost` inside Docker), and an unauthenticated API call returning 500 instead of 401.
- **Deployed stack verified locally:** the production Compose stack was built and smoke-tested end to end (sign-in, live checks, an incident opened by the simulator, a WebSocket handshake through the proxy that rejects foreign origins, and a read-only account that gets 403 on every write).

## What I would do next

Incident notes and assignment, per-channel severity routing, IPv6 in the IPAM, and the Phase 2 NOC console (voice and SMS KPIs) on top of the same incident engine.
