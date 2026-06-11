# Plán migrace na PHP 8.5

> Stav k **2026-06-11**, větev `claude/php-8.5-migration-ig91fd`.
> Navazuje na `docs/technicky-dluh.md` a na předchozí (vrácený) pokus o
> migraci na PHP 8.5 z 2026-06-04/05.

## Shrnutí

PHP 8.5 (vyšlo 20. 11. 2025) je dostupné a **codebase je s ním už dnes
100% funkčně kompatibilní beze změny jediného řádku**. Ověřeno lokálně
nainstalovaným PHP 8.5.7 proti aktuálnímu stavu repozitáře (PHP 8.4 ve
`composer.json`):

| Nástroj | Výsledek na PHP 8.5.7 |
|---|---|
| PHPUnit (`composer test`) | **326 testů / 958 asercí – vše prochází**, 0 deprecation warningů |
| PHPStan level 10 (`composer stan`) | **0 chyb** |
| Pint (`composer lint`) | **bez nálezů** |

Migrace tedy sestává ze dvou nezávislých částí:

- **Krok A – verzovací bump** (composer/Dockerfile/CI/CLAUDE.md) – nutný,
  malé riziko, beze změny chování.
- **Krok B – modernizace kódu** využívající nové jazykové prvky 8.5 –
  z větší části **volitelná**; jediný jasně přínosný a bezpečný kus je B1.

## Historie – proč byl předchozí pokus vrácen

| Commit | Co | Proč vadilo na PHP 8.4 |
|---|---|---|
| `8ac076a` / `34ae9c5` | „rector at Laravel 13 a php 8.5" – první pokus o 8.5 | – |
| `76a6ecb` (2026-06-04) | downgrade `composer.json` `php: ^8.5 → ^8.4`; pipe operátor `\|>` v `LegacyJsonTableSeeder::rows()` nahrazen vnořenými voláními | `\|>` je syntaxe zavedená až v PHP 8.5 – na 8.4 parse error |
| `fc1224f` (2026-06-05) | `#[Override]` odstraněn z vlastností `$table`/`$autoIncrement` v 9 seederech | `#[Override]` smí na PHP 8.4 cílit jen na metody; na vlastnosti je to fatal error (PHPStan level 9 to navíc hlásil) |

Obě omezení v PHP 8.5 padají (ověřeno níže testem na obou verzích PHP).
Mezitím byl `LegacyJsonTableSeeder` přejmenován/zrefaktorován na
`JsonTableSeeder` (7 potomků: `EdiheadTableSeeder`, `EdilinesTableSeeder`,
`PrefixesTableSeeder`, `VkvpaDataTableSeeder`, `VkvpaKategorieTableSeeder`,
`VkvpaKolaTableSeeder`, `VkvpaPrihlaseniTableSeeder`).

## Krok A – verzovací bump

| Soubor | Změna |
|---|---|
| `composer.json` | `"php": "^8.4"` → `"php": "^8.5"` |
| `composer.lock` | `composer update --lock` (přepočítá `content-hash` a sekci `platform.php`; bez re-resolvingu balíčků) |
| `Dockerfile` | `FROM php:8.4-apache` → `FROM php:8.5-apache` |
| `.github/workflows/ci.yml` | `php-version: '8.4'` → `'8.5'` ve všech 3 jobech (`test`, `analyse`, `audit`) |
| `CLAUDE.md` | „Laravel 13 / PHP 8.4 web application" → „Laravel 13 / PHP 8.5 web application" |

Kompatibilita závislostí ověřena přes `composer.lock` – žádná nemá horní
mez blokující 8.5:

- `laravel/framework` v13.15.0 → `php: ^8.3`
- `larastan/larastan` v3.10.0 → `php: ^8.2`
- `phpstan/phpstan` 2.2.2 → `php: ^7.4|^8.0`
- `phpunit/phpunit` 13.2.0 → `php: >=8.4.1`
- `symfony/console` v8.1.0 → `php: >=8.4.1`
- `laravel/pulse`, `livewire/livewire`, `laravel/tinker` → `php: ^8.1`
- `mjaschen/phpgeo` → `php: ^8.2`
- `dg/adminer-custom` → bez omezení; `composer install` + `package:discover`
  proběhly bez chyby

`shivammathur/setup-php@v2` podporuje `php-version: '8.5'` (GA od listopadu
2025). Image `php:8.5-apache` existuje a všechna rozšíření, která Dockerfile
instaluje (gd s freetype/jpeg, pdo_mysql, pdo_sqlite, zip, intl), jsou pro
8.5 dostupná stejně jako pro 8.4 (ověřeno `apt-cache search php8.5-*`).

> Pozn.: `SECURITY_AUDIT_2026-06-08.md` (řádek 54) už dnes zmiňuje
> `Dockerfile`: `php:8.5-apache` – po Kroku A bude tvrzení odpovídat
> skutečnosti (dnes je to drobná nepřesnost dokumentu).

## Krok B – modernizace kódu (nové prvky PHP 8.5)

### B1. `#[Override]` na vlastnostech seederů — doporučeno

7 tříd dědících z `JsonTableSeeder` přepisuje `protected string $table` a
`protected ?int $autoIncrement` z rodiče bez označení. Na PHP 8.4 atribut
`#[Override]` na vlastnosti způsobí fatal error (ověřeno), na 8.5 je v
pořádku (ověřeno):

```php
namespace Database\Seeders;

use Override;

class EdiheadTableSeeder extends JsonTableSeeder
{
    #[Override]
    protected string $table = 'edihead';

    #[Override]
    protected ?int $autoIncrement = 23111;
}
```

Týká se: `EdiheadTableSeeder`, `EdilinesTableSeeder`, `PrefixesTableSeeder`,
`VkvpaDataTableSeeder`, `VkvpaKategorieTableSeeder`, `VkvpaKolaTableSeeder`,
`VkvpaPrihlaseniTableSeeder` (přidat `use Override;` + 2× atribut v každém).
Je to přesný protipól commitu `fc1224f` a sjednotí styl s `#[Override]` na
metodách, který projekt už všude jinde používá (modely, FormRequesty,
`AppServiceProvider`).

### B2. Pipe operátor `\|>` — volitelné, kosmetické

`JsonTableSeeder::rows()` šel kvůli 8.4 z pipe na vnořená volání. Lze vrátit:

```php
protected function rows(): array
{
    /** @var list<array<string, mixed>> $rows */
    $rows = sprintf('seeders/data/%s.json', $this->table)
        |> database_path(...)
        |> file_get_contents(...)
        |> (fn (string|false $x): mixed => json_decode((string) $x, true, 512, JSON_THROW_ON_ERROR));

    return $rows;
}
```

Obě varianty jsou PHPStan level 10 čisté a Pint je nemění – jde o estetiku,
ne o funkční zlepšení. Doporučuji ponechat na zvážení (je to doslovný revert
vlastní historie projektu).

Další velmi častý vzor `strtoupper(substr(trim($x), 0, 4))` (extrakce
„velkého čtverce" z lokátoru, ~8 výskytů v `QsoGeometry`, `ScoringService`,
`EdiScoreDebugger`, `EdiInkubatorController`) se pipe operátorem **nezkrátí**
– `substr()` má 3 argumenty, takže by vyžadoval extra closure a zápis by
vyšel delší než dnešní nested forma. Případná DRY extrakce do
`Maidenhead::bigSquare(string $locator): string` je nezávislá na PHP 8.5 a
patří spíš do `docs/technicky-dluh.md`.

### B3. `array_first()` / `array_last()` / `array_any()` / `array_all()` / `array_find()`

Nové globální funkce (ověřeno `function_exists()` → `true` na 8.5.7).
Projekt dnes důsledně používá `Arr::first()`/`->first()`/`reset()`/`end()`
nebo `=== []`/`!== []` kontroly – nic nekoliduje s `symfony/polyfill-php85`
(který Laravel 13 už táhne) a žádné stávající místo nevyžaduje refaktor.
K dispozici pro budoucí kód, žádná akce teď.

### B4. `clone()` s přepisem vlastností (wither pattern)

Hodnotové `final readonly class` objekty (`EdiQso`, `EdiHeader`, `EdiLog`,
`EnrichedQso`, `BigSquareCount`, `EdiDebugRow`, `EdiValidationReport`,
`EdiDebugReport`) by mohly těžit z `clone($obj, ['pole' => $nova])`, ale
v kódu dnes neexistuje místo, které by „kopírovalo s úpravou" – všechny
vznikají jednorázově z konstruktoru / `EdiQso::fromMatch()`. Žádná akce,
jen poznámka pro budoucí funkce (např. nástroje na opravu/normalizaci EDI).

### B5. `#[\NoDiscard]` — samostatný follow-up, ne součást této migrace

Kandidáti, kde zahození návratové hodnoty je pravděpodobně bug:
`ScoringService::scoreEdi()`, `EdiParser::parse()`,
`Maidenhead::qsoPoints()`/`distance()`/`bearing()`, `EdiValidator::validate()`,
`CategoryResolver::resolve()`.

Riziko: `#[\NoDiscard]` vyhazuje `E_DEPRECATED` při zahození hodnoty –
před zavedením je nutné projít všechna volání (vč. testů) a ověřit, že to
nezalarmuje `composer test`/PHPStan. Přínos (obrana proti zapomenutému
„persist výsledku") je reálný, ale riziko falešných poplachů > přínos pro
to, aby to bylo součástí verzovací migrace. Navrhuji jako samostatnou
následnou úlohu, po jednom místě a s ověřením testů.

### B6. Ostatní prověřené body — bez dopadu, žádná akce

- ✅ Necanonické casty `(boolean)/(integer)/(double)/(binary)`
  (deprecated v 8.5) – v `app/` se nevyskytují.
- ✅ Operátor zpětných uvozovek (alias za `shell_exec`, deprecated v 8.5) –
  nepoužívá se.
- ✅ INI direktiva `disable_classes` (odstraněna v 8.5) – v
  `docker/php/*.ini` se nenastavuje.
- ✅ `EdiScore::$body` zůstává „virtuální" hooked vlastnost (get-only, bez
  backing store) → třída nemůže být `readonly class` ani na 8.5 – RFC
  `readonly_hooks` povoluje hooky na readonly jen u *backed* vlastností.
  Komentář v souboru zůstává pravdivý beze změny.
- ✅ Nová `ext-uri`/`Uri\...` extension – v `app/` není žádné
  `parse_url()`/URL parsování k migraci; k dispozici pro budoucí použití.

## Doporučené pořadí prací

| # | Úkol | Náklad | Riziko |
|---|------|--------|--------|
| 1 | Krok A – verzovací bump (composer/Dockerfile/CI/CLAUDE.md) + `composer update --lock` | nízký | nízké – ověřeno zeleně i bez této změny |
| 2 | B1 – `#[Override]` na 7 seederech (14 vlastností) | triviální | žádné |
| 3 | (volitelně) B2 – pipe operátor v `JsonTableSeeder::rows()` | triviální | žádné, jen styl |
| 4 | (volitelně, samostatně) B5 – `#[\NoDiscard]` na vybraných metodách po auditu volání | střední | nutné ověřit testy/PHPStan |

## Ověření / test plán

1. `composer install` na PHP 8.5 – ✅ ověřeno (proběhne bez `composer update`,
   protože `^8.4` ⊂ rozsah dostupných balíčků; po Kroku A doplnit
   `composer update --lock`).
2. `composer test` – 326/326 ✅ ověřeno.
3. `composer stan` (level 10) – 0 chyb ✅ ověřeno.
4. `composer lint` – ✅ ověřeno.
5. `npm run build` – nezávislé na PHP verzi, beze změny.
6. Docker: `docker compose build web` s novým `Dockerfile`
   (`php:8.5-apache`) + `docker compose exec web composer setup` – ověřit
   instalaci rozšíření (gd/freetype/jpeg, pdo_mysql, pdo_sqlite, zip, intl).
7. CI: push větve, sledovat všechny 4 joby (`test`, `analyse`, `audit`,
   `build`).
