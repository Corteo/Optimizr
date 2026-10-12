#!/usr/bin/env bash
# Construit la version WordPress.org d'Optimizr : dist/<slug>/ et dist/<slug>-<version>.zip.
#
# Différences avec la version GitHub :
#   - fichiers listés dans .distignore retirés (mises à jour GitHub, fichiers de développement) ;
#   - lignes marquées « build:github-only » retirées des fichiers PHP.
#
# Usage : bin/build-wporg.sh [slug]   (slug par défaut : optimizr)
set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
slug="${1:-optimizr}"
version="$(sed -n 's/^ \* Version: *//p' "$root/optimizr.php")"
out="$root/dist/$slug"

rm -rf "$out" "$root/dist/$slug-$version.zip"
mkdir -p "$out"

# Copie de l'arborescence suivie par git, moins les exclusions de .distignore
excludes=()
while IFS= read -r line; do
    line="${line%%#*}"
    line="$(echo "$line" | xargs)"
    [ -n "$line" ] && excludes+=("$line")
done < "$root/.distignore"

cd "$root"
git ls-files -z | while IFS= read -r -d '' file; do
    for pattern in "${excludes[@]}"; do
        case "$file" in
            "$pattern" | "$pattern"/*) continue 2 ;;
        esac
    done
    mkdir -p "$out/$(dirname "$file")"
    cp "$file" "$out/$file"
done

# Retrait des lignes propres à la version GitHub
grep -rl 'build:github-only' "$out" --include='*.php' | while IFS= read -r file; do
    sed -i.bak '/build:github-only/d' "$file" && rm -f "$file.bak"
done

# Garde-fous : readme.txt complété, rien ne doit rester du système de mise à jour GitHub
if grep -rqE 'plugin-update-checker|PucFactory|token\.php|haptic_get_github_token' "$out"; then
    echo "Erreur : du code de mise à jour GitHub subsiste dans $out" >&2
    grep -rnE 'plugin-update-checker|PucFactory|token\.php|haptic_get_github_token' "$out" >&2
    exit 1
fi
hidden="$(find "$out" -mindepth 1 -name '.*')"
if [ -n "$hidden" ]; then
    echo "Erreur : fichiers cachés refusés par WordPress.org (à ajouter dans .distignore) :" >&2
    echo "$hidden" >&2
    exit 1
fi
if grep -q 'REPLACE_WITH_WPORG_USERNAME' "$out/readme.txt"; then
    echo "Erreur : renseignez l'identifiant WordPress.org dans « Contributors » (readme.txt)" >&2
    exit 1
fi
if command -v php >/dev/null; then
    find "$out" -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null
fi

(cd "$root/dist" && zip -qr "$slug-$version.zip" "$slug")
echo "Version WordPress.org $version : dist/$slug-$version.zip"
