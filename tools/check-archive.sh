#!/usr/bin/env bash
# Verify a production archive: one oogle-theme/ root, runtime files only, no
# dev tooling, metadata or repository internals, and (optionally) the version.
#   tools/check-archive.sh oogle-theme.zip [X.Y.Z]
set -euo pipefail
ZIP="$1"
LIST="$(unzip -Z1 "$ZIP")"
echo "$LIST" | awk -F/ '{print $1}' | sort -u | grep -qx 'oogle-theme' || { echo 'archive root is not oogle-theme/'; exit 1; }
[ "$(echo "$LIST" | awk -F/ '{print $1}' | sort -u | wc -l)" -eq 1 ] || { echo 'archive has more than one top-level entry'; exit 1; }
if echo "$LIST" | grep -E '(^|/)(\.git|\.github|\.gitattributes|\.gitignore|\.distignore|\.editorconfig|\.wp-env\.json|node_modules|vendor|docs|tools|tests|__MACOSX|\.DS_Store|\.map$|phpcs\.xml|AGENTS\.md|CONTRIBUTING\.md|SECURITY\.md|UPGRADE\.md|package(-lock)?\.json|composer\.(json|lock))(/|$)'; then
	echo 'Dev or metadata files leaked into the archive'; exit 1
fi
for required in oogle-theme/style.css oogle-theme/theme.json oogle-theme/templates/index.html oogle-theme/functions.php oogle-theme/inc/updates.php oogle-theme/readme.txt oogle-theme/LICENSE; do
	echo "$LIST" | grep -qx "$required" || { echo "missing $required"; exit 1; }
done
STYLE="$(unzip -p "$ZIP" oogle-theme/style.css)"
echo "$STYLE" | grep -qE '^Requires PHP: 8\.4$' || { echo 'style.css in archive does not declare Requires PHP: 8.4'; exit 1; }
echo "$STYLE" | grep -qE '^Update URI: https://github\.com/neumanc/oogle-theme$' || { echo 'style.css in archive has the wrong Update URI'; exit 1; }
if [ -n "${2:-}" ]; then
	echo "$STYLE" | grep -qE "^Version: ${2//./\\.}$" || { echo "style.css in archive is not version $2"; exit 1; }
fi
echo "archive ok: $(echo "$LIST" | grep -vc '/$') files"
