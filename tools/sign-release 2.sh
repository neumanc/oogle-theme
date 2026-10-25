#!/usr/bin/env bash
# Sign a release package with the maintainer's Ed25519 key and (optionally)
# attach the signature to the GitHub Release. The private key never enters a
# repository or CI: it lives with the maintainer (default
# ~/.oogle/release-signing/oogle-theme-release-signing.pem). inc/updates.php on
# every site refuses a package whose .sig does not verify against the public
# key pinned there (tools/release-signing.pub is the same key, PEM form).
#
#   tools/sign-release.sh vX.Y.Z [--upload] [--key PATH]
#       Reproduces oogle-theme.zip from the tag with the exact git archive
#       command the Release workflow uses, proves it is byte-identical to the
#       asset the workflow published (its .sha256), signs it and verifies the
#       signature with the committed public key. --upload attaches
#       oogle-theme.zip.sig to the Release (gh release upload --clobber).
#   tools/sign-release.sh path/to/oogle-theme.zip [--key PATH]
#       Signs an arbitrary archive (lab tests); writes <zip>.sig beside it.
set -euo pipefail
cd "$(dirname "$0")/.."

KEY="${OOGLE_SIGNING_KEY:-$HOME/.oogle/release-signing/oogle-theme-release-signing.pem}"
PUB="tools/release-signing.pub"
UPLOAD=0
TARGET=""
while [ $# -gt 0 ]; do
	case "$1" in
		--upload) UPLOAD=1 ;;
		--key) KEY="$2"; shift ;;
		-h|--help) sed -n '2,20p' "$0"; exit 0 ;;
		*) TARGET="$1" ;;
	esac
	shift
done
[ -n "$TARGET" ] || { echo 'usage: tools/sign-release.sh <vX.Y.Z | oogle-theme.zip> [--upload] [--key PATH]'; exit 2; }
[ -r "$KEY" ] || { echo "signing key not readable: $KEY"; exit 1; }
[ -r "$PUB" ] || { echo "public key missing: $PUB"; exit 1; }

sign_file() { # $1 zip → writes $1.sig
	local zip="$1" sig="$1.sig" raw sha keyid b64
	raw="$(mktemp -t oogle-sig-XXXXXX)"
	openssl pkeyutl -sign -inkey "$KEY" -rawin -in "$zip" -out "$raw"
	openssl pkeyutl -verify -pubin -inkey "$PUB" -rawin -in "$zip" -sigfile "$raw" >/dev/null \
		|| { echo 'signature does not verify with tools/release-signing.pub: wrong key?'; rm -f "$raw"; exit 1; }
	sha="$(shasum -a 256 "$zip" | awk '{print $1}')"
	keyid="$(openssl pkey -pubin -in "$PUB" -outform DER | tail -c 32 | shasum -a 256 | cut -c1-16)"
	b64="$(base64 < "$raw" | tr -d '\n')"
	rm -f "$raw"
	{
		echo 'oogle-release-signature: 1'
		echo 'algorithm: ed25519'
		echo "key-id: $keyid"
		echo "signed-file: $(basename "$zip")"
		echo "sha256: $sha"
		echo "signature: $b64"
	} > "$sig"
	echo "signed  $zip"
	echo "  key-id  $keyid"
	echo "  sha256  $sha"
	echo "  sig     $sig"
}

if [[ "$TARGET" =~ ^v[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
	TAG="$TARGET"
	WORK="$(mktemp -d -t oogle-release-XXXXXX)"
	git rev-parse -q --verify "refs/tags/$TAG" >/dev/null || { echo "no local tag $TAG (git fetch --tags)"; exit 1; }
	git archive --format=zip --prefix=oogle-theme/ "$TAG" -o "$WORK/oogle-theme.zip"
	bash tools/check-archive.sh "$WORK/oogle-theme.zip" "${TAG#v}" >/dev/null
	gh release download "$TAG" --pattern 'oogle-theme.zip.sha256' --dir "$WORK" --clobber
	PUBLISHED="$(awk '{print $1}' "$WORK/oogle-theme.zip.sha256")"
	LOCAL="$(shasum -a 256 "$WORK/oogle-theme.zip" | awk '{print $1}')"
	[ "$PUBLISHED" = "$LOCAL" ] || { echo "REFUSING: the published asset ($PUBLISHED) is not the archive reproduced from $TAG ($LOCAL)"; exit 1; }
	echo "reproduced $TAG: published asset matches ($LOCAL)"
	sign_file "$WORK/oogle-theme.zip"
	if [ "$UPLOAD" = 1 ]; then
		gh release upload "$TAG" "$WORK/oogle-theme.zip.sig" --clobber
		echo "uploaded oogle-theme.zip.sig to release $TAG"
	else
		echo "not uploaded (pass --upload); file: $WORK/oogle-theme.zip.sig"
	fi
else
	[ -r "$TARGET" ] || { echo "not readable: $TARGET"; exit 1; }
	sign_file "$TARGET"
fi
