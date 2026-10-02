#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
ref="${1:?Usage: bash scripts/package.sh TAG_OU_COMMIT}"
git rev-parse --verify "${ref}^{commit}" >/dev/null
sha="$(git rev-parse --short=12 "$ref")"
mkdir -p release
out="release/mediaschool-board-${sha}.tar.gz"
git archive --format=tar.gz --prefix=mediaschool-board/ "$ref" -o "$out"
sha256sum "$out" > "${out}.sha256"
printf 'Paquet : %s\nChecksum : %s.sha256\n' "$out" "$out"
