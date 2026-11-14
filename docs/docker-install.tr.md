🇹🇷 Türkçe | [🇬🇧 English](docker-install.md)

[← README'ye dön](../README.tr.md)

# Docker ile kurulum

imap-mcp'yi Docker ile çalıştırmanın iki yolu var:

- **A. Hazır imaj:** derleme yok, en hızlı yol.
- **B. Kaynak koddan:** depoyu klonla, imajı kendin derle.

İkisi de aynı `.env` dosyasını ve aynı Nginx Proxy Manager ayarını kullanır.

## Gereksinimler

- Compose eklentisiyle Docker Engine 24+ (`docker compose version`).
- Sunucuya yönlenmiş bir alan adı, örn. `mail-mcp.ornek.com`.
- HTTPS veren bir reverse proxy. Compose dosyaları aynı makinede [Nginx Proxy Manager](https://nginxproxymanager.com/)
  (NPM) olduğunu varsayar ve konteyneri NPM'in Docker ağına bağlar. claude.ai gibi OAuth istemcileri HTTPS ister.
- Açmak istediğin her posta kutusu için bir IMAP hesabı, mümkünse **uygulama şifresi** ile.

## Anahtarlar ve ayarlar

imap-mcp ücretli bir dış API çağırmaz. Tek gizli bilgiler şunlar:

| Ayar | Zorunlu | Ne işe yarar | Nereden alınır |
|---|---|---|---|
| `APP_KEY` | **Evet** | Laravel şifreleme anahtarı. IMAP şifreleri bununla şifrelenir. Yoksa konteyner açılmaz. | Bir kez üret: `echo "base64:$(openssl rand -base64 32)"`. **Yedekle.** Kaybedersen kayıtlı IMAP şifreleri çözülemez. |
| `APP_URL` | **Evet** | NPM'de girdiğin haliyle herkese açık HTTPS adresi. OAuth keşif adresleri bundan üretilir. | Alan adın, örn. `https://mail-mcp.ornek.com` |
| `NPM_NETWORK` | Evet (NPM ile) | NPM'in bağlı olduğu Docker ağının adı. | `docker inspect <npm-konteyneri> -f '{{range $k,$v := .NetworkSettings.Networks}}{{$k}} {{end}}'` |
| `APP_PORT` | Hayır (varsayılan `8080`) | Konteyner içinde nginx'in dinlediği port. NPM'deki Forward Port bununla aynı olmalı. `9000` olamaz (PHP-FPM'in portu). | Boş herhangi bir port |
| `TRUSTED_PROXIES` | Hayır (varsayılan `*`) | `X-Forwarded-*` başlıklarına güvenilen proxy'ler. Konteyner portu dışarı açık değilken `*` güvenli. | Portu açarsan NPM'in IP'si veya alt ağı |
| `IMAGE`, `VERSION` | Hayır | Yalnızca `docker-compose.release.yml` için: hangi imaj ve etiket çalışacak. | `IMAGE=bmericc/imap-mcp`, `VERSION=1.0.0` |
| `AUTORUN_LARAVEL_MIGRATION` | Hayır (varsayılan `true`) | `false` yapılırsa açılışta migration'lar çalışmaz. | — |
| IMAP şifresi | Hesap başına | Web panelinden veya `mail:add-account` ile girilir, şifreli saklanır. Yapay zekaya hiç gönderilmez. | Posta sağlayıcın. Gmail, Outlook ve Mailcow için uygulama şifresi kullan. |

Passport OAuth anahtarları (`storage/oauth-*.key`) ilk açılışta otomatik üretilir ve `storage` volume'unda durur.

### OAuth callback'leri

Kayıt edilmesi gereken bir callback adresi yok. Yapay zeka istemcileri kendilerini Dynamic Client Registration
(`/oauth/register`) ile kaydeder ve kendi yönlendirme adreslerini gönderir. Varsayılan olarak tüm HTTPS
yönlendirme alan adlarına izin verilir.

## A. Hazır imajla kurulum

İmajlar `linux/amd64` ve `linux/arm64` için yayınlanır:

- `ghcr.io/bahricanli/imap-mcp`
- `bmericc/imap-mcp` (Docker Hub)

Etiketler: `1.2.3`, `1.2`, `1`, `latest`.

1. Bir dizin oluştur:

   ```bash
   mkdir imap-mcp && cd imap-mcp
   ```

2. Compose dosyasını indir:

   ```bash
   curl -O https://raw.githubusercontent.com/bahricanli/imap-mcp/main/docker-compose.release.yml
   ```

3. Örnek ayarları indir:

   ```bash
   curl -o .env https://raw.githubusercontent.com/bahricanli/imap-mcp/main/.env.example
   ```

4. Bir `APP_KEY` üret:

   ```bash
   echo "base64:$(openssl rand -base64 32)"
   ```

5. `.env`'yi düzenle: `APP_KEY`, `APP_URL`, `NPM_NETWORK` ve gerekirse `APP_PORT`.
   Sürüm sabitlemek veya Docker Hub kullanmak için `VERSION=1.0.0` ve/veya `IMAGE=bmericc/imap-mcp` ekle.

6. Başlat:

   ```bash
   docker compose -f docker-compose.release.yml up -d
   ```

7. İlk kullanıcıyı oluştur (otomatik yönetici olur):

   ```bash
   docker compose -f docker-compose.release.yml exec mail-mcp php artisan mail:create-user
   ```

8. [Proxy host'u](#nginx-proxy-manager) kur, `https://alanadin/panel` adresinde giriş yap ve bir posta hesabı ekle.

## B. Docker ile kaynak koddan

1. Depoyu klonla:

   ```bash
   git clone https://github.com/bahricanli/imap-mcp.git && cd imap-mcp
   ```

2. `.env` oluştur:

   ```bash
   cp .env.example .env
   ```

3. Bir `APP_KEY` üret ve `APP_URL`, `NPM_NETWORK` ile birlikte `.env`'ye yaz:

   ```bash
   echo "base64:$(openssl rand -base64 32)"
   ```

4. Derle ve başlat (imaj `imap-mcp:local` olarak etiketlenir):

   ```bash
   docker compose up -d --build
   ```

5. İlk kullanıcıyı oluştur:

   ```bash
   docker compose exec mail-mcp php artisan mail:create-user
   ```

## Nginx Proxy Manager

Konteyner portu dışarı açılmaz. NPM konteynere Docker ağı üzerinden `mail-mcp` adıyla ulaşır.

Bir Proxy Host oluştur:

| Alan | Değer |
|---|---|
| Domain Names | `mail-mcp.ornek.com` (`APP_URL` ile aynı) |
| Scheme | `http` |
| Forward Hostname / IP | `mail-mcp` |
| Forward Port | `.env`'deki `APP_PORT` (varsayılan `8080`) |
| SSL | Let's Encrypt, **Force SSL**, HTTP/2 |

Ek ayar gerekmez. Uygulama NPM'in gönderdiği `X-Forwarded-Proto` / `X-Forwarded-Host` başlıklarına güvenir ve
OAuth adreslerini `https://` ile üretir.

NPM başka bir makinedeyse compose dosyasındaki `ports:` bölümünü aç ve NPM'i sunucunun IP'sine yönlendir.
Bu durumda `TRUSTED_PROXIES`'i NPM'in adresiyle sınırla.

Kontrol:

```bash
curl https://mail-mcp.ornek.com/.well-known/oauth-authorization-server
```

Çıktıdaki tüm adresler `https://mail-mcp.ornek.com/` ile başlamalı.

## Güncelleme

Hazır imaj:

```bash
docker compose -f docker-compose.release.yml pull
```

```bash
docker compose -f docker-compose.release.yml up -d
```

Kaynak koddan:

```bash
git pull --ff-only
```

```bash
docker compose up -d --build
```

Migration'lar açılışta otomatik çalışır. Ana sürüm yükseltmesinden önce [değişiklik günlüğünü](../CHANGELOG.md) oku.

## Yedekleme

Tüm durum `storage` volume'unda: SQLite veritabanı (kullanıcılar, posta hesapları, OAuth token'ları) ve
Passport anahtarları. Volume'u **ve** `.env`'yi (`APP_KEY`) yedekle.

```bash
docker run --rm -v imap-mcp_storage:/data -v "$PWD":/backup alpine tar czf /backup/imap-mcp-storage.tgz -C /data .
```

Volume adı `<proje>_storage` biçimindedir; proje adı dizin adıdır. Farklıysa `docker volume ls` ile bak.

Konteyner durdurulmuşken boş bir volume'e geri yükle:

```bash
docker run --rm -v imap-mcp_storage:/data -v "$PWD":/backup alpine tar xzf /backup/imap-mcp-storage.tgz -C /data
```

## Sık sorunlar

| Belirti | Neden ve çözüm |
|---|---|
| Konteyner `APP_KEY tanımlı değil` ile kapanıyor | `.env`'de `APP_KEY` yok. Yukarıdaki gibi üret. Sonradan değiştirme. |
| Konteyner `APP_PORT=9000 kullanılamaz` ile kapanıyor | 9000 konteyner içinde PHP-FPM'in portu. Başka bir `APP_PORT` seç. |
| `network npm_default declared as external, but could not be found` | `NPM_NETWORK` yanlış. Gerçek adı yukarıdaki `docker inspect` komutuyla bul. |
| NPM `502 Bad Gateway` veriyor | Forward Port `APP_PORT` ile aynı değil ya da konteyner NPM'in ağında değil. |
| OAuth adresleri `http://` ile başlıyor | `APP_URL` `https://` ile başlamıyor ya da proxy `X-Forwarded-Proto` göndermiyor. |
| claude.ai: "couldn't connect" | `/panel` değil kök adresi (`https://alanadin/`) kullan. Yukarıdaki `curl` testine bak. |
| Hesap eklerken `Bağlanılamadı` | IMAP sunucusu, port veya şifreleme yanlış, ya da sağlayıcı uygulama şifresi istiyor. Panelde "Bağlantıyı test et"i dene. |
| Yeniden derlemeden sonra kayıtlı hesaplar çalışmıyor | `APP_KEY` değişmiş. Eski anahtarı geri koy. |

Loglar:

```bash
docker compose logs -f mail-mcp
```
