# Fáze 5 — EdiParser (doménové jádro)

Parsování EDI deníku (REG1TEST) z `read_edi.php` vytrženo do čisté, testovatelné služby — bez `echo`, `exit` a míchání s DB/HTML.

## Soubory

| Soubor | Kam | Účel |
|--------|-----|------|
| `app/Services/Edi/EdiQso.php` | tamtéž | VO jednoho spojení (15 polí) |
| `app/Services/Edi/EdiHeader.php` | tamtéž | VO hlavičky + typované přístupy |
| `app/Services/Edi/EdiLog.php` | tamtéž | VO celého deníku |
| `app/Services/Edi/EdiParser.php` | tamtéž | **parser** (čistý, stejný regex i automat) |
| `app/Services/Edi/EdiImportService.php` | tamtéž | zápis do `edihead`/`edilines` (Eloquent, transakce) |
| `app/Exceptions/EdiParseException.php` | tamtéž | chyba parsování (nese vadné řádky) |
| `tests/Unit/EdiParserTest.php` | tamtéž | unit testy parseru |
| `tests/Feature/EdiImportTest.php` | tamtéž | test perzistence |
| `tests/fixtures/sample.edi` | tamtéž | minimální platná fixtura |

## Co zůstalo zachováno (funkcionalita)

- **Identický regex** QSO řádku (15 skupin) i pořadí stavového automatu (`head` → `records` → `remarks` → `end`).
- Odstranění **BOM**, `trim`, **uppercase** QSO řádků, kontrola **deklarovaného počtu** (`[QSORecords;N]`).
- Mapování polí do `edilines` včetně původních „ošklivých" názvů sloupců (`Mode-code`, `Sent QSO number`, `New-WWL-(N)`…).
- E-mail účastníka se (jako v legacy) bere z pole `RHBBS`.

Ověřeno proti reálnému deníku z repa (`source/02OK2KJT.edi`): 121 deklarováno = 121 naparsováno, žádný vadný řádek.

## Co se zlepšilo

- **Žádné `echo`/`exit`** – chyby přes `EdiParseException` (volající rozhodne, jak je zobrazit).
- **Čisté oddělení**: parser nezná DB; zápis je zvlášť, v **transakci** (buď se uloží hlavička i všechna QSO, nebo nic).
- **Testovatelnost**: parser je čistá funkce `string → EdiLog`.

> Pozn.: dřívější domněnka o chybě se `$stl->close()` ve smyčce se po pečlivém čtení nepotvrdila – zakomentované `if` větve tam nechávají osiřelou `}`, která reálně uzavírá `foreach`, takže legacy zápis fungoval. Kód byl ale křehký (míchání parsování, DB a renderu); přepis to rozplétá.

## Mimo tuto fázi (záměrně)

To, co `read_edi.php` dělal po naparsování, patří jinam:
- **Odvození kategorie** (`vkvpa_kategorie` podle pásma/sekce) a **skóre** z pohledu `vysledky` (`lbody`, `lnasobic`, `platnych`) → **Fáze 7** (`ScoringService`).
- **Předvyplnění formuláře** (`require edit_hlaseni.php`) → **Fáze 6** (`EdiController` + Blade).
- Sloupce `edilines.sqr/lon/lat` (pro mapy) → **Fáze 9**.

## Testy fáze

```bash
php artisan test --filter=EdiParserTest   # unit, bez DB
php artisan test --filter=EdiImportTest   # vyžaduje migrace (edihead, edilines)
```

Pro test proti plnému deníku zkopíruj `source/02OK2KJT.edi` do `tests/fixtures/` a v testu nastav `declaredTotal === 121`.
