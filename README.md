# VKV Provozní aktiv

Webový systém pro správu a vyhodnocování závodů v pásmu VKV (Very High Frequency) pro radioamatéry. Umožňuje registraci soutěžních deníků ve formátu EDI, automatické vyhodnocení, zobrazení výsledků a mapové vizualizace spojení.

> **Jazyk domény:** česky/slovensky. Jména tras, databázové sloupce a terminologie jsou v češtině/slovenštině.

---

## Obsah

- [Technologie](#technologie)
- [Funkce](#funkce)
- [Architektura](#architektura)
- [Instalace](#instalace)
- [Docker](#docker)
- [Konfigurace prostředí](#konfigurace-prostředí)
- [Vývoj](#vývoj)
- [Testy a kvalita kódu](#testy-a-kvalita-kódu)
- [Databáze](#databáze)
- [Routování](#routování)
- [Autentizace](#autentizace)
- [EDI pipeline](#edi-pipeline)
- [Bodování](#bodování)
- [Mapové pohledy](#mapové-pohledy)
- [Emaily](#emaily)
- [CI/CD](#cicd)

---

## Technologie

| Vrstva | Technologie |
|--------|-------------|
| Backend | PHP 8.5, Laravel 13 |
| Frontend | Blade, Tailwind CSS 4.3, Vite 8 |
| Databáze | MySQL 8.0 (s `ALLOW_INVALID_DATES`) |
| Fronty | Laravel Queue (database driver) |
| Kontejnerizace | Docker + Docker Compose |
| Testy | PHPUnit 12 / Pest |
| Statická analýza | PHPStan level 9 (Larastan) |
| Code style | Laravel Pint |
| Správa DB | Adminer (HTTP Basic Auth) |

---

## Funkce

- **Registrace deníků** – formulář pro odeslání závodního deníku (volací znak, kategorie, kolo)
- **EDI import** – upload a parsování `.edi` souborů (REF 01 formát), podpora Windows-1250 kódování
- **Automatické bodování** – výpočet skóre (`pocet × nasobice`) dle závodních pravidel
- **Výsledkové listiny** – přehled výsledků dle kola, kategorie a volacího znaku; vyhledávání
- **Roční výsledky** – kumulativní skóre přes všechna kola v roce
- **Mapové vizualizace** – tři typy Leaflet map pro každý deník:
  - **Ježek** – čáry z domácí stanice na všechna pracovaná QSO
  - **Špendlíky** – pin na každé QSO s vzdáleností a azimutem
  - **Lokátory** – velké čtverce (4-znakový Maidenhead) s počtem QSO
- **Admin rozhraní** – uzavření kola, schválení/smazání záznamu, EDI debug, správa kategorií
- **EDI debug** – analýza bodování bez uložení (pro adminy)
- **Emailové notifikace** – potvrzení závodníkovi + notifikace rozhodcům
- **Token přihlášení** – jednorázový odkaz s platností 5 dní

---

## Architektura

### EDI import pipeline

```
EDI soubor (Windows-1250 nebo UTF-8)
        │
        ▼
EdiParser::parse(string) ──► EdiLog (EdiHeader + EdiQso[] + raw)
        │
        ▼
EdiImportService::import(EdiLog) ──► edihead + edilines (transakce)
        │
        ▼
ScoringService::scoreEdi(Edihead) ──► EdiScore (pocet × nasobice = body)
        │
        ▼
EdiController::store() ──► VkvpaData row + EDI_ID
```

### Dvě databázové schémata

**Legacy schéma** (`edihead`, `edilines`): zachováno z původního systému. Sloupce mají nestandardní PHP identifikátory (`Mode-code`, `Received-WWL`, `Sent QSO number` apod.). Přistupujte k nim přes `$line->{'Received-WWL'}`. PHPStan má `property.notFound` potlačeno pro soubory, které tyto sloupce používají. Oba modely mají `#[WithoutTimestamps]` (vlastní časové sloupce `stamp`, `d_cas`).

**Aplikační schéma** (`vkvpa_*`): `VkvpaData` (závodní záznamy/výsledky), `VkvpaKola` (kola závodu), `VkvpaKategorie` (kategorie), `VkvpaPrihlaseni` (přihlašovací tokeny), `VkvpaDiskuse` (diskuze), `VkvpaConfig` (konfigurace key-value).

### Adresářová struktura

```
app/
├── Http/
│   ├── Controllers/        # Route handlery
│   └── Middleware/         # EnsureAdmin
├── Models/                 # Eloquent modely
├── Services/
│   ├── Edi/                # EdiParser, EdiImportService, EdiReducer, CategoryResolver
│   └── Scoring/            # ScoringService, EdiScoreDebugger, value objekty
├── Mail/                   # HlaseniPrijato, HlaseniProVyhodnocovatele
└── Support/                # Maidenhead, ContestWindow
config/
├── navigation.php          # Struktura menu (ne hard-coded v Blade)
└── vkvpa.php               # Doménová konfigurace (token_ttl_days aj.)
database/
├── migrations/
├── factories/
└── seeders/
resources/
├── css/app.css             # Tailwind 4 (@import 'tailwindcss', @theme)
├── js/app.js
└── views/
    ├── auth/               # Přihlašovací formulář
    ├── emails/             # Šablony emailů
    ├── layouts/app.blade.php
    ├── pages/              # Stránky (hlaseni, kola, vysledky, map, edi-upload, admin/*)
    └── partials/           # menu, footer, menu-item, no-active-period
```

---

## Instalace

### Požadavky

- PHP 8.5 s rozšířeními: `pdo`, `gd`, `mbstring`, `intl`
- Composer
- Node.js 20+
- MySQL 8.0

### Krok za krokem

```bash
# 1. Klonování repozitáře
git clone <repo-url>
cd vkvpa-new

# 2. Instalace závislostí, konfigurace, migrace a build assets
composer setup
```

Příkaz `composer setup` provede automaticky:
1. `composer install`
2. `cp .env.example .env`
3. `php artisan key:generate`
4. `php artisan migrate`
5. `npm ci && npm run build`

### Ruční postup

```bash
cp .env.example .env
# Upravte .env (DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD, ADMIN_PASS)

composer install
php artisan key:generate
php artisan migrate

npm ci
npm run build
```

---

## Docker

```bash
# Spuštění databáze a webového servera
docker compose up -d

# Inicializace projektu v kontejneru
docker compose exec web composer setup
```

> **Důležité:** V `.env` nastavte `DB_HOST=db` (ne `127.0.0.1`) pro komunikaci v rámci Docker sítě.

### Adminer

Adminer (webová správa databáze) je dostupný na `http://localhost:8080/adminer` a je chráněn HTTP Basic Auth.

Před spuštěním vytvořte soubor `.htpasswd` v kořenovém adresáři projektu:

```bash
# htpasswd je součástí balíčku apache2-utils / httpd-tools
htpasswd -c .htpasswd admin
```

> Soubor `.htpasswd` je v `.gitignore` – **nikdy ho necommitujte**.

### Docker services

| Service | Image | Port |
|---------|-------|------|
| `web` | vlastní Dockerfile | `8080:80` |
| `db` | `mysql:8.0` | `3306:3306` |

---

## Konfigurace prostředí

Klíčové proměnné v `.env`:

```env
APP_NAME="VKV Provozni Aktiv"
APP_URL=http://localhost:8080
APP_LOCALE=cs

# Databáze
DB_CONNECTION=mysql
DB_HOST=127.0.0.1        # docker: db
DB_PORT=3306
DB_DATABASE=digipa
DB_USERNAME=root
DB_PASSWORD=secret
DB_ROOT_PASSWORD=secret

# Admin účet (vytvoří se při composer setup)
ADMIN_USER=Beda
ADMIN_PASS=               # Povinné – heslo pro administrátorský účet

# Email
MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@vkvpa.cz"
CONTACT_MAIL=ok1vum@hamradio.cz

# Fronta
QUEUE_CONNECTION=database
```

---

## Vývoj

```bash
# Vývojový server (PHP + Vite HMR + queue worker + pail logy – vše souběžně)
composer dev

# Pouze frontend (Vite)
npm run dev

# Produkční build frontendu
npm run build
```

`composer dev` spouští souběžně:
- `php artisan serve`
- `php artisan queue:listen`
- `php artisan pail` (log viewer)
- `vite`

---

## Testy a kvalita kódu

```bash
# Spuštění všech testů (SQLite in-memory, není potřeba běžící databáze)
composer test

# Spuštění konkrétního testu
php artisan test --filter EdiParserTest
php artisan test tests/Unit/EdiParserTest.php

# PHPStan – statická analýza (level 9)
composer stan

# Pint – kontrola code style (bez zápisů)
composer lint

# Pint – automatická oprava code style
./vendor/bin/pint
```

Testy využívají `DB_CONNECTION=sqlite` a `DB_DATABASE=:memory:` – není potřeba MySQL.

Skutečné EDI soubory pro testy (fixture) jsou v `resources/edi/` a využívají se v unit testech.

---

## Databáze

### Migrace

| Soubor | Účel |
|--------|------|
| `create_cache_table` | Laravel cache |
| `create_jobs_table` | Laravel queue jobs |
| `create_edihead_table` | Hlavičky EDI logů (legacy schéma) |
| `create_edilines_table` | QSO záznamy (legacy schéma) |
| `create_prefixes_table` | Mapování prefixů na země (DXCC) |
| `create_vkvpa_config_table` | Key-value konfigurace |
| `create_vkvpa_data_table` | Závodní záznamy / výsledky |
| `create_vkvpa_diskuse_table` | Diskuzní příspěvky ke kolům |
| `create_vkvpa_kategorie_table` | Kategorie závodů |
| `create_vkvpa_kola_table` | Kola závodu |
| `create_vkvpa_prihlaseni_table` | Dočasné přihlašovací tokeny |
| `create_users_table` | Admin uživatelé |
| `add_missing_indexes` | Výkonnostní indexy |
| `fix_database_integrity` | Opravy integrity |

### Modely a vztahy

```
VkvpaKola ──► VkvpaData ◄── VkvpaKategorie
                │
                └──► Edihead ──► Ediline[]
```

| Model | Klíčové vztahy a atributy |
|-------|---------------------------|
| `VkvpaData` | `belongsTo(VkvpaKola, VkvpaKategorie, Edihead)` |
| `Edihead` | `hasMany(Ediline)`, `#[WithoutTimestamps]` |
| `Ediline` | `belongsTo(Edihead)`, nestandardní názvy sloupců, `#[WithoutTimestamps]` |
| `VkvpaKola` | `hasMany(VkvpaData, VkvpaDiskuse)`, `isActive()`, scope `active()` |
| `VkvpaKategorie` | `hasMany(VkvpaData)` |
| `VkvpaConfig` | statické `get(key, default)` a `put(key, value)` |
| `VkvpaPrihlaseni` | tokeny s TTL = `vkvpa.token_ttl_days` (výchozí: 5 dní) |
| `User` | přihlašování přes `name` (ne email), `is_admin` boolean |

---

## Routování

### Veřejné trasy

| Metoda | URI | Controller | Název |
|--------|-----|------------|-------|
| GET | `/` | `HlaseniController@index` | `edit_hlaseni` |
| GET | `/kola` | `KolaController@index` | `edit_kola` |
| GET | `/hlaseni` | `HlaseniController@index` | `hlaseni.index` |
| POST | `/hlaseni` | `HlaseniController@store` | `hlaseni.store` |
| GET | `/vysledky` | `VysledkyController@listina` | `vysledkova_listina` |
| GET | `/vysledky/rocni` | `VysledkyController@rocni` | `rocni_vysledky` |
| GET | `/edi` | `EdiController@create` | `read_edi` |
| POST | `/edi` | `EdiController@store` | `read_edi.store` |
| GET | `/edi/{head}/soubor` | `EdiController@zobrazit` | `edi.soubor` |
| GET | `/edi/{head}/soubor-redukovany` | `EdiController@zobrazitRedukovany` | `edi.soubor.redukovany` |
| GET | `/edi/{head}/mapa/jezek` | `MapController@jezek` | `edi.mapa.jezek` |
| GET | `/edi/{head}/mapa/spendliky` | `MapController@spendliky` | `edi.mapa.spendliky` |
| GET | `/edi/{head}/mapa/lokatory` | `MapController@lokatory` | `edi.mapa.lokatory` |
| GET | `/login` | `AuthController` | `login` |
| GET | `/login/token/{kod}` | token login | – |
| GET | `/mail-image` | `MailImageController@show` | `mail.image` |

### Admin trasy (middleware: `admin`)

| Metoda | URI | Controller | Název |
|--------|-----|------------|-------|
| POST | `/admin/kolo/{kolo}/vyhodnotit` | `VyhodnoceniController@vyhodnotit` | `kolo.vyhodnotit` |
| POST | `/admin/kolo/{kolo}/uzavrit` | `VyhodnoceniController@uzavrit` | `kolo.uzavrit` |
| POST | `/admin/zaznam/{zaznam}/prevzit` | `ZaznamController@prevzit` | `zaznam.prevzit` |
| POST | `/admin/zaznam/{zaznam}/smazat` | `ZaznamController@smazat` | `zaznam.smazat` |
| GET/POST | `/admin/edi-debug` | `EdiDebugController` | `edit_edi_debug` |
| GET | `/admin/deniky` | `DenikyController@index` | `edit_deniky` |
| GET | `/admin/kategorie` | `KategorieController@index` | `edit_kategorie` |
| GET | `/admin/importy` | `ImportController@index` | `edit_import` |

---

## Autentizace

Přihlášení je session-based se dvěma způsoby vstupu:

1. **Standardní formulář** na `/login` – přihlášení přes `name` + heslo
2. **Token login** na `/login/token/{kod}` – jednorázový alfanumerický kód s TTL 5 dní (konfigurovatelné přes `vkvpa.token_ttl_days`)

Admin trasy jsou chráněny middleware `EnsureAdmin` (`middleware('admin')`). Admin práva se řídí atributem `User::is_admin` (boolean).

Struktura navigačního menu je deklarována v `config/navigation.php` – pro veřejné (`public`) i admin (`admin`) sekce zvlášť.

---

## EDI pipeline

### Formát souborů

Systém podporuje EDI soubory ve formátu REF 01 (standardní závodní log pro radioamatéry). Soubory mohou být v kódování Windows-1250; `EdiParser` je automaticky převádí přes `iconv` před zpracováním.

Vzorové EDI soubory jsou v `resources/edi/` a slouží jako fixture pro unit testy.

### Klíčové třídy

| Třída | Soubor | Odpovědnost |
|-------|--------|-------------|
| `EdiParser` | `app/Services/Edi/EdiParser.php` | Parsování EDI textu → `EdiLog` (value object) |
| `EdiImportService` | `app/Services/Edi/EdiImportService.php` | Uložení `EdiLog` → `edihead` + `edilines` v transakci |
| `EdiReducer` | `app/Services/Edi/EdiReducer.php` | Filtrování EDI na závodní okno (08:00–11:00 UTC) |
| `CategoryResolver` | `app/Services/Edi/CategoryResolver.php` | Určení kategorie z hlavičky (pásmo + sekce + DX) |

### Value objekty

- **`EdiLog`** – kompletní parsed log (header + QSO[] + raw source)
- **`EdiHeader`** – hlavičková data (callsign, WWL, pásmo, sekce, datum)
- **`EdiQso`** – jedno QSO spojení

### Rozlišení kategorií

`CategoryResolver::resolve(pcall, pBand, pSect)` určuje kategorii:

- **Pásmo** – aliasy: `144`/`145 MHz → "144"`, `432`/`435 MHz → "432"`, atd.
- **Sekce** – `MO` (multi operátor), `SO` (single), nebo `null`
- **DX varianta** – pokud prefix není `OK`/`OL`
- Pokud pásmo není rozpoznáno → `UnknownBandException`

---

## Bodování

### Vzorec

```
body = pocet × nasobice
```

- **`pocet`** – počet QSO v závodním okně (08:00–11:00 UTC) mimo vlastní velký čtverec (první 4 znaky `PWWLo`)
- **`nasobice`** – počet unikátních cizích velkých čtverců + 1

Konstanta `NON_EDI_NULLIFY_FROM_KOLO = 91`: záznamy bez EDI souboru se v ročních výsledcích počítají jako 0 bodů pro kola ≥ 91.

### Pořadí

`ScoringService::rankRound()` přiřazuje husté pořadí (dense rank) v rámci každé kategorie daného kola – závodníci se stejným skóre dostanou stejné pořadí.

---

## Mapové pohledy

Tři Leaflet-based mapové pohledy pro každý `Edihead`:

| Trasa | Popis |
|-------|-------|
| `/edi/{head}/mapa/jezek` | Čáry z domácí stanice na všechna pracovaná QSO |
| `/edi/{head}/mapa/spendliky` | Pin na každé QSO, popup s vzdáleností a azimutem |
| `/edi/{head}/mapa/lokatory` | Velké čtverce (4-znakový Maidenhead) s počtem QSO |

Podpůrná třída `Maidenhead` zajišťuje převod lokátor ↔ lat/lon, výpočet vzdálenosti (Haversine) a azimutu (stupně).

---

## Emaily

Dvě `Mailable` třídy:

| Třída | Příjemce | Účel |
|-------|----------|------|
| `HlaseniPrijato` | závodník | potvrzení přijetí deníku |
| `HlaseniProVyhodnocovatele` | rozhodci závodů | notifikace o novém záznamu |

Emailová adresa v patičce stránek je renderována jako PNG obrázek (`MailImageController` přes GD) – ochrana proti scrapingu.

Konfigurace odesílatele: `MAIL_FROM_ADDRESS` a `CONTACT_MAIL` v `.env`.

---

## CI/CD

GitHub Actions workflow (`.github/workflows/ci.yml`) – job `quality`:

| Krok | Příkaz |
|------|--------|
| Testy | `composer test` |
| Statická analýza | `composer stan` |
| Code style | `composer lint` |
| Frontend build | `npm ci && npm run build` |

Běží na `ubuntu-latest`, PHP 8.5, Node 22. Timeout 15 minut.

---

## Licence

Interní projekt. Všechna práva vyhrazena.
