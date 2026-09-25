#!/usr/bin/env bash
# Oogle Theme — front-end render check over HTTP for both supported configurations
# (dev only; excluded from the release zip). Needs the theme lab running (wp-env).
#   tests/child-theme-http.sh http://localhost:8888 parent   (oogle-theme active)
#   tests/child-theme-http.sh http://localhost:8888 child    (oogle-fixture-child active)
set -uo pipefail
BASE="${1:?usage: child-theme-http.sh <base-url> parent|child}"
BASE="${BASE%/}"
MODE="${2:?usage: child-theme-http.sh <base-url> parent|child}"
TMP="$(mktemp -d)"
pass=0
fail=0
t() { if [ "$2" = 0 ]; then pass=$((pass + 1)); echo "PASS  $1"; else fail=$((fail + 1)); echo "FAIL  $1"; fi; }
get() { curl -s --noproxy '*' -o "$TMP/$1.html" -w '%{http_code}' "$BASE$2"; }
has() { grep -q -- "$2" "$TMP/$1.html"; echo $?; }
hasnt() { if grep -q -- "$2" "$TMP/$1.html"; then echo 1; else echo 0; fi; }
before() { # $2 occurs before $3 in page $1
	local a b
	a="$(grep -bo -- "$2" "$TMP/$1.html" | head -1 | cut -d: -f1)"
	b="$(grep -bo -- "$3" "$TMP/$1.html" | head -1 | cut -d: -f1)"
	[ -n "$a" ] && [ -n "$b" ] && [ "$a" -lt "$b" ]; echo $?
}

echo "MODE: $MODE at $BASE"
t "home answers 200" "$([ "$(get home /)" = 200 ]; echo $?)"
t "search answers 200" "$([ "$(get search '/?s=hello')" = 200 ]; echo $?)"
t "missing post answers 404" "$([ "$(get missing '/?p=999999999')" = 404 ]; echo $?)"
for page in home search missing; do
	t "$page: no PHP error text in the page" "$(if grep -qE 'Fatal error|Warning:|Notice:|Deprecated:|Parse error' "$TMP/$page.html"; then echo 1; else echo 0; fi)"
	t "$page: parent base stylesheet from the parent directory" "$(has "$page" '/themes/oogle-theme/assets/css/base.css')"
	t "$page: header part rendered (parent's)" "$(has "$page" 'class="wp-block-group oogle-header')"
done

if [ "$MODE" = child ]; then
	t "home: child stylesheet from the child directory" "$(has home '/themes/oogle-fixture-child/assets/css/fixture.css')"
	t "home: child stylesheet printed after the parent's base" "$(before home 'oogle-base-css' 'oogle-fixture-child-css')"
	t "home: child palette in the global styles" "$(has home '--wp--preset--color--base: #fbf8f1')"
	t "home: child card token in the global styles" "$(has home '--wp--custom--card--radius: 0')"
	t "home: child footer part (template-part override)" "$(has home 'oogle-fixture-footer')"
	t "search: child search template (template override)" "$(has search 'oogle-fixture-search')"
	t "missing: parent 404 template with the child's oogle/hidden-404 (pattern override)" "$(has missing 'oogle-fixture-404')"
else
	t "home: no child assets" "$(hasnt home 'oogle-fixture')"
	t "home: parent palette in the global styles" "$(has home '--wp--preset--color--base: #ffffff')"
	t "home: parent card token default" "$(has home '--wp--custom--card--radius: var(--wp--custom--radius--md)')"
	t "home: parent footer part" "$(has home 'oogle-footer')"
	t "missing: parent 404 body" "$(has missing 'Page not found')"
fi

rm -rf "$TMP"
echo
echo "RESULT ($MODE, HTTP): $pass passed, $fail failed"
[ "$fail" -eq 0 ]
