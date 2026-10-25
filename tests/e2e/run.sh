#!/usr/bin/env bash
# Oogle Theme — end-to-end upgrade test (dev only; never shipped).
#
# Runs the REAL core upgrader (bulk, single and WP-CLI paths) against REAL
# archives in a dedicated wp-env lab (port 8890; separate from the main lab),
# with the fixture child active, exactly as docs/RELEASES.md prescribes:
#
#   prev → new        the currently published release updates to the archive
#                     built from HEAD (what production sites will do), on all
#                     three paths; a tampered archive is refused
#   new  → next       the HEAD archive updates to the same archive with the
#                     version bumped one patch (exercises HEAD's updater):
#                     without a .sig it is not offered; signed by an unknown key
#                     it is refused; a downgrade is never offered
#   new  → next       with the maintainer's key available (not in CI): all three
#     (signed)        paths succeed; a tampered archive and an archive without
#                     theme.json are refused with the parent untouched
#
#   tests/e2e/run.sh            # PREV_TAG defaults to the latest published Release
#   PREV_ZIP=path/oogle-theme.zip tests/e2e/run.sh   # use a local archive as "prev"
#   KEEP=1 tests/e2e/run.sh     # leave the lab running afterwards
#
# Once the published release itself verifies signatures (1.2.0+), a run without
# the maintainer's key cannot produce a package it would install: the three
# "prev → new" success scenarios then expect the signature refusal instead,
# which is exactly what a 1.2.0+ site does with an unsigned or wrongly signed
# release. With the key (the maintainer's pre-tag run) they expect success.
# Needs docker, node, gh (to download the previous release), zip/unzip, openssl.
set -euo pipefail
cd "$(dirname "$0")"
ROOT="$(cd ../.. && pwd)"
ART="$PWD/artifacts"
KEY="${OOGLE_SIGNING_KEY:-$HOME/.oogle/release-signing/oogle-theme-release-signing.pem}"
WPENV="npx -y @wordpress/env@11"
pass=0; fail=0
step() { echo; echo "### $*"; }
cli() { $WPENV run cli "$@"; }
scenario() { # <target> <path> <expect>
	local out
	if out="$(cli wp eval-file /var/www/html/oogle-src/tests/e2e/upgrade.php "$1" "$2" "$3" 2>&1)"; then pass=$((pass+1)); echo "$out" | grep -E '^(PASS|FAIL|RESULT|upgrader said|installed)'; else fail=$((fail+1)); echo "$out" | grep -vE '^(ℹ|✔)' ; echo "!! scenario $1/$2/$3 FAILED"; fi
}
install_parent() { # <scenario dir>
	cli sh -c "rm -rf wp-content/themes/oogle-theme && cd wp-content/themes && unzip -q -o /var/www/html/oogle-artifacts/$1/oogle-theme.zip && test -f oogle-theme/style.css" >/dev/null
	cli wp theme activate oogle-fixture-child >/dev/null 2>&1 || true
}
sign() { # <zip> <mode: real|eph>
	if [ "$2" = real ]; then OOGLE_SIGNING_KEY="$KEY" bash "$ROOT/tools/sign-release.sh" "$1" >/dev/null; else OOGLE_SIGNING_KEY="$ART/ephemeral.pem" bash "$ROOT/tools/sign-release.sh" "$1" >/dev/null 2>&1 || true; fi
	# sign-release.sh refuses an ephemeral key (it does not match the pinned public key); write the .sig ourselves.
	if [ "$2" = eph ]; then
		local raw; raw="$(mktemp)"; openssl pkeyutl -sign -inkey "$ART/ephemeral.pem" -rawin -in "$1" -out "$raw"
		printf 'oogle-release-signature: 1\nalgorithm: ed25519\nkey-id: 0000000000000000\nsigned-file: oogle-theme.zip\nsignature: %s\n' "$(base64 < "$raw" | tr -d '\n')" > "$1.sig"; rm -f "$raw"
	fi
}
sha() { (cd "$(dirname "$1")" && shasum -a 256 "$(basename "$1")" > "$(basename "$1").sha256"); }

step "Build artifacts"
mkdir -p "$ART" && find "$ART" -mindepth 1 -delete # keep the directory itself: the lab bind-mounts it
openssl genpkey -algorithm ed25519 -out "$ART/ephemeral.pem" 2>/dev/null
MODE=eph; [ -r "$KEY" ] && MODE=real
echo "signing mode: $MODE ($([ $MODE = real ] && echo 'maintainer key present: positive signed scenarios run' || echo 'no maintainer key: only unsigned/negative scenarios run'))"
[ -z "$(git -C "$ROOT" status --porcelain)" ] || echo "NOTE: working tree is dirty; 'new' is built from HEAD, not from uncommitted changes"
NEW_VERSION="$(git -C "$ROOT" show HEAD:style.css | grep -E '^Version:' | awk '{print $2}')"
IFS=. read -r MA MI PA <<< "$NEW_VERSION"; NEXT_VERSION="$MA.$MI.$((PA+1))"
PREV_TAG="${PREV_TAG:-$(gh release list --repo neumanc/oogle-theme --exclude-drafts --exclude-pre-releases --limit 1 --json tagName -q '.[0].tagName')}"
echo "prev=$PREV_TAG  new=$NEW_VERSION (HEAD)  next=$NEXT_VERSION"

mkdir -p "$ART/prev"
if [ -n "${PREV_ZIP:-}" ]; then
	cp "$PREV_ZIP" "$ART/prev/oogle-theme.zip"; sha "$ART/prev/oogle-theme.zip"; echo "prev = local archive $PREV_ZIP"
else
	gh release download "$PREV_TAG" --repo neumanc/oogle-theme --pattern 'oogle-theme.zip*' --dir "$ART/prev" --clobber
	[ "$(awk '{print $1}' "$ART/prev/oogle-theme.zip.sha256")" = "$(shasum -a 256 "$ART/prev/oogle-theme.zip" | awk '{print $1}')" ] || { echo "published $PREV_TAG sha256 does not match its sidecar"; exit 1; }
fi
PREV_VERSION="$(unzip -p "$ART/prev/oogle-theme.zip" oogle-theme/style.css | grep -E '^Version:' | awk '{print $2}')"
PREV_SIGNS=0; unzip -p "$ART/prev/oogle-theme.zip" oogle-theme/inc/updates.php | grep -q oogle_update_signing_keys && PREV_SIGNS=1
echo "prev package version $PREV_VERSION (verifies signatures: $PREV_SIGNS)"

rezip_as_version() { # <src zip> <dest zip> <version>: the same files with style.css Version changed
	local tmp; tmp="$(mktemp -d)"
	(cd "$tmp" && unzip -q "$1")
	perl -pi -e "s/^Version: .*/Version: $3/" "$tmp/oogle-theme/style.css"
	rm -f "$2"; (cd "$tmp" && zip -q -X -r "$2" oogle-theme); rm -rf "$tmp"
}
mkdir -p "$ART/new" && git -C "$ROOT" archive --format=zip --prefix=oogle-theme/ HEAD -o "$ART/new/oogle-theme.zip"
bash "$ROOT/tools/check-archive.sh" "$ART/new/oogle-theme.zip" "$NEW_VERSION"
if [ "$(printf '%s\n%s\n' "$PREV_VERSION" "$NEW_VERSION" | sort -V | tail -1)" = "$PREV_VERSION" ]; then
	# HEAD is not ahead of the published release (normal on main between releases): the
	# same files are tested under the next patch number so that core offers them.
	IFS=. read -r MA MI PA <<< "$PREV_VERSION"; NEW_VERSION="$MA.$MI.$((PA+1))"; NEXT_VERSION="$MA.$MI.$((PA+2))"
	echo "HEAD version is not ahead of $PREV_VERSION: testing HEAD's files as $NEW_VERSION (next: $NEXT_VERSION)"
	rezip_as_version "$ART/new/oogle-theme.zip" "$ART/new/oogle-theme.zip.tmp" "$NEW_VERSION" && mv "$ART/new/oogle-theme.zip.tmp" "$ART/new/oogle-theme.zip"
fi
sha "$ART/new/oogle-theme.zip" && sign "$ART/new/oogle-theme.zip" $MODE

mkdir -p "$ART/next" && rezip_as_version "$ART/new/oogle-theme.zip" "$ART/next/oogle-theme.zip" "$NEXT_VERSION" && sha "$ART/next/oogle-theme.zip" && sign "$ART/next/oogle-theme.zip" $MODE
mkdir -p "$ART/next-unsigned" && cp "$ART/next/oogle-theme.zip" "$ART/next/oogle-theme.zip.sha256" "$ART/next-unsigned/"
mkdir -p "$ART/next-eph" && cp "$ART/next/oogle-theme.zip" "$ART/next/oogle-theme.zip.sha256" "$ART/next-eph/" && sign "$ART/next-eph/oogle-theme.zip" eph
mkdir -p "$ART/tampered" && cp "$ART/new/oogle-theme.zip.sha256" "$ART/new/oogle-theme.zip.sig" "$ART/tampered/" && cp "$ART/new/oogle-theme.zip" "$ART/tampered/oogle-theme.zip" && printf 'x' >> "$ART/tampered/oogle-theme.zip"
mkdir -p "$ART/next-tampered" && cp "$ART/next/oogle-theme.zip.sha256" "$ART/next/oogle-theme.zip.sig" "$ART/next-tampered/" && cp "$ART/next/oogle-theme.zip" "$ART/next-tampered/oogle-theme.zip" && printf 'x' >> "$ART/next-tampered/oogle-theme.zip"
mkdir -p "$ART/next-broken" && cp "$ART/next/oogle-theme.zip" "$ART/next-broken/" && zip -q -d "$ART/next-broken/oogle-theme.zip" oogle-theme/theme.json && sha "$ART/next-broken/oogle-theme.zip" && sign "$ART/next-broken/oogle-theme.zip" $MODE
ls -la "$ART"/*/ | grep -E 'oogle-theme|^/'

step "Start the e2e lab (wp-env, port 8890)"
$WPENV stop >/dev/null 2>&1 || true # a lab left running (KEEP=1) holds stale bind mounts of the rebuilt artifacts
$WPENV start >/dev/null
cli sh -c 'mkdir -p wp-content/mu-plugins && cp /var/www/html/oogle-src/tests/e2e/mu-mock.php wp-content/mu-plugins/oogle-e2e-mock.php && : > wp-content/debug.log' >/dev/null
cli sh -c 'php -r "echo PHP_VERSION, PHP_EOL;"; wp core version' 2>/dev/null | grep -vE '^(ℹ|✔)'
install_parent prev
cli wp theme activate oogle-fixture-child 2>&1 | grep -vE '^(ℹ|✔)'
cli wp eval-file /var/www/html/oogle-src/tests/e2e/upgrade.php seed 2>&1 | grep -vE '^(ℹ|✔)'

A_EXPECT=success
if [ "$PREV_SIGNS" = 1 ] && [ "$MODE" = eph ]; then
	A_EXPECT="fail:not signed by a key this theme trusts"
	step "A. Production path today: $PREV_VERSION (its own updater, verifies signatures) → $NEW_VERSION signed with an UNKNOWN key: must be refused on every path"
else
	step "A. Production path today: $PREV_VERSION (its own updater) → $NEW_VERSION"
fi
install_parent prev; scenario new bulk "$A_EXPECT"
install_parent prev; scenario new single "$A_EXPECT"
install_parent prev; scenario new cli "$A_EXPECT"
install_parent prev; scenario tampered bulk "fail:does not match the SHA-256"

step "B. HEAD's updater: $NEW_VERSION → $NEXT_VERSION"
install_parent new; scenario next-unsigned bulk not-offered
install_parent new; scenario next-eph bulk "fail:not signed by a key this theme trusts"
install_parent new; scenario next-eph single "fail:not signed by a key this theme trusts"
install_parent new; scenario prev bulk not-offered
if [ "$MODE" = real ]; then
	install_parent new; scenario next bulk success
	install_parent new; scenario next single success
	install_parent new; scenario next cli success
	install_parent new; scenario next-tampered bulk "fail:does not match the SHA-256"
	install_parent new; scenario next-broken bulk "fail:theme.json is missing"
	install_parent new; scenario next-broken single "fail:theme.json is missing"
else
	echo "skipped (no maintainer key): next bulk/single/cli success, next-tampered, next-broken"
fi

step "debug.log"
if cli sh -c 'test -s wp-content/debug.log' >/dev/null 2>&1; then cli cat wp-content/debug.log | grep -vE '^(ℹ|✔)'; echo "!! debug.log is not empty"; fail=$((fail+1)); else echo "debug.log empty"; fi

[ "${KEEP:-0}" = 1 ] || $WPENV stop >/dev/null
echo; echo "E2E RESULT: $pass scenarios passed, $fail failed (signing mode: $MODE)"
[ "$fail" -eq 0 ]
