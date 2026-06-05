# Analýza technologického dluhu

> Stav k **2026-06-05**, větev `claude/tech-debt-analysis-ZAweo`.
> Hodnoceno: PHP 8.4.19, Laravel 13, ~2 370 řádků PHP v `app/`, 27 testovacích souborů.
>
> **Rychlé výhry P2, P4 a P5 jsou v této větvi již vyřešeny** (viz ✅ níže).
> Otevřené zůstává P1 (refaktor), P3 (jen pokud schéma není v produkci) a P6 (volitelné).

## Shrnutí

Kódová báze je **v nadprůměrné kondici**. Baseline kvality je zelený:

| Nástroj | Výsledek |
|---------|----------|
| PHPUnit | **159 testů / 501 asercí – vše prochází** |
| PHPStan (Larastan) | **level 10 – 0 chyb** |
| Pint (`laravel` preset) | **bez nálezů** |

Celý kód má `declare(strict_types=1)`, modely jsou v striktním Eloquent režimu
(`preventLazyLoading`, `preventSilentlyDiscardingAttributes`), value objekty jsou
neměnné a doménová logika (parsování, bodování) je oddělená od HTTP vrstvy a dobře
otestovaná. **Nejde tedy o záchranu zanedbaného projektu**, ale o cílené odstranění
hrstky konkrétních dluhů, dokud je báze malá.

Dluh je seřazen podle poměru dopad/náklad.

---

## P1 – Duplikace EDI import pipeline (nejvýznamnější)

**Kde:** `app/Http/Controllers/EdiController.php::store()` (ř. 43–125) a
`app/Http/Controllers/Admin/ImportController.php::importFile()` (ř. 95–161).

Celý tok zpracování deníku je **zkopírovaný do dvou míst**:

1. validace shody `TDate` s daty QSO,
2. `koloForTDate()`,
3. kontrola duplicity (`EDI=true, znacka, id_kola`),
4. `CategoryResolver::resolve()` + odchyt `UnknownBandException`,
5. `import()` + `scoreEdi()`,
6. **stejný 14klíčový `VkvpaData::create([...])` payload.**

Liší se jen forma výstupu (HTTP redirect s chybami vs. položka v souhrnu importu).

**Riziko:** každá změna pravidel příjmu (nový sloupec, jiná validace, změna
duplicitní logiky) se musí provést dvakrát. Obě cesty mají vlastní testy, takže
**rozejití se neprojeví selháním testu** – jen tichým rozdílem chování mezi jednotlivým
a hromadným nahráním.

**Náprava:** vyjmout jádro do akce/služby, např.

```php
final class IngestEdiLog
{
    /** @return IngestResult  (ok | skip | error + důvod + vzniklý VkvpaData|null) */
    public function handle(EdiLog $log): IngestResult { /* kroky 1–6 */ }
}
```

Oba controllery pak jen přeloží `IngestResult` na svůj výstup. Payload `VkvpaData::create`
existuje jen jednou. Odhad: ~1–2 h včetně přesměrování testů.

---

## P2 – Kolize názvů migrací ✅ vyřešeno

**Kde:** `database/migrations/`

```
2026_06_05_000001_add_performance_indexes.php
2026_06_05_000001_create_diskuse_table.php   ← stejný timestamp prefix
```

Dvě migrace mají **identický prefix `2026_06_05_000001`**. Laravel řadí migrace podle
celého názvu souboru, takže pořadí dnes určuje až abecední porovnání zbytku
(`add_…` < `create_…`). Funguje to náhodou; jakékoli přejmenování nebo přidání
další migrace se stejným prefixem pořadí tiše změní.

**Náprava (provedeno):** `create_diskuse_table` přečíslováno na
`2026_06_05_000002_create_diskuse_table.php`. Migrace jsou na sobě nezávislé
(`add_performance_indexes` sahá jen na `vkvpa_data`/`edihead`), pořadí je teď
deterministické; ověřeno čistým `php artisan migrate`. Drobnost: v řadě chybí číslo
`000006` (mezi `…000005_create_vkvpa_data` a `…000007_create_vkvpa_kategorie`) – kosmetické, ponecháno.

---

## P3 – Fragmentace migrací schématu

**Kde:** `database/migrations/`

Základní schéma je celé z `2026_05_29`, ale následují tři „opravné" migrace
z pozdějších dní:

- `2026_06_03_000001_add_missing_indexes`
- `2026_06_03_000002_fix_database_integrity`
- `2026_06_05_000001_add_performance_indexes`

Indexy se tak spravují **na třech místech** a název `fix_database_integrity` napovídá,
že základní migrace nebyly úplné. Pro projekt, který **ještě není nasazený v produkci**,
je čistší tyto úpravy zapsat zpět do původních `create_*` migrací a opravné migrace
zrušit – jedna migrace = jedna pravda o schématu.

> Pozn.: pokud už schéma kdekoli v produkci běží, tohle **nedělat** – konsolidace
> migrací po nasazení je anti-vzor. Před zásahem ověřit, že tomu tak není.

---

## P4 – Mrtvý kód: výchozí Laravel `welcome` ✅ vyřešeno

**Kde:** `resources/views/welcome.blade.php` (223 řádků).

Jde o **defaultní uvítací stránku Laravelu**. Kořenová cesta `/` směřuje na
`HlaseniController@index`; `welcome` není referencováno z žádné routy ani `view()`
volání (ověřeno grepem). Je to největší Blade soubor v projektu a přitom nedostupný.

**Náprava (provedeno):** `resources/views/welcome.blade.php` smazán.

---

## P5 – Rozjetá dokumentace verzí a kvality ✅ vyřešeno

Tři zdroje pravdy si **odporují**:

| Údaj | `composer.json` | běhové prostředí | CI (`ci.yml`) | `README.md` | `CLAUDE.md` |
|------|-----------------|------------------|---------------|-------------|-------------|
| PHP | `^8.4` | 8.4.19 | **8.5** | **8.5** | 8.4 |
| PHPStan level | – | level **10** | – | level **10** | level **9** |

- `CLAUDE.md` uvádí PHPStan **level 9**, skutečnost (`phpstan.neon`) je **level 10**.
- README i CI tvrdí **PHP 8.5**, ale `composer.json` vyžaduje `^8.4` a kód běží na 8.4.

**Riziko:** CI běží na jiné verzi PHP než vývojáři (8.5 vs 8.4) → testy mohou projít
v CI a selhat lokálně (nebo naopak); `CLAUDE.md` navádí budoucího přispěvatele na
nesprávnou úroveň analýzy.

**Náprava (provedeno):** sjednoceno na **PHP 8.4** všude – CI (`ci.yml`),
README (3 místa) i `CLAUDE.md` teď souhlasí s `composer.json` (`^8.4`) a běhovým
prostředím. (Varianta „zvednout na `^8.5`" by rozbila `composer install` na 8.4 –
proto volba dolů.) Level PHPStanu v `CLAUDE.md` opraven z 9 na **10**.

---

## P6 – Legacy schéma s nestandardními názvy sloupců (řízený, ne odstranitelný)

**Kde:** `Ediline`, `Edihead`, `ScoringService`, `MapController`.

Sloupce `Received-WWL`, `Mode-code`, `Sent QSO number`, `QSO-Points`… vynucují
přístup přes `$line->{'Received-WWL'}`, dynamické casty a výjimku v `phpstan.neon`
(`property.notFound`). Kvůli tomu nelze zapnout `preventAccessingMissingAttributes`
(viz komentář v `AppServiceProvider`), takže překlep v názvu sloupce u těchto modelů
**neodhalí ani PHPStan, ani runtime**.

Tohle je **vědomě přijatý** dluh kvůli kompatibilitě s původním systémem a je dobře
zdokumentovaný. Není nutné ho hned řešit, ale stojí za zvážení **tenká accessor
vrstva** (`getReceivedWwl(): string`) jako jediný bod přístupu – zúžila by plochu, kde
se píší „magické" stringy, a umožnila typovou kontrolu.

---

## Co je naopak v pořádku (a nesahat na to)

- **Bodovací logika** (`ScoringService`, `Maidenhead`, `ContestWindow`) – čistá,
  konfigurovatelná přes `config/vkvpa.php`, dobře pokrytá testy.
- **Cache ročních výsledků** – `Cache::flexible` se **cílenou** invalidací v
  `rankRound()`; korektní stale-while-revalidate.
- **Value objekty** EDI (`EdiLog`, `EdiHeader`, `EdiQso`) – neměnné, bez DB/IO.
- **Bilingvní vrstva** `lang/cs` + `lang/en` – kompletní a symetrická.
- **Bezpečnostní drobnosti** – obfuskace e-mailu přes `MailImageController`,
  produkční guard na povinný config v `AppServiceProvider`.

---

## Doporučené pořadí prací

| # | Úkol | Náklad | Dopad | Stav |
|---|------|--------|-------|------|
| 1 | P4 – smazat `welcome.blade.php` | triviální | čistota | ✅ hotovo |
| 2 | P2 – přečíslovat kolidující migraci | triviální | stabilita pořadí | ✅ hotovo |
| 3 | P5 – sjednotit verze PHP + level v dokumentaci | nízký | konzistence CI/dev | ✅ hotovo |
| 4 | P1 – vyjmout `IngestEdiLog` z obou controllerů | střední | **odstranění duplikace** | otevřeno |
| 5 | P3 – konsolidovat migrace (jen pokud není v produkci) | střední | údržba schématu | otevřeno |
| 6 | P6 – accessor vrstva nad legacy sloupci (volitelně) | střední | typová bezpečnost | otevřeno |

Rychlé výhry (1–3) jsou hotové. Bod 4 je jediný strukturální dluh, který se
**vyplatí udělat dřív, než kód přijme třetí cestu příjmu deníku**.
