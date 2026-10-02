#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
umask 077
mkdir -p backups
chmod 700 backups
file="backups/board-$(date -u +%Y%m%dT%H%M%SZ).dump"
# Dump custom : ne pas allouer de pseudo-terminal, préserver les octets.
if docker compose exec -T db sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Fc' > "$file"; then
    test -s "$file"
    printf 'Sauvegarde : %s\nCopier vers la destination protégée prévue.\n' "$file"
else
    rm -f "$file"
    exit 1
fi
