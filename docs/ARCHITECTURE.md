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
| `setup.php` | text domain, editor styles (`base.css` + `editor.css`), remove core patterns, pattern categories, disable remote patterns | — |
| `assets.php` | `base.css`; per-block stylesheets via `wp_enqueue_block_style()`; script modules + their stylesheets enqueued on first render of a trigger class; Gravity Forms token map on `gform_enqueue_scripts` | `oogle/assets/block_styles` (block ⇒ file map), `oogle/assets/script_modules` (handle ⇒ file, style, class) |
| `block-styles.php` | `register_block_style()` for the component vocabulary | `oogle/block_styles` |
| `editor.php` | curated `allowed_block_types_all` (post editing only; Site Editor untouched) | `oogle/editor/allowed_blocks` |
| `images.php` | JPEG → WebP sub-sizes (AVIF opt-in); hero Cover gets `fetchpriority="high"`, no lazy | `oogle/images/avif` |
| `cleanup.php` | removes the emoji loader on the front end | `oogle/cleanup/emoji` |
| `a11y.php` | render-time fixes for verified core defects (accordion panel `role="region"`) | — |
| `updates.php` | answers core's `update_themes_github.com` check from the latest GitHub Release; renames the extracted folder if needed; caches 6 h / 1 h on failure | `oogle/updates/enabled`, `oogle/updates/request_args`, `oogle/updates/api_url`, `oogle/updates/style_url` |

`functions.php` only requires these files. Total PHP ≈ 750 lines, a third of it comments.

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
| `single` | … plus the `comments` part (renders nothing when comments are closed and none exist) |
| `index` | Blog index: H1 from `oogle/hidden-blog-heading`, cards via `oogle/query-cards` |
| `archive`, `search` | Query title as H1, cards via `oogle/query-cards` |
| `404` | `oogle/hidden-404`: heading, lead, search, home link |

Copy that appears in a template is placed in a hidden pattern (`patterns/hidden-*.php`,
`Inserter: false`) so it is translatable; HTML templates cannot be.

## Template parts

`header`, `footer` and `navigation-overlay` are **expected to be overridden by the
child** (they are content). `breadcrumbs` uses core's Breadcrumbs block — no plugin
coupling. `post-meta` is date + category. `comments` is core's Comments block (list,
pagination, form).

## Patterns

See [PATTERNS.md](PATTERNS.md). Seven in the inserter plus four hidden template patterns,
all core-block compositions, all using preset slugs and block styles only.

## Section styles

`styles/section-*.json` are theme.json partials with `blockTypes: ["core/group"]`. They
recolor the Group and its nested headings/links/captions. Use them for "tinted", "reversed"
and "primary" sections; do not hand-set background colors on section groups.

## Assets

See [CSS.md](CSS.md) and [JAVASCRIPT.md](JAVASCRIPT.md).
