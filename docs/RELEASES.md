# Versioning and releases

## Semantic versioning, with a theme-specific definition of "breaking"

The theme's public API is what a child theme or site content can depend on:

- `theme.json` slugs (colors, gradients, font families, font sizes, spacing, custom keys)
- block style names (`is-style-card`, …) and section style slugs
- pattern slugs and their heading structure
- template and template-part names
- CSS class names documented in the docs (`oogle-header`, `oogle-lcp`, `oogle-section`)
- filter names (`oogle/...`)

Renaming or removing any of these = **major**. Adding = **minor**. Everything else = **patch**.

`1.0.0` was declared after the release-gate audit of 17 September 2026: clean-install,
editor, accessibility, cross-browser, PHP 8.1/8.3/8.4, WordPress 7.0/7.1 and upgrade tests
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
   AGENTS.md (lint, phpcs, JSON, modules, patterns, clean-install walk, axe, upgrade test
   for a major).
2. Commit, tag `vX.Y.Z`, push the tag.
3. `.github/workflows/release.yml` checks that the tag matches `style.css` and that the
   CHANGELOG has the section, builds `oogle-theme.zip` with
   `git archive --format=zip --prefix=oogle-theme/ vX.Y.Z` (dev files excluded by
   `.gitattributes export-ignore`; `.distignore` mirrors the list for other packagers),
   writes `oogle-theme.zip.sha256`, and publishes a GitHub Release with both files and the
   CHANGELOG section as notes.
4. Reproduce locally at any time with the same `git archive` command; the zip is a pure
   function of the tag.

## Distribution to client sites

`inc/updates.php` (since 1.0.0) answers WordPress's `update_themes_github.com` filter from
the repository's latest Release. The release **must** carry an asset named
`oogle-theme.zip` (or `oogle-theme-X.Y.Z.zip`) whose single top-level folder is
`oogle-theme/`; GitHub's automatic "Source code" zips are ignored on purpose (wrong folder
name, dev files included). Drafts and pre-releases are ignored. Version comparison uses
`version_compare()` on the tag without its `v`. The new version's `Requires at least` /
`Requires PHP` are read from `style.css` at the tag. Results are cached in a site transient
for six hours (one hour after a failure); Dashboard → Updates → *Check again* clears it.
Procedure and the tested upgrade: [../UPGRADE.md](../UPGRADE.md).

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

### Upgrade test recipe (mocked GitHub)

In a lab install, add an mu-plugin that short-circuits `pre_http_request` for the three
URLs the updater uses — `…/releases/latest` (JSON with `tag_name`, `assets[0].name =
oogle-theme.zip`, `assets[0].browser_download_url` in the exact form above), the raw
`style.css` at the tag, and
the zip download (write the local zip to `$args['filename']` when `stream` is set) — then
`wp theme update oogle-theme` and compare file checksums and post/option counts before and
after. This exercises the real updater and the real core upgrader with no network.

## Compatibility policy

- **WordPress:** the current major and the previous one (at 1.0.0: 7.1 and 7.0). A new
  WordPress major is tested within its release month; support for the oldest one is dropped
  in the next minor of the theme and `Requires at least` is raised then.
- **PHP:** the versions receiving security support from php.net, floor 8.1 (at 1.0.0:
  8.1–8.4 tested). The floor rises only in a theme major, and only once no client host runs
  the old version.
- **Browsers:** current and previous major of Chrome, Edge, Firefox and Safari; newer CSS
  is used only where its absence degrades gracefully.

Each WordPress major release: re-run the pattern validation snippet, the axe check,
`tools/payload.sh` and the clean-install walk on the lab before updating client sites, and
re-check the "no cascade layers" decision (CSS.md).
