#!/usr/bin/env bash
# Verify a release package the way inc/updates.php does, from the shell:
# the .sha256 sidecar matches the zip and the .sig verifies against the
# pinned public key (tools/release-signing.pub). Needs only openssl.
#   tools/verify-release.sh oogle-theme.zip [oogle-theme.zip.sig] [oogle-theme.zip.sha256]
#   tools/verify-release.sh vX.Y.Z        (downloads the three assets of that Release with gh)
set -euo pipefail
cd "$(dirname "$0")/.."
PUB="tools/release-signing.pub"
if [[ "${1:-}" =~ ^v[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
	WORK="$(mktemp -d -t oogle-verify-XXXXXX)"
	gh release download "$1" --pattern 'oogle-theme.zip*' --dir "$WORK" --clobber
	set -- "$WORK/oogle-theme.zip" "$WORK/oogle-theme.zip.sig" "$WORK/oogle-theme.zip.sha256"
fi
ZIP="${1:?usage: verify-release.sh <zip|vX.Y.Z> [sig] [sha256]}"
SIG="${2:-$ZIP.sig}"
SHA="${3:-$ZIP.sha256}"
status=0
if [ -r "$SHA" ]; then
	[ "$(awk '{print $1}' "$SHA")" = "$(shasum -a 256 "$ZIP" | awk '{print $1}')" ] && echo "ok    sha256 matches $SHA" || { echo "FAIL  sha256 does not match $SHA"; status=1; }
else
	echo "skip  no .sha256 at $SHA"
fi
RAW="$(mktemp -t oogle-sig-XXXXXX)"
grep -E '^signature:' "$SIG" | head -1 | sed 's/^signature:[[:space:]]*//' | tr -d '\r\n' | base64 -d > "$RAW" 2>/dev/null || { echo 'FAIL  .sig has no decodable signature line'; exit 1; }
[ "$(wc -c < "$RAW" | tr -d ' ')" = 64 ] || { echo 'FAIL  signature is not 64 bytes'; exit 1; }
if openssl pkeyutl -verify -pubin -inkey "$PUB" -rawin -in "$ZIP" -sigfile "$RAW" >/dev/null 2>&1; then
	echo "ok    Ed25519 signature verifies against $PUB"
else
	echo "FAIL  Ed25519 signature does NOT verify against $PUB"; status=1
fi
rm -f "$RAW"
exit $status
