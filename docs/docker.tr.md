🇹🇷 Türkçe | [🇬🇧 English](docker.md)

[← README'ye dön](../README.tr.md)

# Docker ayrıntıları

Adım adım kurulum için [Docker ile kurulum](docker-install.tr.md) belgesine bak. Bu sayfa imajın ve compose
dosyalarının nasıl kurulduğunu anlatır.

## İmaj

[`Dockerfile`](../Dockerfile) `serversideup/php:8.4-fpm-nginx` tabanlıdır. Bu temel imaj nginx ve PHP-FPM'i tek
konteynerde, S6 Overlay altında, `www-data` olarak çalıştırır. Bu yüzden tek imaj ve tek konteyner var.

- Kod `/var/www/html`'e kopyalanır, derleme sırasında `composer install --no-dev --optimize-autoloader` çalışır.
- nginx `NGINX_HTTP_PORT`'u dinler (compose bunu `APP_PORT`'tan verir, varsayılan `8080`).
- `AUTORUN_ENABLED=true`: açılışta temel imaj `migrate --force` çalıştırır ve config, route, view cache'lerini
  oluşturur. Migration'ları atlamak için `AUTORUN_LARAVEL_MIGRATION=false`.
- Sağlık ucu: `/up`.

### Açılış betiği

[`docker/entrypoint.d/40-prepare-storage.sh`](../docker/entrypoint.d/40-prepare-storage.sh) temel imajın Laravel
otomasyonlarından önce çalışır:

1. `APP_PORT` `9000` ise hata verip durur (PHP-FPM'in portu).
2. `APP_KEY` boşsa hata verip durur. Kod imajın içinde olduğu için otomatik üretilen bir anahtar her yeniden
   oluşturmada değişir ve kayıtlı IMAP şifreleri okunamaz hale gelir.
3. `storage/` alt dizinlerini ve yoksa SQLite dosyasını oluşturur.
4. İlk açılışta Passport anahtarlarını (`storage/oauth-*.key`) üretir.

## Compose dosyaları

| Dosya | Kullanım | İmaj |
|---|---|---|
| [`docker-compose.yml`](../docker-compose.yml) | Bu checkout'tan derleme | `imap-mcp:local` (`build: .`) |
| [`docker-compose.release.yml`](../docker-compose.release.yml) | Yayınlanmış imajı çalıştırma | `${IMAGE:-ghcr.io/bahricanli/imap-mcp}:${VERSION:-latest}` |

İkisi de tek bir servis tanımlar: `mail-mcp` (konteyner adı da bu).

- `env_file: .env`; ayrıca `environment:` içinde SQLite, `LOG_CHANNEL=stderr` ve güvenli çerez zorlanır.
- Tek bir named volume, `storage`, `/var/www/html/storage`'a bağlanır (veritabanı ve Passport anahtarları).
- Nginx Proxy Manager'ın `mail-mcp:<APP_PORT>` adresine ulaşabilmesi için harici `${NPM_NETWORK:-npm_default}`
  ağı. Varsayılan olarak hiçbir port dışarı açılmaz.

Uygulamada kuyruk worker'ı veya zamanlayıcı yok, bu yüzden ek servis de yok.

Yereldeki `docker-compose.override.yml` dosyası `docker compose` tarafından otomatik okunur ve git'te yok sayılır.
Port açmak gibi makineye özel değişiklikler için kullan.

## Yayınlanan imajlar

| Registry | Repo |
|---|---|
| GitHub Container Registry | `ghcr.io/bahricanli/imap-mcp` |
| Docker Hub | `bahricanli/imap-mcp` |

Platformlar: `linux/amd64`, `linux/arm64`. `v1.2.3` sürümünün etiketleri: `1.2.3`, `1.2`, `1`, `latest`.

## CI

[`.github/workflows/docker.yml`](../.github/workflows/docker.yml):

- **Pull request'ler ve `main` push'ları:** amd64 imajı derlenir ve
  [`scripts/smoke-test.sh`](../scripts/smoke-test.sh) çalışır. Hiçbir şey yayınlanmaz.
- **`v*.*.*` tag'leri:** aynı test, ardından çok platformlu derleme GHCR'ye ve Docker Hub'a gönderilir.
  "Use workflow from" alanında bir tag seçilerek elle de başlatılabilir (`workflow_dispatch`).
- Docker Hub için `DOCKERHUB_USERNAME` ve `DOCKERHUB_TOKEN` repo secret'ları gerekir. Yoksa Docker Hub adımı
  atlanır ve yalnızca GHCR'ye yayınlanır.
- Derleme önbelleği: GitHub Actions cache, scope `imap-mcp`.

[`.github/workflows/mirror-dockerhub.yml`](../.github/workflows/mirror-dockerhub.yml) GHCR'de zaten yayınlanmış bir sürümü
yeniden derlemeden Docker Hub'a kopyalar (aynı digest). `version` ile elle çalıştırılır (örn. `1.0.1`);
`aliases` açıksa `1.0`, `1` ve `latest` de bu sürüme taşınır.

Duman testi konteyneri proxy arkasındaymış gibi başlatır ve şunları kontrol eder: `/up`, `/login`, ana sayfa,
OAuth keşif belgeleri, MCP ucunun `401` + `WWW-Authenticate` cevabı, migration'lar, Passport anahtarı ve
`APP_KEY` olmadan konteynerin açılmadığı.

Yerelde çalıştırmak için:

```bash
docker build -t imap-mcp:test .
```

```bash
APP_PORT=8090 bash scripts/smoke-test.sh imap-mcp:test
```
