# AGENTS.md — working on Oogle Theme

Instructions for anyone (human or AI coding agent) changing this repository. They exist so
the architecture stays the same from project to project. Read this before editing; when in
doubt, prefer the boring option.

## What this repository is

The **reusable parent block theme** for Oogle client sites. It is used by several unrelated
sites at once. A change here ships to all of them through the GitHub updater. It must never
know that any particular client exists.

## Rules

1. **Native first.** HTML, CSS and the browser before JavaScript; WordPress APIs before custom
   abstractions; core blocks before custom blocks. If core provides it, use core.
2. **No frameworks.** No Bootstrap, Tailwind, jQuery, React on the front end, Font Awesome,
   animation libraries, page builders, utility-class systems, or "helpers" with one caller.
3. **Production needs nothing but WordPress.** No Node, npm, Composer or build step at
   runtime. Dev tooling may use them (see phpcs.xml.dist, .github/workflows). Never add a
   runtime dependency without a written justification in the pull request.
4. **theme.json is the design system.** Every reusable value is a preset or `settings.custom`
   token. CSS consumes `--wp--preset--*` / `--wp--custom--*`; it never invents a second token
   scheme or hard-codes hex/px values (documented exceptions: white text over a black scrim,
   `50%` for circles).
5. **Slugs are the API.** Palette, gradient, font, size, spacing and custom slugs, block style
   names (`is-style-*`), pattern slugs (`oogle/*`), template and part names, `oogle-*` class
   names and `oogle/*` filter names are public. Renaming or removing one is a **major**
   version. Adding is minor. Arrays of presets in a child *replace* the parent's, so adding a
   palette slug forces every child to add it — prefer `settings.custom` (which merges) for
   values a child may not care about.
6. **Minimal JavaScript.** Ask first whether `<details>`, `<dialog>`, CSS, `position: sticky`,
   `:has()`, scroll-driven animations or a core Interactivity API block can do it. A new
   module is a vanilla ES module in `assets/js/`, registered in `inc/assets.php`, enqueued only
   when a rendered block carries its trigger class, with its own stylesheet, inert under
   `prefers-reduced-motion`, and usable without it. Over ~150 lines: stop and reconsider.
7. **Client-specific code does not belong here.** Business data, copy, phone numbers, schema,
   colours, fonts, post types, taxonomies, CRM/analytics integrations, redirects: those live in
   the child theme (presentation) or the site mu-plugin (functionality). Promote something
   into the parent only when a **second, unrelated** site needs it unchanged.
8. **Accessibility is architecture.** WCAG 2.2 AA. Semantic landmarks, one H1 per page,
   visible focus, 44 px targets, native controls, ARIA only where native semantics are
   missing, everything cancelled under reduced motion. Run axe and a keyboard walk before
   claiming a fix.
9. **Core Web Vitals matter.** Per-block CSS, no render-blocking JS, `oogle-lcp` on the hero
   image, `oogle-defer` on images that must not compete with it, no layout-shifting motion.
   Measure with `tools/payload.sh`; targets in docs/CSS.md and docs/JAVASCRIPT.md.
10. **Progressive enhancement.** Every page must be complete and usable with JavaScript off.
    Hiding rules that depend on a script are scoped to a class the script adds.
11. **Secure code, not security theatre.** Escape on output, sanitize on input, capability
    checks and nonces where the theme handles requests (it currently handles none), timeouts
    and validation on remote calls. Do not add version hiding, login obfuscation, REST
    blocking or security headers to the theme — those belong to the server, CDN or a plugin.
12. **Do not refactor working architecture for taste.** Change code to fix a verified defect,
    to meet a documented goal, or because WordPress now provides a materially better native
    API. "Newer" is not a reason. "I would have written it differently" is not a reason.
13. **Backwards compatibility is deliberate.** Keep filters and their signatures; keep class
    names; when a breaking change is unavoidable, document a search-and-replace recipe in the
    CHANGELOG and bump the major version.
14. **Test before release.** `php -l`, phpcs (WordPress standard), JSON validity, `node --check`,
    the pattern validation snippet, a clean-install walk (front end, post editor, Site Editor),
    an axe run, and the upgrade test in UPGRADE.md. Do not write "tested" without doing it.
15. **Document every change** in CHANGELOG.md under *Unreleased*, in Keep-a-Changelog form.

## File organisation

| Path | Rule |
|---|---|
| `style.css` | Header only. `Version:` is the single version declaration. |
| `theme.json` | Settings and styles. Section styles for Groups live in `styles/*.json` with `blockTypes`. |
| `functions.php` | Requires `inc/*.php`; no logic. |
| `inc/<concern>.php` | One concern per file, every function `oogle_`-prefixed, every behaviour filterable with an `oogle/<file>/<thing>` filter. `declare(strict_types=1)`, typed signatures, `defined('ABSPATH') \|\| exit`. |
| `templates/*.html` | Structural only: header part → `<main>` → content/query → footer part. No copy; copy that must be translatable goes in a hidden pattern. |
| `parts/*.html` | `header`, `footer`, `navigation-overlay` are content and are overridden by the child. |
| `patterns/*.php` | Core blocks only, presets only, canonical block markup (build in the editor, copy the code), placeholder copy that is obviously placeholder, `esc_html_e()`/`esc_url()` on every output. Hidden patterns are `hidden-*.php` with `Inserter: false`. |
| `assets/css/base.css` | The one global stylesheet, also loaded in the editor. Keep it small. |
| `assets/css/editor.css` | Editor-only corrections, loaded after base.css. |
| `assets/css/blocks/core-<block>.css` | Styles for one block, mapped in `inc/assets.php`. Single class or class+element selectors; `:where()` when you only add declarations; never out-specify core. |
| `assets/css/<module>.css` | Stylesheet of a script module, enqueued with it. |
| `assets/css/integrations/` | Mappings for third-party plugins, loaded only when that plugin renders. |
| `assets/js/<module>.js` | One ES module per behaviour, no shared globals. |
| `docs/` | Architecture and conventions; excluded from the release zip. |
| `tools/` | Dev scripts; excluded from the release zip. |

## CSS conventions in short

Logical properties always (`margin-inline`, `inline-size`). Fluid values via `clamp()`
tokens, never viewport-unit font sizes alone. Grid for two-dimensional layout, flex for one,
`gap` for spacing. One theme-owned media-query pair (header collapse) — add none. No
cascade layers (core is unlayered), no `!important` except the two documented uses
(reduced motion; Gravity Forms variable mapping against an ID selector).

## Before you open a pull request

- [ ] `php -l` on every PHP file; `phpcs` clean (formatting auto-fixed with `phpcbf`)
- [ ] `jq . theme.json styles/*.json` and `node --check` on modules
- [ ] Patterns validate (`docs/PATTERNS.md` snippet) and insert without "invalid content"
- [ ] Clean-install walk: home, page, post with comments, archive, search, 404, editor, Site Editor; `debug.log` empty
- [ ] axe: 0 violations on the pages you touched; keyboard walk of any interactive change
- [ ] No client name, URL, phone, colour or copy in the parent (the CI grep for client names must pass)
- [ ] CHANGELOG entry under *Unreleased*
