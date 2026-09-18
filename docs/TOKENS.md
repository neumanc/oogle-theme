# Tokens — the parent/child contract

Every design token is declared once, in `theme.json`, and consumed everywhere as the custom
property WordPress generates from it. There is no `tokens.css` and no second naming scheme.

**Slugs are the API.** A child theme changes values. Renaming or removing a slug is a
breaking change (major version). Adding one is a minor version.

## Color (`settings.color.palette`) → `--wp--preset--color--{slug}`

| Slug | Role | Contrast rule |
|---|---|---|
| `base` | page background | — |
| `surface` | alternate section background | — |
| `contrast` | body text, dark surfaces | ≥ 4.5:1 on `base` and `surface` |
| `contrast-soft` | secondary text | ≥ 4.5:1 on `base` |
| `muted` | meta text, UI borders | ≥ 4.5:1 on `base` (text) and ≥ 3:1 (borders) |
| `line` | decorative rules only | exempt (decorative) — never a control boundary |
| `primary` | brand color for large text/UI | may fail 4.5:1; **never body-size text** |
| `primary-deep` | links, focus ring, small brand text | ≥ 4.5:1 on `base` |
| `primary-tint` | soft highlight panels | — |
| `accent` | primary CTA background | ≥ 4.5:1 with `base` as the label |
| `accent-deep` | CTA hover/active, accent as text | ≥ 4.5:1 on `base` |
| `accent-tint` | CTA panels, soft highlight | — |

Gradients: `scrim`, `scrim-strong` — overlays for text on photos (used by the Cover block
in the hero pattern; a preset because core applies preset color classes with `!important`,
so CSS cannot override an overlay color).

## Typography

Families (`--wp--preset--font-family--{slug}`): `heading`, `body`, `mono`. The child adds
`fontFace` entries pointing at its own WOFF2 files.

Sizes (`--wp--preset--font-size--{slug}`), fluid: `x-small`, `small`, `medium` (body),
`large`, `x-large`, `xx-large`, `xxx-large`, `xxxx-large`, `display`.

> **Why `xxx-large` and not `3x-large`:** WordPress kebab-cases slugs and splits digit/letter
> boundaries, so `3x-large` becomes `--font-size--3-x-large` and any reference to the slug
> as written breaks (verified on 7.1). Slugs must avoid digit+letter runs.

Weights are `settings.custom.font.weight.{regular|medium|semibold|bold}` →
`--wp--custom--font--weight--*`.

## Spacing (`settings.spacing.spacingSizes`) → `--wp--preset--spacing--{slug}`

`10` 4px · `20` 8px · `30` 12px · `40` 16px · `50` 24px · `60` fluid 32–48px · `70` fluid
48–80px · `80` fluid 64–128px. Custom spacing is disabled in the editor; sections use `70`,
cards `50`, inline rhythm `20`–`40`.

## Layout

`contentSize` 44rem (prose), `wideSize` 77.5rem (1240px). A "container-wide" is not a token;
use `alignfull` with an inner constrained group when a section needs more.

## Custom (`settings.custom`) → `--wp--custom--{group}--{name}`

`color.error` (form validation; a custom token rather than a palette entry so a child that
does not override it inherits it — `settings.custom` merges, preset arrays replace) ·
`radius.{sm,md,lg,full}` · `border.width` · `measure.prose` (68ch; available to children,
not applied by the parent since 1.0.0 — see CSS.md) · `section.{tight,base,loose}` (fluid
section padding) · `transition.{fast,base}` · `font.weight.*` · `target.min` (44px) ·
`z.{header,overlay}` (`overlay` is reserved; core's own overlay uses its own z-index).

## Shadows (`settings.shadow.presets`) → `--wp--preset--shadow--{sm|md|lg}`

## Editor settings the contract relies on

`color.custom`, `customGradient`, `typography.customFontSize`, `spacing.customSpacingSize`
are **false**; the core default palettes/gradients/sizes are **off**. Clients pick presets.

## Rules

- No hex, px font-size or raw spacing in patterns, templates or CSS. Use the preset.
- Semantic slugs only (`accent`, never `clay`).
- **Preset arrays replace, they do not merge.** A child that declares `palette` must declare
  every slug above. The same applies to `fontFamilies`, `fontSizes`, `spacingSizes`,
  `shadow.presets`, `gradients`. (`settings.custom` merges by key.)
