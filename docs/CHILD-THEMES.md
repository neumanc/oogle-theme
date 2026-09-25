# Child themes — building a client site on Oogle Theme

The parent is never edited on a client site. A site is:

- **Oogle Theme** (parent, updated from GitHub) — layout, tokens, templates, parts,
  patterns, block styles, accessibility and performance defaults;
- **its child theme** — the site's identity: token values, fonts, header/footer,
  site patterns, the few templates whose structure differs, a little CSS;
- **plugins / mu-plugin** — functionality that must survive a theme change (post types,
  integrations); see [PLATFORM.md](PLATFORM.md) for what goes where;
- **content** — pages, menus, media, edited in WordPress.

A child contains only what differs from the parent. What it may rely on is listed in
[EXTENSION-API.md](EXTENSION-API.md); everything else in the parent is internal. A working
minimal example is `tests/fixtures/oogle-fixture-child/` (about 270 lines in total, a
visibly different site, CI-tested).

## Files

```
<site>/                      required  purpose
├── style.css                yes       header only (block themes never load it as CSS)
├── theme.json               yes       the identity: palette, fonts, sizes, token values
├── functions.php            usually   enqueue the child CSS, preload fonts, filters
├── screenshot.png           no        1200×900 for Appearance → Themes
├── assets/css/site.css      usually   the site's own rules (any name except base.css/editor.css)
├── assets/fonts/            if fonts  subset WOFF2 + LICENSE.md
├── parts/                   usually   header.html, footer.html, navigation-overlay.html
├── patterns/                usually   site content patterns in the site's own namespace
├── templates/               rarely    only templates whose STRUCTURE differs
└── styles/                  rarely    extra section styles, or a replacement section-*.json
```

Omit anything you do not need; WordPress falls back to the parent file by file.

### style.css

```
/*
Theme Name: Example Inn
Template: oogle-theme
Version: 1.0.0
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 8.4
Text Domain: example-inn
Update URI: false
*/
```

- `Template: oogle-theme` is the parent's **directory name**. Install the parent from the
  release asset so it is exactly `wp-content/themes/oogle-theme/`.
- `Update URI: false` stops WordPress offering the child an "update" from wordpress.org if
  a public theme ever uses the same directory name. The child is deployed, not updated.
  (Core then skips the child in its per-host update filters; excluding it from the
  wordpress.org lookup is done on the wordpress.org side, which cannot be tested locally.)
  Also give the child a directory name nobody else would pick (e.g. `example-inn-site`, not `inn`).
- Keep `Requires PHP` / `Requires at least` equal to the parent's.

### functions.php

WordPress loads the **child's** `functions.php` first, then the parent's. So at the top
level of the child file the parent's constants and functions do not exist yet; use hooks.

```php
<?php
declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

// Site CSS, printed after the parent's global stylesheet.
add_action( 'wp_enqueue_scripts', function (): void {
	wp_enqueue_style( 'example-inn', get_theme_file_uri( 'assets/css/site.css' ), array( 'oogle-base' ), wp_get_theme()->get( 'Version' ) );
} );

// The same CSS in the editor canvas.
add_action( 'after_setup_theme', function (): void {
	add_editor_style( 'assets/css/site.css' );
} );

// A third-party block in the curated inserter.
add_filter( 'oogle/editor/allowed_blocks', function ( $blocks ) {
	if ( is_array( $blocks ) ) {
		$blocks[] = 'gravityforms/form';
	}
	return $blocks;
} );
```

Use named, prefixed functions in a real child (as the fixture does); closures are shown
for brevity.

## Identity through theme.json

Most of a site's look is values. In the child's `theme.json`:

| To change | Set |
|---|---|
| Colours | `settings.color.palette` — **all twelve slugs** (arrays replace) |
| Fonts | `settings.typography.fontFamilies` — `heading`, `body`, `mono`, each with `fontFace` pointing at `file:./assets/fonts/…` |
| Type scale | `settings.typography.fontSizes` (all nine slugs), or heading styles under `styles.elements` |
| Content and wide width | `settings.layout.contentSize` / `wideSize` |
| Section rhythm | `settings.custom.section.{tight,base,loose}` |
| Cards | `settings.custom.card.{background,borderColor,radius,shadow,padding}` |
| Buttons and form controls | `settings.custom.control.radius`; colours via `styles.elements.button` |
| Radius scale | `settings.custom.radius.{sm,md,lg,full}` |
| Headings, links, captions | `styles.elements.*` |
| One block everywhere | `styles.blocks["core/…"]` |

`settings.custom` merges key by key, so set only what differs. The fixture sets square
cards without shadow, pill-shaped controls and its own palette and fonts in about 60 lines
of JSON; the parent's CSS is untouched.

## CSS

- Block themes do not load `style.css`. Enqueue your own file with `oogle-base` as a
  dependency (above), and add it to the editor with `add_editor_style()`.
- **Never** name a child file `assets/css/base.css` or `assets/css/editor.css`: core resolves
  the parent's editor styles child-first and yours would replace them in the editor.
- Change values before writing rules. A rule you write should be no more specific than the
  parent's (single class, or class + element; `:where()` when only adding declarations).
  No `!important` except against a third-party ID selector.
- Per-block CSS of your own: `wp_enqueue_block_style( 'core/cover', array( 'handle' => …,
  'src' => get_theme_file_uri( … ), 'path' => get_theme_file_path( … ) ) )` on `init`. It
  loads only on pages that render the block. (The parent's `oogle/assets/block_styles`
  filter only maps to files inside the parent.)
- Dark bands of your own: put `oogle-dark` on the Group (header, footer, a band) and it
  gets the parent's on-dark focus ring, buttons, lead and eyebrow colours, with no CSS.

## Templates, parts and patterns

WordPress resolves each file child first, then parent:

- **Parts** — `header`, `footer`, `navigation-overlay` are the site's own content (logo,
  navigation, phone, address, CTA); every client child overrides them. `breadcrumbs`,
  `post-meta`, `comments` can be overridden the same way.
- **Templates** — copy one only when its structure differs, or add new ones
  (`single-room.html`, `taxonomy-….html`). Keep the contract: header part → `<main>` →
  footer part.
- **Template patterns** — to change what a parent template shows, override its pattern by
  slug instead of copying the template: a child `patterns/query-cards.php` with
  `Slug: oogle/query-cards` changes every archive, search and blog listing. Same for
  `oogle/hidden-404`, `oogle/hidden-blog-heading` and `oogle/hidden-no-results`.
- **Site patterns** — under the site's own namespace (`example-inn/…`), with its own
  pattern category registered on `init`. Use parent block styles and tokens in them.
- **Section styles** — add `styles/*.json` with `blockTypes: ["core/group"]`; a file named
  like a parent one (`section-tint.json`) replaces it.
- Edits made in the Site Editor are stored in the database and win over theme files; export
  them back into the child's files before relying on a file change.

## Fonts

Self-host. Latin subset, variable if available, weight axis restricted to what you use.
Recipe (fontTools, offline):

```py
from fontTools.ttLib import TTFont
from fontTools.varLib import instancer
f = TTFont("Inter[wght].ttf")
f = instancer.instantiateVariableFont(f, {"wght": (400, 600)}, optimize=True)
f.flavor = "woff2"; f.save("inter-var.woff2")
```

Declare in `theme.json` under `fontFamilies[].fontFace` with `fontWeight: "400 600"` and
`fontDisplay: "swap"`, and preload the one or two files used above the fold from
`functions.php` (`<link rel="preload" … crossorigin>` on `wp_head`, priority 2). Add
metric-matched fallback faces (`size-adjust`, `ascent-override`) in the site CSS if the swap
causes layout shift. Record licences in `assets/fonts/LICENSE.md`. No Google Fonts CDN.

## Contrast check

Before shipping a palette, fill in the contrast table in [TOKENS.md](TOKENS.md) with
computed ratios and keep it in the site repository.

## Site functionality

Post types, taxonomies, meta, integrations, tracking: not in the child. Reusable pieces
belong in Oogle Core modules; one-site pieces in the site's mu-plugin
(`wp-content/mu-plugins/<site>.php` requiring `<site>/*.php`, prefixed functions, an
option-stored version for one-time upgrades). Reason: switching themes must never hide
content. Decision guide: [PLATFORM.md](PLATFORM.md).

## Checklist for a new child

- [ ] `style.css` with `Template: oogle-theme`, matching requirements, `Update URI: false`
- [ ] `theme.json`: full palette, three font families with `fontFace`, token values
- [ ] contrast table filled in
- [ ] header, footer, navigation-overlay parts
- [ ] site CSS enqueued with `oogle-base`, added to the editor; no reserved file names
- [ ] form plugin block added to `oogle/editor/allowed_blocks`
- [ ] axe clean and keyboard walk on home, a page per template, a form
- [ ] `phpcs` with the WordPress standard (see `tests/fixtures/phpcs.xml.dist`)

## Updating the parent

See [../UPGRADE.md](../UPGRADE.md). A parent update replaces
`wp-content/themes/oogle-theme/` only; the child directory is never written. Within 1.x
nothing listed in EXTENSION-API.md changes. Read the CHANGELOG for any **major** version;
it lists what changed with a search-and-replace recipe.
