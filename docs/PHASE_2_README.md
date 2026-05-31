# Fáze 2 — Konfigurace a připojení (S1, S5, S6)

Tato fáze odstraňuje hesla z kódu, sjednocuje připojení k DB a převádí kódování — vše při zachování běhu legacy souborů.

## Co je v balíčku

| Soubor | Kam | Účel |
|--------|-----|------|
| `.env.example` | kořen repa | šablona konfigurace (zkopíruj na `.env`, vyplň) |
| `.gitignore` | kořen repa | chrání `.env`, `*.pem`, `private_key`, `mail.inc`, `*.bak` |
| `config/database.php` | `config/` | připojení Laravelu z `.env` (čte DB_NAME i DB_DATABASE) |
| `legacy/connect_mysqli.php` | nahraď kořenový `connect_mysqli.php` | env-based připojení, prepared janitor, helpery |
| `legacy/connect.php` | nahraď kořenový `connect.php` | tenký alias → connect_mysqli.php |
| `tools/convert_encoding.sh` | `tools/` | převod 4 souborů Win-1250 → UTF-8 |

## Postup integrace

1. **Tajemství ven z gitu** (kritické — `.env` a privátní klíč jsou v historii):
   ```bash
   git rm --cached .env .idea/private_key mail.inc 2>/dev/null || true
   cp .env.example .env      # a vyplň skutečné hodnoty
   ```
   Pak **rotuj** všechna hesla a privátní klíč (jednou commitnuté tajemství je třeba považovat za kompromitované).

2. **Nahraď connect soubory** novými z `legacy/` (přepíší hardcoded hesla).

3. **Převeď kódování:**
   ```bash
   bash tools/convert_encoding.sh
   # po kontrole:
   find . -name '*.bak' -delete
   ```

4. **Laravel config:** vlož `config/database.php`, doplň `APP_KEY` (`php artisan key:generate`).

## Testy fáze

- Legacy běh: stránka přes `index.php` se připojí k DB (žádné „DB connection error"), výpisy fungují.
- Laravel: `php artisan migrate:status` projde proti stejné `.env`.
- Kódování: `for f in edit_import.php export.php import.php login.php; do iconv -f UTF-8 -t UTF-8 "$f" >/dev/null && echo "$f OK"; done` → 4× OK.
- `grep -rn "password\s*=\s*\"" connect*.php` → žádný výsledek (hesla pryč z kódu).

## Poznámky

- `mfa()` ponechán jako `fetch_array` (superset) kvůli shodě s `head.php`. V `head.php` jsou ale stále vlastní definice `mq/mfa/mnr` — ty řeší **Fáze 3/4** (přesun layoutu a autentizace), kde se duplicita odstraní.
- DB má mix `utf8mb3`/`utf8mb4`. Default připojení je `utf8mb4`; při kolačních potížích u JOINů nastav v `.env` `DB_CHARSET=utf8`.
- Konstanty `ADMIN_USER`/`ADMIN_PASS` z `.env` zatím nikam nezapojuju — patří do **Fáze 4** (Laravel Auth), kde nahradí hardcoded `Beda`/`oK1dOz` z `head.php`.
