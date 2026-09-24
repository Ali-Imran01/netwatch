# NetWatch

Self-hosted network asset & uptime monitoring platform — tracks sites, subnets, devices, and provider circuits; monitors host/circuit uptime and latency; manages incidents from detection through resolution with an SLA-aware reporting layer.

[![CI](https://github.com/Ali-Imran01/netwatch/actions/workflows/ci.yml/badge.svg)](https://github.com/Ali-Imran01/netwatch/actions/workflows/ci.yml)

## Tech stack

- **Backend:** Laravel 12 (PHP 8.3), API-first
- **Frontend:** React + Vite + TypeScript + Tailwind + Recharts
- **Database:** MySQL 8
- **Queue / cache:** Redis + Laravel Horizon
- **Real-time:** Laravel Reverb + Echo
- **Auth:** Laravel Sanctum (SPA session auth) + Policies (RBAC: admin / engineer / viewer)
- **Testing:** Pest (backend), Vitest (frontend)
- **DevOps:** Docker Compose (Laravel Sail), GitHub Actions CI

## Quick start

```bash
# Backend
docker compose up -d
./vendor/bin/sail artisan migrate:fresh --seed

# Frontend
cd frontend
npm install
npm run dev
```

Backend API: `http://localhost:8000` · Frontend: `http://localhost:5173`

## Clean-room note

This project is an original, clean-room design — see [`DESIGN.md`](DESIGN.md) for the full statement, architecture, and data model. No code, schema, or naming is derived from any prior employer's system; all seed/demo data is fictional or drawn from RFC 5737 documentation ranges.

## License

[MIT](LICENSE)
