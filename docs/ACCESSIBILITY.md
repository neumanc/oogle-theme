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
| Accordion | `inc/a11y.php` | Core 7.1 omits a role on the panel; theme adds `role="region"` (APG pattern) |
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

## 0.1.0 results (framework test page, Precision child)

axe 4.10 (wcag2a/aa, 2.1, 2.2, best-practice): **0 violations** after the accordion role
fix (the only finding was core's `aria-prohibited-attr` on the accordion panel). Incomplete
items were contrast over gradients (manual check passed for the placeholder) and the
submenu chevron target size (fixed). Keyboard walk and accordion `aria-expanded` verified.
