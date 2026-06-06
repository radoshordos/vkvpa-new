# Analýza technologického dluhu

> Stav k **2026-06-06**, větev `claude/technical-debt-analysis-HKso4`.
> Hodnoceno: PHP 8.4.19, Laravel 13, ~4 070 řádků PHP v `app/`, 26 testovacích
> souborů, 25 Blade šablon.
>
> Navazuje na audit z 2026-06-05. Mezitím přibyla **vizualizační stránka deníku**
> (`EdiVizualizaceController` – nově největší soubor v `app/`), akce
> `ImportEdiAction`, modernizace na events/jobs a typed config, a accessor metody
> na `Ediline`. Tento dokument přepočítává dluh na **aktuální** stav.

## Shrnutí

Kódová báze zůstává **v nadprůměrné kondici**. Baseline kvality je zelený:

| Nástroj | Výsledek |
|---------|----------|
| PHPUnit | **161 testů / 507 asercí – vše prochází** |
| PHPStan (Larastan) | **level 10 – 0 chyb** |
| Pint (`laravel` preset) | **bez nálezů** |

Celý kód má `declare(strict_types=1)`, doménová logika (parsování, bodování) je
oddělená od HTTP vrstvy a dobře otestovaná, konfigurace je centralizovaná v
`config/vkvpa.php` + typovém fasádovém `VkvpaSettings`. Z minulého auditu jsou
**rychlé výhry hotové** a P1 je z poloviny vyřešená (akce vyjmuta).

Nově ale **vizualizační feature přinesla vlastní dluh** – duplikuje logiku
`MapController`, obsahuje jeden tichý bug v bodování a nemá testy. To je dnes
nejvýznamnější otevřený bod (D1–D3 níže).

Dluh je seřazen podle poměru dopad/náklad.

---

## D1 – Duplikace mapové/vizualizační logiky (nově nejvýznamnější)

**Kde:** `app/Http/Controllers/EdiVizualizaceController.php` (239 ř.) a
`app/Http/Controllers/MapController.php` (175 ř.).

Vizualizační stránka znovu implementuje **prakticky stejný kód**, který už má
`MapController`:

| Logika | MapController | EdiVizualizaceController |
|--------|---------------|--------------------------|
| dotaz `lines()` v závodním okně + dopočet lat/lon ze středu lokátoru | `points()` ř. 99–142 | `enrichedLines()` ř. 53–101 |
| výpočet `dist` (haversine) a `azimut` (bearing) | ř. 127–128 | ř. 78–79 |
| agregace velkých čtverců (4 znaky) s počtem QSO + střed | `squares()` ř. 149–174 | `squares()` ř. 104–128 |

Metoda `squares()` je v obou souborech **téměř znak po znaku totožná**. Resolving
souřadnic protistanice (lat/lon → fallback `Maidenhead::toLatLon` → skip + log) je
zkopírovaný také.

**Riziko:** stejný typ tichého rozdílu jako u starého P1 – oprava v jedné mapě se
neprojeví ve druhé a testy to nechytí (mapy mají jen render-testy, vizualizace
nemá test žádný – viz D3).

**Náprava:** vyjmout sdílené jádro do služby, např.
`app/Services/Edi/QsoGeometry.php` s metodami `enrichedQsos(Edihead, $home)` a
`bigSquares(Edihead)` vracejícími typované value objekty / `Collection`. Oba
controllery (i případné budoucí mapy) z ní jen čtou. Odhad: ~2 h.

---

## D2 – Tři různé způsoby výpočtu bodů za QSO + tichý bug ✅ vyřešeno

Body za jedno spojení se počítaly **na třech místech třemi způsoby**:

| Místo | Původně | |
|-------|---------|---|
| `ScoringService::scoreEdi()` | vždy `Maidenhead::qsoPoints($home, $sq)` | ← kanonické (z lokátorů) |
| `MapController::points()` | vždy `$l->qsoPoints()` (sloupec `QSO-Points` z deníku) | ← jiná hodnota! |
| `EdiVizualizaceController::enrichedLines()` | `Maidenhead::qsoPoints()` s rozbitým fallbackem | ← bug |

Dva problémy:

1. **Nekonzistence:** mapa „N" (špendlíky) ukazovala v popupu body z deníku
   (`QSO-Points`), zatímco skóre i vizualizace přepočítávaly z lokátorů. CLAUDE.md
   přitom výslovně říká, že **sloupec `QSO-Points` se má ignorovat**.

2. **Bug** (`EdiVizualizaceController.php`, původně ř. 83–84):
   ```php
   $points = preg_match('/^[A-R]{2}\d{2}$/', $homeSq) !== false
       && preg_match('/^[A-R]{2}\d{2}$/', $workedSq) !== false ? … : $l->qsoPoints();
   ```
   `preg_match()` vrací `1` / `0` / `false`; `!== false` je pravdivé i pro `0`, takže
   podmínka byla prakticky vždy `true` a fallback `: $l->qsoPoints()` byl **mrtvý kód**.

**Náprava (provedeno):** všechna tři místa teď počítají body výhradně přes
`Maidenhead::qsoPoints($homeSq, $workedSq)` (neplatný lokátor → 0). Rozbitá
podmínka i mrtvý fallback odstraněny, mapa „N" teď ukazuje stejné body jako skóre.
Sloupec `QSO-Points` se nikde nečte. Zbývající plnou deduplikaci řeší D1.

---

## D3 – Vizualizace bez testů

**Kde:** `EdiVizualizaceController` (239 ř., **největší soubor v `app/`**) nemá
žádný testovací soubor (`tests/Feature/EdiVizualizace*` neexistuje).

Obsahuje netriviální čistou logiku, kterou se vyplatí pokrýt unit-testy: bucketing
časové osy (`timeline()`, 15min intervaly 08:00–11:00), azimutová růžice
(8 sektorů), histogram vzdáleností, agregace statistik. Tyhle metody jsou ideální
kandidáti na rychlé, levné testy – a chytily by i bug z D2.

**Náprava:** feature-test na render `/edi/{head}/vizualizace` + unit-testy na
`timeline/azimuthRose/distHistogram` (nejlépe až budou v sdílené službě z D1).
Odhad: ~1 h.

---

## P1 – Duplikace EDI import pipeline (z poloviny vyřešeno)

**Kde:** `app/Actions/ImportEdiAction.php` vs.
`app/Http/Controllers/Admin/ImportController.php::importFile()` (ř. 95–161).

**Pokrok od minula:** jádro příjmu deníku bylo vyjmuto do `ImportEdiAction`
a **jednotlivý** upload (`EdiController::store()`) ho už používá (ř. 56). 👍

**Co zůstává:** **hromadný** import (`ImportController::importFile()`)
`ImportEdiAction` **nevyužívá** – má vlastní kopii celého toku:

1. validace shody `TDate` s daty QSO (ř. 106–112) ↔ `ImportEdiAction::assertTDateMatchesQsos()`,
2. `koloForTDate()` (ř. 114),
3. kontrola duplicity (ř. 116),
4. `CategoryResolver::resolve()` + odchyt `UnknownBandException` (ř. 120–124),
5. `import()` + `scoreEdi()` (ř. 126–131),
6. **stejný `VkvpaData::create([...])` payload** (ř. 133–150).

Drobná nekonzistence: `ImportController` testuje duplicitu přes
`->where('EDI', true)`, zatímco `ImportEdiAction` přes scope `->hasEdi()`
(stejný dotaz, dvě formy).

**Náprava:** `ImportEdiAction::execute()` zobecnit tak, aby místo vyhazování
výjimek mohl vracet i strukturovaný výsledek (ok | skip | error), a hromadný import
ho jen volal ve smyčce a mapoval na řádek souhrnu. Payload `VkvpaData::create`
existuje pak jen jednou. Odhad: ~1–1,5 h.

---

## P3 – Fragmentace migrací schématu (otevřeno, mírně narostlo)

**Kde:** `database/migrations/`

Základní schéma je z `2026_05_29`, ale následují **čtyři** pozdější úpravy:

- `2026_06_03_000001_add_missing_indexes`
- `2026_06_03_000002_fix_database_integrity`
- `2026_06_05_000001_add_performance_indexes`
- `2026_06_05_000002_create_diskuse_table`

Indexy se spravují na třech místech a `fix_database_integrity` (mj. odstraňuje
redundantní `nullable()` a opravuje `kod` na UNIQUE) napovídá, že základní migrace
nebyly úplné. Pro projekt, který **ještě není v produkci**, je čistší tyto úpravy
zapsat zpět do `create_*` migrací. Drobnost: `bodu_za_qso` má v základní migraci
`->default(1)` (ostatní skóre `0`) – nekonzistentní výchozí hodnota.

> Pozn.: pokud schéma **už běží v produkci**, konsolidaci migrací **nedělat** –
> je to po nasazení anti-vzor. Před zásahem ověřit.

---

## P6 – Legacy schéma s nestandardními názvy sloupců (z velké části zkroceno)

**Pokrok od minula:** `Ediline` má teď **accessor vrstvu** –
`receivedWwl()`, `qsoPoints()`, `newWwl()` (ř. 52–65) – která je jediným
správným bodem přístupu k magickým stringům `Received-WWL`, `QSO-Points`,
`New-WWL-(N)`. `ScoringService` i `MapController` je už používají. 👍

**Vyřešeno:** poslední únik mimo accessory (`$l->{'Mode-code'}` ve
`EdiVizualizaceController`) je zacelen – `Ediline` má teď `modeCode(): int` a
controller ho používá. Magický `$line->{'...'}` přístup je tak soustředěný
**výhradně** v modelu `Ediline`. (Volitelný další krok: nahradit `int` enumem
SSB/CW.)

Zbytek dluhu je **vědomě přijatý**: kvůli kompatibilitě nelze zapnout
`preventAccessingMissingAttributes` (komentář v `AppServiceProvider:35`), takže
překlep v názvu legacy sloupce neodhalí PHPStan ani runtime. Accessor vrstva
plochu rizika dál zužuje.

---

## Drobnosti ✅ vyřešeno

- **Duplikovaný dotaz „průběžné výsledky"** – sloučen do scope
  `VkvpaData::prubezne($idKola, $idKategorie)`; `HlaseniController` i
  `VysledkyController::pribezne()` ho teď oba volají.
- **Hardcoded limity v `ImportController`** – `max:20480` a `$limit = 200`
  přesunuty do `config/vkvpa.php` (`import_max_size_kb`, `import_max_files`)
  a čteny přes `VkvpaSettings::importMaxSizeKb()` / `importMaxFiles()`.

---

## Co je naopak v pořádku (a nesahat na to)

- **Bodovací logika** (`ScoringService`, `Maidenhead`, `ContestWindow`) – čistá,
  konfigurovatelná, dobře pokrytá testy.
- **Cache ročních výsledků** – `Cache::flexible` s **cílenou** invalidací v
  `rankRound()`; korektní stale-while-revalidate.
- **Vazba `CategoryResolver` na ID ze seederu** – hardcoded matice ID kategorií je
  **hlídaná** příkazem `php artisan` `ValidateCategoryMatrix` a testem
  `CategoryResolverValidationTest`, takže rozejití se seedem se odhalí.
- **Value objekty** EDI (`EdiLog`, `EdiHeader`, `EdiQso`) – neměnné, bez DB/IO.
- **Bilingvní vrstva** `lang/cs` + `lang/en` – kompletní.
- **Centralizovaná konfigurace** `config/vkvpa.php` + typový `VkvpaSettings`.

---

## Vyřešeno od minulého auditu (2026-06-05)

| Bod | Stav |
|-----|------|
| P2 – kolidující prefix migrací (`…000001`) | ✅ `create_diskuse` přečíslováno na `…000002` |
| P4 – mrtvý `welcome.blade.php` | ✅ smazáno |
| P5 – rozjetá dokumentace verzí (PHP 8.4/8.5, PHPStan 9/10) | ✅ sjednoceno na 8.4 / level 10 |
| P1 (část) – jádro importu | ✅ vyjmuto do `ImportEdiAction`, používá `EdiController` |
| P6 (část) – přístup k legacy sloupcům | ✅ accessory na `Ediline` |

### Vyřešeno v této větvi (`claude/technical-debt-analysis-HKso4`)

| Bod | Stav |
|-----|------|
| D2 – výpočet bodů sjednocen + bug `preg_match` | ✅ všude `Maidenhead::qsoPoints`, fallback/bug odstraněn |
| P6 (zbytek) – `Mode-code` leak | ✅ accessor `Ediline::modeCode()` |
| Drobnost – duplikovaný dotaz „průběžné" | ✅ scope `VkvpaData::prubezne()` |
| Drobnost – hardcoded import limity | ✅ přesunuto do `config/vkvpa.php` |

---

## Doporučené pořadí prací

| # | Úkol | Náklad | Dopad | Stav |
|---|------|--------|-------|------|
| 1 | D2 – sjednotit výpočet bodů + bug `preg_match` | triviální | **korektnost** | ✅ hotovo |
| 2 | P6 (zbytek) + drobnosti (dotaz, limity do configu) | nízký | čistota | ✅ hotovo |
| 3 | D1 – vyjmout sdílenou `QsoGeometry` z Map/Vizualizace controllerů | střední | **odstranění duplikace** | otevřeno |
| 4 | D3 – testy pro vizualizaci | nízký | regrese | otevřeno |
| 5 | P1 – hromadný import přes `ImportEdiAction` | střední | dokončení dedup | otevřeno |
| 6 | P3 – konsolidace migrací (jen pokud není v produkci) | střední | údržba schématu | otevřeno |

Rychlé výhry (1–2) jsou hotové. Zbývají tři strukturální body: **D1** (sdílená
geometrická služba) a **P1** (dokončení `ImportEdiAction`) odstraní poslední dvě
velká místa kopírovaného kódu; **D3** je levné dotestování vizualizace, nejlépe až
po D1. P3 je volitelné a jen mimo produkci.
