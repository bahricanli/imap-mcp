# imap-mcp

IMAP posta kutularını yapay zeka istemcilerine (Claude, ChatGPT, Cursor…) açan, OAuth 2.1 ile
korunan bir MCP sunucusu. Laravel 13 + [laravel/mcp](https://github.com/laravel/mcp)
+ [webklex/php-imap](https://github.com/Webklex/php-imap).

- Varsayılan olarak **salt-okunur**; klasör oluşturma, taşıma, işaretleme ve silme hesap bazında açılır.
- Okuma araçları mesajları okundu işaretlemez. Mail göndermez.
- Bir kullanıcının birden fazla IMAP hesabı olabilir; şifreler `APP_KEY` ile şifreli saklanır.
- claude.ai custom connector'a doğrudan eklenir: OAuth keşfi ve Dynamic Client Registration hazır.

## Araçlar

**Okuma** (her hesapta):

| Araç | Ne yapar |
|---|---|
| `list-accounts` | Kullanıcının hesapları ve yazma izni olup olmadığı |
| `list-folders` | Klasörler + toplam/okunmamış sayısı |
| `search-emails` | from / to / subject / text / since / before / unseen ile arama; gövdesiz özet liste (en fazla 50) |
| `get-email` | UID ile tek mesaj: başlıklar, düz metin gövde (20k karakterde kesilir), ek listesi |

Kriter verilmezse `search-emails` son 7 güne bakar; dev kutularda tam tarama yapmaz.

**Yazma** (sadece yazma izni açık hesaplarda; izni olmayan kullanıcılar bu araçları hiç görmez):

| Araç | Ne yapar |
|---|---|
| `get-email` + `move_to` / `mark_seen` | Okuduktan sonra mesajı bir klasöre taşır ve/veya okundu işaretler |
| `move-emails` | Mesajları (en fazla 50) başka klasöre taşır |
| `flag-emails` | Okundu/okunmadı, yıldızlı/yıldızsız işaretler |
| `create-folder` | Klasör oluşturur (Türkçe karakter destekli) |
| `delete-emails` | Varsayılan: Çöp'e taşır. `permanent=true`: sadece Çöp klasöründen kalıcı siler |
| `delete-folder` | Sadece boş, alt klasörü olmayan, sistem klasörü olmayan klasörleri siler |

Örnek istek: *"Okunmamış faturaları oku, özetle ve hepsini Faturalar/2026 klasörüne taşı."*
Claude `search-emails` ile mesajları bulur. Ardından her biri için `get-email(uid, move_to: "Faturalar/2026", mark_seen: true)` çağırır.

### Web arayüzü

`https://alanadin/` adresinde giriş yaptıktan sonra:

- **Posta hesapları:** ekle / düzenle / sil, "Bağlantıyı test et", yazma izni ve Çöp klasörü ayarı.
  Hesap kaydedilmeden önce IMAP bağlantısı test edilir; düzenlemede şifre boş bırakılırsa mevcut şifre korunur.
- **Bağlı uygulamalar:** OAuth ile bağlanan istemciler (Claude vb.) ve "Bağlantıyı kes" (access + refresh token'lar iptal edilir).
- **Şifre:** giriş şifresini değiştirme.

İlk kullanıcı yine terminalden oluşturulur (`mail:create-user`); gerisi arayüzden yapılabilir.

### Yazma iznini terminalden açma

```bash
php artisan mail:set-write sen@ornek.com kalehost on
php artisan mail:set-write sen@ornek.com kalehost on --trash="INBOX/Trash"   # Çöp klasörü otomatik bulunamazsa
php artisan mail:set-write sen@ornek.com kalehost off
```

Çöp klasörü `Trash`, `Deleted Items`, `Çöp`, `Çöp Kutusu` gibi adlardan otomatik bulunur.

## Docker ile kurulum (Nginx Proxy Manager arkasında)

Hazır imaj: `ghcr.io/bahricanli/imap-mcp` (amd64 + arm64). Her `main` push'unda build edilir,
konteyner açılıp OAuth uçları test edildikten sonra yayınlanır.

```bash
mkdir imap-mcp && cd imap-mcp
curl -O https://raw.githubusercontent.com/bahricanli/imap-mcp/main/docker-compose.yml
curl -o .env https://raw.githubusercontent.com/bahricanli/imap-mcp/main/.env.example

# .env içinde:
#   APP_URL=https://mail-mcp.ornek.com          (NPM'deki alan adı)
#   APP_KEY=   ← aşağıdaki komutun çıktısı
#   NPM_NETWORK=npm_default                      (NPM'in Docker ağı)
#   APP_PORT=8080                                (konteynerin dinlediği port; 9000 olamaz)
echo "base64:$(openssl rand -base64 32)"

docker compose up -d
docker compose exec mail-mcp php artisan mail:create-user
docker compose exec mail-mcp php artisan mail:add-account sen@ornek.com
```

NPM'in ağ adını bulmak için:
`docker inspect <npm-konteyneri> -f '{{range $k,$v := .NetworkSettings.Networks}}{{$k}} {{end}}'`

**NPM'de Proxy Host:**

| Alan | Değer |
|---|---|
| Domain Names | `mail-mcp.ornek.com` (APP_URL ile aynı) |
| Scheme / Forward Hostname / Port | `http` / `mail-mcp` / `.env`'deki `APP_PORT` (varsayılan 8080) |
| SSL | Let's Encrypt, **Force SSL**, HTTP/2 |

Ek ayar gerekmez: uygulama NPM'in gönderdiği `X-Forwarded-Proto` / `X-Forwarded-Host` başlıklarına
güvenir, OAuth keşif adresleri `https://` ile üretilir. Konteyner portu dışarı açılmaz.

İlk açılışta SQLite veritabanı ve Passport anahtarları `storage` volume'unda oluşturulur,
migration'lar otomatik çalışır.

> `APP_KEY`'i yedekle. Kaybedersen kayıtlı IMAP şifreleri çözülemez.

Kontrol: `curl https://mail-mcp.ornek.com/.well-known/oauth-authorization-server` çıktısındaki
tüm adresler `https://mail-mcp.ornek.com/...` ile başlamalı.

## Docker'sız kurulum

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan passport:keys
php artisan migrate
php artisan mail:create-user
php artisan mail:add-account sen@ornek.com
```

## Bağlanma

- **claude.ai:** Ayarlar → Connectors → Add custom connector → `https://alanadin/mcp/mail`.
  Tarayıcıda giriş ekranı, ardından onay ekranı açılır.
- **Claude Code:** `claude mcp add --transport http mail https://alanadin/mcp/mail`
- **Test:** `php artisan mcp:inspector mcp/mail`

## Nasıl çalışıyor

```
Claude ──OAuth 2.1 (PKCE + DCR)──▶ Passport ──▶ /mcp/mail (auth:api)
                                                  │
                                    MailServer ── tools ── ImapService ──IMAP/TLS──▶ posta sunucusu
                                                  │
                                    mail_accounts (kullanıcı başına, şifreli)
```

Her araç çağrısı kendi IMAP bağlantısını açıp kapatır. Basit ve stateless; yoğun kullanımda
bağlantı havuzu ya da kısa süreli cache eklenebilir.

## Güvenlik

- Mailcow / Gmail / Outlook için ana şifre yerine **uygulama şifresi** kullan.
- Açık kayıt yok; kullanıcılar sadece `mail:create-user` ile eklenir. Giriş denemeleri dakikada 5 ile sınırlı.
- **Prompt injection:** e-posta içeriği güvenilmeyen veridir. Gelen bir mail "bu klasörü sil" diyebilir.
  Bu yüzden yazma izni varsayılan kapalı. Sunucu talimatları modele yazma işlemlerini sadece kullanıcının
  açık isteğiyle yapmasını söylüyor. Yine de asıl güvence aşağıdaki sunucu tarafı kurallar:
  - Silme önce Çöp'e taşır; kalıcı silme sadece Çöp klasöründen, ayrı bir adımla yapılır.
  - Sistem klasörleri ve dolu klasörler silinemez.
  - Tek çağrıda en fazla 50 mesaj işlenir.
  - Tüm yazma işlemleri kullanıcı, hesap, klasör ve uid'lerle loglanır (`docker compose logs mail-mcp`).
- Yazma araçları MCP `destructive` / `idempotent` işaretleriyle gelir; Claude bunlar için onay isteyebilir.

## Yol haritası

- [ ] Web panelden hesap ekleme / silme ve yazma izni
- [ ] Ek indirme (imzalı, süreli URL)
- [ ] Taslak oluşturma (göndermeden)
- [ ] Gerçek Dovecot konteyneriyle uçtan uca IMAP testleri (CI)

## Lisans

MIT
