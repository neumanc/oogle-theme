# Changelog

All notable changes to Oogle Theme. Format: [Keep a Changelog](https://keepachangelog.com/).
Versioning: see docs/RELEASES.md.

## [Unreleased]

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
