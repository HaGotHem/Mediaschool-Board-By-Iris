#!/bin/sh
set -eu
echo "[board] Vérification de la configuration"
php bin/check-config.php
echo "[board] Application des migrations"
php bin/migrate.php
echo "[board] Démarrage de PHP-FPM"
exec "$@"
