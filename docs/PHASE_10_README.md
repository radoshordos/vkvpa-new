# Fáze 10 — Úklid

Závěrečná fáze: odstranění legacy souborů, legacy session mostu a zavedení CI (PSR-12 + statická analýza + testy).

## Soubory

| Soubor | Kam | Účel |
|--------|-----|------|
| `tools/cleanup_legacy.sh` | `tools/` | `git rm` všech legacy PHP4 souborů, adresářů a tajemství |
| `pint.json` | kořen | code style PSR-12 (Laravel Pint) |
| `phpstan.neon` | kořen | statická analýza (level 6) |
| `.github/workflows/ci.yml` | tamtéž | CI: Pint + PHPStan + testy na PHP 8.5 |
| `resources/views/layouts/app.blade.php` (úprava) | tamtéž | admin přes `auth()` (most odstraněn) |
| `app/Http/Controllers/Auth/AuthController.php` (úprava) | tamtéž | bez `bridgeLegacySession()` |
| `resources/views/pages/kola.blade.php` (úprava) | tamtéž | admin přes `auth()` |

## Odstranění legacy session mostu

Most `session('prihlasen')` z Fáze 4 (který držel naživu legacy stránky) je odstraněn — admin stav se nyní určuje výhradně přes `auth()->user()->is_admin`. To je možné právě teď, kdy už legacy `.php` soubory mizí.

## Co smazat (provede `cleanup_legacy.sh`)

- **Infrastruktura:** `index.php`, `connect*.php`, `head.php`, `menu.php`, `mernuback.php`, `bottom.php`, `login.php`, `logout.php`
- **Hlášení/admin:** `edit_*.php`, `nova_kola.php`, `import.php`, `export.php`, `show_edi.php`, `read_edi.php`
- **Výsledky:** `vysledkova_listina.php`, `rocni_vysledky.php`, `vyhodnoceni.php`, `uzavreni.php`, `vysledky.php`
- **Maily:** `mail*.php`, adresář `phpmailer/`
- **Mapy:** `map*.php`, adresáře `maptest/`, `maptest2/`, `leaflet/`
- **Zbytky:** `qthstat.php`, `qthstst.php`, `test.php`, `I_0005319956.php`
- **Tajemství (untrack + rotovat):** `.env`, `.idea/private_key`, `mail.inc`

## CI / nástroje

Doplň vývojové závislosti:
```bash
composer require --dev laravel/pint phpstan/phpstan larastan/larastan
```
Lokální kontrola před commitem:
```bash
vendor/bin/pint --test          # PSR-12
vendor/bin/phpstan analyse       # statická analýza
php artisan test                 # všechny testy fází 1–9
```

> Pozn.: `phpstan.neon` ignoruje „undefined property" u modelů kvůli sloupcům s nestandardními názvy (`Mode-code`, `Sent QSO number`…). Pro plnou typovou přesnost lze později doplnit anotace `@property` nebo accessory.

## Doporučené pořadí dokončení

1. Nasaď a otestuj Laravel verzi paralelně s legacy.
2. Až vše sedí, spusť `tools/cleanup_legacy.sh`, zkontroluj `git status`, spusť testy a Pint/PHPStan.
3. Commitni a **rotuj všechna tajemství** (hesla, privátní klíč) — byla v git historii.
4. Volitelně pročisti i historii (git filter-repo / BFG), pokud má repo zůstat sdílený.
