#!/bin/bash
set -e

CONTAO_VERSION="${CONTAO_VERSION:-5.3}"

# Erstelle Contao-Projekt falls noch nicht vorhanden
if [ ! -f /var/www/html/composer.json ]; then
    echo ">>> Erstelle neues Contao ${CONTAO_VERSION} Projekt..."
    composer create-project contao/managed-edition:"${CONTAO_VERSION}" /tmp/contao --no-interaction --no-dev
    cp -a /tmp/contao/. /var/www/html/
    rm -rf /tmp/contao
fi

# Bundle als lokales Repository einbinden
cd /var/www/html

# Prüfe ob das Repository bereits konfiguriert ist
if ! composer config repositories.blog-sync-bundle 2>/dev/null | grep -q "path"; then
    echo ">>> Füge lokales Bundle-Repository hinzu..."
    composer config repositories.blog-sync-bundle path /var/www/bundle --no-interaction
fi

# Bundle installieren falls noch nicht vorhanden
if ! composer show agencypowerstack/contao-blog-sync 2>/dev/null; then
    echo ">>> Installiere contao-blog-sync Bundle..."
    composer require agencypowerstack/contao-blog-sync:"@dev" --no-interaction
fi

# Composer install für aktuelle Änderungen
echo ">>> Aktualisiere Dependencies..."
composer install --no-interaction

# DATABASE_URL in .env.local schreiben
if [ -n "$DATABASE_URL" ]; then
    grep -q "DATABASE_URL" /var/www/html/.env.local 2>/dev/null || echo "DATABASE_URL=${DATABASE_URL}" >> /var/www/html/.env.local
fi

# Blog-Images-Verzeichnis anlegen (damit contao:symlinks den Symlink erstellen kann)
mkdir -p /var/www/html/files/blog-images

# Echtes Verzeichnis in public/ entfernen falls vorhanden (kein Symlink)
if [ -d /var/www/html/public/files/blog-images ] && [ ! -L /var/www/html/public/files/blog-images ]; then
    rm -rf /var/www/html/public/files/blog-images
fi

# Datenbank-Migration
echo ">>> Führe Datenbank-Migrationen aus..."
php vendor/bin/contao-console contao:migrate --no-interaction || true

# Symlinks neu generieren (erstellt public/files/blog-images -> files/blog-images)
php vendor/bin/contao-console contao:symlinks || true

# Admin-User anlegen falls nicht vorhanden
if [ -n "$CONTAO_ADMIN_PASSWORD" ]; then
    ADMIN_USER="${CONTAO_ADMIN_USER:-admin}"
    ADMIN_EMAIL="${CONTAO_ADMIN_EMAIL:-admin@example.com}"
    if ! php vendor/bin/contao-console contao:user:list --format=json 2>/dev/null | grep -q "\"${ADMIN_USER}\""; then
        echo ">>> Erstelle Admin-User '${ADMIN_USER}'..."
        php vendor/bin/contao-console contao:user:create \
            --username="${ADMIN_USER}" \
            --name="${ADMIN_USER}" \
            --email="${ADMIN_EMAIL}" \
            --password="${CONTAO_ADMIN_PASSWORD}" \
            --admin \
            --no-interaction || true
    fi
fi

# Dateiberechtigungen setzen
chown -R www-data:www-data /var/www/html/var 2>/dev/null || true
chown -R www-data:www-data /var/www/html/public 2>/dev/null || true
chown -R www-data:www-data /var/www/html/files 2>/dev/null || true

echo ">>> Contao ist bereit auf http://localhost:8080"

exec "$@"
