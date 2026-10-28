#!/bin/sh
# Kalıcı volume (storage/) içinde SQLite veritabanı ve Passport anahtarlarını hazırlar.
# Migration'lar serversideup'ın AUTORUN adımında (50-laravel-automations) çalışır.
set -e
cd /var/www/html

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
