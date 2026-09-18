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

`style.css` declares `Update URI: https://github.com/oogle/oogle-theme`. WordPress calls
the theme's `update_themes_github.com` handler (`inc/updates.php`) during its normal
twice-daily check; the handler reads the latest GitHub Release and returns its version,
release page and `oogle-theme.zip` asset. From there core does everything: the notice in
Appearance → Themes and Dashboard → Updates, `wp theme update oogle-theme`, auto-updates if
enabled, and the temp-backup/rollback core performs when a copy fails.

Requirements (`Requires at least`, `Requires PHP`) are read from the new version's own
`style.css` at the release tag, so a host that cannot run the new version is not offered it.

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

Performed on a clean WordPress 7.1 / PHP 8.3 install with a mocked GitHub API serving the
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
