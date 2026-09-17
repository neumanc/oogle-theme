# JavaScript

## The default is one small module, loaded only when used

Oogle ships a single script of its own: `assets/js/reveal.js` (~1 KB, an ES module, no
dependencies). It loads only on pages where a block carries an `oogle-reveal*` class. Every
other interactive behaviour comes from core's Interactivity API modules, which load
themselves only for blocks that need them:

| Need | Provided by | Bytes (7.1, min) |
|---|---|---|
| Mobile navigation overlay, focus trap, Escape, submenus | Navigation block | ~3 KB |
| FAQ accordion | Accordion block | ~1.3 KB |
| Image/gallery lightbox | Image block "Expand on click" | ~7 KB |
| Tabs | Tabs block | small |
| Sticky header | CSS `position: sticky` | 0 |
| Smooth scroll | CSS `scroll-behavior` (off under reduced motion) | 0 |
| Scroll-entrance reveals | `reveal.js` + CSS in `base.css` | ~1 KB |
| Hover zoom, scroll-driven image drift | CSS only (`oogle-zoom-hover`, `oogle-drift`) | 0 |

Zero JS was never the goal. The goal is the smallest maintainable amount, and motion that
earns its place.

## The reveal contract (0.3.0)

1. Add `oogle-reveal` (or `oogle-reveal--clip`, `--left`, `--right`, `--stagger`) to a
   block's Additional CSS class(es).
2. `inc/assets.php` sees the class during `render_block` and enqueues the module once.
3. The module adds `oogle-js-reveal` to `<html>`, then observes each element. Elements
   already in view on load are shown immediately (no flash on the first screen); the rest
   get `is-visible` when 12% of them crosses the viewport, minus a 10% bottom margin.
4. The hiding rules in `base.css` are scoped to `.oogle-js-reveal`, so with JS off, blocked
   or failed, everything is simply visible.
5. Under `prefers-reduced-motion: reduce` the module exits before touching the DOM and the
   CSS cancels every transition anyway.

`--stagger` animates the element's direct children with 80 ms steps (up to six). Keep it for
grids of images or short lists; do not stagger paragraphs of copy.

What the module will never do: scroll-jack, pin, parallax the page, measure layout in a
scroll handler, or run on every page.

## Before writing any

Ask: can HTML or CSS do this accessibly? `<details>`, `<dialog>`, `<input type="range">`,
`:has()`, `scroll-snap`, `position: sticky`, `@media (prefers-reduced-motion)`,
scroll-driven animations (`animation-timeline: view()`).

## If a module is justified

- One file per behaviour in `assets/js/`, vanilla ES2020+, no framework, no jQuery, no
  shared "app" object.
- Register with `wp_register_script_module()` and enqueue only when the block/style that
  needs it renders (a `render_block` check on a block style class, as `reveal.js` does).
- Progressive enhancement: the markup must be usable before the module runs.
- Respect `prefers-reduced-motion` in the module as well as in CSS.
- Over ~150 lines: stop and ask whether it should be a plugin or should not exist.

## Third-party

Gravity Forms loads jQuery + jQuery Migrate (~120 KB) plus its own scripts (~300 KB) on
pages that render a form; measured on the Precision homepage it is 9 of the page's 38
requests. Put forms on conversion pages, not in every section. Analytics/tag managers
belong in the site plugin, loaded async.
