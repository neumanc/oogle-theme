# WordPress 7.1 — verified behaviour that shaped decisions

Verified on WordPress 7.1 / PHP 8.3 (Playground CLI and Docker) on 16 September 2026.

## Native blocks that removed planned custom work

| Block | Status | Consequence |
|---|---|---|
| `core/accordion` (+ item/heading/panel) | present; `h3 > button[aria-expanded][aria-controls]` + panel with `hidden="until-found"`; Interactivity API module 1.3 KB. The block's **save** markup includes `role="region"`/`role="group"` but its **dynamic render** drops them (axe flags the panel) — theme re-adds `role="region"` | FAQ uses it; `core/details` stays available for single disclosures |
| `core/breadcrumbs` | present; `<nav aria-label="Breadcrumbs"><ol>`, separator via `--separator` and `li::after`, options for home/current items and taxonomy preference | Theme's `parts/breadcrumbs.html` uses it — no SEO-plugin coupling; Rank Math still emits BreadcrumbList schema |
| `core/tabs` (+ tab-list/tab-panels/tab-panel) | present | Not validated in 0.1.0 (my hand-written markup rendered no tab list; build it in the editor first) |
| `core/icon` | present; requires an icon slug from core's library | Not validated (`"check"` rendered nothing); not needed yet |
| `core/terms-query`, `term-template`, `term-name`, `term-count` | present | Taxonomy listings without PHP |
| `core/navigation-overlay-close`, template-part area `navigation-overlay` | present | The mobile overlay is now a customizable template part; a child can add CTA/phone to it |
| `core/image` `isDecorative` attribute, `rounded` style | present | — |
| `core/group` `position.sticky`, `dimensions.minHeight`, `color.heading/button/link` | present | Sticky header without CSS |
| Block bindings sources | `core/post-meta`, `core/post-data`, `core/term-data`, `core/pattern-overrides` | Project meta can be shown without custom fields UI |
| Block support `contentRole` | present on accordion/group/navigation | Patterns referenced with `core/pattern` edit content-only |

Not present: `core/grid` as a block (Grid is a Group layout), `core/form`, `core/badge`,
`core/slider`, `core/skip-to-content` (skip link is injected, not a block).

## Behaviours that changed the implementation

1. **Slugs are kebab-cased with digit/letter splits.** `3x-large` → `--wp--preset--font-size--3-x-large`. Use `xxx-large`.
2. **Preset color classes are `!important`.** Overlay colors on Cover cannot be overridden in CSS; use a gradient preset.
3. **`post-content` needs `align: full`** for `alignfull` children inside a constrained `main`.
4. **Core Grid layout sets `container-type` on the grid parent**, so container queries on grid children resolve against the grid.
5. **Section styles cannot target nested block-style variations** (`styles.blocks.core/paragraph.variations.lead` inside a section style emits nothing). Use CSS with two-class specificity for that case.
6. **Block style variations from theme.json get per-instance classes** (`is-style-lead--4`) and `:root :where(p.is-style-lead--4)` rules — 0,1,0 specificity; any 0,2,0 CSS beats them.
7. **`core/pattern` references in post content stay linked** to the theme file and edit content-only; inserter insertions are copies.
8. **Navigation collapses at a fixed 600px.** Not configurable; the theme re-applies the two collapsed rules for 600–1023px in the header only.
9. **Core's skip-link CSS prints in the footer**, after theme stylesheets; theme overrides of it lose (and are unnecessary).
10. **Accordion panel lacks a role** → axe `aria-prohibited-attr`. Theme adds `role="region"`; report upstream.
11. `image_editor_output_format` is empty by default; the theme converts JPEG → WebP. PNG is deliberately left alone (palette PNG → WebP produced a broken file in the Playground/GD lab).
12. Global styles output for the neutral theme.json is ~22–24 KB uncompressed (~5 KB gz), mostly preset classes. Acceptable; do not add presets nobody uses.

13. **Navigation fallback auto-creates a menu.** If a Navigation block without `ref` renders before any `wp_navigation` post exists, core creates a "Navigation" post containing a Page List block — and that post then wins over menus created later (it is also an axe `list` violation: `<ul>` nested directly in `<ul>`). On a fresh site, create the real menu **before** the first front-end render, or delete the auto-created one. Observed on dev after deployment.
14. **Custom overlay template part** (`"overlay":"<slug>"` on the Navigation block) renders the theme's part inside the overlay with `disable-default-overlay`; nested Navigation blocks are forced to `overlayMenu: never` and rendered in a `div`. The theme owns all overlay styling in that mode. Images inside get `fetchpriority="low"` automatically.
15. **Grid column/row spans** (`style.layout.columnSpan/rowSpan`) work with `minimumColumnWidth` grids and collapse responsively through core-generated container queries; a 2×2 + 1 + 1 / 3 composition fills a 3-column grid exactly.

## Gravity Forms 3.1 (for reference)

Renders with its "theme framework": CSS 558 KB + 101 KB raw (**~36 KB gz**), jQuery + migrate
(~100 KB raw), GF scripts ~270 KB raw. The theme maps GF's CSS custom properties to tokens
(`assets/css/integrations/gravity-forms.css`, 3 KB) and loads it only when a form renders.
Keep forms on conversion pages.
