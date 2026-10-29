#!/usr/bin/env bash
# Konteyneri proxy arkasındaymış gibi çalıştırıp temel uçları doğrular.
# Kullanım: scripts/smoke-test.sh <imaj> ; APP_PORT ile iç port değiştirilebilir.
set -euxo pipefail

IMAGE="$1"
PORT="${APP_PORT:-8080}"

docker image inspect "$IMAGE" --format '{{.Id}} {{.Architecture}}'

docker run -d --name mail-mcp-smoke -p "$PORT:$PORT" \
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

docker logs mail-mcp-smoke 2>&1 | tail -30

curl -fsS "http://localhost:$PORT/up" >/dev/null
FWD=(-H 'X-Forwarded-Proto: https' -H 'X-Forwarded-Host: mail-mcp.example.com')

# Web arayüzü /panel altında; tarayıcıyla kök adrese gelen panele yönlenir.
test "$(curl -s -o /dev/null -w '%{http_code}' "http://localhost:$PORT/login")" = 200
test "$(curl -s -o /dev/null -w '%{redirect_url}' "${FWD[@]}" -H 'Accept: text/html' "http://localhost:$PORT/")" = "https://mail-mcp.example.com/panel"
# MCP istemcisinin SSE GET'i desteklenmiyor: 405 + Allow: POST
test "$(curl -s -o /dev/null -w '%{http_code}' -H 'Accept: text/event-stream' "http://localhost:$PORT/")" = 405

meta=$(curl -fsS "${FWD[@]}" "http://localhost:$PORT/.well-known/oauth-authorization-server")
echo "$meta"
test "$(jq -r .authorization_endpoint <<<"$meta")" = "https://mail-mcp.example.com/oauth/authorize"
test "$(jq -r .registration_endpoint <<<"$meta")" = "https://mail-mcp.example.com/oauth/register"

# MCP kök adreste: token'sız POST 401 + WWW-Authenticate
headers=$(curl -s -o /dev/null -D - -X POST -H 'Content-Type: application/json' "${FWD[@]}" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' "http://localhost:$PORT/")
echo "$headers"
grep -q '^HTTP/[0-9.]* 401' <<<"$headers"
rm_url=$(grep -i '^www-authenticate:' <<<"$headers" | sed -n 's/.*resource_metadata="\([^"]*\)".*/\1/p')
echo "resource_metadata=$rm_url"
case "$rm_url" in https://mail-mcp.example.com/.well-known/oauth-protected-resource*) ;; *) echo "beklenmeyen resource_metadata"; exit 1;; esac

# Başlıktaki metadata adresi gerçekten açılmalı ve kök kaynağı göstermeli.
rm_path="${rm_url#https://mail-mcp.example.com}"
prm=$(curl -fsS "${FWD[@]}" "http://localhost:$PORT${rm_path}")
echo "$prm"
resource=$(jq -r .resource <<<"$prm")
case "$resource" in https://mail-mcp.example.com|https://mail-mcp.example.com/) ;; *) echo "resource kök değil: $resource"; exit 1;; esac
test "$(jq -r '.authorization_servers[0]' <<<"$prm")" = "https://mail-mcp.example.com"

docker exec mail-mcp-smoke php artisan migrate:status | tail -3
docker exec mail-mcp-smoke test -f storage/oauth-private.key

echo "SMOKE TEST OK"
