#!/usr/bin/env bash
# Konteyneri proxy arkasındaymış gibi çalıştırıp temel uçları doğrular.
# Kullanım: scripts/smoke-test.sh <imaj> ; APP_PORT ile iç port değiştirilebilir.
set -euxo pipefail

IMAGE="$1"
PORT="${APP_PORT:-8080}"

docker image inspect "$IMAGE" --format '{{.Id}} {{.Architecture}}'

docker run -d --name t -p "$PORT:$PORT" \
  -e APP_PORT="$PORT" \
  -e NGINX_HTTP_PORT="$PORT" \
  -e APP_KEY="base64:$(openssl rand -base64 32)" \
  -e APP_URL=https://mail-mcp.example.com \
  -e APP_ENV=production -e APP_DEBUG=false \
  -e DB_CONNECTION=sqlite -e DB_DATABASE=/var/www/html/storage/database.sqlite \
  -e LOG_CHANNEL=stderr \
  "$IMAGE"

for _ in $(seq 1 45); do
  curl -fsS "http://localhost:$PORT/up" >/dev/null 2>&1 && break
  sleep 2
done

docker logs t 2>&1 | tail -30

curl -fsS "http://localhost:$PORT/up" >/dev/null
test "$(curl -s -o /dev/null -w '%{http_code}' "http://localhost:$PORT/login")" = 200

meta=$(curl -fsS -H 'X-Forwarded-Proto: https' -H 'X-Forwarded-Host: mail-mcp.example.com' \
  "http://localhost:$PORT/.well-known/oauth-authorization-server")
echo "$meta"
grep -q '"authorization_endpoint":"https://mail-mcp.example.com/oauth/authorize"' <<<"$meta"

code=$(curl -s -o /dev/null -w '%{http_code}' -X POST -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' "http://localhost:$PORT/mcp/mail")
test "$code" = 401

docker exec t php artisan migrate:status | tail -3
docker exec t test -f storage/oauth-private.key

echo "SMOKE TEST OK"
