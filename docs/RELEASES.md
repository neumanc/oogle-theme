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

`0.x` until the theme has run a production site and a second, unrelated site has adopted
it without needing a breaking change. That is the proof of reusability; `1.0.0` is not
declared before it.

## One version, one place

`style.css` `Version:` is the only declaration; PHP reads it via `wp_get_theme()`.
`CHANGELOG.md` follows Keep a Changelog: every change under `Unreleased`, moved to a dated
section at release.

## Release steps

1. Update `CHANGELOG.md`, bump `Version:` in `style.css`, run `php -l` on every PHP file and
   the pattern/template validation snippet (PATTERNS.md).
2. Commit, tag `vX.Y.Z`, push.
3. Build the zip with `git archive --format=zip --prefix=oogle-theme/ vX.Y.Z -o oogle-theme-X.Y.Z.zip`
   (exclude dev files via `.gitattributes export-ignore`: `docs/`, `tools/`, `.editorconfig`, `phpcs.xml.dist`).
4. Attach to a GitHub Release.

## Distribution to client sites (designed, not yet built)

`style.css` declares `Update URI: https://github.com/oogle/oogle-theme`. WordPress fires the
`update_themes_github.com` filter for that host; ~80 lines in a future `inc/updates.php`
can fetch `releases/latest`, compare versions and return the zip URL so the update appears
in Appearance → Themes like any other. Nothing a site owns lives inside the parent folder,
so an update can never overwrite customization. Not built at 0.1.0 by decision: prove the
theme first.

## Compatibility policy

Supports the current WordPress major and the previous one. Each WordPress major release:
re-run the pattern validation snippet, the axe check and `tools/payload.sh` on the lab
before updating client sites, and re-check the "no cascade layers" decision (CSS.md).
