# Audit – VKV Provozní aktiv

**Poslední revize:** 2026-06-15
**Rozsah:** celá codebase (Laravel 13 / PHP 8.5) – kvalita kódu, build a CI,
autentizace, autorizace, zpracování vstupu, upload souborů, SQL, XSS, CSRF,
konfigurace nasazení a závislosti.
**Metoda:** statická analýza zdrojového kódu, spuštění celé testovací a
kvalitativní sady (PHPUnit, PHPStan level 10, Pint), `composer audit`,
`npm audit` a kontrola konfigurace nasazení (Docker/Apache, `.htaccess`, CSP,
session, rate limiting).

Tento soubor sjednocuje dříve oddělené audity (`SECURITY_AUDIT.md`,
`SECURITY_AUDIT_2026-06-08.md`) do jediného dokumentu a doplňuje předprodukční
audit kvality.

---

## Shrnutí

Aplikace jde do produkčního nasazení. Aktuální stav po nápravách:

| Oblast | Stav |
|--------|------|
| PHPUnit | ✅ 341 testů zelených (1033 asercí) |
| PHPStan (level 10) | ✅ 0 chyb |
| Pint (code style) | ✅ bez výtek |
| `composer audit` (locked) | ✅ žádné zranitelnosti |
| `npm audit` | ✅ 0 zranitelností |
| Frontend build (Vite) | ✅ projde |
| Bezpečnost (nálezy 1–5) | ✅ vše opraveno |

Z bezpečnostního hlediska je aplikace na velmi dobré úrovni – Eloquent
s vázanými parametry, výchozí escapování v Blade, CSRF ochrana, nonce-based CSP,
bezpečnostní hlavičky, rate limiting a v produkci vynucená šifrovaná a `Secure`
session. Nebyla nalezena žádná kritická zranitelnost (RCE, SQLi, auth bypass).

---

## Část A – Předprodukční audit kvality (2026-06-15)

Spuštění plné CI sady odhalilo, že **build byl rozbitý**: poslední merge
(„phone-validation-uzivatele-admin", PR #124) přidal odkazy na soubory, které
se do repozitáře nikdy nedostaly. Následující defekty byly nalezeny a opraveny.

| # | Závažnost | Oblast | Stav |
|---|-----------|--------|------|
| A1 | Vysoká (build) | Chybějící třída `App\Rules\ValidPhone` – odkazována ze 4 míst | ✅ Opraveno |
| A2 | Vysoká (build) | Chybějící `App\Http\Controllers\Admin\UzivateleController` + view – routa `/admin/uzivatele` by skončila 500 | ✅ Opraveno |
| A3 | Nízká (test) | Zastaralý test – neposílal nově povinná pole `jmeno`/`telefon` | ✅ Opraveno |
| A4 | Nízká (analýza) | PHPStan level 10 – nullovatelné typy v `BackfillEdilineQsoAtTest` | ✅ Opraveno |
| A5 | Nízká (styl) | Pint – formátování v `ValidateCategoryMatrix` (PHP 8.5 pipe `|>`) | ✅ Opraveno |

### A1 – Chybějící pravidlo `ValidPhone`

`StoreHlaseniRequest` a Livewire `Prihlaska` validují `telefon` přes
`new ValidPhone`, ale třída nikdy nebyla commitnutá → fatální chyba při
validaci hlášení (500) a 11 padajících testů. Doplněno pravidlo
`app/Rules/ValidPhone.php` (povolené znaky `+ - / ( ) mezera`, 9–15 číslic),
konzistentní s existujícím `ValidMaidenhead`.

### A2 – Chybějící `UzivateleController` + view

`routes/web.php` registruje `/admin/uzivatele → UzivateleController@index`
(`uzivatele.index`), na routu odkazuje navigace i výsledkové listiny, existují
i překlady (`admin.uzivatele_*`), ale controller ani Blade šablona nebyly
commitnuté. Doplněno:
- `app/Http/Controllers/Admin/UzivateleController.php` – přehled kontaktních
  údajů z `edi_entries` s filtrem dle kola a fulltextem (značka / jméno /
  e-mail / telefon), stránkováno po 50.
- `resources/views/pages/admin/uzivatele.blade.php` – tabulka dle připravených
  překladů; routa je za `admin` middleware (citlivá osobní data).

### A3 – Zastaralý autorizační test

`test_anyone_can_create_new_record_without_session` posílal hlášení bez
`jmeno`/`telefon`, které PR #124 učinil povinnými. Test doplněn o tato pole,
aby odrážel aktuální validaci.

### A4 – Typová bezpečnost v testu

`BackfillEdilineQsoAtTest` volal `$lines[0]->…` (nullovatelný přístup do
kolekce) a `artisan()->assertSuccessful()` na unionu `PendingCommand|int`.
Opraveno bez potlačení: přístup přes `firstOrFail()` a zúžení typu přes
`assertInstanceOf(PendingCommand::class, …)` v privátním helperu.

### A5 – Code style

`ValidateCategoryMatrix` (využívá PHP 8.5 pipe operátor `|>`) sjednocen Pintem
(mezery v castech/konkatenaci, seskupení importů). Beze změny logiky.

> Pozn.: soubor `ValidateCategoryMatrix.php` používá PHP 8.5 syntaxi (`|>`) –
> PHPStan i Pint je proto nutné spouštět na PHP 8.5 (jako v CI), na 8.4 ho
> nelze ani naparsovat.

---

## Část B – Bezpečnostní audit (nálezy 1–5, vše opraveno)

| # | Závažnost | Oblast | Stav |
|---|-----------|--------|------|
| 1 | Střední | Upload fotek v diskusi – název/přípona z klienta + možné SVG/polyglot (stored XSS) | ✅ Opraveno |
| 2 | Střední / Nízká | Veřejné hlášení se ukládalo rovnou jako „schválené" | ✅ Opraveno |
| 3 | Nízká / Info | Magic-link token přihlašoval „prvního admina" + ořez `kod` na 32 znaků | ✅ Opraveno |
| 4 | Nízká | Možný DoS přes velikost generovaného PNG | ✅ Opraveno |
| 5 | Střední | Adminer ve veřejném webrootu s křehkou ochranou | ✅ Opraveno |

### 1. Upload fotek v diskusi – nebezpečné odvození názvu/přípony, riziko stored XSS přes SVG

**Soubor:** `DiskuseController`, `StorePrispevekRequest`

Původně se ukládaný soubor pojmenovával podle klientské přípony
(`getClientOriginalExtension()`) a pravidlo `image` připouštělo i SVG, které by
se na veřejném disku vykreslilo včetně JavaScriptu (stored XSS).

**Náprava:** fotky se ukládají pod náhodným server-side názvem (`->store()`),
přípona se odvozuje z obsahu; pravidlo nahrazeno `mimes:jpeg,png,gif,webp`
(SVG vyloučeno). Ověřeno v `DiskuseController`, `StorePrispevekRequest`.

### 2. Veřejně „schválený" výsledkový řádek s libovolnými body

**Soubor:** `HlaseniController::store`

Nepřihlášený uživatel mohl do aktivního kola vložit záznam s libovolnou
značkou a body, který se ihned zobrazil jako převzatý (`approved=true`) –
riziko podvržení/narušení integrity výsledků.

**Náprava:** veřejné (neEDI i EDI) hlášení se ukládá jako `approved=false`
(stav „Čeká") a zobrazí se až po převzetí vyhodnocovatelem; jen administrátor
zakládá rovnou převzatý záznam. Ověřeno v `HlaseniController`.

### 3. Magic-link token přihlašoval „prvního admina"

**Soubor:** `AuthController`, migrace `vkvpa_prihlaseni`

Jednorázový kód nebyl svázán s konkrétním uživatelem (přihlásil vždy prvního
admina – ztráta auditní stopy). Latentně byl sloupec `kod` typu `varchar(32)`,
ač se ukládá SHA-256 (64 znaků) → v MySQL by se hash ořezával.

**Náprava:** přidán `vkvpa_prihlaseni.user_id`; token se váže na konkrétního
administrátora (s ověřením práv a zpětnou kompatibilitou), konzumace
v transakci s `lockForUpdate`. Sloupec `kod` rozšířen na `varchar(64)`.
Kryptografie OK: `Str::password(32)` (CSPRNG), SHA-256 hash, jednorázové,
TTL 5 dní, rate limit 10/min.

### 4. Možný DoS přes velikost generovaného PNG

**Soubor:** `MailImageController`

Šířka generovaného PNG se odvozovala z délky (base64) vstupu – velmi dlouhý
vstup mohl vytvořit nečekaně široký obrázek a spotřebovat paměť/CPU.

**Náprava:** délka textu pro generovaný PNG omezena na 100 znaků. Navíc se
renderují jen texty z `vkvpa.mail_image_allowlist`, jinak 404.

### 5. Adminer (DB admin nástroj) ve veřejném webrootu

**Soubory:** `public/adminer/*`, `public/adminer/.htaccess`, `docker/entrypoint.sh`

Adminer (plná správa databáze) je ve veřejném webrootu. Původní ochrana byla
křehká (Apache 2.2 syntaxe `Order/Deny/Allow` na Apache 2.4, chybějící
`.htpasswd`).

**Náprava (rozhodnutí provozovatele: Adminer ponechat + provisioning):**
- `.htaccess` přepsán do syntaxe Apache 2.4 (`mod_authz_user`), bránou je
  HTTP Basic auth (`Require valid-user`).
- `docker/entrypoint.sh` vygeneruje `/etc/apache2/adminer.htpasswd` (bcrypt)
  z `ADMINER_AUTH_USER` / `ADMINER_AUTH_PASSWORD`; heslo se neukládá do image
  ani repozitáře a soubor leží mimo webroot. Bez proměnných `.htpasswd`
  nevznikne a Basic auth selže (fail-closed).
- Cílové Docker/Apache nasazení má `AllowOverride All`, takže `.htaccess` se
  uplatní.

**Zbytkové riziko:** `.htaccess` je ignorován pod nginx a vývojovým
`php artisan serve` – tam Adminer neprovozuj na nedůvěryhodné síti. Silnější
alternativou (neaplikováno na přání provozovatele) je Adminer z repozitáře
úplně vyřadit a DB spravovat přes `ssh -L` tunel.

---

## Co je řešeno dobře (pozitivní zjištění)

- **CSRF:** výchozí web middleware skupina + `@csrf` ve všech formulářích; API
  je stateless, jen GET.
- **CSP:** nonce-based, `script-src` bez `'unsafe-inline'`; každý inline
  `<script>` nese `@cspNonce`, žádné inline event handlery.
- **Bezpečnostní hlavičky:** `SecurityHeaders` nastavuje CSP (object-src
  'none', base-uri 'self', frame-ancestors 'self'), X-Content-Type-Options,
  X-Frame-Options, Referrer-Policy, Permissions-Policy a HSTS přes HTTPS.
- **Session:** v produkci `AppServiceProvider::boot()` tvrdě vynucuje
  `SESSION_SECURE_COOKIE=true` a `SESSION_ENCRYPT=true` (jinak výjimka při
  bootu); `http_only` true, `same_site=lax`, serializace `json`.
- **Cache:** `serializable_classes => false` – z cache se nikdy nedeserializují
  objekty (žádný gadget-chain), kešují se jen pole/skaláry.
- **SQL injection:** veškerý přístup přes Eloquent/Query Builder s vázanými
  parametry; jediné `selectRaw`/`DB::raw` obsahují pouze statické řetězce.
- **XSS:** uživatelský vstup v Blade escapován přes `{{ }}`, data do JS přes
  `@json`/`@js`; výskyty `{!! !!}` nesou jen překladové/konfigurační řetězce.
- **Autorizace:** `EnsureAdmin` chrání `/admin/*`; editace cizího hlášení přes
  `?id` je vyhrazena adminovi, běžný uživatel pracuje jen s vlastním řádkem ze
  session (IDOR ošetřen). Admin akce nad záznamem jsou logované s identitou.
- **Upload EDI / ZIP import:** kontrola nekomprimované velikosti (anti
  zip-bomb), whitelist přípon, obsah se čte do paměti (žádná extrakce na disk →
  bez zip-slip), limit počtu souborů. EDI se servíruje jako
  `text/plain; charset=utf-8` se sanitizovaným filename.
- **Rate limiting:** login 5/min, login-token 10/min, edi-upload 10/min,
  diskuse 5/min, hlaseni 15/min, api 60/min.
- **CORS:** omezeno na `api/*`, jen `GET`, bez credentials.
- **Mass assignment:** explicitní `#[Fillable]`; `password`/`remember_token`
  jsou `#[Hidden]`.
- **Tajemství:** žádné natvrdo zapsané klíče/hesla; `.env` necommitován,
  admin se zakládá z `ADMIN_USER`/`ADMIN_PASS`.
- **Nebezpečné funkce:** žádný výskyt `eval/exec/system/shell_exec/unserialize`
  v `app/`.

---

## Poznámky / doporučení do budoucna

- **Mass assignment `is_admin`:** `User` má `is_admin` ve `#[Fillable]`.
  Aktuálně neexploatovatelné – neexistuje veřejná registrace ani endpoint
  plnící `User` z požadavku (vytváří se jen seederem). Pokud přibude veřejná
  správa uživatelů, odebrat `is_admin` z `Fillable` a nastavovat ho explicitně.
- **Údaje závodníků (`/admin/uzivatele`):** stránka zobrazuje citlivá osobní
  data (jméno, e-mail, telefon). Je za `admin` middleware; do dokumentace se
  publikují jen smyšlená demonstrační data.
- **Adminer:** viz zbytkové riziko u nálezu #5.
