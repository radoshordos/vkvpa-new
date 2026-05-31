# VKV Provozní aktiv — migrace dokončena (PHP4 → PHP 8.5 + Laravel 13)

Souhrn všech fází dle `AGENTS.md`. Každá fáze byla malá, testovatelná a zachovávala funkcionalitu i grafický design.

## Přehled fází

| Fáze | Obsah | Klíčové výstupy |
|------|-------|-----------------|
| 1 | Datová vrstva | 9 Eloquent modelů z existujících migrací |
| 2 | Konfigurace a připojení | `.env`/`config/database.php`, sjednocený bridge, konverze kódování (S1, S5, S6) |
| 3 | Layout | Blade `layouts/app` + partials, beze změny vzhledu |
| 4 | Autentizace | Laravel Auth, token login, middleware (S2, S3, S4) |
| 5 | EDI parser | `EdiParser`/`EdiImportService` + value objekty |
| 6 | Routing a hlášení | routy, `HlaseniController`, `StoreHlaseniRequest`, EDI upload |
| 7 | Vyhodnocení a skóre | `ScoringService` (pořadí, uzávěrka, skóre, roční) |
| 8 | Maily | Laravel Mail (2 Mailables), konec PHPMaileru (S7) |
| 9 | Mapy | `MapController` + Leaflet 1.9.4, sjednocení 7× map*.php |
| 10 | Úklid | smazání legacy, odstranění mostu, CI (Pint + PHPStan + testy) |

## Vyřešený bezpečnostní dluh

| # | Problém | Řešeno ve fázi |
|---|---------|----------------|
| S1 | Hardcoded DB hesla | 2 |
| S2 | Hardcoded admin login (`Beda`/`oK1dOz`) | 4 |
| S3 | SQL injection (interpolace `$_GET`/`$_POST`) | 4, 6 |
| S4 | `ereg()` (odstraněno v PHP 7) | 4, 6 |
| S5 | Tajemství v repu (`.env`, privátní klíč) | 2 (untrack), 10 (úklid) |
| S6 | Mix kódování Win-1250/UTF-8 | 2 |
| S7 | File-based stav (`mail.inc`) | 8 |
| — | `extract($_POST)` + poziční INSERT | 6 |

## Testy

```bash
php artisan test
```
Pokrytí: Auth, EdiParser, EdiImport, Hlaseni (validace + upload), ScoringService, HlaseniMail, Maidenhead, Map.

## Ruční kroky před produkcí

1. `composer install`, `php artisan key:generate`, vyplnit `.env` (DB, MAIL, ADMIN_PASS).
2. `php artisan migrate --seed` (data + admin účet z `.env`).
3. Zkopírovat `css/styl.css` do `public/css/`.
4. Registrovat alias middleware `admin` v `bootstrap/app.php`.
5. **Rotovat všechna tajemství** z původního repa.
6. Spustit `tools/cleanup_legacy.sh` po ověření funkčnosti.

## Body k ověření proti reálným datům

- Definice pohledu `vysledky` (rekonstruováno v `ScoringService::scoreEdi`, Fáze 7) — chyběla v dumpu.
- Plnění `edilines.lon/lat` (Fáze 9 dopočítá z lokátoru, pokud chybí).
- Plná vizuální parita formuláře hlášení (Fáze 6c) a admin CRUD (Fáze 6b) — kostry hotové, dotažení dle potřeby.
