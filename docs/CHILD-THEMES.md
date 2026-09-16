# Child themes — customizing a client site

The parent is never edited on a client site. Everything a site owns lives in its child theme
(presentation) and, for functionality that must survive a theme change, in a site
`mu-plugin`.

## Minimum child

```
precisionkb/
├── style.css        Theme Name, Template: oogle-theme, Version
├── theme.json       full palette + fontFamilies (with fontFace) + any value overrides
├── functions.php    font preloads, site pattern category, allowed-block additions
├── assets/fonts/    subset WOFF2 files + LICENSE.md
├── parts/           header.html, footer.html (site content: logo, phone, CTA, NAP)
└── patterns/        site content patterns (real copy, real links)
```

## What to override, and what not to

| Do in the child | Do NOT do in the child |
|---|---|
| Change every token *value* | Rename a slug |
| Add `fontFace` for your fonts | Load Google Fonts |
| Replace `parts/header.html` and `parts/footer.html` | Copy and edit parent templates unless the structure genuinely differs (then add, e.g., `templates/single-project.html`) |
| Add content patterns under your own namespace | Edit parent patterns |
| Add blocks to `oogle/editor/allowed_blocks` (e.g. a form plugin's block) | Turn the curated list off |
| Keep `assets/css/site.css` empty; if a rule lands there, it is a candidate parent block style | Add a "site.css" full of overrides |

## Fonts

Self-host. Latin subset, variable if available, weight axis restricted to what you use.
Recipe used for Precision (fontTools, offline):

```py
from fontTools.ttLib import TTFont
from fontTools.varLib import instancer
f = TTFont("Inter[wght].ttf")
f = instancer.instantiateVariableFont(f, {"wght": (400, 600)}, optimize=True)
f.flavor = "woff2"; f.save("inter-var.woff2")
```

Declare in `theme.json` under `fontFamilies[].fontFace` with `fontWeight: "400 600"` and
`fontDisplay: "swap"`, and preload the one or two files used above the fold from
`functions.php`. Record licenses in `assets/fonts/LICENSE.md`.

## Contrast check

Before shipping a palette, fill in the table in [TOKENS.md](TOKENS.md) with computed ratios.
Precision's is in `precision-kitchen-bath-v2/design/DESIGN-SYSTEM.md` §2.

## Site functionality

Post types, taxonomies, meta, CRM bridges, tracking snippets: `wp-content/mu-plugins/<site>/`.
Reason: switching themes must never hide content. See the Precision mu-plugin for the shape.

## Updating the parent

Replace `wp-content/themes/oogle-theme/` with the new version. Nothing the site owns lives
inside it. Read the parent CHANGELOG for any **major** version — it lists renamed slugs,
styles and patterns with a search-and-replace recipe.
