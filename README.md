# VKV Provozní aktiv

Webový systém pre správu a vyhodnocovanie závodov v pásme VKV (Very High Frequency) pre amatérskych rádioamatérov. Systém umožňuje registráciu súťažných denníkov, spracovanie EDI súborov, automatické vyhodnotenie a zobrazenie výsledkov.

## Technológie

- **Backend**: PHP 8.5, Laravel 13
- **Frontend**: Blade, TailwindCSS 4.0, Vite 8.0
- **Databáza**: MySQL 8.0
- **Kontajnerizácia**: Docker + Docker Compose
- **Testovanie**: PHPUnit 12 / Pest
- **Statická analýza**: PHPStan, Pint

## Funkcie

- Registrácia súťažných denníkov cez webový formulár
- Spracovanie a validácia EDI (Electronic Data Interchange) log súborov
- Automatické vyhodnotenie a bodovanie závodov
- Zobrazenie rebríčkov a výsledkov (`/vysledky`)
- Administrátorské rozhranie pre správu kategórií a závodov
- Vizualizácia staníc na mape (Leaflet + Maidenhead lokátor)
- Emailové notifikácie pre účastníkov a rozhodcov

## Inštalácia

### Požiadavky

- PHP 8.5
- Composer
- Node.js 20+
- MySQL 8.0

### Postup

```bash
# Klonovanie repozitára
git clone <repo-url>
cd vkvpa-new

# Konfigurácia prostredia
cp .env.example .env

# Inicializácia projektu (inštalácia závislostí, migrácie, build assets)
composer setup
```

### Docker

```bash
# Spustenie databázy a webového servera
docker compose up -d

# Inicializácia projektu v kontajneri
docker compose exec app composer setup
```

### Konfigurácia `.env`

Kľúčové premenné prostredia:

```env
APP_URL=http://localhost

DB_HOST=127.0.0.1
DB_DATABASE=vkvpa
DB_USERNAME=vkvpa
DB_PASSWORD=

MAIL_MAILER=smtp
MAIL_HOST=
MAIL_FROM_ADDRESS=
```

## Vývoj

```bash
# Spustenie vývojového servera (Vite + queue + logy)
composer dev

# Spustenie testov
composer test

# Statická analýza
composer stan

# Kontrola kódu
composer lint
```

## Štruktúra projektu

```
app/
├── Http/Controllers/    # Routové handlery
├── Models/              # Eloquent modely
├── Services/            # Biznis logika (EdiImportService, ScoringService)
├── Mail/                # Emailové notifikácie
└── Support/             # Pomocné triedy (Maidenhead, ContestWindow)
database/
├── migrations/          # Schéma databázy
├── factories/           # Testovacie továrne
└── seeders/             # Počiatočné dáta
resources/
├── views/               # Blade šablóny
├── css/                 # TailwindCSS
└── js/                  # Klientský JavaScript
```

## Licencia

Interný projekt. Všetky práva vyhradené.
