# Production image: one build serves the API, the React SPA and (via other commands) Horizon, the scheduler and Reverb.
# Build from the repo root:  docker build -f deploy/Dockerfile -t netwatch/app .

# ---- 1. React SPA -----------------------------------------------------------
FROM node:24-alpine AS spa
WORKDIR /spa
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci
COPY frontend/ ./
# The SPA and API share one origin in production, so no API URL. These are public values baked into the bundle.
ARG VITE_REVERB_APP_KEY
ARG VITE_REVERB_HOST
ARG VITE_REVERB_PORT=443
ARG VITE_REVERB_SCHEME=https
ARG VITE_DEMO_LOGIN=
ENV VITE_DEMO_LOGIN=$VITE_DEMO_LOGIN \
    VITE_API_URL="" \
    VITE_REVERB_APP_KEY=$VITE_REVERB_APP_KEY \
    VITE_REVERB_HOST=$VITE_REVERB_HOST \
    VITE_REVERB_PORT=$VITE_REVERB_PORT \
    VITE_REVERB_SCHEME=$VITE_REVERB_SCHEME
RUN npm run build

# ---- 2. PHP dependencies ----------------------------------------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
# pcntl/posix are needed by Horizon at runtime (the final image has them), not to install.
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction \
    --ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix
COPY . .
RUN composer dump-autoload --optimize --no-dev --no-scripts

# ---- 3. Runtime: FrankenPHP (PHP + Caddy with automatic HTTPS) ---------------
FROM dunglas/frankenphp:1-php8.3
RUN install-php-extensions pdo_mysql redis pcntl gd zip intl opcache \
    && apt-get update && apt-get install -y --no-install-recommends iputils-ping curl \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY --from=vendor /app /app
COPY --from=spa /spa/dist /srv/spa
COPY deploy/Caddyfile /etc/caddy/Caddyfile
COPY deploy/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache /data /config \
    # Caddy binds 80/443 as a non-root user.
    && setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp

USER www-data
ENTRYPOINT ["entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile", "--adapter", "caddyfile"]
