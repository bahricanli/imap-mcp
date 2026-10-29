# imap-mcp

IMAP posta kutularını yapay zeka istemcilerine (Claude, ChatGPT, Cursor…) **salt-okunur** açan,
OAuth 2.1 ile korunan bir MCP sunucusu. Laravel 13 + [laravel/mcp](https://github.com/laravel/mcp)
+ [webklex/php-imap](https://github.com/Webklex/php-imap).

- Mesajları okundu işaretlemez, silmez, taşımaz, göndermez.
- Bir kullanıcının birden fazla IMAP hesabı olabilir; şifreler `APP_KEY` ile şifreli saklanır.
- claude.ai custom connector'a doğrudan eklenir: OAuth keşfi ve Dynamic Client Registration hazır.

## Araçlar

| Araç | Ne yapar |
|---|---|
| `list-accounts` | Kullanıcının tanımlı hesapları |
| `list-folders` | Klasörler + toplam/okunmamış sayısı |
| `search-emails` | from / to / subject / text / since / before / unseen ile arama; gövdesiz özet liste (en fazla 50) |
| `get-email` | UID ile tek mesaj: başlıklar, düz metin gövde (20k karakterde kesilir), ek listesi |

Kriter verilmezse `search-emails` son 7 güne bakar; dev kutularda tam tarama yapmaz.

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
#   APP_PORT=8080                                (konteynerin dinlediği port)
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
- E-posta içeriği güvenilmeyen veridir (prompt injection). Sunucu talimatları ve `get-email` açıklaması
  modele bunu söylüyor. İleride gönderme/taşıma gibi yazma araçları eklenirse ayrı scope ve onay gerekir.

## Yol haritası

- [ ] Web panelden hesap ekleme / silme
- [ ] Ek indirme (imzalı, süreli URL)
- [ ] Taslak oluşturma (göndermeden), ayrı OAuth scope ile
- [ ] UTF-7 klasör adları için ek testler (Türkçe karakterli klasörler)

## Lisans

MIT
