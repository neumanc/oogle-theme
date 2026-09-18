#!/usr/bin/env bash
# The version is declared once (style.css); readme.txt's Stable tag and the
# CHANGELOG section must agree with it. With an argument (a tag), all must
# equal that tag.
set -euo pipefail
cd "$(dirname "$0")/.."
VER="$(grep -E '^Version:' style.css | awk '{print $2}')"
STABLE="$(grep -E '^Stable tag:' readme.txt | awk '{print $3}')"
echo "style.css=$VER readme.txt=$STABLE tag=${1:-}"
[[ "$VER" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || { echo 'style.css Version is not X.Y.Z'; exit 1; }
[ "$VER" = "$STABLE" ] || { echo 'readme.txt Stable tag does not match style.css'; exit 1; }
grep -qE "^## \[$VER\]" CHANGELOG.md || { echo "CHANGELOG.md has no [$VER] section"; exit 1; }
if [ -n "${1:-}" ]; then
	[ "$1" = "$VER" ] || { echo "style.css Version $VER does not match tag $1"; exit 1; }
fi
echo ok
