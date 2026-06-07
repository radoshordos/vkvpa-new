# Bezpečnostní audit – VKV Provozní aktiv

**Datum:** 2026-06-07
**Rozsah:** celá codebase (Laravel 13 / PHP 8.4), se zaměřením na autentizaci,
autorizaci, zpracování vstupu, upload souborů, SQL, XSS, CSRF a konfiguraci.
**Metoda:** statická analýza zdrojového kódu (controllery, modely, middleware,
služby, Blade šablony, konfigurace).

---

## Shrnutí

Aplikace je z bezpečnostního hlediska na velmi slušné úrovni – používá Eloquent
s vázanými parametry, výchozí escapování v Blade, CSRF ochranu, bezpečnostní
hlavičky, rate limiting a v produkci vynucuje šifrovanou a `Secure` session.
Nebyla nalezena žádná kritická zranitelnost (RCE, SQLi, auth bypass).

Identifikovány byly **3 nálezy** (2× střední, 1× nízká) a několik
informativních poznámek. Detaily níže.

| # | Závažnost | Oblast | Soubor | Stav |
|---|-----------|--------|--------|------|
| 1 | Střední | Upload souboru – název/přípona + možné SVG/polyglot (stored XSS) | `DiskuseController`, `StorePrispevekRequest` | ✅ Opraveno |
| 2 | Střední / Nízká | Veřejné hlášení se ukládalo rovnou jako „schválené" | `HlaseniController::store` | ✅ Opraveno |
| 3 | Nízká / Info | Magic-link token přihlašoval „prvního admina" + ořez `kod` na 32 znaků | `AuthController`, migrace | ✅ Opraveno |
| 4 | Nízká | Možný DoS přes velikost generovaného PNG | `MailImageController` | ✅ Opraveno |

## Stav nápravy (2026-06-07)

Všechny čtyři nálezy byly opraveny ve stejné větvi. Ověřeno: `composer test`
(236 testů zelených), PHPStan level 10 bez chyb, Pint bez výtek.

- **#1** – fotky v diskusi se ukládají pod náhodným server-side názvem
  (`->store()`), přípona se odvozuje z obsahu, nikoli z klienta; pravidlo
  `image` nahrazeno `mimes:jpeg,png,gif,webp` (SVG vyloučeno).
- **#2** – veřejné (neEDI i EDI) hlášení se ukládá jako `schvaleno=false`
  („Čeká") a zobrazí se až po převzetí vyhodnocovatelem; jen administrátor
  zakládá rovnou převzatý záznam.
- **#3** – přidán sloupec `vkvpa_prihlaseni.user_id`; token se váže na
  konkrétního administrátora a přihlásí právě jeho (s ověřením práv a
  zpětnou kompatibilitou pro starší tokeny). Při té příležitosti opraven
  i latentní defekt: sloupec `kod` byl `varchar(32)`, ač ukládáme SHA-256
  (64 znaků) → v MySQL by se hash ořezával; rozšířeno na `varchar(64)`.
- **#4** – délka textu pro generovaný PNG omezena na 100 znaků.

---

## Nálezy

### 1. [Střední] Upload fotek v diskusi – nebezpečné odvození názvu/přípony, riziko stored XSS přes SVG

**Soubor:** `app/Http/Controllers/DiskuseController.php:50-53`,
`app/Http/Requests/StorePrispevekRequest.php:31,34`

```php
$nazev = time().'_'.$request->string('znacka')->value().'.'.$file->getClientOriginalExtension();
$foto  = $file->storeAs('diskuse/'.$kolo->id, $nazev, 'public');
```

Problémy:

- **Přípona z klienta:** název ukládaného souboru používá
  `getClientOriginalExtension()` (plně řízeno útočníkem) místo přípony
  odvozené ze skutečného obsahu.
- **`znacka` smí obsahovat `/`:** validační regex `^[A-Z0-9\/]+$`
  (`StorePrispevekRequest.php:31`) povoluje lomítko, které se promítne do
  cesty `storeAs()` a vytvoří podadresáře (omezené, `..`/`.` nelze, ale
  nečekané chování názvu).
- **SVG / polyglot:** pravidlo `image` (`StorePrispevekRequest.php:34`)
  obvykle akceptuje i SVG. Uložené SVG na **veřejném** disku se při přímém
  otevření URL (`Storage::url()`, viz `diskuse.blade.php:67`) vykreslí
  v prohlížeči **včetně JavaScriptu** → uložené (stored) XSS. Stejně tak
  GIF/PHP polyglot uložený s příponou odvozenou od klienta může být riziko,
  pokud by veřejné úložiště servírovalo PHP.

**Doporučení:**

1. Generovat **náhodný server-side název** a příponu odvozovat z detekovaného
   MIME, ne z klienta:
   ```php
   $foto = $file->store('diskuse/'.$kolo->id, 'public'); // náhodný hash název
   ```
2. Omezit typy a vyloučit SVG:
   `'foto' => ['nullable', 'mimes:jpeg,png,gif,webp', 'max:4096']`.
3. Pokud má `znacka` zůstat součástí názvu, odstranit z ní `/` před použitím
   ve filename (pro vlastní data v DB lomítko ponechat lze).

---

### 2. [Střední / Nízká] Nepřihlášený uživatel může vytvořit veřejně „schválený" výsledkový řádek s libovolnými body

**Soubor:** `app/Http/Controllers/HlaseniController.php:54-82`

Při zakládání nového záznamu (`id_zaznamu = 0`, `StoreHlaseniRequest::authorize()`
vrací `true`) se ukládá:

```php
'body'      => $this->intFrom($v['body'] ?? 0),
'pocet'     => ...,
'schvaleno' => true,
```

Tj. kdokoli (bez přihlášení) může do **aktivního** kola vložit záznam
s libovolnou značkou a libovolným počtem bodů, který se ihned zobrazí ve
veřejné výsledkové listině jako **převzatý** (`schvaleno=true`). Jde o riziko
spoofingu / narušení integrity výsledků.

Zmírňující okolnosti: omezeno na aktivní kola (`StoreHlaseniRequest::withValidator`),
rate limit 15/min a možnost admina záznam smazat. U EDI deníků se body navíc
přepočítávají při vyhodnocení (`ScoringService`).

**Doporučení:** nové **manuální** záznamy zakládat jako `schvaleno=false`
(stav „Čeká" na převzetí vyhodnocovatelem), případně přidat CAPTCHA na
veřejný formulář. Tím se zachová tok „závodník nahlásí → vyhodnocovatel
převezme".

---

### 3. [Nízká / Info] Magic-link token přihlašuje jako „první admin", neváže se na konkrétního uživatele

**Soubor:** `app/Http/Controllers/Auth/AuthController.php:85`

```php
$admin = User::query()->where('is_admin', true)->first();
Auth::login($admin);
```

Jednorázový kód není svázán s konkrétním uživatelem – přihlásí vždy prvního
admina. Při více administrátorech token nerozlišuje identitu (ztráta
auditní stopy „kdo se přihlásil").

Z kryptografického hlediska je mechanismus **v pořádku**: kód
`Str::password(32, …)` (CSPRNG, 32 znaků), uložen jako `sha256` hash
(`SendEdiMailsListener.php:36-37`), jednorázový s `lockForUpdate` v transakci,
TTL 5 dní, rate limit 10/min.

**Doporučení:** pokud má systém více adminů, vázat token na `user_id`. Pro
jednoho admina je stav akceptovatelný.

---

### 4. [Nízká] Možný DoS přes velikost generovaného PNG

**Soubor:** `app/Http/Controllers/MailImageController.php:23`

```php
$width = max(1, strlen($text) * 12);
$im = imagecreate($width, 16);
```

`text` přichází jako base64 query parametr; po dekódování a filtraci na
tisknutelné ASCII se z délky odvozuje šířka obrázku. Velmi dlouhý vstup
(až do limitu délky URL serveru) může vytvořit nečekaně široký obrázek a
spotřebovat paměť/CPU.

**Doporučení:** omezit délku textu, např. `$text = substr($text, 0, 100);`
před výpočtem šířky.

---

## Co je vyřešeno dobře (pozitivní zjištění)

- **CSRF:** výchozí web middleware skupina + `@csrf` ve všech formulářích;
  API je stateless, jen GET.
- **Bezpečnostní hlavičky:** `SecurityHeaders` middleware nastavuje
  `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`,
  `Permissions-Policy` a HSTS přes HTTPS.
- **Session:** v produkci `AppServiceProvider::boot()` tvrdě vynucuje
  `SESSION_SECURE_COOKIE=true` a `SESSION_ENCRYPT=true`; `http_only`
  výchozí true; `same_site=lax`; serializace `json` (žádný gadget-chain
  přes `unserialize`).
- **SQL injection:** veškerý přístup přes Eloquent/Query Builder s vázanými
  parametry. `selectRaw`/`DB::raw` (`ScoringService.php`, `DashboardController.php`)
  obsahují pouze statické řetězce. Vyhledávání používá `like` s parametrem.
- **XSS:** uživatelský vstup v Blade je escapován přes `{{ }}`; data do JS
  jdou přes `@json` / `@js` (escapuje `</script>`). Výskyty `{!! !!}`
  (`deniky.blade.php`, `importy.blade.php`) obsahují jen překladové řetězce,
  ne uživatelský vstup.
- **EDI soubory:** servírovány jako `text/plain; charset=utf-8` inline,
  filename je sanitizovaný (`preg_replace('/[^A-Za-z0-9\-]/', …)`) → žádná
  HTTP header injection ani spuštění HTML. Parser používá striktní regex a
  `iconv`, žádné nebezpečné funkce.
- **ZIP import (admin):** kontrola **nekomprimované** velikosti každého
  souboru (anti zip-bomb), limit počtu souborů, obsah se čte do paměti –
  žádná extrakce na disk → žádný zip-slip.
- **Autorizace:** `EnsureAdmin` middleware chrání `/admin/*`; editace cizího
  hlášení přes `?id` je vyhrazena adminovi, běžný uživatel pracuje jen
  s vlastním řádkem ze session (`HlaseniController::index:26`,
  `StoreHlaseniRequest::authorize`). Komentář explicitně upozorňuje na únik
  PII bez tohoto omezení.
- **Rate limiting:** definováno pro `login` (5/min), `login-token` (10/min),
  `edi-upload` (10/min), `diskuse` (5/min), `hlaseni` (15/min), `api` (60/min).
- **Mass assignment:** explicitní `#[Fillable]` na modelech; `password` a
  `remember_token` jsou `#[Hidden]`.
- **Nebezpečné funkce:** žádný výskyt `eval/exec/system/shell_exec/unserialize`
  v `app/`.
- **Debug:** `APP_DEBUG=false` výchozí; `.env.example` upozorňuje na rotaci
  hesel a necommitování `.env`.

---

## Poznámka k mass assignment `is_admin`

`User` má `is_admin` ve `#[Fillable]` (`app/Models/User.php:18-23`). Aktuálně
to **není** zneužitelné – neexistuje veřejná registrace ani endpoint, který by
`User` plnil z požadavku (vytváří se jen seederem). Pokud by v budoucnu
přibyla veřejná správa uživatelů, odebrat `is_admin` z `Fillable` a nastavovat
ho explicitně.
