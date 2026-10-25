# Security audit, remediation and release — 24 October 2026

Scope: the Oogle Theme parent repository (`neumanc/oogle-theme`) at `v1.1.0` (`b7c0c09`),
every PHP, JavaScript, template, pattern, configuration, build, update and release component,
plus the parent/child contract as exercised by the fixture child and the six client child
themes in the workspace. Performed as an execution assignment: findings were fixed, covered by
regression tests, and shipped as **1.2.0**. Deployment to client sites was not in scope.

## 1. Executive summary

**Initial risk assessment.** Low. The theme is presentation only: no form handling, no AJAX or
REST endpoints, no database queries, no file uploads, no Customizer or options, no user input
of its own. Every string it prints is static placeholder copy, escaped at output. The one
security-relevant component is the GitHub updater, and it was already fail-closed on every
path examined (exact-host asset URLs, no redirects on metadata, token confined to
`api.github.com`, mandatory `.sha256`, package identity and version gates on every upgrader
path, major-version policy). The attack surface a WordPress theme normally has was absent.

**Confirmed findings by severity.**

| Severity | Count | Resolved in 1.2.0 |
|---|---|---|
| Critical | 0 | — |
| High | 0 | — |
| Medium | 1 | 1 |
| Low | 9 | 9 (one closed in the follow-up commit after publication) |
| Informational / outside the repository | 2 | recommendations recorded |

**What was fixed.** The Medium finding — the update checksum lived in the same GitHub Release
as the archive, so it proved only that the transfer was intact, never who published it — is
closed by signed releases: an Ed25519 signature over the archive, verified against a public key
pinned in the theme, with the private key held offline by the maintainer and never in GitHub or
CI. The Low findings harden the updater's transport (TLS verification and response-size caps a
filter cannot lift, asset URLs bound to the release tag), the package gate (symbolic links
refused, rejected packages removed from `wp-content/upgrade/`), the rotator module (no
`innerHTML`, Trusted-Types compatible), the release workflow (first-party publishing, SLSA
provenance, no persisted credentials, Dependabot for actions, archive symlink/traversal check),
and the test coverage (an end-to-end test of the real core upgrader on every path and both
supported WordPress versions).

**Residual risks.** A compromised maintainer machine or signing key (the trust anchor; rotation
and backup procedure documented), and site-side PHP (plugins) — neither can be mitigated by a
theme. CI's development tooling (`composer`, `npx`) is installed by version range, not hash, in
read-only jobs that cannot influence the artefact (built by `git archive` in a separate job);
left as is, noted below. Repository settings recommended but deliberately not changed by the
audit are listed in §2.10.

## 2. Technical findings

Each entry: component, root cause, prerequisites, realistic risk, fix, tests.

### 2.1 Medium — Update checksum is not an independent trust anchor (`inc/updates.php`)

- **Root cause.** `oogle-theme.zip.sha256` is an asset of the same GitHub Release as the
  archive. Whoever can write the one can write the other. SECURITY.md said so honestly, but
  the consequence was that any party able to edit Releases — a leaked token with `repo`
  scope, a compromised GitHub account, a malicious or compromised Actions dependency running
  in the release job with `contents: write` — could publish an archive every site would install.
- **Prerequisites.** Write access to the repository's Releases (or to the workflow that
  publishes them). Not reachable from a site, from the network, or by a lower-privileged
  WordPress user.
- **Fix.** Every release carries `oogle-theme.zip.sig` (detached Ed25519 signature over the
  archive bytes, `key: value` text file). `oogle_update_fetch_release()` requires the asset at
  GitHub's canonical URL for the release's own tag; `oogle_update_pre_download()` fetches it,
  parses it (`oogle_update_parse_signature()`), and after the SHA-256 match verifies the
  downloaded file with `sodium_crypto_sign_verify_detached()` against the keys in
  `oogle_update_signing_keys()` (`oogle_update_verify_signature()`). The key list is pinned,
  not filterable, and supports rotation (several keys). A missing, malformed, unreachable or
  non-verifying signature refuses the update before any file is replaced; a release cached by
  an older updater without a signature URL is refused and the cache flushed. The private key
  was generated on the maintainer's machine (`~/.oogle/release-signing/`, mode 600) and never
  entered the repository, a GitHub secret, CI or this audit's transcript; the Release workflow
  publishes zip + `.sha256` and the maintainer attaches the `.sig` with
  `tools/sign-release.sh vX.Y.Z --upload`, which first reproduces the archive from the tag and
  refuses unless it is byte-identical to the published asset. `tools/release-signing.pub`
  holds the same public key in PEM form; `tools/verify-release.sh` checks a release with
  OpenSSL. Trust model, rotation and key-loss procedure: SECURITY.md.
- **Tests.** `tests/updater-security.php`: pinned key equals the committed `.pub`; a vector
  signed with the real key verifies against the pinned key; one extra byte, one flipped bit,
  an unknown (freshly generated) key, an empty key set, garbage key material all refused;
  rotation set accepted; `.sig` parsing (CRLF, unknown lines, missing/not-base64/wrong-length,
  HTML); release without `.sig` not offered; `.sig` on another host or another tag rejected;
  download gate: checksum match but unknown-key signature refused and temp file removed;
  malformed or missing `.sig` at install time refused; cached release without signature URL
  refused and cache flushed. `tests/e2e/run.sh`: through the real core upgrader, an unsigned
  "next" package is never offered, one signed with an unknown key is refused on the bulk and
  single paths with the parent untouched, and a correctly signed one installs on the bulk,
  single and `wp theme update` paths.

### 2.2 Low — `oogle/updates/request_args` could disable TLS verification

- **Root cause.** The filter's result was used as-is except for `redirection`; a filter
  returning `sslverify => false` would have been honoured.
- **Prerequisites.** Site PHP (a plugin) — already trusted — or a mistaken proxy snippet.
  Risk: defence in depth against a well-meant misconfiguration rather than an attack.
- **Fix.** `sslverify` is forced to `true` after the filter, like `redirection`.
- **Tests.** "sslverify forced true after the filter" in the updater suite.

### 2.3 Low — Unbounded response bodies on metadata requests

- **Root cause.** No `limit_response_size` on the releases list and `style.css` requests
  (the sidecar requests had 4 KB caps).
- **Risk.** A hostile or broken host (or a filtered `api_url` in a lab) could make a site read
  an arbitrarily large body into memory.
- **Fix.** 1 MB cap on the releases list, 64 KB on `style.css`, re-applied after the filter.
  A truncated body fails JSON or header validation and is refused.
- **Tests.** "limit_response_size restored after the filter", "style.css request capped".

### 2.4 Low — Asset URLs not bound to the release's tag

- **Root cause.** `oogle_update_is_release_asset_url()` accepted any non-empty tag segment.
- **Risk.** Release metadata naming an asset of a different tag of the same repository would
  have passed URL validation; the package/version gates would still have caught a version
  mismatch, so defence in depth.
- **Fix.** Optional `$tag` parameter compared exactly (after percent-decoding) for the package
  and both sidecars.
- **Tests.** Tag-mismatch cases in both the URL unit tests and the mocked API scenarios.

### 2.5 Low — Rejected package left extracted under `wp-content/upgrade/` (found by the new e2e test)

- **Root cause.** Core returns an `upgrader_source_selection` error without clearing its
  working directory. A package refused by `oogle_update_validate_package()` therefore stayed
  on disk (PHP files included) under a random directory name.
- **Risk.** Low: a package reaching this gate has already passed the checksum and (now)
  signature checks, so its content is the maintainer's. Hygiene and defence in depth.
- **Fix.** `oogle_update_source_selection()` deletes the working directory on rejection.
- **Tests.** Unit: "rejected package: the extracted files and the working directory are
  removed". E2E: "no extracted package left in wp-content/upgrade" after each refusal.

### 2.6 Low — No symbolic-link check on the extracted package

- **Root cause.** Core's unzip rejects `../` entries (`validate_file`) and writes zip
  symlinks as regular files, so an extracted package cannot contain a link; the gate did not
  assert it.
- **Fix.** `oogle_update_find_symlink()` walks the extracted directory (not following links);
  any link rejects the package. `tools/check-archive.sh` additionally refuses an archive with a
  symlink or traversal entry at build time.
- **Tests.** "package containing a symbolic link → rejected"; "no symlink in the installed parent".

### 2.7 Low — `innerHTML` in the rotator module (`assets/js/rotator.js`)

- **Root cause.** The pause/play control's icons were assigned as static SVG strings.
- **Risk.** No injection (constant markup), but the assignment is refused under a Content
  Security Policy with `require-trusted-types-for 'script'`, which would break the control.
- **Fix.** Icons built once with `createElementNS` and swapped with `replaceChildren`.
- **Tests.** Browser regression suite (Chromium, WebKit, Firefox; 261 assertions) including
  the rotator control and axe: 0 violations.

### 2.8 Low — Release workflow and CI supply chain (`.github/workflows`)

- **Findings.** Publishing through a third-party action (`softprops/action-gh-release`, SHA
  pinned but one more dependency with `contents: write`); checkout token persisted in jobs that
  run third-party tooling; no build provenance; no Dependabot for actions; `release.yml` did not
  mark releases *Latest* explicitly (RELEASES.md relies on it for 1.0.0 sites).
- **Fix.** The release job publishes with the runner's own `gh release create --verify-tag
  --latest`; attests SLSA build provenance (`actions/attest-build-provenance`, SHA pinned;
  verify with `gh attestation verify oogle-theme.zip --owner neumanc`); `persist-credentials:
  false` on every checkout; `.github/dependabot.yml` for `github-actions`. Permissions stay
  least-privilege (`contents: read` everywhere; `contents: write`, `id-token: write`,
  `attestations: write` only in the release job). No workflow interpolates untrusted input
  into a shell; `pull_request` (not `pull_request_target`) is used.
- **Not changed.** `composer global require` and `npx` pin by version range. They run in
  read-only gate jobs; the artefact is built by `git archive` in a separate job from a clean
  checkout, so they cannot alter it. Pinning them by hash would need a lock file committed to a
  repository that otherwise has no dependency manifest; recorded as an accepted residual.

### 2.9 Low — Test coverage gaps

- **Findings.** Every suite mocked discovery or called the gate functions directly; nothing
  ran the real `Theme_Upgrader` against a built archive. CI exercised WordPress 7.1 only
  although 7.0 is supported.
- **Fix.** `tests/e2e/run.sh` + `tests/e2e/upgrade.php` + `tests/e2e/mu-mock.php`: a dedicated
  wp-env lab installs the currently published release with the fixture child active and runs
  `Theme_Upgrader::bulk_upgrade()`, `Theme_Upgrader::upgrade()` and `wp theme update` against
  archives built from the commit (success, tampered, unsigned, unknown key, missing
  `theme.json`, downgrade), asserting the parent is byte-identical to the archive or
  untouched, the child's files, active theme, theme mods and content unchanged, maintenance
  mode off, `wp-content/upgrade/` clean and `debug.log` empty. CI runs it on every push
  (unsigned scenarios); the maintainer's pre-tag run covers the signed path. The child
  compatibility job now runs on WordPress 7.0 and 7.1.
- **Found by it.** Finding 2.5, and two mock-fidelity issues in the harness itself (core's
  plugin update endpoint shape; WP-CLI's wordpress.org theme-information lookup).

### 2.9a Low — `git archive` is not byte-reproducible across git builds (found while signing)

- **Observation.** The archive reproduced from `v1.2.0` with Apple Git 2.54 on macOS has
  exactly the same content as the one the Ubuntu runner built (every extracted file identical,
  same entries, same embedded commit id) but a different deflate stream, hence a different
  SHA-256. RELEASES.md stated the zip was "a pure function of the tag"; it is a pure function
  of the tag's *content*.
- **Risk.** None to sites (the signature covers whatever bytes are published and verified
  against the content of the tag). The risk was operational: the signing step refused, which
  would have left 1.2.0+ sites without updates until resolved.
- **Fix.** `tools/sign-release.sh` downloads the published asset, checks it against its own
  `.sha256`, and when the bytes differ from the local reproduction extracts both and signs the
  published asset only if every file is identical (`diff -r`), printing both digests; any
  content difference still refuses. RELEASES.md corrected.


Observed via the GitHub API: `main` is not protected, no rulesets, Actions `allowed_actions:
all`, `sha_pinning_required: false`, Dependabot security updates disabled (secret scanning and
push protection are enabled). Recommended, in order: a ruleset on `v*` tags restricting
creation to administrators (the release trigger); `sha_pinning_required: true` (the workflows
already comply); Dependabot security updates on; a `release` environment with a required
reviewer if the signing step is ever moved into CI. Branch protection requiring pull requests
was not applied because this is a single-maintainer repository and it would block the
maintainer's own release flow; it is the maintainer's call.

### 2.11 Informational — outside the repository

`client-child-themes/healingaffairs/healingaffairscounseling-login.md` and
`client-child-themes/smokycove/smokycove-login.md` are small untracked files whose names and
word counts suggest plaintext credentials. They were not read, printed or changed. They are not
in any git history reachable from this repository. Recommendation: move the contents to a
password manager and delete the files; rotate the credentials if the files were ever synced or
shared.

### 2.12 Reviewed with no finding

- **Output escaping.** Every pattern escapes every output (`esc_html_e`, `esc_url`,
  `esc_attr_e`, `wp_json_encode` with hex flags inside block comments); the one
  `phpcs:ignore` is a value escaped two lines above. Templates and parts contain no PHP, no
  scripts, no inline handlers, no `target="_blank"`. `WP_HTML_Tag_Processor` is used for every
  render-time attribute change (a11y role, LCP/defer attributes).
- **Injection, CSRF, authorisation.** No SQL, no `$_GET`/`$_POST`/`$_REQUEST`, no nonces
  needed (no state-changing request is handled), no capability-gated feature (the inserter
  allow-list is editorial, documented as not a security control, and only ever narrows).
- **Files and inclusion.** `functions.php` requires a fixed list; no dynamic template loading;
  the only file operations are the updater's on core's extracted package.
- **SSRF / redirects / tokens.** Requests go to three fixed GitHub hosts with exact-host
  comparison; `api_url`/`style_url` are documented lab hooks that cannot redirect the token or
  the package (asset URLs must still be github.com for the canonical repository and tag). The
  real-transport redirect test (`tests/redirect-transport.php`) still proves the 3xx fail-closed.
- **Information disclosure.** No debug output; error messages name the reason without paths or
  secrets; the `User-Agent` mirrors core's own (site URL), as core does for wordpress.org.
- **JavaScript.** Two dependency-free ES modules; the only sinks are `classList`,
  `setAttribute` with fixed values and, after 2.7, DOM construction; labels come from core's
  script-module data via `JSON.parse` into `aria-label` (text, not markup). No third-party
  scripts, fonts or CSS; no external `url()` in any stylesheet.
- **Secrets.** `gitleaks` over all 29 commits: no leaks. `tools/secret-scan.sh` clean.
- **Dependencies.** None at runtime (no Composer, no npm, no bundled libraries). The
  development tooling is listed in §2.8.
- **Roles.** Nothing in the theme behaves differently per role or exposes anything to a
  lower role: no settings, no endpoints, no shortcodes with side effects.
- **theme.json / styles.** Validated against the official WordPress 7.0 and 7.1 schemas.

## 3. Compatibility

| Check | Result |
|---|---|
| WordPress 7.1.4-alpha / PHP 8.4.26 (lab): updater suite (235, with and without token), redirect transport, editor allow-list (25), child suites parent-only (32 + 17 HTTP) and fixture child (49 + 19 HTTP), updater with child active (235), `debug.log` empty | pass |
| WordPress 7.0.7 / PHP 8.4.26 (lab): the same suites | pass |
| PHP 8.5.11 (host): `php -l` on every file; CI matrix runs syntax, WPCS and PHPCompatibility on 8.4 and 8.5 | pass |
| WPCS (`WordPress-Extra` + `Docs`), PHPCompatibilityWP `8.4-`, fixture child WPCS | clean |
| theme.json and style variations against the WordPress 7.0 and 7.1 schemas | pass |
| Browser regression (Playwright: Chromium, WebKit, Firefox; desktop and mobile; axe on `/` and the fixture page) | 261 passed, 0 failed |
| End-to-end upgrade (`tests/e2e/run.sh`, signed mode): 1.1.0 → 1.2.0 bulk/single/CLI; tampered refused; 1.2.0 → 1.2.1 unsigned not offered, unknown key refused (bulk, single), downgrade not offered, signed bulk/single/CLI; tampered and missing-`theme.json` refused; `debug.log` empty | 14 scenarios pass |
| Child-theme compatibility | No public API changed; the fixture child passes in both configurations; the six client children use only `oogle/editor/allowed_blocks`, pattern overrides by slug and `oogle/images/avif`, all unchanged |
| Updater compatibility | 1.0.0 sites (the original `/releases/latest` updater) and 1.1.0 sites both install 1.2.0 with their installed code — each verified live against the published release (§4); 1.2.0 sites require the `.sig` from then on |
| Known limitations | A release the maintainer has not yet signed is invisible to 1.2.0+ sites until the `.sig` is attached (fail closed, one-hour cache). Loss of the signing key requires one manual upload per site (SECURITY.md). Hosts without `ext-sodium` use WordPress's bundled `sodium_compat` polyfill (slower, same result). |

## 4. Release

| Item | Value |
|---|---|
| Version | 1.2.0 (minor: additions to the release contract, no public API change) |
| Tag | `v1.2.0` → commit `c518b7947efa862ab65f2ef8863337a5c988a909` (merge of PR #1 into `main`) |
| Release | https://github.com/neumanc/oogle-theme/releases/tag/v1.2.0 — public, not draft, not prerelease, marked *Latest* (`/releases/latest` answers `v1.2.0`) |
| Published | 2026-10-25T01:22:26Z (UTC; 24 October 2026 local) by `.github/workflows/release.yml` run 40150182662 after all eight gate jobs passed |
| `oogle-theme.zip` | 121 329 bytes, SHA-256 `e2cf7a96dcfbc6b18dcda9f1e866535ac51c503757364104c0b317dea90816e3` (equals the `oogle-theme.zip.sha256` asset and GitHub's own asset digest) |
| `oogle-theme.zip.sig` | Ed25519 over the archive, key id `dc56680030516262`; verifies with OpenSSL against `tools/release-signing.pub` (`tools/verify-release.sh v1.2.0`: ok / ok) |
| Provenance | `gh attestation verify oogle-theme.zip --owner neumanc`: built by `.github/workflows/release.yml` at `refs/tags/v1.2.0` from commit `c518b79…` |
| Reproducibility | The archive reproduced locally from the tag (Apple Git 2.54) has identical content (every extracted file byte-identical, same 65 entries, same commit comment) but a different deflate stream from the runner's, so the SHA-256 differs (`152ac694…` locally). `git archive` is content-deterministic, not byte-deterministic across git/zlib builds. `tools/sign-release.sh` therefore signs the published asset when, and only when, its extracted content equals the reproduction; this run used that path. Recorded as a Low finding, closed in the follow-up commit. |

**Update installation results (live GitHub, no mock; `tests/e2e` lab, WordPress 7.1 / PHP 8.4,
fixture child active).**

| Check | Result |
|---|---|
| A real 1.1.0 parent (the published v1.1.0 asset) discovers 1.2.0 through core's update check from live GitHub; package URL is the canonical release asset | pass |
| `wp theme update oogle-theme` on that site: parent 1.1.0 → 1.2.0, "Updated 1 of 1 themes"; child files byte-identical; child still active; theme mods unchanged; maintenance mode off | pass |
| The installed 1.2.0 (65 files) is byte-identical to the published asset | pass |
| A real 1.0.0 parent (the published v1.0.0 asset; its updater reads `/releases/latest` and has no checksum or package gate) with the fixture child active: discovers 1.2.0 from live GitHub, `wp theme update` 1.0.0 → 1.2.0, child files byte-identical, child active, theme mods unchanged, maintenance mode off, installed tree byte-identical to the asset | pass |
| The installed 1.2.0 updater discovers its own release (filed under `no_update`, as core does for the current version), caches the `.sha256` and `.sig` URLs, and its download gate downloads the live asset, matches the SHA-256 and verifies the live Ed25519 signature against the pinned key | pass |
| `debug.log` after the 1.1.0 and 1.2.0 checks | empty |
| `debug.log` after the 1.0.0 check | only `PHP Deprecated` notices from WP-CLI's bundled Symfony Finder (`phar:///usr/local/bin/wp/vendor/symfony/finder/…`) on PHP 8.4, emitted by `wp theme update` itself when it reads its download cache; nothing from WordPress or the theme |

## 5. Deployment recommendation

- **Can production sites update safely?** Yes. §4 records the end-to-end verification against
  the real GitHub release. The release changes no public API; the parent
  directory is the only thing replaced; the end-to-end test proves the child's files, the
  active theme, theme mods and content survive on every path.
- **Sites needing manual attention.** None known. A site whose host blocks `github.com`
  downloads would fail closed exactly as before. A site with `OOGLE_GITHUB_TOKEN` set is
  unaffected (the token is still sent only to `api.github.com`; the two sidecars are fetched
  anonymously).
- **Recommended order.** A lab or the least critical site first (Dashboard → Updates → Check
  again → update; walk home, one page per template, the overlay menu, a form; purge the page
  cache), then the remaining sites. Automatic updates will do the same within twelve hours.
- **Rollback.** Appearance → Themes → Add New → Upload the previous `oogle-theme.zip`
  (v1.1.0 asset) with *Replace current with uploaded*, or restore the host backup; the child
  theme and content are untouched either way. Core's own temp-backup restores the previous
  parent automatically if the copy step fails mid-update.
- **Operational note for the maintainer.** The signing key is the trust anchor: back
  `~/.oogle/release-signing/oogle-theme-release-signing.pem` up in a password manager now.
  Every future release needs `tools/sign-release.sh vX.Y.Z --upload` after the workflow
  publishes; until then 1.2.0+ sites are not offered it.
