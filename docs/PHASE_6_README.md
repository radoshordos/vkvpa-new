# Fáze 6 — Routing, hlášení a administrace

Největší fáze, proto rozdělená na podčásti. **Tento balíček = 6a** (jádro). 6b/6c navazují.

## Rozpad fáze

- **6a (teď):** routing (náhrada `index.php?str=`), bezpečné podání hlášení (FormRequest + Eloquent místo `extract`/SQLi/`preg_match`), napojení EDI uploadu na služby z Fáze 5, přepnutí menu/login na `route()`, kostry ostatních stránek.
- **6b:** admin CRUD — `edit_deniky`, `edit_kategorie`, `edit_import`, plný edit/del/confirm hlášení (`Admin\*Controller`).
- **6c:** plná vizuální parita formuláře `edit_hlaseni` (JS dopočty bodů, všechna pole, hlášky 1:1).

## Soubory (6a)

| Soubor | Kam | Účel |
|--------|-----|------|
| `routes/web.php` | `routes/` | pojmenované routy místo `?str=` |
| `app/Http/Controllers/Controller.php` | tamtéž | základní controller |
| `app/Http/Controllers/HlaseniController.php` | tamtéž | formulář + uložení hlášení |
| `app/Http/Requests/StoreHlaseniRequest.php` | tamtéž | **validace** (náhrada extract/preg_match/SQLi) |
| `app/Http/Controllers/EdiController.php` | tamtéž | upload EDI (služby z Fáze 5) |
| `app/Http/Controllers/KolaController.php` | tamtéž | výpis kol |
| `app/Http/Controllers/VysledkyController.php` | tamtéž | kostra výsledků (skóre → Fáze 7) |
| `app/Http/Controllers/Admin/{Deniky,Kategorie,Import}Controller.php` | tamtéž | stuby (6b) |
| `resources/views/pages/*.blade.php` | tamtéž | pohledy |
| `resources/views/partials/menu*.blade.php` (úprava) | tamtéž | odkazy přes `route()` |
| `resources/views/layouts/app.blade.php` (úprava) | tamtéž | banner/login přes route |
| `tests/Feature/HlaseniTest.php` | tamtéž | testy |

## Bezpečnostní opravy v této fázi

| Legacy (edit_hlaseni.php) | Nově |
|---|---|
| `extract($_POST)` (přepis proměnných) | `StoreHlaseniRequest::validated()` – jen povolená pole |
| Poziční `INSERT INTO vkvpa_data VALUES (...)` (křehké, SQLi) | `VkvpaData::create([...])` s pojmenovanými sloupci |
| `$_GET['edit']/['del']/['confirm']` interpolované do SQL | route model binding `{data}` (Eloquent) |
| ruční `preg_match` značky/lokátoru/mailu | validační pravidla (`regex`, `email`) |

## Mapování legacy → routy

| `?str=` | routa (name) | controller |
|---------|--------------|-----------|
| `edit_hlaseni` (default) | `/` , `edit_hlaseni` | `HlaseniController@index` |
| `edit_kola` | `edit_kola` | `KolaController@index` |
| `vysledkova_listina` | `vysledkova_listina` | `VysledkyController@listina` |
| `rocni_vysledky` | `rocni_vysledky` | `VysledkyController@rocni` |
| `read_edi` | `read_edi` | `EdiController@create/store` |
| `edit_deniky`/`edit_kategorie`/`edit_import` | (admin) | `Admin\*Controller` |

## Integrace

1. Vlož controllery, request, views.
2. `routes/web.php` nahraď (volá `require auth.php` z Fáze 4).
3. Ujisti se, že běží Fáze 1 (modely), 4 (auth + `admin` alias) a 5 (EDI služby).
4. `php artisan route:list` → routy odpovídají tabulce výše.

## Testy fáze

```bash
php artisan test --filter=HlaseniTest
```
Pokrývá: validní uložení (+ uppercase značky/lokátoru), nesoulad součinu bodů, duplicita, EDI upload (import + prefill), ochrana admin rout.

## Mimo 6a (záměrně)

- Maily při podání → **Fáze 8** (v controlleru označeno `TODO Fáze 8`).
- Odvození kategorie a skóre z pohledu `vysledky` (po EDI uploadu i ve výsledcích) → **Fáze 7**.
- `map`, `mapb`, `export`, `show_edi` → **Fáze 9** (mapy) / 6b.
