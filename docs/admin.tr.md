🇹🇷 Türkçe | [🇬🇧 English](admin.md)

[← README'ye dön](../README.tr.md)

# Yönetim

Çoğu iş [web panelinden](features.tr.md) yapılabilir. Aşağıdaki Artisan komutları ilk kurulum ve betikle
yapılan değişiklikler içindir.

Docker'da her komutun başına `docker compose exec mail-mcp` ekle (hazır imaj kullanıyorsan
`-f docker-compose.release.yml` de ekle):

```bash
docker compose exec mail-mcp php artisan mail:create-user
```

## Komutlar

| Komut | Ne yapar |
|---|---|
| `php artisan mail:create-user [--admin]` | Ad, e-posta ve şifre (en az 12 karakter) sorar ve bir panel kullanıcısı oluşturur. İlk kullanıcı her zaman yöneticidir; `--admin` sonraki kullanıcıları da yönetici yapar. |
| `php artisan mail:add-account <kullanıcı-e-postası>` | Bir kullanıcıya IMAP hesabı ekler. Label, sunucu, port, şifreleme, sertifika kontrolü, yazma izni, kullanıcı adı ve şifre sorar; kaydetmeden önce bağlantıyı test eder. Kullanıcının ilk hesabı varsayılan olur. |
| `php artisan mail:set-write <kullanıcı-e-postası> <label> on\|off [--trash=YOL]` | Bir hesapta yazma iznini açar veya kapatır. `--trash` Çöp klasörünün yolunu ayarlar; boş değer otomatik bulma demektir. |
| `php artisan mcp:inspector /` | Test için yerel sunucuya karşı MCP Inspector'ı açar. |

Örnekler:

```bash
php artisan mail:set-write sen@ornek.com is on
```

```bash
php artisan mail:set-write sen@ornek.com is on --trash="INBOX/Trash"
```

```bash
php artisan mail:set-write sen@ornek.com is off
```

Çöp klasörü `Trash`, `Deleted Items`, `Çöp`, `Çöp Kutusu` gibi adlardan otomatik bulunur.

## Kullanıcılar ve roller

- **Kullanıcılar** yalnızca kendi posta hesaplarını ve bağlı uygulamalarını görür ve yönetir.
- **Yöneticiler** ayrıca **Kullanıcılar** sayfasında kullanıcı ekleyebilir, şifre sıfırlayabilir, yöneticilik
  verip kaldırabilir ve kullanıcı silebilir. Yönetici başka kullanıcıların IMAP bilgilerini görmez.
- Açık kayıt yok.

## Erişimi kesme

- Tek uygulama: **Bağlı uygulamalar** → **Bağlantıyı kes** o uygulamanın tüm token'larını iptal eder.
- Tek kullanıcı: yönetici kullanıcıyı siler; hesapları ve bağlı uygulamaları da kaldırılır.
- Herkes: Passport anahtarlarını yenilemek (`storage/oauth-*.key` dosyalarını silip yeniden başlatmak) tüm
  token'ları geçersiz kılar. Bunun için `APP_KEY`'i **değiştirme**; kayıtlı IMAP şifreleri okunamaz hale gelir.

## Loglar

Docker'da loglar stderr'e gider:

```bash
docker compose logs -f mail-mcp
```

Her yazma işlemi kullanıcı, hesap, klasör ve uid'lerle loglanır, örneğin
`mail-mcp: move {"user_id":1,"account":"is","from":"INBOX","to":"Faturalar/2026","uids":[…]}`.
