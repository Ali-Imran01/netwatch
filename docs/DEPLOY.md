# Deploying NetWatch to a VPS

One small server, Docker Compose, automatic HTTPS. The production stack (`compose.prod.yaml`) was built and smoke-tested locally: login, live checks, incidents, the WebSocket handshake through the proxy, and viewer write protection all work against it. What has **not** been tested is a real domain and certificate, which is why the checklist below ends with verifying them.

## What runs

| Service | Role | Exposed |
|---|---|---|
| `app` | FrankenPHP: Caddy (HTTPS, static SPA) + PHP for `/api`, `/sanctum`, `/up` | 80, 443 |
| `horizon` | Queue workers (probes, alerts, broadcasts) | no |
| `scheduler` | `schedule:work`: dispatches checks every 30 s, rollups, pruning | no |
| `reverb` | WebSocket server for live updates | no (Caddy proxies `/app/*`) |
| `mysql`, `redis` | Data | no |

Horizon's dashboard is deliberately not routed. To look at it, use an SSH tunnel and a local run, or read `docker compose logs horizon`.

## Requirements

- A VPS with 2 vCPU / 2 GB RAM or more, Ubuntu 24.04, Docker Engine with the Compose plugin.
- A domain with an **A record** pointing at the server *before* you start (Caddy requests the certificate on first boot).
- Ports **80 and 443** (TCP, and 443/UDP for HTTP/3) open. Firewall: `ufw allow 22,80,443/tcp && ufw allow 443/udp && ufw enable`.

## First deploy

```bash
git clone https://github.com/Ali-Imran01/netwatch.git && cd netwatch
cp .env.production.example .env.production
```

Fill in `.env.production`. Generate the secrets on the server:

```bash
echo "APP_KEY=base64:$(openssl rand -base64 32)"
echo "DB_PASSWORD=$(openssl rand -hex 24)"
echo "DB_ROOT_PASSWORD=$(openssl rand -hex 24)"
echo "REVERB_APP_ID=$(openssl rand -hex 4)"
echo "REVERB_APP_KEY=$(openssl rand -hex 16)"      # public: built into the SPA
echo "REVERB_APP_SECRET=$(openssl rand -hex 24)"
```

Set `SITE_DOMAIN` to your domain. Leave `SITE_ADDRESS`, `PUBLIC_SCHEME`, `PUBLIC_PORT` as in the example for real HTTPS.

```bash
docker compose -f compose.prod.yaml --env-file .env.production up -d --build
docker compose -f compose.prod.yaml --env-file .env.production exec app php artisan migrate --force
docker compose -f compose.prod.yaml --env-file .env.production exec app php artisan netwatch:user you@example.com "Your Name" --role=admin
```

`netwatch:user` prompts for the password (12+ characters). There are **no default accounts** in production. Never run `db:seed` with the default `DatabaseSeeder` there: it creates `admin@netwatch.test` / `password`.

Verify:

```bash
curl -sI https://YOUR_DOMAIN/            # 200, with X-Frame-Options / X-Content-Type-Options
curl -s  https://YOUR_DOMAIN/api/user    # {"message":"Unauthenticated."}
```

Then sign in in a browser: the Status page should say **Live** (that is the WebSocket working through HTTPS).

## Public read-only demo

Run this on its **own** instance (not the one you use for real monitoring):

1. In `.env.production` set `DEMO_LOGIN=true` (shows a "fill in the demo account" button on the sign-in page).
2. After migrating: `docker compose ... exec app php artisan db:seed --class=DemoSeeder`
3. Rebuild if you changed `DEMO_LOGIN` (it is baked into the SPA): `up -d --build`.

`DemoSeeder` creates the fictional carrier *Straits Link Networks* (4 sites, 12 devices, 6 subnets, 5 circuits, 17 simulator monitors plus 2 real HTTP/DNS checks against `example.com`, maintenance windows) and one **viewer** account, `demo@netwatch.example` / `read-only-demo`. Those credentials are public by design; a viewer cannot change anything. Simulator monitors need no network, so the demo behaves the same everywhere: a circuit outage every ~30 minutes that opens and resolves an incident by itself, a flapping link that never goes Down, a latency spike, and a scheduled maintenance window. Sign-in and heavy actions are rate limited.

Create your own admin with `netwatch:user` as above if you want to poke at the write side.

## Telegram alerts (optional, can be added later)

1. In Telegram, talk to **@BotFather**, `/newbot`, keep the token.
2. Add the bot to your NOC group (or message it directly) and find the chat id, e.g. via `https://api.telegram.org/bot<TOKEN>/getUpdates` after sending it a message.
3. In `.env.production`: `TELEGRAM_BOT_TOKEN=<token>` and `TELEGRAM_WEBHOOK_SECRET=$(openssl rand -hex 24)`, then `up -d` to reload.
4. Register the webhook so the Acknowledge button works:
   ```bash
   curl -s "https://api.telegram.org/bot<TOKEN>/setWebhook" \
     -d "url=https://YOUR_DOMAIN/api/telegram/webhook" \
     -d "secret_token=<TELEGRAM_WEBHOOK_SECRET>" \
     -d 'allowed_updates=["callback_query"]'
   ```
5. In the app, **Incidents → Alert channels → New**: type `telegram`, target = the chat id. Click **Send test**.

Button presses are only honoured from chats that are configured, enabled alert channels, and only with the correct secret header. Email channels work the same way once `MAIL_MAILER` points at a real SMTP/API provider instead of `log`.

## Updating

```bash
git pull
docker compose -f compose.prod.yaml --env-file .env.production up -d --build
docker compose -f compose.prod.yaml --env-file .env.production exec app php artisan migrate --force
```

Rollback: `git checkout <previous tag/commit>` and `up -d --build` again (migrations are additive; restore a backup if you must undo one).

## Backups

```bash
# Nightly, from cron on the host:
docker compose -f compose.prod.yaml --env-file .env.production exec -T mysql \
  sh -c 'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction netwatch' | gzip > /var/backups/netwatch-$(date +%F).sql.gz
```

Raw check results are pruned after 14 days; 5-minute and hourly rollups are kept, and they are what SLA figures are computed from. Keep the certificate volume (`caddy_data`) across rebuilds so you do not hit certificate rate limits.

## Security checklist

- [ ] `APP_DEBUG=false`, `APP_ENV=production` (the example does this).
- [ ] Unique `APP_KEY`, DB passwords and Reverb secret; `.env.production` is not committed (it is in `.gitignore`).
- [ ] No `admin@netwatch.test` account exists: `docker compose ... exec app php artisan tinker --execute='echo App\Models\User::where("email","like","%@netwatch.test")->count();'` prints `0`.
- [ ] Only 22, 80, 443 reachable from outside; MySQL, Redis and Reverb have no published ports (they do not in `compose.prod.yaml`).
- [ ] `REVERB_ALLOWED_ORIGINS` is your domain (WebSockets from other sites are refused; the handshake was verified to reject a foreign origin).
- [ ] Session cookie is `Secure` (`SESSION_SECURE_COOKIE=true`).
- [ ] Telegram webhook secret set before registering the webhook.

## Troubleshooting

- **Status page says Offline:** the WebSocket is not connecting. Check `docker compose logs reverb`, that `REVERB_APP_KEY` was set *before* the build (it is baked into the SPA: change it and you must rebuild), and that `SITE_DOMAIN` matches the address in the browser.
- **419 on sign-in:** `SANCTUM_STATEFUL_DOMAINS` / `SESSION_DOMAIN` must equal the domain you browse.
- **Ping monitors always fail:** the container needs `NET_RAW` (Docker's default). Some hosts restrict outbound ICMP.
- **Broadcasts fail with "connect to localhost:8080":** `REVERB_HOST` must be `reverb` (the service name), not the public domain.
- **Certificate not issued:** DNS not pointing at the server yet, or ports 80/443 blocked. `docker compose logs app`.
