# Fáze 7 — Vyhodnocení a skóre

Skórování z `vyhodnoceni.php`, `uzavreni.php` a chybějícího DB pohledu `vysledky` převedeno do jedné testovatelné služby.

## Soubory

| Soubor | Kam | Účel |
|--------|-----|------|
| `app/Services/Scoring/ScoringService.php` | tamtéž | pořadí, uzávěrka, skóre deníku, roční výsledky |
| `app/Services/Scoring/EdiScore.php` | tamtéž | DTO skóre z deníku (lbody/lnasobic/platnych) |
| `app/Http/Controllers/Admin/VyhodnoceniController.php` | tamtéž | admin akce vyhodnotit/uzavřít |
| `app/Http/Controllers/VysledkyController.php` (úprava) | tamtéž | listina (řazení dle pořadí) + roční |
| `app/Http/Controllers/EdiController.php` (úprava) | tamtéž | po importu dopočte skóre do prefillu |
| `resources/views/pages/vysledky-listina.blade.php` (úprava) | tamtéž | filtr kola/QRP + pořadí |
| `resources/views/pages/vysledky-rocni.blade.php` (úprava) | tamtéž | roční agregace po kategoriích |
| `resources/views/pages/kola.blade.php` (úprava) | tamtéž | admin akce vyhodnotit/uzavřít |
| `routes/web.php` (úprava) | tamtéž | routy `kolo.vyhodnotit` / `kolo.uzavrit` |
| `tests/Feature/ScoringServiceTest.php` | tamtéž | testy |

## Mapování legacy → služba

| Legacy | Metoda |
|--------|--------|
| `vyhodnoceni.php` (pořadí po kategoriích) | `ScoringService::rankRound()` |
| `uzavreni.php` (nastavení `vyhodnoceno`) | `ScoringService::closeRound()` |
| pohled `vysledky` (lbody/lnasobic/platnych) | `ScoringService::scoreEdi()` |
| `rocni_vysledky.php` (roční součet) | `ScoringService::yearlyResults()` |

Pořadí je **husté** (shodný počet bodů = stejné pořadí 1, 2, 2, 3…), shodně s legací. Roční součet zachovává pravidlo **nulování ne-EDI hlášení od kola ≥ 91**.

## ⚠️ Rekonstrukce pohledu `vysledky`

Původní DB pohled `vysledky` **nebyl v repozitáři ani v dumpu**. `scoreEdi()` ho rekonstruuje z `edilines` s těmito (dokumentovanými) předpoklady:
- platné QSO = řádek s `QSO-Points > 0`,
- `lbody` = součet `QSO-Points`,
- `lnasobic` = počet různých „velkých čtverců" (první 4 znaky `Received-WWL`).

**Ověř proti reálným datům.** Pokud měl původní pohled jinou definici (např. jiné počítání násobičů či duplicit), uprav `scoreEdi()` — logika je na jednom místě a pokrytá testem. Případně lze pohled vytvořit i v DB migrací; preferovaný je ale výpočet v PHP (testovatelný).

## Dokončení Fáze 6

EDI upload (`EdiController`) teď po importu zavolá `scoreEdi()` a předvyplní `bodu_za_qso`, `nasobice`, `body`, `pocet` — to byla část odložená z Fáze 6.

## Testy fáze

```bash
php artisan test --filter=ScoringServiceTest
```
Pokrývá: husté pořadí se shodou, uzávěrku, skóre z fixtury (2 QSO → 5 bodů × 2 čtverce = 10), roční agregaci dle značky.

## Mimo tuto fázi (záměrně)

- Měsíční matice (sloupce 01–12) v ročních výsledcích – zatím agregovaný součet; matici lze doplnit jako zobrazovací detail.
- Schvalování hlášení (`schvaleno`) a kompletní admin CRUD → **Fáze 6b**.
- Odvození kategorie z pásma/sekce při EDI uploadu (`vkvpa_kategorie` LIKE) – lze doplnit do `EdiController`/služby; nebylo součástí skórování.
