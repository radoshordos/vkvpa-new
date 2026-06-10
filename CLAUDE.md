# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

VKV Provozní aktiv – a Laravel 13 / PHP 8.4 web application for managing amateur radio VHF contest log submissions, EDI file parsing, scoring, and results display. The domain language is Czech/Slovak.

## Commands

```bash
composer setup      # first-time setup: install deps, copy .env, migrate, npm build
composer dev        # dev server: php artisan serve + queue + pail logs + vite (all concurrently)
composer test       # run PHPUnit test suite (clears config cache first)
composer stan       # PHPStan level-10 static analysis (Larastan)
composer lint       # Pint code-style check (--test, no writes)
./vendor/bin/pint   # auto-fix code style

# Run a single test file or filter:
php artisan test --filter EdiParserTest
php artisan test tests/Unit/EdiParserTest.php
```

Tests use SQLite in-memory (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`). No running database is needed for tests.

Docker: `docker compose up -d` starts MySQL + web; then run `docker compose exec web composer setup`.

## Architecture

### EDI Import Pipeline

The core flow for submitting a contest log:

1. `EdiParser::parse(string)` → `EdiLog` (value object with `EdiHeader` + `EdiQso[]` + raw source)
2. `EdiImportService::import(EdiLog)` → writes to `edihead` + `edilines` tables in one transaction
3. `ScoringService::scoreEdi(Edihead)` → computes `EdiScore` (pocet × nasobice = body)
4. `EdiController::store()` orchestrates steps 1–3, creates a `VkvpaData` row linked via `edihead_id` (nullable FK; `NULL` = entry without an EDI log)

EDI files may arrive as Windows-1250; `EdiParser` converts via `iconv` before processing. Real-world fixture EDI files live in `resources/edi/` and are used in unit tests.

### Database: Two Schemas

**EDI schema** (`edihead`, `edilines`): derived from the original system but fully normalized to `snake_case` column names (`mode_code`, `received_wwl`, `qso_points`, `t_date`, `p_call`, etc.) — accessed as ordinary Eloquent attributes, no magic-string `$line->{'...'}` access and no `property.notFound` suppressions. `Ediline` exposes a few PHP 8.4 property hooks (`receivedWwl`, `qsoPoints`, `modeCode`, `mode`, `newWwl`) that normalize/cast the raw columns. Both models carry `#[WithoutTimestamps]` since they have custom time columns (`stamp`, `d_cas`). The original SQL dump (with the old dash column names) is kept for provenance only in `database/source_sql/` and is excluded from PHPStan/Pint.

**Application schema** (`vkvpa_*` tables): `VkvpaData` (contest entry/result row), `VkvpaKola` (contest round), `VkvpaKategorie` (category), `VkvpaPrihlaseni`, `Prispevek` (discussion).

### Scoring Formula

`ScoringService::scoreEdi()` implements the contest rules:
- Count QSOs within the contest window (`ContestWindow::from()` = `'0800'`, `to()` = `'1100'` UTC) on the contest day taken from `TDate`; QSOs outside the window or day score 0
- QSOs to the station's own big square (first 4 chars of `PWWLo`) **are** counted — `pocet` includes them
- `boduZaQso` = sum of per-QSO points recomputed from locators via `Maidenhead::qsoPoints()` (own big square = 2 points, +1 for each further ring of big squares); the EDI `QSO-Points` column is ignored
- `nasobice` = count of distinct big squares including the home square (home always counts, even if not worked)
- `body = boduZaQso × nasobice`

`ScoringService::rankRound()` assigns dense ranks within each category of a round.

`NON_EDI_NULLIFY_FROM_KOLO = 91`: entries without an EDI file are counted as 0 points in yearly totals for rounds ≥ 91.

`RankRoundJob` runs synchronously (`dispatchSync`) so ranking and cache invalidation never depend on a queue worker; every mutation of a ranked entry (admin toggle/delete/edit, round evaluation) must dispatch it.

### Caching

`config/cache.php` keeps Laravel's secure default `serializable_classes => false` — objects are never unserialized from cache, so **only plain arrays/scalars may be cached** (a cached Eloquent collection comes back as `__PHP_Incomplete_Class`). Two application caches exist:
- Yearly results (`ScoringService::yearlyResults()`): `Cache::flexible` over attribute arrays, rehydrated via `VkvpaData::hydrate()`; invalidated by `rankRound()`. A round belongs to the year of its `datum_konani` (not the year in `nazev`).
- "All round stations" map layer (`QsoGeometry::roundStations()`): per-round TTL cache (`vkvpa.round_stations_cache_ttl`); no targeted invalidation needed because the layer is only disclosed after the round closes, when the data no longer changes.

### Map Views

The map lives on the visualization page (`/edi/{head}/vizualizace`, `EdiVizualizaceController`) as four switchable Leaflet layers (no separate map routes — they were removed for UX simplicity):
- `crk` (default) – combined view (rays + mode pins + distance circles + locator grid + all round stations)
- `jezek` – lines from home station to all worked stations
- `spendliky` – pins per QSO with distance/bearing popup
- `lokatory` – big squares (4-char Maidenhead) with QSO counts

The `crk` "all round stations" layer (`QsoGeometry::roundStations()`) is withheld until the round is closed/evaluated (`KoloStav`) to avoid leaking competitors' logs during reception. Each Leaflet map also has a fullscreen toggle (`resources/js/leaflet-fullscreen.js`).

`Maidenhead` support class handles locator→lat/lon conversion, distance (haversine), and bearing.

### Authentication

Session-based with two entry points:
- Standard login form at `/login`
- Token login at `/login/token/{kod}` (one-time alphanumeric code, TTL = `vkvpa.token_ttl_days` = 5 days)

Admin routes are protected by `EnsureAdmin` middleware (`middleware('admin')`). `User::is_admin` boolean flag.

### Navigation Config

Menu structure is declared in `config/navigation.php` (not hard-coded in Blade). Keys map to named routes; used by `resources/views/partials/menu.blade.php`.

### Mail

Two Mailable classes: `HlaseniPrijato` (confirmation to contestant) and `HlaseniProVyhodnocovatele` (notification to contest judges). Email address in footer is rendered as an image via `MailImageController` (obfuscation against scrapers).

### Code Style

All files use `declare(strict_types=1)`. PHPStan runs at level 10. Pint enforces code style. CI runs tests + stan + lint + frontend build in one job.
