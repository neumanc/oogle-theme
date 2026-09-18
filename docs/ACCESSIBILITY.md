# Accessibility — WCAG 2.2 AA

Accessibility is decided in the theme so it does not have to be re-decided per page.

## Decided in the parent

| Criterion | Where | How |
|---|---|---|
| Landmarks | templates/parts | `<header>` and `<footer>` from the template-part wrapper, `<main>` from the main Group, `<nav aria-label="Primary">` / `"Footer"` from the Navigation block. Inner groups are `div`s — never nest two `<header>` elements |
| Skip link | core | Injected and styled by core when the template has `<main>` |
| Headings | patterns | Hero owns the H1 (with `page-no-title`); other patterns start at H2; footer labels are paragraphs, not headings |
| Keyboard | core blocks | Only native controls; no `div` click handlers exist |
| Focus visibility | `base.css` | 2px `primary-deep` ring, 2px offset, on every focusable element; light ring on dark sections |
| Targets (2.5.8) | `base.css`, `core-navigation.css` | Buttons ≥ 44px tall; header nav links ≥ 44px; submenu chevron ≥ 24px; overlay controls ≥ 44px |
| Motion | `base.css` | `prefers-reduced-motion` disables transitions/animations and smooth scroll |
| Accordion | `inc/a11y.php` | Core 7.0/7.1 omits a role on the panel; theme adds `role="region"` (APG pattern) |
| Comments | `parts/comments.html` | Core Comments block: list, pagination and form; renders nothing when comments are closed and none exist |
| Images | patterns | Placeholder alt reads "Describe this image"; Cover backgrounds `alt=""` |
| Overlay text | hero | `scrim` gradient preset; verify contrast against the real photo, use `scrim-strong` or a solid panel if in doubt |

## Decided in the child

Palette contrast (see TOKENS.md), real alt text, link text, form plugin configuration
(visible labels, error summaries, consent wording).

## QA checklist (run on each new page type)

1. axe (browser extension or injected `axe.run`) → 0 serious/critical.
2. Keyboard: Tab through header, overlay menu (open, Escape, focus returns), accordion,
   lightbox, forms; every stop has a visible ring.
3. Screen reader (VoiceOver): landmarks announced, headings in order, buttons named.
4. 320px width and 200% zoom: no horizontal scroll, no clipped text.
5. Reduced motion on: nothing animates.
6. Contrast: spot-check text over photos.

## Results on record

### 0.1.0 (framework test page, first client child)

axe 4.10 (wcag2a/aa, 2.1, 2.2, best-practice): **0 violations** after the accordion role
fix (the only finding was core's `aria-prohibited-attr` on the accordion panel). Incomplete
items were contrast over gradients (manual check passed for the placeholder) and the
submenu chevron target size (fixed). Keyboard walk and accordion `aria-expanded` verified.

### 1.0.0 (clean install, parent only, WordPress 7.1)

axe 4.13 on twelve page types (home with every pattern, kitchen-sink page, page, child
page, narrow page, blank landing, blog index and page 2, category, search, 404, two posts
including comments): **0 violations** in Chromium, WebKit and Firefox after the fixes
below; `color-contrast` reported *incomplete* only for text over the hero scrim (manual
check: white on the placeholder scrim ≥ 4.5:1). Keyboard: first Tab lands on the skip
link; the overlay opens on Enter, traps focus (Close → links → CTA → logo → Close), closes on
Escape and returns focus to the open button; submenu toggle is reachable by Tab; accordion
toggles with `aria-expanded`/`aria-controls`, panel `role="region"`. No horizontal overflow
at 320, 375, 412, 601, 768, 1023, 1024, 1199, 1280, 1440, 1920 or 2560 px.

Fixed at 1.0.0: the blog index had no H1 (`page-has-heading-one`); breadcrumbs used
`white-space: nowrap`, so a long title widened the page on phones.

## Known limitations

- **Rotator (2.2.2 Pause, Stop, Hide):** the opt-in hero rotator has no on-page pause
  control; it honours `prefers-reduced-motion` and only rotates decorative photographs
  behind static copy. Do not use it for content that must be read.
- **Lightbox:** core's lightbox (`Expand on click`) is opt-in per image; core handles its
  dialog semantics and focus return.
- **Contrast over photographs** cannot be guaranteed by the theme; the `scrim` /
  `scrim-strong` presets are the tools, the child checks the real photos.
- **Third-party forms** bring their own markup; the theme maps Gravity Forms' variables and
  restores a visible focus ring, but labels, errors and consent wording are configured per
  site.
