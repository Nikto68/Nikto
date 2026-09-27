#!/usr/bin/env bash
# Runs the end-to-end suite locally: mock Bot API + PHP dev server + tests/e2e.php
# Usage: tests/run.sh [path-to-ffmpeg]
set -euo pipefail
cd "$(dirname "$0")/.."
FFMPEG="${1:-$(command -v ffmpeg || true)}"
WORK="$(mktemp -d)"
export MOCK_LOG="$WORK/mock.jsonl"
export EMOJIBOT_CONFIG="$WORK/config.php"
export APP_URL="http://127.0.0.1:18080"
: > "$MOCK_LOG"
cat > "$EMOJIBOT_CONFIG" <<PHP
<?php
return [
    'bot_token' => '123456789:TEST_TOKEN_abcdefghijklmnopqrstuvwxyz',
    'admin_ids' => [6595849261],
    'base_url' => '$APP_URL',
    'secret' => 'test_secret_0123456789abcdefghijklmnopqrstuvwxyz',
    'db' => ['driver' => 'sqlite', 'sqlite_path' => '$WORK/test.sqlite'],
    'ffmpeg' => '$FFMPEG',
    'api_url' => 'http://127.0.0.1:18081',
    'stars_packages' => [['stars' => 25, 'coins' => 50], ['stars' => 100, 'coins' => 240]],
    'max_parallel_jobs' => 2,
];
PHP
php -S 127.0.0.1:18081 tests/mock_telegram.php > "$WORK/mock.log" 2>&1 &
MOCK=$!
PHP_CLI_SERVER_WORKERS=6 php -S 127.0.0.1:18080 -t public > "$WORK/app.log" 2>&1 &
APPSRV=$!
trap 'kill $MOCK $APPSRV 2>/dev/null || true' EXIT
sleep 1
php tests/e2e.php
