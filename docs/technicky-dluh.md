# Analýza technologického dluhu

> Stav k **2026-06-06**, větev `claude/technical-debt-analysis-HKso4`.
> Hodnoceno: PHP 8.4.19, Laravel 13, ~4 070 řádků PHP v `app/`, 26 testovacích
> souborů, 25 Blade šablon.
>
> Navazuje na audit z 2026-06-05. Mezitím přibyla **vizualizační stránka deníku**
> (`EdiVizualizaceController` – nově největší soubor v `app/`), akce
> `ImportEdiAction`, modernizace na events/jobs a typed config, a accessor metody
> na `Ediline`. Tento dokument přepočítává dluh na **aktuální** stav.
>
> **Aktualizace 2026-06-29:** projekt nově cílí na **PHP 8.5** (`composer.json: ^8.5`,
> kód využívá pipe operátor `|>`) – původní hodnocení proběhlo ještě na 8.4.19.
> Aktuální baseline: **522 testů / 1848 asercí**, PHPStan level 10 bez chyb, Pint
> čistý. Tabulky `edi_category`/`edi_head` byly přejmenovány na Laravel konvenci
> (`edi_categories`/`edi_heads`); model FK sloupce a casing tříd řeší navazující práce.

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

## D1 – Duplikace mapové/vizualizační logiky ✅ vyřešeno

`EdiVizualizaceController` a `MapController` měly **prakticky stejný kód**: dotaz
`lines()` v závodním okně + dopočet lat/lon ze středu lokátoru, výpočet `dist`
(haversine) a `azimut` (bearing) a agregaci velkých čtverců (metoda `squares()`
byla v obou téměř znak po znaku totožná).

**Náprava (provedeno):** sdílené jádro vyjmuto do `app/Services/Edi/QsoGeometry.php`
s metodami `enrichedQsos(Edihead, $home, $orderColumn)` a `bigSquares(Edihead)`
vracejícími typované value objekty `EnrichedQso` a `BigSquareCount`. Oba controllery
(i případné budoucí mapy) z ní jen čtou; grafové metody vizualizace pracují s value
objekty. JSON kontrakt pro Leaflet/Chart.js zůstal beze změny. Pokryto testy
`QsoGeometryTest`.

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

## D3 – Vizualizace bez testů ✅ vyřešeno

`EdiVizualizaceController` (největší soubor v `app/`) neměl žádný test.

**Náprava (provedeno):** přidán `tests/Feature/EdiVizualizaceTest` (render stránky
`/edi/{head}/vizualizace` + obsah configu) a `tests/Feature/QsoGeometryTest`
(výpočet bodů z lokátorů, vzdálenost/azimut, agregace velkých čtverců). Sada má
nově 166 testů.

---

## P1 – Duplikace EDI import pipeline ✅ vyřešeno

Jádro příjmu deníku (validace shody `TDate` s QSO, `koloForTDate`, dedup, resolve
kategorie, `import` + `scoreEdi`, `EdiEntry::create`) bylo dříve zkopírované do
`ImportEdiAction` (jednotlivý upload) i `ImportController::importFile()` (hromadný).

**Náprava (provedeno):** `ImportController::importFile()` už tok neduplikuje –
deleguje na `ImportEdiAction` a jen mapuje výsledek/výjimku na řádek souhrnu
importu. Payload `EdiEntry::create` existuje na jediném místě. `execute()` dostal
parametr `notify` (default `true`); hromadný import ho volá s `notify: false`, takže
se při backfillu **nerozesílají potvrzovací e-maily** účastníkům (zachované původní
chování). Z controlleru odstraněny nepotřebné závislosti (`EdiImportService`,
`ScoringService`, `CategoryResolver`).

---

## P3 – Fragmentace migrací schématu ✅ vyřešeno

Základní schéma z `2026_05_29` doplňovaly tři „opravné" migrace
(`add_missing_indexes`, `fix_database_integrity`, `add_performance_indexes`), takže
indexy i integritní pravidla byly roztroušené na více místech.

**Náprava (provedeno – projekt zatím není v produkci):** úpravy zapsány zpět do
původních `create_*` migrací a opravné migrace smazány – jedna migrace = jedna
pravda o schématu:

- `create_edihead` ← index `PCall`
- `create_edilines` ← kompozitní index `(IDS, Time)`
- `create_edi_entries` ← odstraněno redundantní `nullable()` u sloupců s DEFAULT
  (NOT NULL + DEFAULT) a doplněny indexy `category_id`, `(round_id, approved)`,
  `(znacka, round_id)`
- `create_login_tokens` ← UNIQUE na `token`

Net stav schématu zůstal identický; ověřeno `migrate:fresh` i celou testovací sadou.
Ponechány legitimní `create_diskuse_table` a `add_aktivni_to_edi_rounds`.
Drobnost ponechána beze změny: `qso_points` má `->default(1)` (ostatní skóre `0`) –
aplikace hodnotu vždy nastavuje explicitně, default je bezvýznamný.

> Pozn.: protože šlo o přepis již aplikovaných migrací, je po této změně nutné
> v dev prostředí spustit `php artisan migrate:fresh` (data se zahodí). Na
> nasazeném schématu by se konsolidace **nedělala** – je to po nasazení anti-vzor.

---

## P6 – Legacy schéma s nestandardními názvy sloupců ✅ vyřešeno

Tabulky `edihead`/`edilines` jsou nově **plně normalizované na `snake_case`**
(`mode_code`, `received_wwl`, `qso_points`, `new_wwl_n`, `t_date`, `p_call`…).
Magické dash-stringy (`Received-WWL`, `QSO-Points`, `New-WWL-(N)`, `Mode-code`,
`Sent QSO number`) v aplikačním kódu **už nejsou** – přistupuje se k běžným
Eloquent atributům. `Ediline` ponechává tenkou vrstvu PHP 8.4 property hooků
(`receivedWwl`, `qsoPoints`, `modeCode`, `mode`, `newWwl`), které surové sloupce
jen normalizují/castují (`trim`, int cast, enum `QsoMode`).

Důsledky:

- **Žádné** `property.notFound` potlačení v PHPStan (level 10 čistý).
- `preventAccessingMissingAttributes` je **zapnuté** (`AppServiceProvider`);
  `Edihead`/`Ediline` nepotřebují žádný per-model opt-out (ten má jen `User`
  kvůli `Authenticatable` traitu). Překlep v názvu sloupce teď odhalí runtime.

---

## Drobnosti ✅ vyřešeno

- **Duplikovaný dotaz „průběžné výsledky"** – sloučen do scope
  `EdiEntry::prubezne($idKola, $idKategorie)`; `HlaseniController` i
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
- **Kategorie `CategoryResolver`** – párují se z normalizovaného číselníku
  `edi_categories` (pásmo × sekce × varianta) přes cachovanou mapu, žádná hardcoded
  matice ID v kódu. `edi_categories` je **jediný** číselník kategorií (duplicitní
  `vkvpa_kategorie` byla zrušena; `edi_entries.category_id` má FK na `edi_categories`).
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
| D1 – duplikace map/vizualizace | ✅ sdílená služba `QsoGeometry` + value objekty |
| D2 – výpočet bodů sjednocen + bug `preg_match` | ✅ všude `Maidenhead::qsoPoints`, fallback/bug odstraněn |
| D3 – testy vizualizace | ✅ `EdiVizualizaceTest` + `QsoGeometryTest` (166 testů) |
| P1 – hromadný import přes `ImportEdiAction` | ✅ delegace + `notify: false` (bez mailů) |
| P6 (zbytek) – `Mode-code` leak | ✅ accessor `Ediline::modeCode()` |
| Drobnost – duplikovaný dotaz „průběžné" | ✅ scope `EdiEntry::prubezne()` |
| Drobnost – hardcoded import limity | ✅ přesunuto do `config/vkvpa.php` |

---

## Doporučené pořadí prací

| # | Úkol | Náklad | Dopad | Stav |
|---|------|--------|-------|------|
| 1 | D2 – sjednotit výpočet bodů + bug `preg_match` | triviální | **korektnost** | ✅ hotovo |
| 2 | P6 (zbytek) + drobnosti (dotaz, limity do configu) | nízký | čistota | ✅ hotovo |
| 3 | D1 – sdílená `QsoGeometry` z Map/Vizualizace controllerů | střední | **odstranění duplikace** | ✅ hotovo |
| 4 | D3 – testy pro vizualizaci | nízký | regrese | ✅ hotovo |
| 5 | P1 – hromadný import přes `ImportEdiAction` | střední | dokončení dedup | ✅ hotovo |
| 6 | P3 – konsolidace migrací (projekt není v produkci) | střední | údržba schématu | ✅ hotovo |

**Veškerý dluh z tohoto auditu je vyřešen.** Poslední dvě velká místa kopírovaného
kódu (mapy/vizualizace a import) jsou odstraněna a pokryta testy, druh provozu je
typovaný enumem `App\Enums\QsoMode` a migrace jsou zkonsolidované do `create_*`
(jedna pravda o schématu). Nezbývají žádné otevřené body.
