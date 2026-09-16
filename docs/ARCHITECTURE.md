# Theme architecture

## Layers

```
WordPress core blocks + theme.json engine
   └── oogle-theme (parent)   tokens (slugs+neutral values) · templates · parts · patterns · block styles · per-block CSS
        └── client child      token values · fonts · header/footer content · site patterns · site templates
             └── site mu-plugin   post types · taxonomies · integrations (never presentation)
```

## PHP modules (`inc/`)

| File | Responsibility | Filters exposed |
|---|---|---|
| `setup.php` | editor style, remove core patterns, pattern categories, disable remote patterns | — |
| `assets.php` | `base.css`; per-block stylesheets via `wp_enqueue_block_style()`; Gravity Forms token map on `gform_enqueue_scripts` | `oogle/assets/block_styles` (block ⇒ file map) |
| `block-styles.php` | `register_block_style()` for the component vocabulary | `oogle/block_styles` |
| `editor.php` | curated `allowed_block_types_all` (post editing only; Site Editor untouched) | `oogle/editor/allowed_blocks` |
| `images.php` | JPEG → WebP sub-sizes (AVIF opt-in); hero Cover gets `fetchpriority="high"`, no lazy | `oogle/images/avif` |
| `cleanup.php` | removes the emoji loader on the front end | `oogle/cleanup/emoji` |
| `a11y.php` | render-time fixes for verified core defects (accordion panel `role="region"`) | — |

`functions.php` only requires these files. Total PHP ≈ 400 lines.

## Templates

Templates are structural only: header part → `<main>` (constrained) → title/content or
query → footer part. Design lives in patterns inside content. `post-content` is
`align: full` so full-bleed sections work inside a constrained `main`.

| Template | Use |
|---|---|
| `page` | Default: breadcrumbs, title as H1, content |
| `page-no-title` | Hero-led pages; the hero pattern carries the H1; no bottom padding (patterns own rhythm) |
| `page-narrow` | Prose pages at 40rem |
| `blank` | Landing pages, no header/footer |
| `single` | Posts: breadcrumbs, title, meta part, featured image, content, prev/next |
| `index`, `archive`, `search` | Card grids via the `oogle/query-cards` pattern |
| `404` | Search + home link |

## Template parts

`header` and `footer` are **expected to be overridden by the child** (they are content).
`breadcrumbs` uses core's Breadcrumbs block (WP 7.1) — no plugin coupling. `post-meta` is
date + category.

## Patterns

See [PATTERNS.md](PATTERNS.md). Six in the parent, all core-block compositions, all using
preset slugs and block styles only.

## Section styles

`styles/section-*.json` are theme.json partials with `blockTypes: ["core/group"]`. They
recolor the Group and its nested headings/links/captions. Use them for "tinted", "reversed"
and "primary" sections; do not hand-set background colors on section groups.

## Assets

See [CSS.md](CSS.md) and [JAVASCRIPT.md](JAVASCRIPT.md).
