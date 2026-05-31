# Fáze 9 — Mapy

Sedm duplicitních `map*.php` (OpenLayers 4.6.4 + různé verze Leafletu 0.7.7/1.3.1/1.9.4) sjednoceno do jednoho řešení.

## Volba knihovny

Sjednoceno na **Leaflet 1.9.4** — aktuální stabilní verze (Leaflet 2.0 je zatím jen alpha; ověřeno 5/2026). OpenLayers (dnes stabilní 10.x) byl ve variantách `map.php/map2.php/mape.php` ve verzi 4.6.4; tu řešení odstraňuje. Leaflet je lehčí a většina novějších variant ho už používala.

## Soubory

| Soubor | Kam | Účel |
|--------|-----|------|
| `app/Support/Maidenhead.php` | tamtéž | převod QTH lokátoru → lat/lon (náhrada `myc`) |
| `app/Http/Controllers/MapController.php` | tamtéž | data mapy z `edilines` (sjednocuje map*.php) |
| `resources/views/pages/map.blade.php` | tamtéž | Leaflet 1.9.4: markery + spojnice |
| `routes/web.php` (úprava) | tamtéž | routa `edi.mapa` (`/edi/{head}/mapa`) |
| `tests/Unit/MaidenheadTest.php` | tamtéž | testy převodníku |
| `tests/Feature/MapTest.php` | tamtéž | test stránky mapy |

## Co zůstalo zachováno

- QTH stanice (z `PWWLo`) + markery spojení (z `edilines.lon/lat`, případně dopočet z `Received-WWL`).
- Spojnice z QTH ke každému spojení.
- Soutěžní okno `Time` 08:00–11:00 (legacy `time BETWEEN 0800 and 1100`) – jako konstanty v controlleru.
- Popupy s volačkou, lokátorem a body.

## Modernizace

- Z 7 souborů → 1 controller + 1 Blade + 1 čistý převodník (testovatelný).
- Žádné inline `mysqli` ani interpolace do SQL – Eloquent přes route model binding `{head}` (PK `ID`).
- Leaflet z CDN s `integrity` hashem; dlaždice z `https://tile.openstreetmap.org`.
- Lokátor → souřadnice je odděleno do `Maidenhead` (lze použít i jinde, např. dopočet `edilines.sqr/lon/lat`).

## Testy fáze

```bash
php artisan test --filter=MaidenheadTest   # unit (převodník)
php artisan test --filter=MapTest          # mapa z fixtury (2 spojení)
```

## K odstranění (Fáze 10)

`map.php`, `map2.php`, `mapb.php`, `mapb2.php`, `mapc2.php`, `mapd.php`, `mape.php`, adresáře `maptest/`, `maptest2/`, a lokální `leaflet/` assety. Nahrazeno výše uvedeným.

## Pozn. k souřadnicím v `edilines`

Sloupce `lon/lat` byly v legacy plněny jinde (mimo `read_edi.php`). Pokud je import (Fáze 5) neplní, `MapController` je nyní dopočítá z `Received-WWL` přes `Maidenhead`. Volitelně lze dopočet přesunout do `EdiImportService` a sloupce naplnit při importu.
