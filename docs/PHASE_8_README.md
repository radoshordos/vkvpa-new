# Fáze 8 — Maily (Laravel Mail)

Odesílání e-mailů z `edit_hlaseni.php` (přes `mymail()`/PHPMailer) převedeno na Laravel Mail. Současně tím mizí závislost na `phpmailer/` a uzavírá se bod **S7** (kontaktní e-mail už není ve file-based `mail.inc`, ale v konfiguraci/DB).

## Soubory

| Soubor | Kam | Účel |
|--------|-----|------|
| `config/vkvpa.php` | `config/` | kontaktní e-mail vyhodnocovatele (náhrada mail.inc) |
| `app/Mail/HlaseniPrijato.php` | tamtéž | potvrzení účastníkovi |
| `app/Mail/HlaseniProVyhodnocovatele.php` | tamtéž | oznámení vyhodnocovateli + „převzít záznam" |
| `resources/views/emails/hlaseni-prijato.blade.php` | tamtéž | šablona 1 |
| `resources/views/emails/hlaseni-vyhodnocovatel.blade.php` | tamtéž | šablona 2 |
| `app/Http/Controllers/HlaseniController.php` (úprava) | tamtéž | odeslání obou mailů + uložení kódu |
| `app/Http/Controllers/Auth/AuthController.php` (úprava) | tamtéž | `?confirm=` v token loginu |
| `app/Http/Controllers/MailImageController.php` | tamtéž | PNG obfuskace e-mailu (náhrada mail.php) |
| `routes/web.php` (úprava) | tamtéž | routa `mail.image` |
| `resources/views/partials/footer.blade.php` (úprava) | tamtéž | footer používá `route('mail.image')` |
| `tests/Feature/HlaseniMailTest.php` | tamtéž | testy |

## Co bylo nahrazeno

| Legacy | Nově |
|--------|------|
| `mymail()` / PHPMailer (`phpmailer/mymail.php`) | `Mail::to(...)->send(Mailable)` |
| ruční `=?utf-8?B?...?=` předmět + HTML headers | `Envelope`/`Content` + Blade šablona (utf-8 řeší Laravel) |
| `mail.inc` (base64 v souboru) + `ereg` validace | `config('vkvpa.contact_mail')` z `.env`/DB (**S7**) |
| `mail.php` (GD obrázek e-mailu) | `MailImageController` + routa `mail.image` |
| `kod = md5(...)` + `INSERT vkvpa_prihlaseni` | `Str::random(40)` + `VkvpaPrihlaseni::create` |

## Tok „převzít záznam"

1. Při podání hlášení se vyhodnocovateli pošle mail s odkazem `route('login.token', ['kod' => …, 'confirm' => id])`.
2. Odkaz vede na bezpečný token login z **Fáze 4** (`loginViaToken`), který přihlásí administrátora.
3. Díky `?confirm=ID` přesměruje rovnou na převzetí daného hlášení (`hlaseni.edit`). Samotné schválení (`schvaleno`) doplní **Fáze 6b**.

## Konfigurace

V `.env` (klíče už zavedeny ve Fázi 2):
```
MAIL_MAILER=smtp
MAIL_HOST=… MAIL_PORT=587 MAIL_USERNAME=… MAIL_PASSWORD=…
MAIL_FROM_ADDRESS="noreply@vkvpa.cz"
CONTACT_MAIL=ok1vum@hamradio.cz
```
Pro lokální vývoj `MAIL_MAILER=log` (maily půjdou do `storage/logs`).

## Testy fáze

```bash
php artisan test --filter=HlaseniMailTest
```
Pokrývá: odeslání obou mailů + uložení kódu při podání; nezaslání účastnického mailu, když je vyplněn jen telefon (vyhodnocovatel dostane mail vždy).

## Mimo tuto fázi (záměrně)

- QRP varianta mailu (`mail_qrp.php`) – v legacy zakomentovaná; lze doplnit jako další Mailable, pokud se má používat.
- Fronty (`ShouldQueue`) – Mailables jsou připravené (`Queueable`); zapnutí přes queue driver dle potřeby.
- Schválení hlášení po převzetí → **Fáze 6b**.
