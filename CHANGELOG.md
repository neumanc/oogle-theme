# Changelog

All notable changes to Oogle Theme. Format: [Keep a Changelog](https://keepachangelog.com/).
Versioning: see docs/RELEASES.md.

## [Unreleased]

## [1.0.0] — 2026-09-18

Release-gate audit of the whole theme (code, architecture, security, performance,
accessibility, compatibility, packaging) before it becomes the parent of a second client
site. No breaking change: every slug, block style, pattern, part, template, class and
filter from 0.5.0 is kept.

### Added
- `tests/updater-security.php` (115 mocked scenarios, run with and without a token) and
  `tests/redirect-transport.php` (real WP HTTP transport, two local servers) — dev-only WP-CLI
  harnesses, excluded from the release archive and from phpcs.
- `inc/updates.php`: updates from GitHub Releases through WordPress's own `Update URI` /
  `update_themes_github.com` mechanism — latest non-draft, non-prerelease Release, asset
  named `oogle-theme.zip`, requirements read from the tagged `style.css`, 6 h cache (1 h
  after a failure), extracted folder renamed to the theme directory if needed. Filters
  `oogle/updates/enabled`, `oogle/updates/request_args`, `oogle/updates/api_url`,
  `oogle/updates/style_url`. Public repositories only; no secret bundled. Hardened
  before release: redirects are never followed (a 3xx fails closed); the optional
  `OOGLE_GITHUB_TOKEN` is sent to `api.github.com` only, attached after the request-args
  filter so no filter or redirect can route it elsewhere; the package URL must be
  GitHub's release-asset URL for the `Update URI` repository (exact host `github.com`,
  https, no userinfo/port/query, matching owner/repo and asset name) and the release
  page link must be on `github.com`, otherwise no update is offered.
- `parts/comments.html` (core Comments block: list, pagination, form) in `single.html`.
  Renders nothing when comments are closed and none exist.
- Patterns `oogle/hero-split` (text + `oogle-lcp` image, the hero for photos under ~1600px)
  and `oogle/faq` (core Accordion). Hidden patterns `oogle/hidden-404`,
  `oogle/hidden-blog-heading`, `oogle/hidden-no-results` so template copy is translatable.
- `settings.custom.color.error` token (`--wp--custom--color--error`), used by the Gravity
  Forms mapping instead of hard-coded hex. A custom token rather than a palette entry so
  existing children inherit it.
- `load_theme_textdomain( 'oogle' )`.
- `LICENSE` (GPL-2.0-or-later), `AGENTS.md`, `CONTRIBUTING.md`, `SECURITY.md`, `UPGRADE.md`,
  `.distignore`, GitHub Actions `ci.yml` (lint, phpcs, compatibility, JSON, modules,
  client-leak grep, archive dry run) and `release.yml` (tag → reproducible zip + SHA-256 →
  GitHub Release).

### Changed
- Earlier release notes no longer name the first client; the shipped CHANGELOG, like the
  rest of the parent, carries no client business information, and the CI leak check now
  covers every file in the repository.
- GitHub Actions: every action pinned to a commit SHA, CI token read-only
  (`permissions: contents: read`), release workflow lints on PHP 8.4 before archiving.
- **Requires PHP: 8.4.** Oogle Theme supports PHP 8.4 or newer only (Oogle controls its
  hosting). `phpcs.xml.dist` and CI use PHPCompatibility `testVersion 8.4-`; CI runs PHP 8.4
  and 8.5. 1.0.0 was tested on PHP 8.4.25 and 8.5.10. No shims for older PHP.
- Canonical repository is `https://github.com/neumanc/oogle-theme`; `Update URI`, README,
  UPGRADE.md and the updater's trust anchor point there. (Product name stays Oogle Theme.)
- `base.css` is now also loaded in the editor canvas (`add_editor_style`), followed by
  `editor.css`, which undoes the three document-level layout rules. Button targets,
  section adjacency, text wrapping and the header hairline now match between editor and
  front end (the editor previously rendered buttons with no minimum height).
- Reveal and rotator CSS moved out of `base.css` into `assets/css/reveal.css` and
  `assets/css/rotator.css`, enqueued with their modules (script-module map entries gained a
  `style` key). `base.css` shrank from 9.6 KB to 5.5 KB; pages without motion load less.
- Blog index (`index.html`) has an H1 ("Blog", hidden pattern) — it had none, because the
  Query Title block renders nothing on the posts page. `404.html` and `search.html` copy
  moved to hidden patterns / core defaults for translation.
- Lightbox setting moved to `settings.blocks.core/image.lightbox` (`enabled: false`,
  `allowEditing: true`): the root-level `enabled: true` never applied on WordPress 7.x
  because core's block-level `allowEditing` shadows it. Behaviour is unchanged (opt-in per
  image); the setting now says what it does.
- `cards-grid` and `testimonials` grids use a 20rem minimum column so three cards fill the
  wide width instead of leaving an empty fourth track.
- Footer copyright line uses the Site Title block instead of a literal "Site name".
- `numbered` Group style uses `decimal-leading-zero` so item 10 reads "10", not "010".
- `OOGLE_VERSION` reads the version via `get_template()` instead of a hard-coded directory name.
- Gravity Forms stylesheet: removed a duplicated custom property and the legacy fallback
  block that the custom-look rules fully overrode (7.2 KB → 6.6 KB, same rendered result).
- phpcs: formatting normalised with `phpcbf`; the hook-name underscore sniff is excluded with
  a documented reason (slash-namespaced `oogle/*` filters are public API).
- Documentation rewritten for 1.0.0 (README, RELEASES, CSS, JAVASCRIPT, ARCHITECTURE,
  PATTERNS, TOKENS, ACCESSIBILITY, INSTALLATION, CHILD-THEMES, WORDPRESS-NOTES); no client
  site is named anywhere in the parent outside this changelog's history.

### Fixed
- `oogle_maybe_enqueue_script_modules()` no longer remembers that a module was enqueued:
  core (6.9+) dequeues assets enqueued inside a block that renders empty, so a one-shot
  flag could leave every later `oogle-reveal`/`oogle-rotator` block without its module.
  Enqueueing is idempotent, so it simply runs for each triggering block.
- `render_block` callbacks (`inc/assets.php`, `inc/images.php`, `inc/a11y.php`) no longer
  type `$content` as `string`: a plugin returning `null` earlier in the filter chain used
  to raise a `TypeError` and take the whole front end down; non-strings now pass through
  untouched (verified against a null-returning mu-plugin: 500 before, 200 after).
- Paragraphs inside constrained layouts were each rendered as a centred box of their own
  width: the 68ch `max-inline-size` combined with core's `margin-inline: auto !important`
  put body copy, lead and eyebrow paragraphs on three different left edges, and body copy
  at 68ch was wider than `contentSize`. The rule is removed; the measure is the layout's
  content width.
- Breadcrumbs used `white-space: nowrap`, so a long current-page title widened the page on
  phones (horizontal scroll at 320–412px). Crumbs now wrap.
- `reveal.js`: an element taller than roughly eight viewports could never reach the 12%
  intersection ratio and stayed invisible. Elements at least half a viewport tall now reveal
  as soon as they enter.
- `rotator.js`: a `visibilitychange` during an image decode could start a second crossfade
  concurrently; guarded.

### Removed
- Nothing public. Root-level `settings.lightbox` (ineffective) and the paragraph measure
  rule (defective) as described above.

## [0.5.0] — 2026-09-17

Phase 3 of the first client site: nine core pages built on the framework. Four block
styles were promoted from site CSS because a second page needed them.

### Added
- Group style `section-head`: title on the left, intro and link on the right (flex Group with
  two inner Groups; without it the inner constrained Groups shrink-wrap and wrap).
- Group style `numbered`: each direct child gets "01, 02 …" in the heading font with
  hairlines in the current text colour; the indent is restated at 0,3,0 for nested Groups
  because core zeroes side padding on nested constrained Groups.
- Categories (Terms List) style `inline`: a filter row of pill links for any taxonomy, with
  the current term highlighted; `.oogle-filter-row a[aria-current]` for a hand-written "All"
  link beside it. New per-block file `core-categories.css`.
- Post Template style `editorial`: the first item spans two columns from 1024 px; plus
  `.oogle-tile` for bare image/terms/title/excerpt items with a hover zoom.
- `.oogle-sticky`: a sticky aside offset below the header (photo beside a long list).
- `oogle-lcp` now also applies to `core/post-featured-image` (single templates).

### Changed
- Nothing in existing styles. `base.css` + block files: ~29 KB.

## [0.4.0] — 2026-09-17

Second pass of the first client homepage (Phase 2.5): a rotating hero, a dark header, and
the fixes that a real design surfaced in 0.3.0's motion and form layers.

### Added
- `assets/js/rotator.js` (~2.9 KB script module): a crossfading photo stack for hero
  backgrounds. Children of `.oogle-rotator` are the slides; the first is the LCP image, the
  others carry `oogle-defer` and stay `display:none` (so their lazy images are never
  requested) until the module queues them one at a time after `load`. Incoming slides fade
  in over the current one, which stays opaque underneath, so the photo never dips dark
  mid-fade. Paused while the tab is hidden or the stack is off screen; inert under
  `prefers-reduced-motion`, without JS, or with one slide. CSS in `base.css`.
- `inc/assets.php`: script modules are now a filterable map (`oogle/assets/script_modules`)
  of handle → file + trigger class; each is enqueued the first time a block with its class
  renders.
- `inc/images.php`: `oogle-defer` on an Image block forces `loading="lazy"`,
  `fetchpriority="low"`, `decoding="async"` regardless of core's first-N heuristic.
- `oogle-reveal--scale` (photos settle from 1.04 to 1); `--stagger` now steps to eight children.
- `oogle-mosaic--captions` on a mosaic Group: captions overlay the bottom of each photo and
  slide up on hover/focus-within; shown at rest where there is no hover.
- `base.css`: header/main and main/footer butt together (`.wp-site-blocks > header + main`),
  so a dark header over a dark hero shows no strip of page background. Templates already
  pad their own tops.

### Changed
- `.oogle-header` hairline is `color-mix(currentColor 14%)` instead of the `line` token, so
  it works on a dark header.
- Gravity Forms token mapping is now `!important`. GF 3.x prints a per-form `<style>` keyed
  to the wrapper ID that resets the same variables to GF's defaults (its blue, grey borders,
  3 px radius); an ID selector beats any class, so the mapping did not apply. A keyboard
  focus ring is restated above GF's `outline: 0` (2.4.7/2.4.11).

### Fixed
- `oogle-reveal--clip` never fired for elements below the fold: a `clip-path` on the
  observed element gives IntersectionObserver an empty intersection rect. The clip now
  sits on the inner `img`.

## [0.3.0] — 2026-09-16

Purposeful motion and a custom-look form layer, driven by the first client homepage redesign
(Phase 2.5). Still one theme script; still zero jQuery of our own.

### Added
- `assets/js/reveal.js` (~1 KB script module): adds `is-visible` to elements carrying an `oogle-reveal*` class when they enter the viewport (IntersectionObserver). Registered on `init`, enqueued by a `render_block` check the first time such a class renders, so pages without reveals load no JS. Does nothing under `prefers-reduced-motion`; content is fully visible without JS because the hiding rules only apply once `<html>` has `oogle-js-reveal`.
- Reveal CSS in `base.css`: `oogle-reveal` (fade/rise), `--clip` (wipe), `--left`, `--right`, `--stagger` (children, 80 ms steps). All transitions, all cancelled under reduced motion.
- Image motion, CSS only: `oogle-zoom-hover` (restrained scale on hover/focus-within) and `oogle-drift` (scroll-driven `animation-timeline: view()` depth on full-bleed images; static where unsupported).
- Gravity Forms "custom look" layer in `integrations/gravity-forms.css`: theme tokens for labels, inputs (`line` border, `primary-deep` focus ring), full-width accent submit, validation summary and field errors styled with the theme's own colours. Still low specificity; GF's own theme framework remains the base.
- `text-wrap: balance` on headings and `text-wrap: pretty` on paragraphs, list items and captions (progressive).
- Adjacent full-width sections inside post content (`.alignfull + .alignfull`) butt together: each section carries its own vertical padding, so no strip of page background appears between two tinted bands.

### Changed
- docs/JAVASCRIPT.md rewritten: "the default is none" became "the default is one small module, loaded only when used", with the reveal contract documented.

## [0.2.0] — 2026-09-16

First real page built on the framework (the first client homepage) and first
deployment to a LiteSpeed/cPanel host (WordPress 7.1, PHP 8.4).

### Added
- `parts/navigation-overlay.html` — a designed mobile menu using WP 7.1's `navigation-overlay` template-part area; the header Navigation block references it via `"overlay":"navigation-overlay"`. Overlay CSS in `blocks/core-navigation.css` (stacked items, hairlines, submenus expanded below their parent).
- Group block style `mosaic`: a grid of Image blocks that fill their cells so column/row spans compose (captions hidden, used by the lightbox).
- Header class `oogle-header--collapse-lg`: collapses the Navigation block below 1200px instead of 1024px for long menus.
- LCP guarantee now also applies to Image blocks carrying the `oogle-lcp` class (`render_block_core/image`).

### Fixed
- Testimonial citations inherit color so they stay visible on dark section styles.
- Overlay submenu items no longer render beside their parent.

### Changed
- Hero pattern documentation: full-bleed Cover hero is not the only option; a split hero (Columns + Image) is preferred when photography is under ~1600px wide.

## [0.1.0] — 2026-09-16

Framework skeleton, validated on WordPress 7.1 / PHP 8.3 with a proof-of-concept page and
the first client child theme.

### Added
- `theme.json` v3 token contract: 12 semantic colors, 2 scrim gradients, 3 font-family slugs, 9 fluid font sizes, 8 spacing presets, 3 shadows, `custom.*` groups; presets-only editor settings.
- Templates: index, page, page-no-title, page-narrow, blank, single, archive, search, 404. Parts: header, footer, breadcrumbs (core Breadcrumbs block), post-meta.
- Patterns: hero-cover, section-split, cards-grid, cta-panel, testimonials, query-cards (template use).
- Block styles: Group `card`, `card-flush`; Paragraph `eyebrow`, `lead` (theme.json variations); List `checklist`, `inline`; Quote `testimonial`; Button `text`; Columns `reverse-on-stack`. Section styles `section-tint`, `section-reversed`, `section-primary`.
- Per-block CSS via `wp_enqueue_block_style()`; `base.css` (focus, motion, targets); Gravity Forms token mapping loaded on `gform_enqueue_scripts`.
- Editor policy: curated allowed blocks (filterable), core/remote patterns off, pattern categories.
- Images: JPEG → WebP sub-sizes (AVIF opt-in); hero Cover forced `fetchpriority="high"`/eager.
- Cleanup: emoji loader removed on the front end.
- Accessibility: `role="region"` on core Accordion panels.
- `tools/payload.sh` payload measurement; documentation set in `docs/`.

### Decided against (see docs)
- Cascade layers, container queries, a theme updater, any custom block, any theme JavaScript.
