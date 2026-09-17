# CSS conventions

## Where styles live, in order of preference

1. **`theme.json`** — global styles, element styles, block styles, block style *variations*
   (`styles.blocks.core/paragraph.variations.eyebrow`), section styles in `styles/*.json`.
   Applies in the editor for free and prints at near-zero specificity.
2. **Per-block files** `assets/css/blocks/core-<block>.css`, attached with
   `wp_enqueue_block_style()`. Core loads them only when the block renders and inlines them
   when small. Map lives in `inc/assets.php` (filter `oogle/assets/block_styles`).
3. **`assets/css/base.css`** — the only global sheet: focus ring, motion (reduced-motion
   guard, reveal, image motion), element defaults, header z-index, full-width section
   adjacency. Keep under 8 KB.
4. **`assets/css/integrations/`** — third-party mappings loaded only when the integration
   renders (Gravity Forms → its CSS custom properties).

## Specificity rules

- Single class, or class + element. No IDs. `:where()` to zero out structural parts.
- **Never out-specify core.** Core's own rules are 0,1,0–0,3,0; if you match or exceed them
  you can silently disable core behaviour (we did: a 0,2,0 rule on the navigation "open"
  button re-showed it on desktop). Use `:where(.wp-block-navigation) .thing` to stay at 0,1,0
  when you only want to *add* declarations.
- Preset color classes (`has-*-background-color`) carry `!important` in core. Do not fight
  them; change the preset the block uses (that is why the hero uses a gradient *preset*).
- Two `!important` uses in the theme: reduced motion in `base.css`, and the Gravity Forms
  variable mapping in `integrations/gravity-forms.css` (GF prints a per-form `<style>` keyed
  to the wrapper ID that resets the same variables; nothing but `!important` beats an ID).
- No cascade layers. Core's CSS is unlayered and unlayered beats layered regardless of
  specificity, so a layered theme rule could not override a core block without `!important`.

## Responsive rules

- Fluid values (`clamp()`) for type and section spacing; the Grid layout's minimum column
  width for card grids; Columns' built-in stacking below 782px; `flex-wrap`.
- Exactly **one** theme-owned media query pair, in `core-navigation.css`: core collapses
  the Navigation block into the overlay below 600px and that is not configurable, so the
  header re-applies core's two "collapsed" rules between 600 and 1023px. Do not add more
  breakpoints; shorten the menu instead.
- Container queries: none at 0.1.0. Core's Grid layout sets `container-type` on the grid
  *parent*, so a query on a card resolves against the grid width, not the card's. A
  side-by-side "wide card" needs an inner wrapper to query; add it when a design needs it.

## Logical properties

`margin-inline`, `padding-block`, `inset-inline-start`, `inline-size`, `block-size`. Always.

## Banned

Page-specific CSS in the theme; nesting deeper than two levels; hex values; viewport-unit
font sizes without `clamp()`; selectors that depend on core's internal wrapper structure
beyond documented `wp-block-*` class names; utility classes.

## Budget

Authored front-end CSS in the parent (base + per-block files): < 32 KB unminified
(0.1.0: ~17 KB; 0.3.0: ~21.8 KB; 0.4.0: ~25.7 KB; 0.5.0: ~29 KB with four block styles for editorial pages). Only base.css is a separate request; per-block files are inlined by core when
the block renders. Integrations are budgeted separately (Gravity Forms: ~6.5 KB, loaded only
with a form). Measure with `tools/payload.sh`.

## Header collapse ranges (0.2.0)

`.oogle-header` collapses the Navigation block below 1024px; add `oogle-header--collapse-lg`
to the header Group to collapse below 1200px (seven links + phone + CTA need it). These are
the only two ranges; if a menu still does not fit, shorten it.

## Motion (0.3.0)

Reveal classes (`oogle-reveal`, `--clip`, `--left`, `--right`, `--stagger`) are opt-in on
any block and only take effect once `reveal.js` has added `oogle-js-reveal` to `<html>`
(see docs/JAVASCRIPT.md). `oogle-zoom-hover` and `oogle-drift` are pure CSS. Every rule
lives in `base.css`; a site never writes its own keyframes for these. Under
`prefers-reduced-motion: reduce` all of it is cancelled by the one `!important` block.

Rules of thumb: reveal sections and image grids, not every paragraph; one drift image per
screen at most; hover zoom only on linked or lightboxed images; never move text on scroll.

## Full-width sections (0.3.0)

Two consecutive `alignfull` blocks inside post content have no gap between them (`base.css`
sets `margin-block-start: 0` on `.alignfull + .alignfull`). Give every full-width section
its own top and bottom padding; that is what separates content, not the root block gap.

## Text wrapping (0.3.0)

`text-wrap: balance` on `h1`–`h4`, `text-wrap: pretty` on `p`, `li`, `figcaption`.
Progressive; do not add manual `<br>` to fix a widow.

## Editorial page styles (0.5.0)

Four block styles that let pages share a design language without sharing a layout:

| Style | Block | Use |
|---|---|---|
| `section-head` | Group (flex, wrap, space-between, align bottom) with two inner Groups | Title left, intro + link right; the inner Groups get flex bases so they sit side by side |
| `numbered` | Group (or List) | Each direct child gets "01, 02 …"; hairlines in `currentColor` so it reads on any surface; nested Groups keep their indent at 0,3,0 |
| `inline` | Categories (Terms List, any taxonomy) | A pill filter row above a Query Loop; current term highlighted. `.oogle-filter-row a[aria-current]` styles a hand-written "All" link the same way |
| `editorial` | Post Template (grid) | First item spans two columns from 1024 px; `.oogle-tile` on the inner Group for bare image/terms/title/excerpt items |

Plus `.oogle-sticky` (sticky aside offset below the header) in `base.css`.
