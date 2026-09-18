#!/usr/bin/env bash
# Fail if anything that looks like a credential is tracked in the theme.
set -euo pipefail
cd "$(dirname "$0")/.."
pattern='(ghp_[A-Za-z0-9]{20,}|github_pat_[A-Za-z0-9_]{20,}|AKIA[0-9A-Z]{16}|-----BEGIN (RSA |EC |OPENSSH )?PRIVATE KEY-----|xox[baprs]-[A-Za-z0-9-]{10,}|sk_live_[A-Za-z0-9]{10,}|OOGLE_GITHUB_TOKEN'"'"'\s*,\s*'"'"'[^'"'"']{4,}|(password|passwd|secret|api[_-]?key)\s*[:=]\s*["'"'"'][^"'"'"']{6,})'
if grep -rniE "$pattern" --exclude-dir=.git --exclude-dir=node_modules --exclude-dir=vendor --exclude=secret-scan.sh . | grep -v "wp_generate_password" ; then
	echo 'Possible secret found'; exit 1
fi
echo 'no secrets found'
