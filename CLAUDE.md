# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

VKV Provozní aktiv – a Laravel 13 / PHP 8.4 web application for managing amateur radio VHF contest log submissions, EDI file parsing, scoring, and results display. The domain language is Czech/Slovak.

## Commands

```bash
composer setup      # first-time setup: install deps, copy .env, migrate, npm build
composer dev        # dev server: php artisan serve + queue + pail logs + vite (all concurrently)
composer test       # run PHPUnit test suite (clears config cache first)
composer stan       # PHPStan level-9 static analysis (Larastan)
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
4. `EdiController::store()` orchestrates steps 1–3, creates a `VkvpaData` row, and stores `EDI_ID`

EDI files may arrive as Windows-1250; `EdiParser` converts via `iconv` before processing. Real-world fixture EDI files live in `resources/edi/` and are used in unit tests.

### Database: Two Schemas

**Legacy schema** (`edihead`, `edilines`): preserved verbatim from the original system. Columns use non-standard PHP identifiers (`Mode-code`, `Received-WWL`, `Sent QSO number`, etc.). Access these via `$line->{'Received-WWL'}` syntax. PHPStan's `property.notFound` is suppressed for files that use these columns (`ScoringService`, `MapController`). Both models carry `#[WithoutTimestamps]` since they have custom time columns (`stamp`, `d_cas`).

**Application schema** (`vkvpa_*` tables): `VkvpaData` (contest entry/result row), `VkvpaKola` (contest round), `VkvpaKategorie` (category), `VkvpaPrihlaseni`, `VkvpaDiskuse`, `VkvpaConfig`.

### Scoring Formula

`ScoringService::scoreEdi()` implements the contest rules:
- Count QSOs within the contest window (`ContestWindow::FROM = '0800'`, `TO = '1100'` UTC)
- Exclude QSOs to the station's own big square (first 4 chars of `PWWLo`)
- `body = pocet × nasobice` where `pocet` = total qualifying QSOs, `nasobice` = unique foreign big squares + 1

`ScoringService::rankRound()` assigns dense ranks within each category of a round.

`NON_EDI_NULLIFY_FROM_KOLO = 91`: entries without an EDI file are counted as 0 points in yearly totals for rounds ≥ 91.

### Map Views

Three Leaflet-based map views per Edihead (routes `/edi/{head}/mapa/*`):
- `jezek` – lines from home station to all worked stations
- `spendliky` – pins per QSO with distance/bearing popup
- `lokatory` – big squares (4-char Maidenhead) with QSO counts

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

All files use `declare(strict_types=1)`. PHPStan runs at level 9. Pint enforces code style. CI runs tests + stan + lint + frontend build in one job.
