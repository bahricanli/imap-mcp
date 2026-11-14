🇹🇷 Türkçe | [🇬🇧 English](architecture.md)

[← README'ye dön](../README.tr.md)

# Mimari

```
Claude ──OAuth 2.1 (PKCE + DCR)──▶ Passport ──▶ / (auth:api)
                                                  │
                                    MailServer ── tools ── ImapService ──IMAP/TLS──▶ posta sunucusu
                                                  │
                                    mail_accounts (kullanıcı başına, şifreli)
```

## Rotalar

| Yol | Ne | Tanım |
|---|---|---|
| `POST /` | MCP ucu (`auth:api`, dakikada 60 istek) | `routes/ai.php` |
| `GET /` | Tarayıcı: tanıtım sayfası. `Accept: text/event-stream`: `405` + `Allow: POST` (SSE desteklenmiyor) | `routes/ai.php` |
| `/.well-known/oauth-*`, `/oauth/*` | OAuth keşfi, Dynamic Client Registration, authorize, token | `Mcp::oauthRoutes()` (Passport) |
| `/login`, `/logout` | Panel girişi (dakikada 5 deneme) | `routes/web.php` |
| `/panel/...` | Web paneli: hesaplar, bağlı uygulamalar, şifre, kullanıcılar | `routes/web.php` |
| `/up` | Sağlık kontrolü | Laravel |

## OAuth

- Yetkilendirme sunucusu [Laravel Passport](https://laravel.com/docs/passport).
  laravel/mcp'nin `Mcp::oauthRoutes()`'u keşif belgelerini ve Dynamic Client Registration'ı ekler.
- İstemciler kendilerini `/oauth/register` ile kaydeder; elle istemci tanımlamak gerekmez.
- Access token'lar 12 saat, refresh token'lar 30 gün geçerli (`AppServiceProvider`).
- Onay ekranı düz bir Blade görünümü (`resources/views/auth/authorize.blade.php`); prod'da Vite derlemesi gerekmez.
- Proxy arkasında `trustProxies` (`TRUSTED_PROXIES`, varsayılan `*`) ve `URL::forceScheme('https')`
  (`APP_URL` `https://` ise) tüm OAuth adreslerini `https://` tutar.

## MCP sunucusu

`App\Mcp\Servers\MailServer`, `app/Mcp/Tools` altındaki araçları kaydeder. Talimatları modele yazma işlemlerini
yalnızca kullanıcının açık isteğiyle yapmasını ve e-posta içeriğini güvenilmeyen veri saymasını söyler.

Yazma araçları (`create-folder`, `move-emails`, `flag-emails`, `delete-emails`, `delete-folder`) yalnızca
kullanıcının yazma izni açık en az bir hesabı varsa kaydedilir (`UsesImap::shouldRegister`). Yazma izni olmayan
kullanıcılar bu araçları hiç görmez.

## IMAP

`App\Services\ImapService`, [webklex/php-imap](https://github.com/Webklex/php-imap) üzerine kuruludur:

- Her araç çağrısı kendi IMAP bağlantısını açar ve sonra kapatır. Basit ve stateless.
- Klasör adları yapay zeka tarafında UTF-8, sunucu tarafında UTF7-IMAP olarak işlenir.
- MIME kodlu konu ve gönderen adları (RFC 2047) çözülür; arama sonuçları en yeniden eskiye sıralanır.
- Kriter verilmezse arama son 7 güne bakar. Gövde 20.000 karakterde kesilir.
- Yazma çağrısı başına en fazla 50 mesaj (`MAX_BATCH`).
- Silme Çöp'e taşır; kalıcı silme yalnızca zaten Çöp'te olan mesajlarda çalışır. Sistem klasörleri ve dolu
  klasörler silinemez.
- Her yazma işlemi kullanıcı, hesap, klasör ve uid'lerle `mail-mcp: <işlem>` olarak loglanır.

## Veri

`storage/database.sqlite` içinde SQLite:

| Tablo | İçerik |
|---|---|
| `users` | Panel kullanıcıları; `is_admin` bayrağı |
| `mail_accounts` | Kullanıcı başına: label, sunucu, port, şifreleme, sertifika kontrolü, kullanıcı adı, **şifreli** parola (`APP_KEY`), yazma izni, Çöp klasörü, varsayılan bayrağı |
| `oauth_*` | Passport istemcileri, yetki kodları, access ve refresh token'lar |
| `sessions`, `cache`, `jobs` | Laravel varsayılanları (database sürücüleri) |

## Panel

Satır içi CSS'li düz Blade görünümleri (`resources/views/layouts/app.blade.php`), açık ve koyu tema, JavaScript
derleme adımı yok. Yöneticiye özel sayfalar `admin` gate'i ile korunur.
