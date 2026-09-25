# Upgrading the parent theme

## The guarantee

Updating `oogle-theme` replaces the directory `wp-content/themes/oogle-theme/` and nothing
else. Everything a site owns lives outside it:

| What | Where | Touched by a parent update |
|---|---|---|
| Brand tokens, fonts, header/footer/overlay parts, site patterns, site CSS | `wp-content/themes/<child>/` | no |
| Post types, taxonomies, integrations | `wp-content/mu-plugins/`, plugins | no |
| Pages, posts, media, menus, template/part edits made in the Site Editor, global-styles edits | database, `wp-content/uploads/` | no |
| Options and settings | database | no |

Within a major version, the parent also keeps every slug, block style name, pattern slug,
template/part name, `oogle-*` class and `oogle/*` filter. Content written against 1.x keeps
rendering on every later 1.x.

## How updates arrive

`style.css` declares `Update URI: https://github.com/neumanc/oogle-theme`. WordPress calls
the theme's `update_themes_github.com` handler (`inc/updates.php`) during its normal
twice-daily check; the handler reads the latest GitHub Release and returns its version,
release page and `oogle-theme.zip` asset. From there core does everything: the notice in
Appearance → Themes and Dashboard → Updates, `wp theme update oogle-theme`, auto-updates if
enabled, and the temp-backup/rollback core performs when a copy fails.

Requirements (`Requires at least`, `Requires PHP`) are read from the new version's own
`style.css` at the release tag, so a host that cannot run the new version is not offered it.
If that file cannot be read, or does not declare both requirements and the tag's version,
the release is not offered at all (nothing is ever inherited from the installed copy).

Only releases of the installed **major** are offered automatically: a 1.x site receives
1.x patch and minor releases and is never moved to 2.x by the routine check. To adopt a new
major once the child theme has been migrated, either upload the new major's `oogle-theme.zip`
once (Appearance → Themes → Add New → Upload, "Replace current with uploaded") — the site
then follows that major — or opt in from the site mu-plugin:
`add_filter( 'oogle/updates/allowed_major', fn() => 2 );` (return `null` to follow any major).

Two gates run before the installed parent is replaced, on every path (Appearance → Themes,
Dashboard → Updates, automatic updates, WP-CLI, Oogle Core): the downloaded zip must match
the SHA-256 published with the release, and the extracted package must be this theme (name,
no `Template` header, canonical `Update URI`) at exactly the version being installed, with
declared requirements this host meets, a valid `theme.json` and `templates/index.html`. A
package that fails either gate leaves the installed theme untouched; the message names the
reason.

## With a child theme active

This is the normal production configuration, and it is what CI and the upgrade tests run.

- **What is replaced.** Only `wp-content/themes/oogle-theme/`. The updater works on
  `get_template()` — the parent's directory — whichever theme is active. The child's
  directory is never an upgrader target of the parent's updater: the parent's update check
  ignores every other theme, and its download and package gates pass any other theme's
  upgrade through untouched. A child package offered as the parent is refused (it has a
  `Template` header).
- **The child keeps working** because, within a major, nothing in
  [docs/EXTENSION-API.md](docs/EXTENSION-API.md) changes. Only releases of the installed
  major are offered, so a child is never moved onto a new contract automatically.
- **One authority.** Only the theme decides what update is offered for the parent. Another
  plugin that answers `update_themes_github.com` (Oogle Manager does) cannot offer a
  release the theme declined — another major, a site with updates switched off, or a
  release that failed validation.
- **Maintenance mode.** Core's bulk path (Appearance → Themes, Dashboard → Updates,
  `wp theme update`) enables maintenance mode when the parent of the active child is
  updated. Core's single-theme path (automatic background updates) enables it only for the
  active stylesheet, i.e. not for a parent. There the directory swap is a rename of a
  complete, verified directory (core moves the old copy to
  `wp-content/upgrade-temp-backup/` first and restores it if the move fails), so the window
  is milliseconds; with an aggressive page cache, prefer updating through Dashboard →
  Updates or WP-CLI, then purge the cache.
- **Tested** (lab, WordPress 7.1 / PHP 8.4, fixture child active, mocked GitHub serving a
  real package): bulk and single paths both moved the parent to the new version with the
  child's files, the active theme, theme mods and content unchanged and the installed files
  byte-identical to the package; a tampered package was refused at the checksum gate (single
  path) with everything left untouched and maintenance mode off; a competing update filter offering 2.0.0 did not change the
  offer (1.1.0).

**If the parent directory is ever missing or broken** (a failed manual copy, a host
restore): do not open Appearance → Themes or the Customizer first. Those two screens run
core's `validate_current_theme()`, which switches a site whose parent is missing to the
default theme. Restore the parent first (upload the release zip over FTP/SSH into
`wp-content/themes/oogle-theme/`, or restore the backup), then reload; the child and its
settings come back as they were. If the site was already switched, re-activate the child —
theme mods are stored per theme and are still there.

## Procedure for a client site

1. Read the release notes (CHANGELOG.md) for the version you are moving to. A **major**
   version lists renamed or removed slugs/styles/patterns with a search-and-replace recipe;
   apply it to the child theme and content first.
2. Take the host's normal backup (or rely on the upgrader's temp backup for a minor).
3. Dashboard → Updates → *Check again* (forces a fresh GitHub check) → update the theme, or
   `wp theme update oogle-theme`.
4. Walk the site: home, one page per template in use, a project/post, the mobile overlay, a
   form. Purge the page cache (LiteSpeed/CDN) so the new `?ver=` assets are served.
5. If anything is wrong: Appearance → Themes → Add New → Upload the previous
   `oogle-theme.zip` with "Replace current with uploaded", or restore the backup. Child theme
   and content are untouched either way.

Sites deployed from git can disable the check: `add_filter( 'oogle/updates/enabled', '__return_false' );`
in the site mu-plugin.

## Tested upgrade (1.0.0 release gate)

Performed (historical, 17 September 2026) on a clean WordPress 7.1 / PHP 8.3 install — the supported floor is now PHP 8.4 — with a mocked GitHub API serving the
real release artifact (docs/RELEASES.md has the recipe):

1. Installed parent **0.5.0** from the tagged archive, the first client site's child theme and its
   mu-plugin; activated the child; created pages, a menu, a project, uploaded media, edited
   the header part and global styles in the Site Editor (stored in the database).
2. Recorded SHA-256 of every file under the child theme and mu-plugins, plus counts of
   posts, terms, options, `wp_template`/`wp_template_part`/`wp_global_styles` posts.
3. `wp theme update oogle-theme` reported 0.5.0 → 1.0.0 and completed.
4. Every checksum and count matched; the child remained active; the front end, editor and
   Site Editor loaded with an empty `debug.log`.

Result: **PASS**. Re-run this test for every major release.
