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

## Docker ile kurulum

```bash
git clone https://github.com/bahricanli/imap-mcp.git && cd imap-mcp
cp .env.example .env
# APP_URL'i gerçek HTTPS adresine çevir, sonra:
docker compose run --rm mail-mcp php artisan key:generate --show   # çıktıyı .env'de APP_KEY'e yaz
docker compose up -d --build

docker compose exec mail-mcp php artisan mail:create-user
docker compose exec mail-mcp php artisan mail:add-account sen@ornek.com
```

İlk açılışta SQLite veritabanı ve Passport anahtarları `storage` volume'unda oluşturulur,
migration'lar otomatik çalışır. Konteyner 8080'i dinler; önüne TLS sonlandıran bir reverse proxy
(Traefik, nginx, Caddy) koy. OAuth akışı HTTPS olmadan çalışmaz.

> `APP_KEY`'i yedekle. Kaybedersen kayıtlı IMAP şifreleri çözülemez.

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
