# Fáze 3 — Layout (zachování grafického designu)

Extrakce `head.php` / `menu.php` / `bottom.php` do Blade šablon. **Vzhled se nemění** — stejné `css/styl.css`, stejné kontejnery (`#page`, `#banner`, `#mainmenu`, `ul.menu`, `#obsah`, `#copyright`) a stejné texty menu i patičky.

## Soubory

| Soubor | Kam | Z čeho |
|--------|-----|--------|
| `config/navigation.php` | `config/` | data menu (odděleno od markupu) |
| `resources/views/layouts/app.blade.php` | tamtéž | `head.php` + obal stránky + patička |
| `resources/views/partials/menu.blade.php` | tamtéž | `menu.php` |
| `resources/views/partials/menu-item.blade.php` | tamtéž | jedna položka menu |
| `resources/views/partials/footer.blade.php` | tamtéž | `bottom.php` |
| `resources/views/pages/example.blade.php` | tamtéž | ukázka napojení obsahu |

## Co se mechanicky změnilo (bez vlivu na vzhled)

- **HTML5 doctype** + `<html lang="cs">` + `<meta charset="utf-8">` místo XHTML 1.0 Strict. Oba režimy jsou standards mode → CSS se renderuje shodně. Doplněn `viewport` meta (responzivita, nemění desktop vzhled).
- Menu je **data-driven** z `config/navigation.php` místo opakovaných `echo` (stejný výstup).
- Aktivní položka přes Blade `@class(['active' => …])` (vykreslí `class="active"` jen u aktivní interní položky, jako legacy).
- E-maily v patičce stále jako `mail.php?text=<base64>` (zachováno chování).

## Co se zatím NEpřevádělo (záměrně, jiné fáze)

- **Login logika** z `head.php` (kód přes `?kod=`, heslo `Beda`/`oK1dOz`), zápis kontaktu do `mail.inc`, `ereg()` → **Fáze 4** (Laravel Auth, odstranění SQLi a `ereg`).
- Vlastní `mq/mfa/mnr` v `head.php` → odstraní Fáze 4 (zůstávají jen v bridgi z Fáze 2).
- `?str=` odkazy a `logout.php` ponechány tak, jak jsou → **Fáze 6** je přemapuje na pojmenované routy (`route()`); pak stačí upravit `menu-item.blade.php` a `config/navigation.php`.

## Napojení obsahu (vzor pro Fázi 6)

```blade
@extends('layouts.app')
@section('title', 'Výsledková listina – VKV PA')
@section('content')
    {{-- obsah stránky --}}
@endsection
```

Controller předá zvýraznění menu:

```php
return view('pages.vysledkova_listina', ['active' => 'vysledkova_listina']);
```

Admin stav (do Fáze 4): layout default čte `session('prihlasen') === 'Beda'`. Po Fázi 4 se v `app.blade.php` jediný `@php` řádek změní na `auth()->check()` apod.

## Testy fáze

1. **Vizuální shoda:** otevři legacy stránku a Blade variantu vedle sebe — banner, menu (veřejné i admin), patička i barvy/rozložení identické (stejné `css/styl.css`).
2. **Aktivní položka:** s `['active' => 'edit_hlaseni']` má jen daná položka `class="active"`.
3. **Větvení menu:** bez admin session se renderuje veřejné menu (6 položek); s `prihlasen=Beda` admin menu (7 + „Přihlášen" + Odhlásit + 3 externí).
4. **Render bez chyb (feature test):**
   ```php
   $html = view('pages.example', ['active' => 'edit_hlaseni'])->render();
   $this->assertStringContainsString('id="mainmenu"', $html);
   $this->assertStringContainsString('class="active"', $html);
   ```

> CSS (`css/styl.css`) zkopíruj do `public/css/` — layout ho linkuje přes `asset('css/styl.css')`.
