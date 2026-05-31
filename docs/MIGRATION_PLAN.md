# Migrační plán — VKV Provozní aktiv → PHP 8.5 + Laravel 13

> Generováno podle `AGENTS.md`. Každý krok je malý, samostatně testovatelný a zachovává funkcionalitu i grafický design legacy systému.

## 1. Cíle (dle AGENTS.md)

1. Zachovat funkcionalitu stávajícího PHP4 systému.
2. Migrovat na PHP 8.5 + Laravel 13.
3. Zachovat grafický design (HTML/CSS, `css/styl.css`, layout `head`/`menu`/`bottom`).
4. Preferovat moderní syntax, strict typing, PSR-12.
5. Databáze s daty je již v Laravel 13 (migrace + seedery existují).
6. Maily přepsat na Laravel Mail.
7. Mapy (OpenLayers + Leaflet) aktualizovat na nejnovější stabilní verze.

## 2. Stav výchozího kódu (inventura)

### Legacy (kořen repozitáře, ~69 PHP souborů)
- `index.php` — primitivní router přes `?str=...`, whitelist include.
- `connect.php` / `connect_mysqli.php` — dvě nekonzistentní DB konfigurace, **hardcoded hesla**, procedurální mysqli, helpery `mq`/`mfa`/`mnr`, „janitor" čistič osiřelých záznamů.
- `head.php` — `session_start`, **hardcoded login `Beda`/`oK1dOz`**, deprecated `ereg()`, file-based úložiště kontaktního mailu (`mail.inc`, base64), layout hlavičky.
- `menu.php`, `bottom.php` — layout, base64 obfuskace e-mailů.
- `read_edi.php` — parser EDI deníku (REG1TEST), zápis do `edihead`/`edilines` (částečně prepared statements; obsahuje bug — `$stl->close()` uvnitř smyčky).
- `edit_hlaseni.php` (35 kB) — největší soubor, hlavní formulář hlášení.
- `edit_*.php` — administrace kol, deníků, kategorií, importů.
- `vysledkova_listina.php`, `rocni_vysledky.php`, `vyhodnoceni.php`, `uzavreni.php` — výsledky a vyhodnocení.
- `map*.php` (cca 10 variant) — vykreslování QTH lokátorů; `maptest/`, `maptest2/` (DantSu OpenStreetMapStaticAPI).
- `mail.php`, `mail_qrp.php`, `mail_red.php` — odesílání e-mailů + obrázkové „mailto" trefy.
- `export.php`, `import.php`, `show_edi.php` — exporty/importy.

### Již hotovo v Laravel vrstvě
- `database/migrations/` — 9 migrací (`edihead`, `edilines`, `prefixes`, `vkvpa_config`, `vkvpa_data`, `vkvpa_diskuse`, `vkvpa_kategorie`, `vkvpa_kola`, `vkvpa_prihlaseni`).
- `database/seeders/` — seedery tahající data z `database/seeders/data/*.json`.
- `database/source_sql/sql/` — dump původní DB.
- `docker-compose.yml` — `php:8.5-apache` + `mysql:8.0`, DB `digipa`.

## 3. Bezpečnostní dluh (řešit prioritně, ne na konci)

| # | Problém | Kde | Náprava |
|---|---------|-----|---------|
| S1 | Hardcoded DB hesla | `connect.php`, `connect_mysqli.php` | přesun do `.env` / `config/database.php` |
| S2 | Hardcoded admin login v plaintextu | `head.php` (`Beda`/`oK1dOz`) | Laravel Auth, hash hesla, `users` tabulka |
| S3 | SQL injection (interpolace `$_GET`/`$_POST`) | `head.php` a další | Eloquent / Query Builder s bindingy |
| S4 | `ereg()` (odstraněno v PHP 7) | `head.php` | `preg_match` / validace Laravelu |
| S5 | Tajemství commitnutá v repu (`.env`, `.idea/private_key`) | repozitář | `git rm --cached`, rotace hesel a klíče, `.gitignore` |
| S6 | Mix kódování Win-1250 vs UTF-8 | legacy soubory | převést vše na UTF-8 |
| S7 | File-based stav (`mail.inc`) | `head.php`, `bottom.php` | `vkvpa_config` tabulka (klíč `V_ADMIN_MAIL`) |

## 4. Cílová architektura

```
app/
  Models/                 ← Eloquent modely (KROK 1 – tento dokument)
  Http/Controllers/       ← nahradí index.php router + jednotlivé str=*.php
  Services/
    EdiParser.php         ← jádro read_edi.php (čisté, testovatelné)
    ScoringService.php    ← vyhodnocení, násobiče, body
  Mail/                   ← Laravel Mail (nahradí mail*.php)
resources/views/          ← Blade šablony (zachovaný design z head/menu/bottom)
routes/web.php            ← nahradí ?str= router whitelistem pojmenovaných rout
config/database.php       ← připojení z .env (nahradí connect*.php)
database/                 ← už existuje (migrace, seedery)
```

Mapování legacy → cíl:

| Legacy | Cíl |
|--------|-----|
| `index.php` (`?str=`) | `routes/web.php` + Controllery |
| `connect*.php` | `config/database.php` + `.env` |
| `head/menu/bottom.php` | `resources/views/layouts/app.blade.php` + partials |
| `read_edi.php` | `App\Services\EdiParser` + `EdiController@store` |
| `edit_hlaseni.php` | `HlaseniController` + Blade view + FormRequest |
| `edit_kola/deniky/kategorie/import.php` | resource Controllery (admin) |
| `vysledkova_listina/rocni_vysledky.php` | `VysledkyController` |
| `vyhodnoceni/uzavreni.php` | `ScoringService` + Controller |
| `mail*.php` | `App\Mail\*` (Laravel Mail) |
| `map*.php`, `maptest*` | Blade + OpenLayers/Leaflet (aktualizované) |

## 5. Fázový postup (každý krok = samostatný PR, testovatelný)

### Fáze 0 — Příprava (běží v prostředí uživatele)
- 0.1 `composer create-project laravel/laravel` (v8.5 prostředí) — *nelze provést v tomto sandboxu, packagist není dostupný*.
- 0.2 Zkopírovat existující `database/` (migrace + seedery) do nového projektu.
- 0.3 Nastavit `.env` (DB, MAIL) — **bez** commitnutí. Test: `php artisan migrate:fresh --seed` projde.

### Fáze 1 — Datová vrstva (mechanický refaktor) ← **ZAČÍNÁME ZDE**
- 1.1 Eloquent modely pro všech 9 tabulek (viz `app/Models/`). Zachované názvy tabulek i sloupců.
- 1.2 Test: `Edihead::with('lines')->first()` a `VkvpaConfig::get('V_ADMIN_MAIL')` vrací data ze seedů.

### Fáze 2 — Konfigurace a připojení (S1, S5, S6)
- Připojení z `.env`, odstranění `connect*.php`. Převod legacy souborů na UTF-8.

### Fáze 3 — Layout (zachování designu)
- `head/menu/bottom.php` → Blade layout + partials, beze změny vzhledu (stejné `css/styl.css`).

### Fáze 4 — Autentizace (S2, S3, S4)
- Laravel Auth místo hardcoded loginu; nahrazení `ereg`, odstranění SQLi.

### Fáze 5 — EDI parser (doménové jádro)
- `EdiParser` jako čistá služba s unit testy (fixtures z reálných `.edi`); oprava bugu ve smyčce.

### Fáze 6 — Formuláře hlášení a administrace
- `edit_hlaseni` a `edit_*` na Controllery + FormRequesty + Blade.

### Fáze 7 — Výsledky a vyhodnocení
- `ScoringService` (body × násobiče), výsledkové listiny, roční výsledky, uzávěrka.

### Fáze 8 — Maily (Laravel Mail)
- `mail*.php` → `App\Mail\*` Mailable třídy + Blade šablony.

### Fáze 9 — Mapy
- Aktualizace OpenLayers a Leaflet na nejnovější stabilní verze; sjednocení duplicitních `map*.php`.

### Fáze 10 — Úklid
- Smazání legacy souborů, odstranění duplicit (`map2/mapb2/mapc2...`), CI s PHPStan + Pint (PSR-12).

## 6. Testovací strategie
- **Unit**: `EdiParser`, `ScoringService` (deterministické, fixtures).
- **Feature**: routy, formulář hlášení (upload EDI → záznamy v DB), login.
- **Regrese vzhledu**: porovnání vyrenderovaného HTML proti legacy snapshotu.
- Po každé fázi musí projít `php artisan test` + `vendor/bin/pint --test` + `phpstan`.

## 7. Poznámka k tomuto prostředí
Tento sandbox nemá přístup k packagist.org, takže zde nelze spustit `composer install`/`create-project` ani reálný `artisan`. Generuji proto **drop-in soubory** (modely, controllery, služby, Blade, Mailables), které vložíš do svého Laravel projektu a otestuješ lokálně/v Dockeru (`docker-compose.yml` už máš).

---

**Stav: Fáze 1 (Eloquent modely) přiložena v `app/Models/`. Po review pokračujeme Fází 2.**
