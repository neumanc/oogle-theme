#!/usr/bin/env bash
# Validate theme.json and styles/*.json against the official WordPress theme.json
# schema for each supported WordPress version (schemas.wp.org redirects to the
# schema shipped in the Gutenberg branch of that release). jq only proves the
# files parse; this proves every key is one WordPress knows.
#   tools/validate-theme-json.sh 7.0 7.1
set -euo pipefail
cd "$(dirname "$0")/.."
[ "$#" -gt 0 ] || set -- 7.0 7.1
status=0
for version in "$@"; do
	schema="$(mktemp -t theme-json-schema-XXXXXX).json"
	curl -sSfL "https://schemas.wp.org/wp/${version}/theme.json" -o "$schema"
	jq -e '.definitions' "$schema" >/dev/null || { echo "schema for WordPress $version did not download"; exit 1; }
	for file in theme.json styles/*.json; do
		if npx -y ajv-cli@5 validate -s "$schema" -d "$file" --spec=draft7 --strict=false --all-errors >/tmp/ajv.out 2>&1; then
			echo "ok  WordPress $version  $file"
		else
			echo "FAIL WordPress $version  $file"; cat /tmp/ajv.out; status=1
		fi
	done
	rm -f "$schema"
done
exit $status
