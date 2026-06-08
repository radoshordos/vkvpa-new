#!/bin/sh
# Vstupní skript kontejneru.
#
# Vygeneruje .htpasswd pro Adminer (public/adminer/.htaccess) z proměnných
# prostředí ADMINER_AUTH_USER / ADMINER_AUTH_PASSWORD. Heslo se tak NIKDY
# neukládá do image ani do repozitáře – vzniká až za běhu kontejneru.
#
# Soubor leží MIMO webroot i bind-mount (/etc/apache2/), takže ho nelze
# stáhnout přes HTTP ani omylem commitnout. Bez nastavených proměnných se
# .htpasswd nevytvoří a Basic auth selže (fail-closed = přístup odepřen).
set -e

HTPASSWD_FILE=/etc/apache2/adminer.htpasswd

if [ -n "$ADMINER_AUTH_USER" ] && [ -n "$ADMINER_AUTH_PASSWORD" ]; then
    # -B = bcrypt, -c = vytvořit, -b = heslo z argumentu.
    htpasswd -cbB "$HTPASSWD_FILE" "$ADMINER_AUTH_USER" "$ADMINER_AUTH_PASSWORD" >/dev/null 2>&1
    chmod 640 "$HTPASSWD_FILE" || true
    echo "[entrypoint] Adminer Basic auth povolen (uživatel: ${ADMINER_AUTH_USER})."
else
    rm -f "$HTPASSWD_FILE"
    echo "[entrypoint] VAROVÁNÍ: ADMINER_AUTH_USER/ADMINER_AUTH_PASSWORD nejsou nastaveny – přístup k Adminer je odepřen (fail-closed)."
fi

# Předáme řízení původnímu entrypointu základního image (php:*-apache),
# který provede standardní inicializaci a spustí apache2-foreground.
exec docker-php-entrypoint "$@"
