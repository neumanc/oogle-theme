# JavaScript

## The default is a few small modules, each loaded only when used

Oogle ships two scripts of its own, both ES modules with no dependencies:
`assets/js/reveal.js` (~1.9 KB, scroll entrances) and `assets/js/rotator.js` (~3.0 KB,
crossfading hero photos). Each loads only on pages where a block carries its trigger class
(`oogle-reveal*`, `oogle-rotator`), together with its own stylesheet (`assets/css/reveal.css`,
`assets/css/rotator.css`); the map lives in `inc/assets.php` and is filterable
(`oogle/assets/script_modules`). Block templates render before `wp_head()`, so an enqueue
made while a block renders still prints in the head. Every other interactive behaviour comes from core's
Interactivity API modules, which load themselves only for blocks that need them:

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
| Rotating hero photography | `rotator.js` + CSS in `base.css` | ~3 KB |

Zero JS was never the goal. The goal is the smallest maintainable amount, and motion that
earns its place.

## The reveal contract (0.3.0)

1. Add `oogle-reveal` (or `oogle-reveal--clip`, `--left`, `--right`, `--stagger`) to a
   block's Additional CSS class(es).
2. `inc/assets.php` sees the class during `render_block` and enqueues the module once.
3. The module registers each matching element: it adds `oogle-reveal-ready` and either
   shows it at once (already in view on load — no flash on the first screen) or observes
   it, adding `is-visible` when 12% of it crosses the viewport, minus a 10% bottom margin.
   An element at least half a viewport tall reveals as soon as it enters (a tall section
   can never reach 12% of itself; 1.0.0 fix). It also adds `oogle-js-reveal` to `<html>`
   (informational only since 1.0.1).
4. The hiding rules in `reveal.css` are scoped to `.oogle-reveal-ready`, which only the
   module adds to elements it has registered, so with JS off, blocked or failed, in the
   editor, or for markup inserted after load that nobody registered, everything is simply
   visible — unregistered content is never hidden (1.0.1 fix).
5. Dynamic markup: after inserting nodes, dispatch a bubbling `oogle:reveal` event on the
   inserted container (`node.dispatchEvent( new Event( 'oogle:reveal', { bubbles: true } ) )`)
   and the module registers any matching elements inside it. Registering an element twice
   is a no-op. There is no document-wide MutationObserver by design.
6. Under `prefers-reduced-motion: reduce` the module exits before touching the DOM and the
   CSS cancels every transition anyway.

`--stagger` animates the element's direct children with 80 ms steps (up to eight). Keep it
for grids of images or short lists; do not stagger paragraphs of copy. `--clip` wipes the
element's `img` (the clip lives on the image, not the observed element, because a
`clip-path` on the observed element gives IntersectionObserver nothing to intersect).

## The rotator contract (0.4.0)

1. A Group with the class `oogle-rotator` whose direct children are Image blocks. Give the
   first `oogle-lcp` (eager, high priority: it is the LCP candidate) and every other one
   `oogle-defer` (lazy, low priority). Use `alt=""`: the stack is decorative behind stable
   copy, which is the only kind of rotation this module supports. Meaningful content that
   changes needs controls, and this module has none by design.
2. CSS keeps every slide after the first `display:none` until the module adds `is-queued`.
   A lazy image with no box is never requested, so a page without JS, with reduced motion,
   or in a background tab downloads exactly one hero image.
3. After `load` the module queues slide 2 (one image in flight), waits 6.5 s, decodes it,
   fades it in over slide 1 (1.7 s), then queues slide 3, and so on. The outgoing slide stays
   opaque underneath until the fade ends. Timing is paused while the tab is hidden or the
   stack is scrolled out of view.
4. Each shown slide's image eases from `scale(1.08)` to `scale(1)` over the hold: a settle,
   not a pan.
5. `prefers-reduced-motion: reduce`: the module returns before touching the DOM; the first
   image stays, unscaled, and no control is added.
6. Pause/play (1.0.1): when it does rotate, the module appends one
   `<button class="oogle-rotator__toggle" aria-pressed>` to the stack. Pausing clears the
   schedule and adds `is-paused` (the settle stops too); playing resumes. Labels come from
   `script_module_data_oogle-rotator` (`pause`, `play`; translated in `inc/assets.php`).
   The control sits at the stack's top-end corner (z-index 4, the corner least likely
   to be covered by an overlaid content layer); a child theme may reposition it with CSS
   but must keep it reachable by pointer and keyboard — a control under a transparent
   full-width content layer is not.

Accessibility note (WCAG 2.2.2 Pause, Stop, Hide): the rotation starts automatically,
lasts longer than five seconds and runs beside the page's content, so the criterion
applies even though the photographs are decorative; the toggle above is the required
mechanism, and the OS reduced-motion preference disables the rotation entirely. If a site
rotates anything a visitor needs to read, do not use this module; build the content as
static blocks or a core block with controls.

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
  needs it renders (a `render_block` check on a trigger class, as `reveal.js` does). Give it
  its own stylesheet in `assets/css/<module>.css`; never put module CSS in `base.css`, which
  also loads in the editor.
- Progressive enhancement: the markup must be usable before the module runs.
- Respect `prefers-reduced-motion` in the module as well as in CSS.
- Over ~150 lines: stop and ask whether it should be a plugin or should not exist.

## Third-party

Gravity Forms loads jQuery + jQuery Migrate (~100 KB) plus its own scripts (~270 KB) and
~660 KB of CSS on pages that render a form; measured on a real homepage it was 9 of the
page's 50 requests and most of the bytes. Put forms on conversion pages, not in every
section. Analytics/tag managers belong in the site plugin, loaded async.
