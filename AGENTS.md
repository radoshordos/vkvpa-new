# AGENTS.md

This file provides guidance to Codex (Codex.ai/code) when working with code in this repository.

## Project

VKV Provozní aktiv – a Laravel 13 / PHP 8.5 web application for managing amateur radio VHF contest log submissions, EDI file parsing, scoring, and results display. The domain language is Czech/Slovak.

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

Deploy is split so each step runs in the right environment: `composer deploy:assets` builds the frontend (needs Node — run on the host), `composer deploy:php` runs the artisan cache/migrate/queue steps (must run **inside the web container** so `config:cache`/`storage:link` bake the container's `DB_HOST=db` and `/var/www/html` paths, not the host's). `composer deploy` chains both and is only for a single-host (non-Docker) deploy. Under Docker: `composer deploy:assets` on the host, then `docker compose exec web composer deploy:php`.

## Architecture

### EDI Import Pipeline

The core flow for submitting a contest log:

1. `EdiParser::parse(string)` → `EdiLog` (value object with `EdiHeader` + `EdiQso[]` + raw source)
2. `EdiImportService::import(EdiLog)` → writes to `edi_head` + `edi_lines` tables in one transaction
3. `ScoringService::scoreEdi(Edihead)` → computes `EdiScore` (pocet × nasobice = body)
4. `EdiController::store()` orchestrates steps 1–3, creates a `VkvpaData` row linked via `edihead_id` (nullable FK; `NULL` = entry without an EDI log)

EDI files may arrive as Windows-1250; `EdiParser` converts via `iconv` before processing. Real-world fixture EDI files live in `resources/edi/` and are used in unit tests.

### Database: Two Schemas

**EDI schema** (`edi_head`, `edi_lines`): derived from the original system but fully normalized to `snake_case` column names (`mode_code`, `received_wwl`, `qso_points`, `t_date`, `p_call`, etc.) — accessed as ordinary Eloquent attributes, no magic-string `$line->{'...'}` access and no `property.notFound` suppressions. `Ediline` exposes a few PHP 8.4 property hooks (`receivedWwl`, `qsoPoints`, `modeCode`, `mode`, `newWwl`) that normalize/cast the raw columns. Both models carry `#[WithoutTimestamps]` since they have custom time columns (`stamp`, `d_cas`). The historical dataset now lives only as seeder snapshots (see *Seeding* below); the original Adminer SQL dumps were converted and removed.

**Application schema** (`vkvpa_*` tables): `VkvpaData` (contest entry/result row), `VkvpaKola` (contest round), `VkvpaKategorie` (category), `VkvpaPrihlaseni`, `Prispevek` (discussion).

One migration per table: each `create_*` migration holds the table's final schema including its outgoing foreign keys, ordered so referenced tables are created first. FKs are added in a `DB::getDriverName() !== 'sqlite'` guard (SQLite can't `ALTER TABLE ADD FOREIGN KEY` and the test DB runs without them — integrity is enforced by the app + tests there); `edi_lines` is the exception, declaring its FK inline since it works in `CREATE TABLE`.

### Seeding

`DatabaseSeeder` → `SampleDatabaseSeeder` (historical snapshot) + `AdminUserSeeder` (admin from `ADMIN_USER`/`ADMIN_PASS` env, not from a file). Per-table seeders extend `JsonTableSeeder`, which truncates then bulk-inserts in chunks of 500.

The large tables (`edi_head`, `edi_lines`, `vkvpa_data`) ship as gzipped newline-delimited JSON snapshots in `database/seeders/data/{table}.jsonl.gz` — `JsonTableSeeder` streams them line by line via the `compress.zlib://` wrapper, so seeding stays low-memory regardless of size. Small/static tables either keep a plain `{table}.json` array (still supported as a fallback) or inline their rows directly in the seeder's `rows()` (e.g. `PrefixesTableSeeder`, `VkvpaKolaTableSeeder`). The former Adminer SQL dumps were one-off converted into these snapshots and deleted, along with the `legacy:import` command that imported them.

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

### Round Lifecycle (`KoloStav`)

`VkvpaKola::stav()` derives the phase from time (`datum_konani`, `datum_uzaverky`) plus the `vyhodnoceno` timestamp: `Nadchazejici → Aktivni → Prijem → Uzavrene → Vyhodnocene`. There is **no manual "close round" action** — the transition into `Vyhodnocene` (setting `vyhodnoceno`) is automatic, gated by `VkvpaKola::maBytVyhodnoceno()`: the round must be past reception (`Uzavrene`) **and** either every entry is taken over (`schvaleno = true`) or the fallback window elapsed (`vkvpa.finalize_fallback_days` = 20 days after `datum_uzaverky`). `ScoringService::finalizeIfDue()` performs it (ranks + sets `vyhodnoceno`); it's invoked both by the daily `kola:finalize-evaluated` command and inline in `ZaznamController::update()` when an admin takes over the last entry after the deadline. Per-entry "převzetí" is the `VkvpaData.schvaleno` flag (button "P"); un-taking-over is only allowed while the round still accepts reports (`prijimaHlaseni()`, i.e. between `datum_konani` and `datum_uzaverky`) — after the deadline an entry can only be edited (which recomputes points).

### Caching

`config/cache.php` keeps Laravel's secure default `serializable_classes => false` — objects are never unserialized from cache, so **only plain arrays/scalars may be cached** (a cached Eloquent collection comes back as `__PHP_Incomplete_Class`). Two application caches exist:
- Yearly results (`ScoringService::yearlyResults()`): `Cache::flexible` over attribute arrays, rehydrated via `VkvpaData::hydrate()`; invalidated by `rankRound()`. A round belongs to the year of its `datum_konani` (not the year in `nazev`).
- "All round stations" map layer (`QsoGeometry::roundStations()`): per-round TTL cache (`vkvpa.round_stations_cache_ttl`); no targeted invalidation needed because the layer is only disclosed after the round closes, when the data no longer changes.

### Map Views

The map lives on the visualization page (`/edi/{head}/vizualizace`, `EdiVizualizaceController`) as five switchable Leaflet layers (no separate map routes — they were removed for UX simplicity):
- `playback` (default) – QSOs appear chronologically, driven by a time slider + play button
- `crk` – combined view (rays + mode pins + distance circles + locator grid + all round stations)
- `jezek` – lines from home station to all worked stations
- `spendliky` – pins per QSO with distance/bearing popup
- `lokatory` – big squares (4-char Maidenhead) with QSO counts

Chart aggregations (timeline with multipliers, weighted azimuth rose, points per square, season trend, tempo, mode stats, uncounted QSOs, TOP ODX) live in the `DenikStatistiky` service; the visualization page renders all of them. The former "Vizuální inkubátor" page was removed — everything graphical lives on the visualization page.

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

Two Mailable classes: `HlaseniPrijato` (confirmation to contestant) and `HlaseniProVyhodnocovatele` (notification to contest judges). Email address in footer is rendered as an image via `MailImageController` (obfuscation against scrapers; only texts from `vkvpa.mail_image_allowlist` are rendered, anything else is 404).

### CSP (Content Security Policy)

`SecurityHeaders` middleware sends a nonce-based CSP — `script-src` has **no** `'unsafe-inline'` (it does carry `'unsafe-eval'`, which Livewire 4 / Alpine require to evaluate `wire:*` directive expressions — do not remove it or interactive components break). Every inline `<script>` in Blade MUST carry the `@cspNonce` directive (`<script @cspNonce>`), otherwise the browser silently blocks it. Inline event handler attributes (`onclick=`, `onchange=`, …) are blocked by CSP entirely — use `data-*` attributes + listeners (global delegated handlers `[data-autosubmit]` and `[data-file-zone]` live in `resources/js/app.js`). `@vite` and `@livewireScripts` pick the nonce up automatically from `Vite::cspNonce()`.

### Code Style

All files use `declare(strict_types=1)`. PHPStan runs at level 10. Pint enforces code style. CI runs tests + stan + lint + frontend build in one job.
