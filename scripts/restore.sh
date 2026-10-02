#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
env_file="${1:?Usage: bash scripts/restore.sh .env.restore board-restore dump}"
project="${2:?Nom de projet isolé requis}"
dump="${3:?Dump requis}"
if [[ "$env_file" != .env.restore* || "$project" != board-restore* ]]; then
    printf 'Seule une pile de restauration de test dédiée est acceptée.\n' >&2
    exit 1
fi
test -f "$env_file"
test -s "$dump"
# Pas de mutation du projet mediaschool-board ni de suppression de volume.
docker compose --env-file "$env_file" -p "$project" stop web api
docker compose --env-file "$env_file" -p "$project" exec -T db sh -c 'pg_restore -U "$POSTGRES_USER" -d "$POSTGRES_DB" --clean --if-exists --no-owner --no-privileges --single-transaction' < "$dump"
docker compose --env-file "$env_file" -p "$project" up -d --wait
printf 'Restauration effectuée sur %s. Vérifier les comptes, référentiels et fiches.\n' "$project"
