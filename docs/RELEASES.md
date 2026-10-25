# Versioning and releases

## Semantic versioning, with a theme-specific definition of "breaking"

The theme's public API is exactly what [EXTENSION-API.md](EXTENSION-API.md) lists — what
child themes and site content may depend on:

- `theme.json` preset slugs (colours, gradients, font families, font sizes, spacing,
  shadows) and `settings.custom` keys, i.e. the `--wp--preset--*` / `--wp--custom--*`
  variables they generate
- block style names (`is-style-card`, …) and section style slugs and file names
- pattern slugs (including the template patterns a child may override) and their heading
  structure
- template and template-part names and areas, and the template contract (header part →
  `<main>` → footer part; template copy from the template patterns)
- the documented `oogle-*` classes and the `oogle:reveal` event
- the `oogle-base` style handle and the `OOGLE_VERSION` constant
- filter names (`oogle/...`), their arguments, accepted return values and defaults

| Release | May contain | A child theme built for the previous release |
|---|---|---|
| **PATCH** `x.y.Z` | Bug fixes, security fixes, performance work, internal refactoring, documentation; CSS fixes that correct a defect | keeps working with no change |
| **MINOR** `x.Y.0` | Everything a patch may contain, plus **additions**: new tokens, block styles, patterns, templates, parts, classes, filters, filter arguments appended at the end; a new required WordPress version inside the support policy; visually safe tuning of default token values | keeps working with no change; it may adopt the additions |
| **MAJOR** `X.0.0` | Breaking changes to the public API | may need changes; the CHANGELOG gives a search-and-replace recipe; sites are never moved to a new major automatically |

**Breaking (major only):** removing or renaming anything listed above; changing a filter's
arguments, their order, the meaning of its return value or its default; removing a
`settings.custom` key or changing what a token controls; adding a **preset** slug (preset
arrays replace, so every child would have to declare it — use `settings.custom`, which
merges, instead); changing a part's name or area, or removing a template; making a
template stop using its template pattern; raising `Requires PHP` or `Requires at least`
outside the compatibility policy below; changing the updater's release contract (asset
name, `.sha256` sidecar, tag format) in a way already-installed parents cannot follow.

**Not a compatibility contract** (may change in any release): PHP function names, internal
constants (`OOGLE_DIR`, `OOGLE_URI`), handles other than `oogle-base`, file paths under
`assets/` and `inc/`, selectors and specificity, script state classes, pattern inner markup
and placeholder copy, default token values within reason, updater internals.

`1.0.0` was declared after the release-gate audit of 17 September 2026: clean-install,
editor, accessibility, cross-browser, PHP 8.4, WordPress 7.0/7.1 and upgrade tests
passed with the first client site as the proving ground. The second, unrelated site is
the first consumer of the 1.x contract; anything it needs that the parent cannot express
is added as a **minor** version, never by editing what exists.

## One version, one place

`style.css` `Version:` is the only declaration; PHP reads it via `wp_get_theme()`.
`CHANGELOG.md` follows Keep a Changelog: every change under `Unreleased`, moved to a dated
section at release.

## Release steps

1. Move CHANGELOG entries from *Unreleased* to `## [X.Y.Z] — YYYY-MM-DD`; bump `Version:`
   in `style.css` and `Stable tag:` in `readme.txt`. Run the pre-release checklist in
   AGENTS.md (lint, phpcs, JSON, modules, patterns, clean-install walk, axe), and — with the
   signing key on this machine — `tests/e2e/run.sh`: it drives the real core upgrader from
   the published release to this commit and from this commit to a signed "next" package on
   the bulk, single and WP-CLI paths (CI runs the same script without the key, so only the
   maintainer's run covers the signed path).
2. Commit, tag `vX.Y.Z`, push the tag.
3. `.github/workflows/release.yml` first runs the complete gate (`checks.yml`, the same
   workflow CI runs on every push and pull request) on the tagged commit: PHP 8.4 and 8.5
   syntax, WPCS, PHPCompatibility 8.4+, JSON, official theme.json schema validation for
   every supported WordPress version, JavaScript syntax, client-leak and secret scans, the
   updater and package-validation suites plus the redirect transport test on a real
   WordPress 7.1 / PHP 8.4, the child-theme compatibility suites (parent alone and the
   fixture child in `tests/fixtures/`, including a front-end render over HTTP and the
   updater suite with the child active), and an archive dry run. Only when every job has passed does the
   release job verify that the tag is an ancestor of `main` and matches `style.css`,
   `readme.txt` and the CHANGELOG, build `oogle-theme.zip` with
   `git archive --format=zip --prefix=oogle-theme/ vX.Y.Z` (dev files excluded by
   `.gitattributes export-ignore`; `.distignore` mirrors the list for other packagers),
   check the archive (`tools/check-archive.sh`), write `oogle-theme.zip.sha256`, attest the
   build provenance (SLSA, via `actions/attest-build-provenance`), and publish a GitHub
   Release marked *Latest* with both files and the CHANGELOG section as notes, using the
   runner's own `gh`. A gate failure means no Release.
4. **Sign it.** `tools/sign-release.sh vX.Y.Z --upload` reproduces the archive from the tag
   with the same `git archive` command, refuses to continue unless its extracted content is
   identical to the asset the workflow published (bytes may differ across git builds, §2.9a of
   the audit), signs it with the maintainer's Ed25519 key and attaches
   `oogle-theme.zip.sig` to the Release. Sites on 1.2.0 or later are offered the release only
   once this asset exists (older updaters ignore it). Check with `tools/verify-release.sh vX.Y.Z`.
5. Reproduce locally at any time with the same `git archive` command. The archive's *content*
   is a pure function of the tag; its *bytes* are not across git/zlib builds (Apple Git and
   the Ubuntu runner compress identically-sized trees differently, so the SHA-256 differs).
   `tools/sign-release.sh` therefore compares extracted content, not bytes, before signing the
   published asset, and `tools/verify-release.sh` is the check for a downloaded release.

## Release signing

Since 1.2.0 every release carries `oogle-theme.zip.sig`: a detached Ed25519 signature over
the archive bytes, in a small `key: value` text file (`signature:` base64, `key-id:` for log
messages). `inc/updates.php` verifies it against the public key pinned in
`oogle_update_signing_keys()` (the same key as `tools/release-signing.pub`) before anything
is replaced, on every upgrader path. The trust model, and why the `.sha256` alone was not
enough, is in [../SECURITY.md](../SECURITY.md).

- **The private key is never in a repository, a GitHub secret or CI.** It lives with the
  maintainer (default `~/.oogle/release-signing/oogle-theme-release-signing.pem`, created
  with `openssl genpkey -algorithm ed25519`), backed up in a password manager. A compromised
  GitHub account or workflow can therefore publish a release that sites refuse, but not one
  they install.
- **Rotation** adds the new public key to `oogle_update_signing_keys()` in a release signed
  with the old key; the following release is signed with the new key; the old key is removed
  a release later. Keep the `key-id` (first 16 hex characters of the SHA-256 of the raw public
  key) in the list's comment. The updater suite checks that the pinned key is the committed
  `.pub` and that a test vector signed with the real key verifies — so a release that changes
  the key without the matching vector fails CI.
- **Loss of the key** means sites cannot follow automatically: the next release must be
  installed once by manual upload (the signature gate covers only upgrader runs with theme
  context), and it carries the new key. Avoid this with the backup.
- `tools/verify-release.sh` (OpenSSL only) checks a release the way the updater does; the
  `.sig` format is documented in `oogle_update_parse_signature()`.


## Distribution to client sites

`inc/updates.php` (since 1.0.0) answers WordPress's `update_themes_github.com` filter from
the repository's Releases. Since 1.0.1 it reads the newest 30 releases in one request and
picks the newest published, final (`vX.Y.Z`, no `-rc`) release of the **installed major**;
drafts, pre-releases and other majors are skipped (`oogle/updates/allowed_major` opts into
another major). The release **must** carry an asset named `oogle-theme.zip` (or
`oogle-theme-X.Y.Z.zip`) whose single top-level folder is `oogle-theme/`, its
`oogle-theme.zip.sha256` sidecar **and**, for sites on 1.2.0 or later, its
`oogle-theme.zip.sig` signature (all three at GitHub's own asset URL for that very tag);
GitHub's automatic "Source code" zips are ignored on purpose (wrong folder name, dev files
included). The new version's `Requires at least` /
`Requires PHP` and `Version` are read from `style.css` at the tag and must all be present
and consistent, otherwise the release is not offered. Results are cached in a site
transient for six hours (one hour after a failure); Dashboard → Updates → *Check again*
clears it (from 1.3.0; before that it only cleared when something deleted the
`update_themes` transient). Procedure and the tested upgrade: [../UPGRADE.md](../UPGRADE.md).

**Sites still on 1.0.0** run the 1.0.0 updater, which reads only GitHub's *Latest* release
and has no major-version policy. So the newest 1.x release must stay marked *Latest* until
every site has left 1.0.0 (the release workflow marks each new release Latest); publishing
a 2.0.0 as Latest would offer it to those sites. 1.0.1 was never published on its own;
1.0.0 sites move straight to 1.1.0, which contains it.

Production and client installations come from **GitHub Releases → the version → the
`oogle-theme.zip` asset** (or through the updater, which uses exactly that asset). Never
install from GitHub's *Code → Download ZIP*, a workspace zip, or any other source archive:
those carry dev files, repository internals and Finder metadata, and the updater will not
accept them.

Unauthenticated GitHub API calls are limited to 60 per hour per IP; the cache keeps a site
far below that. `define( 'OOGLE_GITHUB_TOKEN', '…' )` in `wp-config.php` raises the limit
for hosts that share an IP; it is never bundled, never used for downloads, and is sent
only to `api.github.com` (the updater never follows redirects). Private repositories are
not supported by the built-in updater.

The asset's `browser_download_url` must be GitHub's own release-asset URL for the
repository in `Update URI` — `https://github.com/<owner>/<repo>/releases/download/<tag>/oogle-theme.zip`
(exact host, no port, userinfo, query or fragment; owner/repo case-insensitive). The real
GitHub API always returns that form; a mocked response must too, or the release is
rejected and no update is offered.

### Upgrade test (real upgrader, mocked GitHub)

`tests/e2e/run.sh` automates the recipe below in a dedicated wp-env lab (port 8890): it
downloads the currently published release, builds the archive of `HEAD`, a "next" patch of
it, and tampered / unsigned / wrongly signed / broken variants, installs the published
release with the fixture child active, and runs `Theme_Upgrader::bulk_upgrade()`,
`Theme_Upgrader::upgrade()` and `wp theme update` against each, asserting the parent ends up
byte-identical to the archive (or untouched after a refusal), the child's files, the active
theme, theme mods and content unchanged, maintenance mode off, `debug.log` empty and no
rejected package left in `wp-content/upgrade/`. CI runs it on every push without the signing
key: there the "published release → this commit" scenarios expect the signature refusal (a
1.2.0+ site must refuse a package signed with an unknown key), and the positive signed
scenarios need the maintainer's run before each tag.

By hand: in a lab install, add an mu-plugin that short-circuits `pre_http_request` for the
URLs the updater uses — `https://api.github.com/repos/<owner>/<repo>/releases?per_page=30`
(a JSON list of releases, each with `tag_name`, `draft`, `prerelease` and `assets` named
`oogle-theme.zip`, `oogle-theme.zip.sha256` and `oogle-theme.zip.sig` whose
`browser_download_url` has the exact form above), the raw `style.css` at the tag, the two
sidecars, and the zip download
(write the local zip to `$args['filename']` when `stream` is set) — then run
`wp theme update oogle-theme` (bulk path) and `Theme_Upgrader::upgrade()` (the path
automatic updates use), with a **child theme active**, and compare the child's file
checksums, the active theme, theme mods and post counts before and after. Include a
tampered zip (must be refused with the parent untouched) and a competing
`update_themes_github.com` callback offering another version (the theme's own offer must
win). This exercises the real updater and the real core upgrader with no network.

## Compatibility policy

- **WordPress:** the current major and the previous one (at 1.0.0: 7.1 and 7.0). A new
  WordPress major is tested within its release month; support for the oldest one is dropped
  in the next minor of the theme and `Requires at least` is raised then.
- **PHP:** 8.4 or newer only. Oogle controls its hosting, so there is no reason to carry
  older runtimes or compatibility shims: `Requires PHP: 8.4`, PHPCompatibility `testVersion
  8.4-`, and CI runs 8.4 (mandatory) plus the newest stable release. Compatibility is claimed
  only for versions actually exercised; see CHANGELOG for what each release was tested on.
- **Browsers:** current and previous major of Chrome, Edge, Firefox and Safari; newer CSS
  is used only where its absence degrades gracefully.

Each WordPress major release: re-run the pattern validation snippet, the axe check,
`tools/payload.sh` and the clean-install walk on the lab before updating client sites, and
re-check the "no cascade layers" decision (CSS.md).
