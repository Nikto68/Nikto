#!/usr/bin/env bash
#
# One-shot deployment helper for shared hosting with SSH access.
# Run it from inside the signal-bot/ directory on your real server:
#
#   cd signal-bot
#   bash deploy/quickstart.sh
#
# It runs in two passes:
#   Pass 1 (no .env yet): installs dependencies, creates .env pre-filled
#     with the known Telegram token/admin id, then stops so you can fill
#     in your real database credentials.
#   Pass 2 (.env exists): runs migrations and starts the worker in the
#     background with an auto-restart loop.
#
# This script does not touch anything outside this project folder and
# does not require root.

set -euo pipefail
cd "$(dirname "$0")/.."

echo "== Signal Bot quickstart =="

if [ ! -f .env ]; then
    echo "-- Pass 1: installing dependencies and creating .env"

    if ! composer install --no-dev --optimize-autoloader; then
        echo "-- composer install failed, retrying without the ext-pcntl requirement"
        echo "   (some shared hosts don't ship pcntl; the app runs fine without it,"
        echo "   it only loses instant graceful-shutdown-on-SIGTERM handling)"
        composer install --no-dev --optimize-autoloader --ignore-platform-req=ext-pcntl
    fi

    cp .env.example .env
    SECRET="$(openssl rand -hex 32)"
    sed -i.bak \
        -e "s/^TELEGRAM_BOT_TOKEN=.*/TELEGRAM_BOT_TOKEN=8692344604:AAEYq5aFqr3s27FWlUp9ztHGd9wKF7msxv0/" \
        -e "s/^TELEGRAM_WEBHOOK_SECRET=.*/TELEGRAM_WEBHOOK_SECRET=${SECRET}/" \
        -e "s/^TELEGRAM_ADMIN_IDS=.*/TELEGRAM_ADMIN_IDS=8213021584/" \
        .env
    rm -f .env.bak

    echo ""
    echo ">> .env created with your Telegram token/admin id and a random webhook secret already filled in."
    echo ">> Open .env now and fill in the DB_HOST / DB_DATABASE / DB_USERNAME / DB_PASSWORD lines"
    echo "   with your real database (create one first in your hosting panel if you don't have one yet)."
    echo ">> Then run this script again:  bash deploy/quickstart.sh"
    exit 0
fi

echo "-- Pass 2: .env found, running migrations"
php bin/migrate.php

echo "-- Starting the worker in the background (auto-restarts on crash)"
mkdir -p storage/logs

cat > run-worker.sh << 'INNER'
#!/bin/bash
cd "$(dirname "$0")"
while true; do
    php worker/market_worker.php >> storage/logs/worker.out 2>&1
    echo "$(date '+%F %T') worker exited, restarting in 5s" >> storage/logs/worker.out
    sleep 5
done
INNER
chmod +x run-worker.sh

# Don't stack a second copy if the script is re-run.
pkill -f 'run-worker\.sh' 2>/dev/null || true
sleep 1

nohup ./run-worker.sh > /dev/null 2>&1 &
disown
WORKER_PID=$!

echo ""
echo ">> Worker started (pid ${WORKER_PID}). Logs: storage/logs/worker.out"
echo ">> If the server reboots, just SSH in and run: cd $(pwd) && nohup ./run-worker.sh > /dev/null 2>&1 & disown"
echo ">> Last step (do this once you know your HTTPS domain):"
echo "     php bin/set-webhook.php https://YOUR-DOMAIN/webhook.php"
echo ""
echo "== Done =="
