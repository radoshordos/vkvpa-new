# Bezpečnostní audit – VKV Provozní aktiv (2. kolo)

**Datum:** 2026-06-08
**Rozsah:** celá codebase (Laravel 13 / PHP 8.4), navazuje na audit z 2026-06-07
(`SECURITY_AUDIT.md`). Zaměření: autentizace, autorizace, zpracování vstupu,
upload souborů, SQL, XSS, CSRF, konfigurace, deployment a regrese ze změn po
prvním auditu (Blade komponenty, extrakce Form Requestů).
**Metoda:** statická analýza zdrojového kódu + kontrola konfigurace nasazení
(Docker/Apache, `.htaccess`, CSP, session, rate limiting).

---

## Shrnutí

Aplikační vrstva je nadále na velmi dobré úrovni. **Všechny čtyři nálezy
z prvního auditu zůstávají opravené** a v kódu přibylých od té doby (Blade
komponenty `badge`/`form-errors`/`icon`, přesun inline validace do Form
Requestů) **nebyla nalezena žádná regrese**.

Tento audit přináší **1 nový nález** (střední, dosud nezdokumentovaný):
databázový nástroj **Adminer** nasazený ve veřejném webrootu s křehkou
ochranou. Ostatní zjištění jsou informativní.

| # | Závažnost | Oblast | Soubor | Stav |
|---|-----------|--------|--------|------|
| 5 | Střední | Adminer ve veřejném webrootu; ochrana závislá na Apache 2.2 syntaxi a chybějícím `.htpasswd` | `public/adminer/*`, `public/adminer/.htaccess` | ✅ Opraveno (Basic auth + provisioning `.htpasswd`) |

---

## Nález

### 5. [Střední] Adminer (DB admin nástroj) ve veřejném webrootu s křehkou ochranou

**Soubory:** `public/adminer/adminer.php` (503 kB engine), `public/adminer/index.php`,
`public/adminer/.htaccess`

Do repozitáře a veřejného webrootu (`public/`) je zakomitovaný kompletní
**Adminer** – jednosouborový nástroj pro plnou správu databáze. Přístup k němu
měl chránit `.htaccess`. Původní podoba ochrany byla křehká:

```apache
Require valid-user
<IfModule mod_authz_host.c>
    Order Deny,Allow
    Deny from all
    Allow from 127.0.0.1
    Satisfy All
</IfModule>
```

Problémy:

- **Zastaralá syntaxe Apache 2.2** (`Order/Deny/Allow`, `Satisfy`) běží na
  serveru **Apache 2.4** (`Dockerfile`: `php:8.5-apache`). Tyto direktivy
  patří do `mod_access_compat`; pokud modul není načten, Apache buď spadne na
  chybu konfigurace, nebo se host-omezení neuplatní.
- **Chybějící `.htpasswd`:** `.htaccess` odkazuje na `/var/www/html/.htpasswd`,
  který `Dockerfile` nevytváří. Basic auth je tak fakticky nefunkční (fail-closed).
- **Ochrana platí jen na Apache s `AllowOverride All`.** Pod **nginx**, na Apache
  bez `AllowOverride`, nebo přes vývojový `php artisan serve` (oba `.htaccess`
  ignorují) by byl `adminer.php` plně dostupný a spustitelný **bez jakékoli
  autentizace**.
- **Vlastní útočná plocha:** Adminer má historii vlastních CVE (SSRF přes pole
  „server", odhalení souborů). Jeho přítomnost v aplikačním webrootu zvyšuje
  plochu nad rámec samotné aplikace.

Pozitivní okolnost: v cílovém Docker/Apache nasazení je `AllowOverride All`
zapnuto (`docker/vhost.conf`, `docker/apache/000-default.conf`), takže
`.htaccess` se uplatní.

**Provedené řešení (rozhodnutí provozovatele: ponechat Adminer + provisioning):**

1. `.htaccess` přepsán do syntaxe Apache 2.4 (`mod_authz_user`), bránou je
   **HTTP Basic auth** (`Require valid-user`). Volitelné zpřísnění na konkrétní
   IP je připraveno jako zakomentovaný `RequireAll` blok. (Tvrdé `Require local`
   se nepoužilo záměrně – za Dockerem s mapováním portů vidí Apache IP bridge,
   ne `127.0.0.1`, což by legitimní přístup zablokovalo.)
2. **Provisioning `.htpasswd` za běhu:** `docker/entrypoint.sh` vygeneruje
   `/etc/apache2/adminer.htpasswd` (bcrypt) z `ADMINER_AUTH_USER` /
   `ADMINER_AUTH_PASSWORD`. Heslo se neukládá do image ani repozitáře; soubor
   leží **mimo webroot i bind-mount**, takže ho nelze stáhnout přes HTTP. Bez
   nastavených proměnných `.htpasswd` nevznikne a Basic auth selže
   (fail-closed). Proměnné jsou zavedené v `docker-compose.yml` a `.env.example`.
3. Ověřeno, že nasazení běží na Apache s `AllowOverride All`
   (`docker/vhost.conf`, `docker/apache/000-default.conf`), takže `.htaccess`
   se uplatní.

**Zbytkové riziko / poznámky:**

- `.htaccess` je ignorován pod **nginx** a vývojovým **`php artisan serve`** –
  tam Adminer nenasazuj ani neprovozuj na nedůvěryhodné síti.
- Silnější alternativou (neaplikováno na přání provozovatele) by bylo Adminer
  z aplikačního repozitáře úplně vyřadit a DB spravovat přes `ssh -L` tunel.

---

## Ověření nápravy z 1. auditu (vše stále platí)

- **#1 Upload fotek (diskuse):** náhodný server-side název přes `->store()`,
  přípona z obsahu; pravidlo `mimes:jpeg,png,gif,webp` (SVG vyloučeno).
  Ověřeno v `DiskuseController:50-54`, `StorePrispevekRequest:36`.
- **#2 Veřejné hlášení:** ukládá se `schvaleno = (bool) is_admin` → veřejnost
  zakládá záznam ve stavu „Čeká". Ověřeno v `HlaseniController:79`.
- **#3 Magic-link token:** vázán na `user_id`, konzumace v transakci
  s `lockForUpdate`, ověření `is_admin`, SHA-256 hash. Ověřeno
  v `AuthController:58-107`.
- **#4 DoS přes PNG:** délka textu oříznuta na 100 znaků. Ověřeno
  v `MailImageController:23`.

## Regresní kontrola změn po 1. auditu

- **Blade komponenty:** `icon` používá `{!! $icon['p'] !!}` výhradně z interního
  registru ikon (klíč `name` volí vývojář), nikoli z uživatelského vstupu →
  bez XSS. `badge` i `form-errors` escapují obsah přes `{{ }}`.
- **Extrakce Form Requestů:** `StoreHlaseniRequest::authorize()` nadále váže
  editaci cizího záznamu na admina, resp. na `owned_data_id` ze session
  (IDOR ošetřen). Validační pravidla zachována.

## Co je nadále v pořádku (pozitivní zjištění)

- **SQL injection:** veškerý přístup přes Eloquent/Query Builder; jediné
  `DB::raw`/`selectRaw` (`DashboardController:45`, `ScoringService:184-185`)
  obsahují pouze statické řetězce a agregace, žádný uživatelský vstup.
- **XSS:** výchozí escapování v Blade; data do JS přes `@json`. Výskyty
  `{!! !!}` nesou jen překladové/konfigurační řetězce.
- **CSRF:** výchozí web middleware + `@csrf`; API je stateless GET.
- **Autorizace:** `EnsureAdmin` chrání `/admin/*`; admin akce nad záznamem
  (`ZaznamController`) jsou logované s identitou admina.
- **Hlavičky:** `SecurityHeaders` nastavuje CSP (object-src 'none', base-uri
  'self', frame-ancestors 'self'), X-Content-Type-Options, X-Frame-Options,
  Referrer-Policy, Permissions-Policy a HSTS přes HTTPS.
- **Session:** produkce tvrdě vynucuje `SESSION_SECURE_COOKIE` a
  `SESSION_ENCRYPT` (`AppServiceProvider::boot`); jinak výjimka při bootu.
- **Rate limiting:** login 5/min, login-token 10/min, edi-upload 10/min,
  diskuse 5/min, hlaseni 15/min, api 60/min.
- **Upload EDI / ZIP import:** kontrola velikosti, whitelist přípon, obsah
  čten do paměti (žádná extrakce na disk → bez zip-slip), limit počtu souborů.
- **CORS:** omezeno na `api/*`, jen `GET`, bez credentials.
- **Mass assignment:** explicitní `#[Fillable]`; `password`/`remember_token`
  jsou `#[Hidden]`. `is_admin` ve `Fillable` zůstává neexploatovatelné –
  neexistuje veřejná registrace ani endpoint plnící `User` z požadavku
  (viz poznámka v 1. auditu; doporučení trvá pro budoucí správu uživatelů).
- **Tajemství:** žádné natvrdo zapsané klíče/hesla v kódu; `.env` necommitován.
- **Nebezpečné funkce:** žádný `eval/exec/system/shell_exec/unserialize`
  v `app/`.
