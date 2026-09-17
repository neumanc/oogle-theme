# Changelog

All notable changes to Oogle Theme. Format: [Keep a Changelog](https://keepachangelog.com/).
Versioning: see docs/RELEASES.md.

## [Unreleased]

## [0.4.0] — 2026-09-17

Second pass of the Precision homepage (Phase 2.5): a rotating hero, a dark header, and
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

Purposeful motion and a custom-look form layer, driven by the Precision homepage redesign
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

First real page built on the framework (Precision Kitchen & Bath homepage) and first
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
the Precision Kitchen & Bath child theme.

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
