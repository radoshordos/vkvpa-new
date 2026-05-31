# Fáze 4 — Autentizace (S2, S3, S4)

Nahrazuje legacy přihlášení Laravel Authem. Žádná hesla v kódu, žádná SQL injection, žádný `ereg()`.

## Soubory

| Soubor | Kam | Účel |
|--------|-----|------|
| `app/Models/User.php` | `app/Models/` | uživatel administrace (login jménem) |
| `database/migrations/2026_05_29_000010_create_users_table.php` | tamtéž | `users` + `sessions` + `password_reset_tokens` |
| `database/seeders/AdminUserSeeder.php` | tamtéž | admin z `.env` (ADMIN_USER/ADMIN_PASS) |
| `app/Http/Controllers/Auth/AuthController.php` | tamtéž | login / logout / token login |
| `app/Http/Middleware/EnsureAdmin.php` | tamtéž | ochrana admin stránek |
| `resources/views/auth/login.blade.php` | tamtéž | formulář (nahrazuje login.php) |
| `routes/auth.php` | `routes/` | routy autentizace |
| `tests/Feature/AuthTest.php` | tamtéž | testy |

## Co se mění oproti legacy

| Legacy (head.php / login.php / logout.php) | Nově |
|---|---|
| `$_POST['heslo']=="oK1dOz" && username=="Beda"` (S2) | `Auth::attempt` proti hashovanému heslu v `users` |
| `WHERE kod='".$_GET['kod']."'` (S3) | Eloquent `where('kod', $kod)` (binding) + expirace |
| `ereg("[a-zA-Z0-9._-]+@…")` (S4) | validační pravidlo `email` (řeší se v admin konfiguraci, Fáze 7) |
| `logout.php` → redirect na `HTTP_REFERER` (open-redirect) | `Auth::logout` + invalidace session + redirect na `/` |
| login jako `$_SESSION['prihlasen']` | Laravel session guard; navíc most `session('prihlasen')` pro legacy stránky |

## Integrace

1. **Migrace + seed:**
   ```bash
   # do .env: ADMIN_USER=Beda a silné ADMIN_PASS
   php artisan migrate
   php artisan db:seed --class=Database\\Seeders\\AdminUserSeeder
   ```
2. **Routy:** v `routes/web.php` přidej `require __DIR__.'/auth.php';`
3. **Alias middleware** v `bootstrap/app.php`:
   ```php
   ->withMiddleware(function (Middleware $middleware) {
       $middleware->alias(['admin' => \App\Http\Middleware\EnsureAdmin::class]);
   })
   ```
4. **Auth provider** (`config/auth.php`) – výchozí `users` provider míří na `App\Models\User` (beze změny).

## Most pro legacy (důležité)

Po přihlášení controller nastaví i `session('prihlasen')` = jméno uživatele. Díky tomu zbývající legacy stránky (a layout z Fáze 3, který čte `session('prihlasen')`) fungují beze změny. Most odstraní **Fáze 10**, kde se layout přepne na `auth()->check()`.

## Hardcoded heslo je pryč – ale rotuj

Heslo `oK1dOz` bylo v gitu. Po nasazení ho **nepoužívej** – nastav nové silné `ADMIN_PASS`. Stejně tak rotuj cokoli dalšího z původního `.env`/klíče (viz Fáze 2).

## Testy fáze

```bash
php artisan test --filter=AuthTest
```
Pokrývá: render formuláře, úspěšný login (+ legacy most), špatné heslo, logout. Ochranu admin rout otestuje Fáze 6 na konkrétních routách.

## Záměrně mimo tuto fázi

- **Odkazy v menu** (`?str=login`, `logout.php`) zůstávají; na `route('login')`/`route('logout')` se přepnou ve **Fázi 6** (routing).
- **Změna kontaktního e-mailu** (původně přes `ereg` + `mail.inc`) → **Fáze 7** (admin konfigurace) přes `VkvpaConfig` z Fáze 1 a validaci `email`. Tím definitivně zmizí `mail.inc`.
