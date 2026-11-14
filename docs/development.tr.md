🇹🇷 Türkçe | [🇬🇧 English](development.md)

[← README'ye dön](../README.tr.md)

# Geliştirme

## Gereksinimler

- `mbstring`, `pdo_sqlite`, `openssl` eklentileriyle PHP 8.3+
- Composer 2
- Docker (yalnızca imaj ve duman testi için)

IMAP eklentisi gerekmez: webklex/php-imap IMAP'i soket üzerinden konuşur.

## Yerel kurulum

```bash
composer install
```

```bash
cp .env.example .env
```

```bash
php artisan key:generate
```

```bash
php artisan passport:keys
```

```bash
touch database/database.sqlite && php artisan migrate
```

```bash
php artisan mail:create-user
```

```bash
php artisan serve
```

Yerelde `.env` içinde `APP_ENV=local`, `APP_DEBUG=true` ve `APP_URL=http://localhost:8000` yap ve
`SESSION_SECURE_COOKIE=true` satırını kaldır; yoksa giriş çerezi düz HTTP üzerinden gönderilmez.

## Testler

```bash
php artisan test
```

Testler bellek içi SQLite veritabanı ve Mockery ile taklit edilmiş bir `ImapService` kullanır; posta sunucusu
gerekmez. CI bunları her push ve pull request'te çalıştırır ([`tests.yml`](../.github/workflows/tests.yml)).

| Dosya | Kapsam |
|---|---|
| `tests/Feature/MailToolsTest.php` | Okuma araçları |
| `tests/Feature/WriteToolsTest.php` | Yazma araçları, sınırlar, Çöp kuralları |
| `tests/Feature/OAuthFlowTest.php` | Keşif, kayıt, authorize, token, MCP çağrısı |
| `tests/Feature/AccountsUiTest.php`, `UserAdminTest.php`, `WebAuthTest.php` | Web paneli |
| `tests/Unit/ImapServiceRulesTest.php` | IMAP servis kuralları |

Docker imajı duman testi:

```bash
docker build -t imap-mcp:test .
```

```bash
APP_PORT=8090 bash scripts/smoke-test.sh imap-mcp:test
```

## Kod stili

[Laravel Pint](https://laravel.com/docs/pint):

```bash
vendor/bin/pint
```

## Proje düzeni

| Yol | İçerik |
|---|---|
| `app/Mcp/Servers/MailServer.php` | MCP sunucusu, talimatlar, araç listesi |
| `app/Mcp/Tools/` | Her araç için bir sınıf; `Concerns/UsesImap.php` ortak bağlantı yönetimi |
| `app/Services/ImapService.php` | Tüm IMAP mantığı ve güvenlik kuralları |
| `app/Http/Controllers/` | Web paneli |
| `app/Console/Commands/` | `mail:*` komutları |
| `resources/views/` | Blade görünümleri |
| `routes/ai.php` | MCP ve OAuth rotaları |
| `routes/web.php` | Panel rotaları |
| `docker/`, `Dockerfile`, `docker-compose*.yml` | Konteyner kurulumu |

## Sürüm yayınlama

1. [CHANGELOG.md](../CHANGELOG.md)'yi güncelle: `[Unreleased]` maddelerini yeni sürümün altına taşı.
2. Commit'le ve `main`'e push et.
3. Tag at ve gönder: `git tag -a v1.2.3 -m v1.2.3`, ardından `git push origin v1.2.3`.
4. CI `1.2.3`, `1.2`, `1` ve `latest` etiketlerini GHCR'ye ve Docker Hub'a yayınlar.
5. Tüm etiketlerin aynı çok platformlu digest'i gösterdiğini kontrol et:

   ```bash
   docker buildx imagetools inspect ghcr.io/bahricanli/imap-mcp:1.2.3
   ```
