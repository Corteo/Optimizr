#!/usr/bin/env bash
# Vérifie la syntaxe de tous les fichiers PHP suivis par git avec le PHP installé (php -l).
# L'intégration continue le lance de PHP 7.4 à PHP 8.4.
#
# Usage : bin/check-php.sh
set -euo pipefail

cd "$(dirname "$0")/.."
status=0
count=0
while IFS= read -r -d '' file; do
    count=$((count + 1))
    if ! output="$(php -l "$file" 2>&1)"; then
        echo "$output" >&2
        status=1
    fi
done < <(git ls-files -z '*.php')

[ "$status" -eq 0 ] && echo "$count fichiers PHP sans erreur de syntaxe ($(php -r 'echo PHP_VERSION;'))."
exit "$status"
