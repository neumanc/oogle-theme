# JavaScript

## The default is none

Oogle 0.1.0 ships **no JavaScript**. Everything interactive on a page comes from core's
Interactivity API modules, which load themselves only for blocks that need them:

| Need | Core provides | Bytes (7.1, min) |
|---|---|---|
| Mobile navigation overlay, focus trap, Escape, submenus | Navigation block | ~3 KB |
| FAQ accordion | Accordion block | ~1.3 KB |
| Image/gallery lightbox | Image block "Expand on click" | ~7 KB |
| Tabs | Tabs block | small |
| Sticky header | CSS `position: sticky` | 0 |
| Smooth scroll | CSS `scroll-behavior` (off under reduced motion) | 0 |

## Before writing any

Ask: can HTML or CSS do this accessibly? `<details>`, `<dialog>`, `<input type="range">`,
`:has()`, `scroll-snap`, `position: sticky`, `@media (prefers-reduced-motion)`.

## If a module is justified

- One file per behaviour in `assets/js/`, vanilla ES2020+, no framework, no jQuery, no
  shared "app" object.
- Register with `wp_register_script_module()` and enqueue only when the block/style that
  needs it renders (a `render_block` check on a block style class).
- Progressive enhancement: the markup must be usable before the module runs.
- Respect `prefers-reduced-motion` in the module as well as in CSS.
- Over ~150 lines: stop and ask whether it should be a plugin or should not exist.

## Third-party

Gravity Forms loads jQuery (~88 KB) plus its own scripts (~270 KB) on pages that render a
form. Put forms on conversion pages, not in every section. Analytics/tag managers belong
in the site plugin, loaded async.
