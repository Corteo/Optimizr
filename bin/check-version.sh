#!/usr/bin/env bash
# Vérifie que le numéro de version (MAJOR.MINOR.PATCH) est le même partout : en-tête « Version » et constante
# du fichier principal, « Stable tag » et changelog du readme.txt, dernière version du CHANGELOG.md et
# « Version actuelle » du README.md. Utilisé par l'intégration continue et avant chaque release.
#
# Usage : bin/check-version.sh   (affiche la version et sort en erreur au moindre écart)
set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
cd "$root"

main=""
for candidate in optimizr.php optimizr-pro.php; do
    [ -f "$candidate" ] && main="$candidate"
done
[ -n "$main" ] || { echo "Erreur : fichier principal introuvable" >&2; exit 1; }

version="$(sed -n 's/^ \* Version: *//p' "$main" | tr -d '\r')"
status=0

if ! [[ "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    echo "Erreur : la version « $version » de $main n'est pas au format MAJOR.MINOR.PATCH" >&2
    exit 1
fi

check() {
    if [ "$2" != "$version" ]; then
        echo "Erreur : $1 indique « $2 » au lieu de « $version »" >&2
        status=1
    fi
}

check "la constante de version de $main" "$(sed -n "s/^define('HAPTIC_OPTIMIZR[A-Z_]*_VERSION', '\([^']*\)');.*/\1/p" "$main")"
check "« Stable tag » de readme.txt" "$(sed -n 's/^Stable tag: *//p' readme.txt | tr -d '\r')"
check "le changelog de readme.txt" "$(sed -n 's/^= \([0-9.]*\) =.*/\1/p' readme.txt | head -n1)"
check "la dernière version de CHANGELOG.md" "$(sed -n 's/^## \[\([0-9.]*\)\].*/\1/p' CHANGELOG.md | head -n1)"
check "« Version actuelle » de README.md" "$(sed -n 's/^\*\*Version actuelle :\*\* \([0-9.]*\).*/\1/p' README.md)"

[ "$status" -eq 0 ] && echo "Version $version : en-tête, constante, readme.txt, CHANGELOG.md et README.md concordent."
exit "$status"
