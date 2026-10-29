#!/bin/sh
# Kalıcı volume (storage/) içinde SQLite veritabanı ve Passport anahtarlarını hazırlar.
# Migration'lar serversideup'ın AUTORUN adımında (50-laravel-automations) çalışır.
set -e
cd /var/www/html

# 9000 konteynerin içinde PHP-FPM'e ayrılmış; nginx bu porta bağlanamaz.
if [ "${NGINX_HTTP_PORT:-8080}" = "9000" ]; then
    echo "[mail-mcp] HATA: APP_PORT=9000 kullanılamaz (konteyner içinde PHP-FPM'in portu). Başka bir port seç, örn. 8080." >&2
    exit 1
fi

mkdir -p storage/app storage/framework/cache storage/framework/sessions storage/framework/views storage/logs

if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ] && [ ! -f "${DB_DATABASE:-storage/database.sqlite}" ]; then
    touch "${DB_DATABASE:-storage/database.sqlite}"
    echo "[mail-mcp] SQLite veritabanı oluşturuldu"
fi

if [ ! -f storage/oauth-private.key ]; then
    php artisan passport:keys --no-interaction
    chmod 600 storage/oauth-private.key
    echo "[mail-mcp] Passport anahtarları üretildi"
fi
