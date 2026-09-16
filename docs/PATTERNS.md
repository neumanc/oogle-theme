# Patterns

Patterns are the primary authoring unit. They are PHP files in `patterns/` with the standard
header; core registers them automatically. Categories: `oogle-sections`, `oogle-components`.

## The initial set (0.1.0)

| Slug | Composition | Notes |
|---|---|---|
| `oogle/hero-cover` | Cover (scrim gradient, `oogle-lcp`) → Group → eyebrow, **H1**, lead, Buttons | Only pattern with an H1; use with the `page-no-title` template |
| `oogle/section-split` | Group → Columns 7/5 → eyebrow, H2, paragraph, checklist, outline button / Image 4:5 | Apply Columns style "Reverse when stacked" to keep the image first on phones |
| `oogle/cards-grid` | Group (tint) → intro → Group **grid** (min column 17rem) → Group `card-flush` × 3 | Duplicate a card to add one; reflows with no breakpoints |
| `oogle/cta-panel` | Group (reversed) → H2, lead, Buttons (primary + text `tel:`) | Every page should end with one |
| `oogle/testimonials` | Group → intro → grid → Group `card` → Quote `testimonial` | Convert a quote to a synced pattern to reuse it |
| `oogle/query-cards` | Query Loop → Post Template grid → `card-flush` | `Inserter: false`; used by archive/search templates |

## Rules

1. **Core blocks only.** No custom blocks; no HTML block except for a form shortcode.
2. **Presets only.** `"backgroundColor":"surface"`, `"fontSize":"large"`,
   `var:preset|spacing|70`. Never hex or px.
3. **Block styles, not classes.** A look that no setting provides becomes a registered block
   style (`is-style-*`); a one-off class is a smell.
4. **Heading discipline.** Hero carries the H1. Every other pattern starts at H2 and nests.
5. **Placeholders are obviously placeholders.** Copy reads "Section heading"; images use
   `assets/img/placeholder.svg` with alt "Describe this image" so QA catches misses.
6. **Markup must be canonical.** Hand-written block HTML must match what the block itself
   serializes, or the editor flags "unexpected or invalid content". To get canonical markup:
   build the block in the editor and copy it (Code editor view), or in the console
   `wp.blocks.serialize(wp.blocks.createBlock('core/cover', {...}, [...]))`. Validate all
   patterns with `wp.blocks.parse(pattern.content)` and check `isValid` (see tools note
   below).
7. **Copies, not links.** A pattern inserted from the inserter is a copy; updating the file
   does not change existing pages. Improvements to existing pages ship as CSS/block styles.
   (A `<!-- wp:pattern {"slug":…} /-->` reference in content *does* stay linked and edits
   content-only — WP 7.x behaviour — useful for templates, not for client-authored pages.)

## Canonical markup for blocks used so far (WP 7.1)

```html
<!-- wp:accordion -->
<div role="group" class="wp-block-accordion"><!-- wp:accordion-item -->
<div class="wp-block-accordion-item"><!-- wp:accordion-heading {"title":"Question?","showIcon":false} -->
<h3 class="wp-block-accordion-heading"><button type="button" class="wp-block-accordion-heading__toggle"><span class="wp-block-accordion-heading__toggle-title">Question?</span></button></h3>
<!-- /wp:accordion-heading --><!-- wp:accordion-panel -->
<div role="region" class="wp-block-accordion-panel"><!-- wp:paragraph --><p>Answer.</p><!-- /wp:paragraph --></div>
<!-- /wp:accordion-panel --></div>
<!-- /wp:accordion-item --></div>
<!-- /wp:accordion -->
```

Cover: see `patterns/hero-cover.php` (gradient preset overlay, `has-background-dim-100`).

## Adding a pattern

Create `patterns/<name>.php` with `Title`, `Slug: oogle/<name>`, `Categories`, `Viewport
Width`, `Description`. Use `esc_html_e()` for copy and `esc_url( get_theme_file_uri() )`
for the placeholder. Validate in the editor. Add it to the table above and to CHANGELOG.

## Validation snippet (browser console, any editor screen)

```js
const walk = b => [b].concat(b.innerBlocks.flatMap(walk));
wp.data.select('core').getBlockPatterns().filter(p => p.name.startsWith('oogle/')).map(p => {
  const bad = wp.blocks.parse(p.content).flatMap(walk).filter(b => !b.isValid);
  return p.name + ': invalid=' + bad.length + ' ' + bad.map(b => b.name).join(',');
}).join('\n');
```
