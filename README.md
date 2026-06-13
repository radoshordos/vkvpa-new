# VKV Provozní aktiv

Webový systém pro správu a vyhodnocování závodů v pásmu VKV (Very High Frequency) pro radioamatéry. Umožňuje registraci soutěžních deníků ve formátu EDI, automatické vyhodnocení, zobrazení výsledků a mapové vizualizace spojení.

> **Jazyk domény:** česky. Jména tras, databázové sloupce a terminologie jsou v češtině.

---

## Obsah

- [Technologie](#technologie)
- [Funkce](#funkce)
- [Úvodní stránka](#úvodní-stránka)
- [Náhledy aplikace](#náhledy-aplikace)
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
- [EDI vizualizace](#edi-vizualizace)
- [Porovnání deníků](#porovnání-deníků)
- [Diskuse](#diskuse)
- [REST API](#rest-api)
- [Emaily](#emaily)
- [Plánované příkazy](#plánované-příkazy)
- [PWA](#pwa)
- [CI/CD](#cicd)

---

## Technologie

| Vrstva | Technologie |
|--------|-------------|
| Backend | PHP 8.5, Laravel 13 |
| Frontend | Blade, Tailwind CSS 4.3, Vite 8 |
| Reaktivní komponenty | Livewire 4 |
| Mapy | Leaflet 1.9.4 |
| Grafy | Chart.js 4.5 |
| Databáze | MySQL 8.0 (s `ALLOW_INVALID_DATES`) |
| Fronty | Laravel Queue (database driver) |
| Kontejnerizace | Docker + Docker Compose |
| Testy | PHPUnit 13 |
| Statická analýza | PHPStan level 10 (Larastan) |
| Code style | Laravel Pint |
| Správa DB | Adminer (HTTP Basic Auth) |
| Geo výpočty | mjaschen/phpgeo |

---

## Funkce

- **Úvodní stránka** – reaguje na životní cyklus závodního kola (viz [Úvodní stránka](#úvodní-stránka)): odpočet do startu / konce závodu / uzávěrky, živé výsledky, počítadlo přijatých hlášení, karta posledního vyhodnoceného kola, poslední příspěvky z diskuse, přehled příštích 3 kol
- **Registrace deníků** – formulář se záložkami: upload EDI souboru nebo ruční zadání (bez EDI)
- **EDI import** – reaktivní Livewire upload `.edi` souborů (REF 01 formát), bez překreslení stránky; podporuje Windows-1250 kódování
- **EDI validace** – kontrola kvality deníku (duplicitní volaná stanice, neplatné lokátory, QSO mimo závodní okno, neshoda deklarovaného a skutečného počtu); varování se zobrazí závodníkovi bez blokování importu
- **Automatické bodování** – výpočet skóre (`boduZaQso × nasobice`) dle závodních pravidel
- **Výsledkové listiny** – přehled výsledků dle kola, kategorie a volacího znaku; vyhledávání
- **Průběžné výsledky** – živá průběžná tabulka aktivního kola
- **Roční výsledky** – kumulativní skóre přes všechna kola v roce
- **Mapové vizualizace** – pět typů Leaflet map pro každý deník:
  - **Ježek** – čáry z domácí stanice na všechna pracovaná QSO
  - **Špendlíky** – pin na každé QSO s vzdáleností a azimutem
  - **Lokátory** – velké čtverce (4-znakový Maidenhead) s počtem QSO
  - **CRK mapa** – kombinovaný pohled ve stylu vkvzavody.crk.cz
  - **Přehrávání** – QSO se objevují chronologicky podle slideru 08:00–11:00 (tlačítko ▶)
- **EDI vizualizace** – komplexní analytická stránka na jedné URL (`/edi/{head}/vizualizace`):
  - Statistické karty – počet QSO, unique lokátory, max/průměr vzdálenost, tempo závodu (špička, pauza, průměr), souhrn po druzích provozu
  - Interaktivní Leaflet mapa s přepínatelnými vrstvami (CRK / ježek / špendlíky / lokátory / přehrávání), body rozlišeny barvou dle druhu provozu (**modrá = SSB**, **oranžová = CW**)
  - Průběh skóre – schodový Chart.js graf: kumulativní body za spojení × průběžný počet násobičů
  - Časová osa QSO s násobiči – skládaný Chart.js Bar chart, 15minutové intervaly v závodním okně 08:00–11:00 UTC, zvýrazněná QSO s novým násobičem
  - Vážená azimutová růžice – Chart.js PolarArea chart, 8 světových stran (45° sektory), přepínání vážení počet/km/body
  - Body podle čtverců – vodorovné sloupce: kolik bodů přinesl který velký čtverec
  - Celoroční trend – body a pořadí stanice ve všech kolech roku
  - Histogram vzdáleností – Chart.js Bar chart, pásma 0–50 / 50–100 / 100–200 / 200–400 / 400–700 / 700+ km
  - TOP ODX – tabulka 10 nejvzdálenějších spojení (km, azimut, čas, mód, body)
- **Porovnání deníků** – samostatná stránka hráč vs. hráč (`/edi/{head}/porovnani`): mapa rozdílů v protistanicích, překryvný graf průběhu skóre, tempo obou stanic a směrová růžice; jen deníky z téhož kola a kategorie, soupeřův deník až po uzávěrce kola
- **Diskuse** – komentáře k závodním kolům (throttle ochrana, moderace adminem)
- **Admin dashboard** – statistiky sezóny, trend účasti, distribuce kategorií, top 10 stanic
- **Admin rozhraní** – CRUD kol a kategorií, převzetí (schválení)/smazání záznamu (vyhodnocení kola probíhá automaticky), EDI debug, hromadný import
- **EDI debug** – analýza bodování bez uložení (pro adminy)
- **REST API** – veřejné JSON API s výsledky a OpenAPI/Swagger dokumentací
- **Přepínání jazyka** – čeština / angličtina (ukládáno do session)
- **Emailové notifikace** – potvrzení závodníkovi + notifikace rozhodcům (odesílání přes frontu)
- **Token přihlášení** – jednorázový odkaz s platností 5 dní
- **PWA** – instalovatelná webová aplikace (manifest + service worker s offline fallbackem)
- **Bezpečnostní hlavičky** – CSP, HSTS, X-Frame-Options, Permissions-Policy

---

## Úvodní stránka

Úvodní stránka (`/`, `HomeController`) je rozcestník pro závodníky a **reaguje na životní cyklus závodního kola**. Hero karta zobrazuje jedno kolo vybrané prioritou: **aktivní → nejbližší nadcházející → poslední proběhlé**. Stav kola (`KoloStav`) určuje obsah karty:

| Fáze | Kdy | Co úvodka zobrazuje |
|------|-----|---------------------|
| **Nadcházející** | přede dnem závodu | datum a čas startu, odpočet do startu (08:00 UTC), info o upload okně; pod hero kartou navíc **karta posledního vyhodnoceného kola** s odkazem na výsledkovou listinu |
| **Závod právě probíhá** | den závodu 08:00–11:00 UTC | odpočet do **konce závodu** (11:00 UTC), tlačítko pro odeslání deníku, živé výsledky, počítadlo přijatých hlášení |
| **Příjem hlášení** | po závodě až do uzávěrky | odpočet do **uzávěrky**, tlačítko pro odeslání deníku, živé průběžné výsledky (auto-refresh 60 s), počítadlo „zatím přijato N hlášení" |
| **Zpracování výsledků** | po uzávěrce, před vyhodnocením | poznámka o zpracování, živá tabulka výsledků |
| **Vyhodnocené** | po vyhodnocení | tlačítko na výsledkovou listinu |

„Závod právě probíhá" je **prezentační podstav** odvozený v `HomeController` (ne nový case v `KoloStav`): platí, když aktuální čas leží v závodním okně (`ContestWindow`, 08:00–11:00 UTC) dne `datum_konani` — funguje i tehdy, když cron kolo ještě formálně neaktivoval.

Další prvky nezávislé na fázi:

- **Diskuse** – tlačítko „Diskuse ke kolu" (s počtem příspěvků) v hero kartě + sekce **„Z diskuse"** s posledními 3 příspěvky napříč koly
- **Odpočet s auto-přepnutím** – po doběhnutí na nulu se stránka sama přenačte a zobrazí novou fázi (ochrana proti reload smyčce: reload se vyzbrojí jen pokud byl cíl při načtení v budoucnu)
- **Místní čas vedle UTC** – u startu závodu a uzávěrky JS doplní čas v časové zóně prohlížeče (`data-local-time`, skryto pokud je místní čas shodný s UTC; bez JS zůstává jen UTC)
- **Rychlé odkazy** – odeslání deníku, průběžné/výsledkové/roční výsledky
- **Plánovaná kola** – mini-kalendář příštích 3 kol

---

## Náhledy aplikace

### Životní cyklus závodního kola

![Životní cyklus závodního kola](docs/kolo-lifecycle.svg)

### Veřejná část

| Kola závodu | Nahrání EDI deníku |
|:-----------:|:------------------:|
| ![Kola závodu](docs/screenshots/kola.png) | ![Nahrání EDI souboru](docs/screenshots/edi-upload.png) |

| Výsledková listina | Roční výsledky |
|:-----------------:|:--------------:|
| ![Výsledková listina](docs/screenshots/vysledky.png) | ![Roční výsledky](docs/screenshots/vysledky-rocni.png) |

### Administrace

| Přihlášení admina | Nahrané EDI deníky |
|:-----------------:|:-----------------:|
| ![Přihlášení](docs/screenshots/login.png) | ![Nahrané deníky](docs/screenshots/admin-deniky.png) |

| EDI debug – kontrola bodování | Správa kategorií |
|:-----------------------------:|:----------------:|
| ![EDI debug](docs/screenshots/admin-edi-debug.png) | ![Správa kategorií](docs/screenshots/admin-kategorie.png) |

| Hromadný import ZIP |
|:-------------------:|
| ![Hromadný import](docs/screenshots/admin-importy.png) |

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
EdiValidator::validate(EdiLog) ──► EdiValidationReport (varování, neblokuje)
        │
        ▼
EdiImportService::import(EdiLog) ──► edihead + edilines (transakce)
        │
        ▼
ScoringService::scoreEdi(Edihead) ──► EdiScore (boduZaQso × nasobice = body)
        │
        ▼
ImportEdiAction ──► VkvpaData row + edihead_id + EdiImported event
        │
        ▼
SendEdiMailsListener (queue) ──► HlaseniPrijato + HlaseniProVyhodnocovatele
```

### Životní cyklus kola (KoloStav)

Diagram stavu je v [`docs/kolo-lifecycle.svg`](docs/kolo-lifecycle.svg). Stav je čistá funkce času – odvozuje se ze tří sloupců `VkvpaKola` (`datum_konani` = start závodu jako datetime, standardně třetí neděle 08:00 UTC):

| Stav | Label | Podmínka |
|------|-------|----------|
| `Nadchazejici` | Nadcházející | `datum_konani > teď` |
| `Aktivni` | Probíhá | závodní okno právě běží (`datum_konani` až `konecZavodu()`, standardně +3 h) |
| `Prijem` | Příjem hlášení | závod skončil, uzávěrka ještě neuplynula |
| `Uzavrene` | Zpracování výsledků | uzávěrka uplynula, `vyhodnoceno = null` |
| `Vyhodnocene` | Vyhodnocené | `vyhodnoceno ≠ null` (nastaví automatika) |

Hlášení se od běžných závodníků přijímají ve stavech `Aktivni` a `Prijem` (od startu závodu do uzávěrky); admin smí nahrávat a opravovat kdykoliv.

**Přechod do `Vyhodnocené`** je automatický: po uzávěrce (stav `Uzavrene`) kolo vyhodnotí buď převzetí posledního dosud nepřevzatého záznamu (admin záznamy přebírá tlačítkem „P" už během příjmu), nebo denní příkaz `kola:finalize-evaluated` jakmile jsou všechny záznamy převzaty, případně po 20 dnech od uzávěrky (`vkvpa.finalize_fallback_days`). Odebrat převzetí (`zaznam.update`) smí admin jen mezi `datum_konani` a `datum_uzaverky`; po uzávěrce už lze záznam jen editovat (body se přepočítají).

### Dvě databázové schémata

**EDI schéma** (`edihead`, `edilines`): odvozeno z původního systému, ale plně normalizováno na `snake_case` názvy sloupců (`mode_code`, `received_wwl`, `qso_points`, `t_date`, `p_call` apod.) – přistupuje se k nim jako k běžným Eloquent atributům, žádný magický `$line->{'...'}` přístup ani potlačení `property.notFound`. Oba modely mají `#[WithoutTimestamps]` (vlastní časové sloupce `stamp`, `d_cas`). Model `Ediline` navíc nabízí **PHP 8.4 property hooks** (`$receivedWwl`, `$qsoPoints`, `$modeCode`, `$mode`, `$newWwl`), které surové sloupce normalizují/castují. Původní SQL dump (se starými dash-názvy) je držen jen jako provenience v `database/source_sql/` a je vyloučen z PHPStan/Pint.

**Aplikační schéma** (`vkvpa_*`): `VkvpaData` (závodní záznamy/výsledky), `VkvpaKola` (kola závodu), `VkvpaKategorie` (kategorie), `VkvpaPrihlaseni` (přihlašovací tokeny), `Prispevek` (diskuze ke kolům).

### Eloquent strict mode

Mimo produkci (`APP_ENV != production`) je povoleno:

```php
Model::preventLazyLoading();                    // odhalí N+1 dotazy
Model::preventSilentlyDiscardingAttributes();   // odhalí překlepy v $fillable
Model::preventAccessingMissingAttributes();     // odhalí přístup na nenačtené sloupce
```

Model `User` má per-model opt-out (`$modelsShouldPreventAccessingMissingAttributes = false`) kvůli `Authenticatable` traitu. `Edihead`/`Ediline` žádný opt-out nepotřebují – po normalizaci schématu na `snake_case` se k jejich sloupcům přistupuje běžně.

### Adresářová struktura

```
app/
├── Actions/
│   └── ImportEdiAction.php     # Orchestrace celého EDI importu (validace, ukládání, scoring, event)
├── Console/Commands/
│   └── EnsureUpcomingRoundsCommand.php   # Průběžné zakládání kol dle ContestCalendar
├── Enums/
│   ├── KoloStav.php        # Enum: fáze kola (nadcházející / aktivní / příjem / uzavřené / vyhodnocené)
│   └── QsoMode.php         # Enum: SSB (1) / CW (2) / Other (0)
├── Events/
│   └── EdiImported.php     # Event po úspěšném importu EDI deníku
├── Http/
│   ├── Controllers/        # Route handlery
│   │   ├── Admin/          # DashboardController, KolaAdminController, KategorieController, …
│   │   ├── Api/            # VysledkyApiController, ApiDocsController
│   │   └── HomeController.php  # Domovská stránka s odpočítáváním a živými výsledky
│   └── Middleware/
│       ├── EnsureAdmin.php
│       ├── SecurityHeaders.php  # CSP, HSTS, X-Frame-Options, Permissions-Policy
│       └── SetLocale.php
├── Listeners/
│   └── SendEdiMailsListener.php  # Odesílání emailů po importu (queue)
├── Livewire/
│   └── EdiUpload.php       # Reaktivní Livewire komponenta pro nahrání EDI (idle/uploading/success/error)
├── Models/                 # Eloquent modely
├── Services/
│   ├── Edi/                # EdiParser, EdiImportService, EdiReducer, CategoryResolver,
│   │                       # EdiValidator, EdiValidationReport, QsoGeometry, PorovnaniRivals,
│   │                       # EnrichedQso, BigSquareCount
│   └── Scoring/            # ScoringService, EdiScoreDebugger, value objekty
├── Mail/                   # HlaseniPrijato, HlaseniProVyhodnocovatele
└── Support/                # Maidenhead, ContestWindow, ContestCalendar, VkvpaSettings
config/
├── navigation.php          # Struktura menu (ne hard-coded v Blade)
└── vkvpa.php               # Doménová konfigurace (token_ttl_days, EDI max 500 KB, ZIP max 20 MB aj.)
database/
├── migrations/
├── factories/
└── seeders/
public/
├── site.webmanifest        # PWA manifest
└── sw.js                   # Service worker (network-first, offline fallback)
resources/
├── css/app.css             # Tailwind 4 (@import 'tailwindcss', @theme)
├── js/
│   ├── app.js                  # Dark mode, mobilní menu, delegované handlery (data-autosubmit, data-file-zone)
│   ├── dashboard.js            # Chart.js grafy admin dashboardu
│   ├── leaflet-fullscreen.js   # Sdílený Leaflet ovládací prvek: celá obrazovka
│   ├── porovnani.js            # Leaflet + Chart.js – porovnání dvou deníků (mapa rozdílů, grafy)
│   └── vizualizace.js          # Leaflet + Chart.js – komplexní EDI vizualizace (mapy vč. přehrávání + grafy)
└── views/
    ├── auth/               # Přihlašovací formulář
    ├── emails/             # Šablony emailů
    ├── layouts/app.blade.php
    ├── livewire/
    │   └── edi-upload.blade.php  # Blade šablona Livewire EdiUpload komponenty
    ├── pages/              # Stránky (home, hlaseni, kola, vysledky, diskuse, vizualizace,
    │                       #          porovnani, edi-upload, admin/*)
    └── partials/           # menu, footer, menu-item, no-active-period
docs/
├── kolo-lifecycle.svg      # Diagram životního cyklu závodního kola (KoloStav)
├── screenshots/            # Snímky obrazovky aplikace
└── technicky-dluh.md       # Dokumentace technického dluhu
```

Vite kompiluje čtyři oddělené JS entry-pointy (`app.js`, `vizualizace.js`, `porovnani.js`, `dashboard.js`) – každá stránka načítá jen co potřebuje.

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
4. `php artisan migrate` _(vyžaduje MySQL)_
5. `php artisan storage:link`
6. `npm install --ignore-scripts && npm run build`

### Ruční postup

```bash
cp .env.example .env
# Upravte .env (DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD, ADMIN_PASS)

composer install
php artisan key:generate
php artisan migrate

php artisan storage:link
npm install --ignore-scripts
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

# Nasazení na server (cache konfigurace + migrace + build)
composer deploy
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

# PHPStan – statická analýza (level 10)
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
| `create_vkvpa_data_table` | Závodní záznamy / výsledky |
| `create_vkvpa_kategorie_table` | Kategorie závodů |
| `create_vkvpa_kola_table` | Kola závodu |
| `create_vkvpa_prihlaseni_table` | Dočasné přihlašovací tokeny |
| `create_users_table` | Admin uživatelé |
| `add_aktivni_to_vkvpa_kola` | Příznak aktivního kola (později zrušen) |
| `create_diskuse_table` | Diskuzní příspěvky ke kolům |
| `kola_lifecycle_datetime` | `datum_konani` jako datetime (start závodu 08:00 UTC), odstranění `aktivni` |

### Modely a vztahy

```
VkvpaKola ──► VkvpaData ◄── VkvpaKategorie
                │
                └──► Edihead ──► Ediline[]

VkvpaKola ──► Prispevek[]
```

| Model | Klíčové vztahy a atributy |
|-------|---------------------------|
| `VkvpaData` | `belongsTo(VkvpaKola, VkvpaKategorie, Edihead)` |
| `Edihead` | `hasMany(Ediline)`, `#[WithoutTimestamps]` |
| `Ediline` | `belongsTo(Edihead)`, nestandardní názvy sloupců, `#[WithoutTimestamps]`; PHP 8.4 property hooks: `$receivedWwl`, `$qsoPoints`, `$modeCode`, `$mode`, `$newWwl` |
| `VkvpaKola` | `hasMany(VkvpaData, Prispevek)`, `stav(): KoloStav`, `isActive()`, scope `active()` |
| `VkvpaKategorie` | `hasMany(VkvpaData)` |
| `VkvpaPrihlaseni` | tokeny s TTL = `vkvpa.token_ttl_days` (výchozí: 5 dní) |
| `Prispevek` | `belongsTo(VkvpaKola)` – diskuzní příspěvky |
| `User` | přihlašování přes `name` (ne email), `is_admin` boolean |

---

## Routování

### Veřejné trasy

| Metoda | URI | Controller | Název |
|--------|-----|------------|-------|
| GET | `/` | `HomeController@index` | `home` |
| GET | `/hlaseni` | `HlaseniController@index` | `hlaseni.index` |
| POST | `/hlaseni` | `HlaseniController@store` | `hlaseni.store` |
| GET | `/kola` | redirect → `/admin/kola` | – |
| GET | `/vysledky` | `VysledkyController@listina` | `vysledkova_listina` |
| GET | `/vysledky/pribezne` | `VysledkyController@pribezne` | `pribezne_vysledky` |
| GET | `/vysledky/rocni` | `VysledkyController@rocni` | `rocni_vysledky` |
| GET | `/diskuse` | `DiskuseController@index` | `diskuse.index` |
| GET | `/diskuse/{kolo}` | `DiskuseController@show` | `diskuse.show` |
| POST | `/diskuse/{kolo}` | `DiskuseController@store` | `diskuse.store` |
| GET | `/edi` | `EdiController@create` | `edi.create` |
| POST | `/edi` | `EdiController@store` | `edi.store` |
| GET | `/edi/{head}/soubor` | `EdiController@zobrazit` | `edi.soubor` |
| GET | `/edi/{head}/soubor-redukovany` | `EdiController@zobrazitRedukovany` | `edi.soubor.redukovany` |
| GET | `/edi/{head}/vizualizace` | `EdiVizualizaceController@show` | `edi.vizualizace` |
| GET | `/edi/{head}/porovnani` | `EdiPorovnaniController@show` | `edi.porovnani` |
| GET | `/lang/{locale}` | closure | `lang.switch` |
| GET | `/login` | `AuthController` | `login` |
| GET | `/login/token/{kod}` | token login | – |
| GET | `/mail-image` | `MailImageController@show` | `mail.image` |

### Admin trasy (middleware: `admin`)

| Metoda | URI | Controller | Název |
|--------|-----|------------|-------|
| GET | `/admin/statistiky` | `DashboardController@index` | `admin.dashboard` |
| GET | `/admin/kola` | `KolaAdminController@index` | `kola.admin.index` |
| GET | `/admin/kola/create` | `KolaAdminController@create` | `kola.admin.create` |
| POST | `/admin/kola` | `KolaAdminController@store` | `kola.admin.store` |
| GET | `/admin/kola/{kolo}/edit` | `KolaAdminController@edit` | `kola.admin.edit` |
| PATCH | `/admin/kola/{kolo}` | `KolaAdminController@update` | `kola.admin.update` |
| PATCH | `/admin/zaznamy/{zaznam}` | `ZaznamController@update` | `zaznam.update` |
| DELETE | `/admin/zaznamy/{zaznam}` | `ZaznamController@destroy` | `zaznam.destroy` |
| DELETE | `/admin/diskuse/{prispevek}` | `DiskuseController@destroy` | `diskuse.destroy` |
| GET | `/admin/edi-debug` | `EdiDebugController@create` | `edi.debug.create` |
| POST | `/admin/edi-debug` | `EdiDebugController@analyze` | `edi.debug.store` |
| GET | `/admin/edi-debug/{head}` | `EdiDebugController@show` | `edi.debug.show` |
| GET | `/admin/deniky` | `DenikyController@index` | `deniky.index` |
| GET | `/admin/kategorie` | `KategorieController@index` | `kategorie.index` |
| POST | `/admin/kategorie` | `KategorieController@store` | `kategorie.store` |
| GET | `/admin/kategorie/{kategorie}/edit` | `KategorieController@edit` | `kategorie.edit` |
| PATCH | `/admin/kategorie/{kategorie}` | `KategorieController@update` | `kategorie.update` |
| GET | `/admin/importy` | `ImportController@index` | `importy.index` |
| POST | `/admin/importy` | `ImportController@store` | `importy.store` |

### REST API trasy (prefix `/api`)

| Metoda | URI | Controller | Název |
|--------|-----|------------|-------|
| GET | `/api/kola` | `VysledkyApiController@kola` | `api.kola` |
| GET | `/api/vysledky/{kolo}` | `VysledkyApiController@kolo` | `api.vysledky.kolo` |
| GET | `/api/vysledky/rocni/{rok}` | `VysledkyApiController@rocni` | `api.vysledky.rocni` |
| GET | `/api/docs` | `ApiDocsController@index` | `api.docs` |
| GET | `/api/docs/spec` | `ApiDocsController@spec` | `api.docs.spec` |

---

## Autentizace

Přihlášení je session-based se dvěma způsoby vstupu:

1. **Standardní formulář** na `/login` – přihlášení přes `name` + heslo
2. **Token login** na `/login/token/{kod}` – jednorázový alfanumerický kód s TTL 5 dní (konfigurovatelné přes `vkvpa.token_ttl_days`)

Admin trasy jsou chráněny middleware `EnsureAdmin` (`middleware('admin')`). Admin práva se řídí atributem `User::is_admin` (boolean).

Struktura navigačního menu je deklarována v `config/navigation.php` – pro veřejnou (`public`) a admin (`admin`) sekci zvlášť.

---

## EDI pipeline

### Formát souborů

Systém podporuje EDI soubory ve formátu REF 01 (standardní závodní log pro radioamatéry). Soubory mohou být v kódování Windows-1250; `EdiParser` je automaticky převádí přes `iconv` před zpracováním.

Vzorové EDI soubory jsou v `resources/edi/` a slouží jako fixture pro unit testy.

Nahrání EDI deníku probíhá přes **Livewire komponentu** `EdiUpload` (`app/Livewire/EdiUpload.php`): soubor se nahraje reaktivně bez přenačtení stránky, ihned se parsuje a zobrazí výsledek (počet QSO, body, varování) nebo chybová hlášení.

### Klíčové třídy

| Třída | Soubor | Odpovědnost |
|-------|--------|-------------|
| `EdiParser` | `app/Services/Edi/EdiParser.php` | Parsování EDI textu → `EdiLog` (value object) |
| `EdiValidator` | `app/Services/Edi/EdiValidator.php` | Kontrola kvality deníku → `EdiValidationReport` (varování, neblokuje) |
| `EdiImportService` | `app/Services/Edi/EdiImportService.php` | Uložení `EdiLog` → `edihead` + `edilines` v transakci |
| `EdiReducer` | `app/Services/Edi/EdiReducer.php` | Filtrování EDI na závodní okno (08:00–11:00 UTC) |
| `CategoryResolver` | `app/Services/Edi/CategoryResolver.php` | Určení kategorie z hlavičky (pásmo + sekce + DX) |
| `QsoGeometry` | `app/Services/Edi/QsoGeometry.php` | Výpočty souřadnic, vzdáleností, azimutů, průběhu skóre a porovnání deníků (sdíleno vizualizací i porovnáním) |
| `PorovnaniRivals` | `app/Services/Edi/PorovnaniRivals.php` | Výběr soupeřů pro porovnání deníků (totéž kolo + kategorie, až po uzávěrce) |
| `BigSquareCount` | `app/Services/Edi/BigSquareCount.php` | Agregace QSO do 4-znakových Maidenhead čtverců |
| `ImportEdiAction` | `app/Actions/ImportEdiAction.php` | Orchestrace celého importu: validace → uložení → scoring → event |
| `SkokanService` | `app/Services/Scoring/SkokanService.php` | „Skokan" – body-delta závodníka oproti poslednímu startu ve stejné kategorii + největší skokan kola (zobrazeno ve výsledkové listině) |
| `EdiUpload` | `app/Livewire/EdiUpload.php` | Livewire reaktivní upload komponenta (stavy idle/uploading/success/error) |

### Value objekty

- **`EdiLog`** – kompletní parsed log (header + QSO[] + raw source)
- **`EdiHeader`** – hlavičková data (callsign, WWL, pásmo, sekce, datum)
- **`EdiQso`** – jedno QSO spojení
- **`EnrichedQso`** – QSO obohacené o lat/lon, vzdálenost, azimut a `QsoMode`
- **`EdiValidationReport`** – výsledek EDI validace s lidsky čitelnými česky psanými varováními

### Enums

Všechny enumy jsou v `app/Enums/`. Backed enumy nesou hodnotu (`value`), která se používá buď v datech (EDI `Mode-code`), nebo v URL.

| Enum | Typ | Hodnoty | Použití |
|------|-----|---------|---------|
| `QsoMode` | `int` | `Ssb(1)`, `Cw(2)`, `Other(0)` | Druh provozu z EDI `Mode-code`; řídí barvy v mapách a vizualizaci. `fromCode()` mapuje neznámý kód na `Other`, `label()` vrací `SSB`/`CW`/`?` |
| `KoloStav` | `string` | `Nadchazejici`, `Aktivni`, `Prijem`, `Uzavrene`, `Vyhodnocene` | Fáze životního cyklu kola, odvozená z času (`datum_konani` = start závodu, `datum_uzaverky`) a sloupce `vyhodnoceno`; do `Vyhodnocené` se kolo dostane automaticky po uzávěrce (vše převzato / +20 dní, viz `VkvpaKola::maBytVyhodnoceno()` a `kola:finalize-evaluated`). Logika v `VkvpaKola::stav()`, diagram: [`docs/kolo-lifecycle.svg`](docs/kolo-lifecycle.svg). `label()` vrací `Nadcházející`/`Probíhá`/`Příjem hlášení`/`Zpracování výsledků`/`Vyhodnocené`; `badgeClass()` barvu badge. |

### Rozlišení kategorií

`CategoryResolver::resolve(pcall, pBand, pSect)` určuje kategorii:

- **Pásmo** – aliasy: `144`/`145 MHz → "144"`, `432`/`435 MHz → "432"`, atd.
- **Sekce** – `MO` (multi operátor), `SO` (single), nebo `null`
- **DX varianta** – pokud prefix není `OK`/`OL`
- Pokud pásmo není rozpoznáno → `UnknownBandException`

### EDI validace

`EdiValidator::validate(EdiLog)` provádí nekritické kontroly kvality:

| Pravidlo | Popis |
|----------|-------|
| Duplicitní volaná stanice | Stejný callsign zalogován vícekrát |
| Neplatný lokátor | Formát nesplňuje Maidenhead specifikaci |
| QSO mimo závodní okno | Čas nebo datum neodpovídá závodnímu dni |
| Neshoda počtu QSO | Deklarovaný počet v hlavičce ≠ skutečný počet záznamů |

Výsledek je `EdiValidationReport` zobrazený závodníkovi jako varování; import pokračuje bez ohledu na nálezy.

---

## Bodování

### Vzorec

```
body = boduZaQso × nasobice
```

- **`pocet`** – počet QSO v závodním okně (den závodu dle `TDate`, čas 08:00–11:00 UTC); QSO do vlastního velkého čtverce jsou **započítána**
- **`boduZaQso`** – součet bodů za spojení přepočítaný z lokátorů (hodnota `QSO-Points` z deníku se ignoruje): vlastní velký čtverec = 2 body, každý sousední pás o bod více (`Maidenhead::qsoPoints()`)
- **`nasobice`** – počet různých velkých čtverců (4-znakový Maidenhead) včetně vlastního; vlastní se počítá vždy, i pokud s ním nebylo pracováno žádné QSO

Konstanta `NON_EDI_NULLIFY_FROM_KOLO = 91`: záznamy bez EDI souboru se v ročních výsledcích počítají jako 0 bodů pro kola ≥ 91.

### Pořadí

`ScoringService::rankRound()` přiřazuje husté pořadí (dense rank) v rámci každé kategorie daného kola – závodníci se stejným skóre dostanou stejné pořadí.

### Výkonové příznaky (QRP / LP)

U každého záznamu se evidují dva výkonové příznaky:

- **`qrp`** – QRP, výkon ≤ 5 W (`EdiHeader::isQrp()`)
- **`lp`** – LP (low power), výkon < 100 W (`EdiHeader::isLp()`)

Oba se při importu EDI nastaví automaticky z pole `SPowe` a předvyplní zaškrtávátko ve formuláři; účastníci bez EDI je zatrhnou ručně. Výsledková listina kola (`/vysledky`) i roční výsledky (`/vysledky/rocni`) nabízejí filtry **„jen QRP"** a **„jen LP"**. Protože QRP (≤ 5 W) je podmnožinou LP (< 100 W), filtr „jen LP" zobrazí i QRP stanice.

---

## Mapové pohledy

Mapa je součástí stránky vizualizace (`/edi/{head}/vizualizace`) jako čtyři přepínatelné Leaflet vrstvy. Dřívější samostatné stránky `/edi/{head}/mapa/*` (M/N/S/C) byly zrušeny – zjednodušení UX, vše je na jedné URL:

| Vrstva | Popis |
|--------|-------|
| CRK (výchozí) | Kombinovaný pohled ve stylu vkvzavody.crk.cz |
| Ježek | Čáry z domácí stanice na všechna pracovaná QSO |
| Špendlíky | Pin na každé QSO, popup s vzdáleností a azimutem |
| Lokátory | Velké čtverce s počtem QSO jako popisek |

Geometrie (souřadnice, vzdálenosti, azimuty) je centralizována v `QsoGeometry`. Vrstva „všechny stanice z kola" (`roundStations`) je viditelná až po uzavření/vyhodnocení kola – v průběhu příjmu hlášení by odhalovala deníky soupeřů. Porovnání s deníkem soupeře má vlastní stránku – viz [Porovnání deníků](#porovnání-deníků).

Podpůrná třída `Maidenhead` zajišťuje převod lokátor ↔ lat/lon. Vzdálenosti a azimuty jsou počítány přes knihovnu **mjaschen/phpgeo** (Haversine/Vincenty).

---

## EDI vizualizace

Trasa `GET /edi/{head}/vizualizace` (`edi.vizualizace`) zobrazí komplexní analytickou stránku pro konkrétní EDI deník – mapu (pět přepínatelných vrstev vč. přehrávání deníku) i grafy na jedné URL. Toto je jediný mapový pohled; samostatné stránky byly zrušeny. Stránka je veřejná (zobrazuje jen vlastní deník účastníka) a v hlavičce – existuje-li aspoň jeden soupeř v téže kategorii – odkazuje na [porovnání deníků](#porovnání-deníků).

### Komponenty stránky

#### Statistické karty

Souhrnné metriky vypočítané ze záznamů v závodním okně:

| Metrika | Popis |
|---------|-------|
| Počet QSO | Celkový počet spojení v závodním okně (08:00–11:00 UTC) |
| Unique lokátory | Počet různých 4-znakových Maidenhead čtverců protistanic |
| Max. vzdálenost | Nejdelší spojení v km (Haversine) |
| Průměr vzdálenost | Průměrná vzdálenost přes všechna QSO v km |
| Tempo závodu | Špička (nejlepší klouzavá hodina), nejdelší pauza mezi QSO, průměrné tempo QSO/hod, počet nezapočítaných QSO |
| Druhy provozu | SSB / CW / ostatní: počet QSO, body, průměrná a max vzdálenost |

#### Interaktivní mapa (Leaflet)

Jeden mapový widget s přepínatelnými vrstvami bez reload stránky:

| Vrstva | Popis | Barvy |
|--------|-------|-------|
| **CRK** | Kombinovaná mapa ve stylu vkvzavody.crk.cz: paprsky, kružnice vzdáleností po 200 km, mřížka velkých čtverců, všechny stanice z kola (≥ 5 QSO, po uzávěrce) | modrá = SSB, oranžová = CW, fialová = stanice z kola |
| **Ježek** | Čáry z domácí stanice + body protistanic | modrá = SSB, oranžová = CW |
| **Špendlíky** | Body protistanic s popupem (volací znak, WWL, vzdálenost, azimut) | modrá = SSB, oranžová = CW |
| **Lokátory** | Velké čtverce s počtem QSO jako popisek | fialová |
| **Přehrávání** (výchozí) | QSO se objevují chronologicky (paprsek + špendlík) podle slideru 08:00–11:00 a tlačítka ▶ | modrá = SSB, oranžová = CW |

Body jsou barevně rozlišeny dle `QsoMode`:
- **Modrá** (`#60a5fa`) – SSB (`QsoMode::Ssb`)
- **Oranžová** (`#fbbf24`) – CW (`QsoMode::Cw`)
- **Šedá** – neznámý mód (`QsoMode::Other`)

#### Průběh skóre (Chart.js Line)

Schodový graf: kumulativní body za spojení × průběžný počet násobičů po každém QSO (`QsoGeometry::prubehSkore()`, sdíleno se stránkou porovnání deníků).

#### Vážená azimutová růžice (Chart.js PolarArea)

Polární graf rozdělující QSO do 8 sektorů po 45°, počítáno po směru hodinových ručiček od severu, s přepínáním vážení: počet QSO / součet km / součet bodů.

```
S (0°) → SV (45°) → V (90°) → JV (135°) → J (180°) → JZ (225°) → Z (270°) → SZ (315°)
```

#### Časová osa QSO s násobiči (Chart.js Bar)

Skládaný sloupcový graf s 12 intervaly po 15 minutách pokrývajícími závodní okno; zvlášť QSO, která přinesla nový násobič:

```
08:00 | 08:15 | 08:30 | 08:45 | 09:00 | 09:15 | 09:30 | 09:45 | 10:00 | 10:15 | 10:30 | 10:45
```

#### Body podle čtverců + celoroční trend

Vodorovné sloupce (kolik bodů přinesl který velký čtverec) a vývoj bodů + pořadí stanice ve všech kolech roku (z veřejné výsledkové listiny).

#### Histogram vzdáleností (Chart.js Bar)

Sloupcový graf s 6 vzdálenostními pásmy:

| Pásmo | Typický dosah |
|-------|---------------|
| 0–50 km | Místní provoz |
| 50–100 km | Regionální |
| 100–200 km | Střední vzdálenosti |
| 200–400 km | Dlouhé trasy |
| 400–700 km | Výjimečné podmínky |
| 700+ km | Rekordní spojení (Es/troposféra) |

### Architektura vizualizace

```
GET /edi/{head}/vizualizace
        │
        ▼
EdiVizualizaceController::show(Edihead)
        │
        ├── QsoGeometry::enrichedQsos()       ─► EnrichedQso[]
        ├── QsoGeometry::bigSquares()         ─► agregace do 4-znakových čtverců
        ├── QsoGeometry::roundStations()      ─► stanice z kola (po uzávěrce, TTL cache)
        ├── QsoGeometry::prubehSkore()        ─► kumulativní skóre po QSO
        ├── PorovnaniRivals::hasRivals()      ─► zobrazit odkaz na porovnání?
        ├── DenikStatistiky::noveNasobice()   ─► chronologie nových čtverců (s idx QSO)
        ├── DenikStatistiky::timeline()       ─► {labels, celkem, nove} (15min intervaly)
        ├── DenikStatistiky::azimuthRose()    ─► {labels, pocet, km, body} (16 sektorů)
        ├── DenikStatistiky::bodyPodleCtvercu() ─► body po velkých čtvercích
        ├── DenikStatistiky::sezona()         ─► celoroční trend stanice
        ├── DenikStatistiky::tempo()          ─► špička / pauza / průměr
        ├── DenikStatistiky::modeStats()      ─► souhrn po druzích provozu
        ├── distHistogram()                   ─► {labels, ssb, cw, ostatni} (pásma × druh provozu)
        └── stats()                           ─► array{pocet, maxDist, avgDist, uniqueSq}
                │
                ▼
        pages/vizualizace.blade.php
                │
                ├── window.__vizConfig = { ...vše jako JSON }
                └── @vite('resources/js/vizualizace.js')
                            │
                            ├── Leaflet – mapa s 5 vrstvami (vč. přehrávání) + legenda
                            └── Chart.js – 6 grafů
```

---

## Porovnání deníků

Trasa `GET /edi/{head}/porovnani` (`edi.porovnani`) je samostatná stránka **porovnání dvou hráčů** – tohoto deníku proti vybranému soupeři (query parametr `?porovnat={id}`). Funkce sem byla přesunuta ze stránky Vizualizace (mapová vrstva „Porovnání" a overlay průběhu skóre).

### Pravidla výběru soupeře

- Porovnat lze **jen deníky z téhož kola a téže kategorie** – soupeře hledá `PorovnaniRivals` podle schválených záznamů výsledkové listiny (`vkvpa_data`)
- **Férovost:** soupeřův deník se vydá až po uzávěrce, resp. vyhodnocení kola (`QsoGeometry::roundResultsDisclosable()`); do té doby se nenabízí nic a query parametr se ignoruje
- Soupeř mimo nabídku (jiná kategorie, jiné kolo) je tiše ignorován
- Vizualizace odkazuje na tuto stránku jen tehdy, když existuje aspoň jeden soupeř (`PorovnaniRivals::hasRivals()`)

### Komponenty stránky (po výběru soupeře)

| Komponenta | Popis |
|------------|-------|
| **Souhrnné karty** | Obě stanice vedle sebe: počet QSO, násobiče, body; plus počty rozdílů (jen já / jen soupeř / oba) |
| **Mapa rozdílů** (Leaflet) | Protistanice po vzoru vushf.dk: **zelená** = udělal jen tento deník, **červená** = jen soupeř, **šedá** = oba; modrý bod = vlastní QTH, oranžový = soupeřovo; vzájemné spojení obou stanic se nepočítá |
| **Průběh skóre** (Chart.js Line) | Schodové křivky obou stanic přes sebe (modrá = vlastní, červená = soupeř): kumulativní body za spojení × průběžný počet násobičů |
| **QSO v čase** (Chart.js Bar) | Tempo obou stanic vedle sebe v 15minutových intervalech závodního okna |
| **Směry QSO** (Chart.js Radar) | Směrová růžice obou stanic přes sebe – počty QSO v 8 světových stranách |

### Architektura porovnání

```
GET /edi/{head}/porovnani?porovnat={id}
        │
        ▼
EdiPorovnaniController::show(Request, Edihead)
        │
        ├── PorovnaniRivals::rivals()      ─► soupeři z téhož kola + kategorie
        ├── QsoGeometry::compareWith()     ─► {onlyMine, onlyRival, both}
        ├── QsoGeometry::prubehSkore()     ─► kumulativní skóre obou deníků
        ├── timelineCounts()               ─► QSO po 15 min (obě stanice)
        └── azimuthCounts()                ─► 8 směrových sektorů (obě stanice)
                │
                ▼
        pages/porovnani.blade.php
                │
                ├── window.__porovnaniConfig = { ...vše jako JSON }
                └── @vite('resources/js/porovnani.js')
                            │
                            ├── Leaflet – mapa rozdílů + legenda
                            └── Chart.js – 3 grafy (průběh, timeline, radar)
```

---

## Diskuse

Každé závodní kolo má vlastní diskuzní vlákno dostupné na `/diskuse/{kolo}`.

- Příspěvky může přidávat kdokoli (throttle: `diskuse`, ochrana před spamem)
- Příspěvky moderuje admin (smazání přes `DELETE /admin/diskuse/{prispevek}`)
- Model `Prispevek` patří pod `VkvpaKola`
- Výchozí redirect `/diskuse` → nejnovější kolo

---

## REST API

Veřejné JSON API pro strojové čtení výsledků. Rate limit: 60 požadavků/minutu per IP. CORS povolen pro všechny originy (GET).

### Endpointy

| Metoda | URI | Popis |
|--------|-----|-------|
| GET | `/api/kola` | Seznam závodních kol |
| GET | `/api/vysledky/{kolo}` | Výsledky konkrétního kola |
| GET | `/api/vysledky/rocni/{rok}` | Roční výsledky (rok ve formátu YYYY) |
| GET | `/api/docs` | Swagger UI dokumentace |
| GET | `/api/docs/spec` | OpenAPI specifikace (YAML) |

OpenAPI specifikace je uložena v `public/api-docs/openapi.yaml` a zobrazena přes Swagger UI na `/api/docs`.

---

## Emaily

Dvě `Mailable` třídy:

| Třída | Příjemce | Účel |
|-------|----------|------|
| `HlaseniPrijato` | závodník | potvrzení přijetí deníku |
| `HlaseniProVyhodnocovatele` | rozhodci závodů | notifikace o novém záznamu (obsahuje magic-link token) |

Emaily jsou odesílány asynchronně přes frontu (`SendEdiMailsListener`) po události `EdiImported`. Tím neblokují HTTP odpověď po nahrání EDI.

Emailová adresa v patičce stránek je renderována jako PNG obrázek (`MailImageController` přes GD) – ochrana proti scrapingu.

Konfigurace odesílatele: `MAIL_FROM_ADDRESS` a `CONTACT_MAIL` v `.env`.

---

## Plánované příkazy

Laravel Scheduler spouští dva Artisan příkazy – stavy nadcházející/probíhá/příjem/zpracování se odvozují čistě z času (žádná plánovaná aktivace/deaktivace), automatizovat je potřeba jen přechod do `Vyhodnocené`:

| Příkaz | Frekvence | Účel |
|--------|-----------|------|
| `kola:ensure-upcoming` | každý den | Zakládá chybějící kola na N měsíců dopředu dle `ContestCalendar` |
| `kola:finalize-evaluated` | každý den | Vyhodnotí kola po uzávěrce (všechny záznamy převzaty nebo +20 dní od uzávěrky); přepočítá pořadí a nastaví `vyhodnoceno` |

`ContestCalendar` automaticky vypočítává termíny závodů: třetí neděle v měsíci 08:00–11:00 UTC, uzávěrka v pátek téhož týdne 23:59 UTC.

> **Poznámka pro nasazení:** Laravel Scheduler musí být registrován v cronu serveru: `* * * * * php /var/www/artisan schedule:run >> /dev/null 2>&1`

---

## PWA

Aplikace je instalovatelná jako Progressive Web App:

- **`public/site.webmanifest`** – deklaruje název `VKV PA`, standalone mód, fialové téma (`#4338ca`), maskable SVG ikonu
- **`public/sw.js`** – service worker s network-first strategií; při výpadku sítě vrací cachovanou odpověď (offline fallback)

Uživatelé na mobilních zařízeních a desktopu mohou aplikaci přidat na domovskou obrazovku / taskbar bez instalace z app storu.

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
