# Extension API — what a child theme may depend on

This page is the **compatibility contract** between Oogle Theme and every child theme built
on it. Everything listed under *Public* keeps working, unchanged, for the whole of a major
version (1.x). Everything under *Internal* may change in any release, including a patch.
If something is not listed here, it is internal.

The contract is deliberately small and almost entirely WordPress-native: theme.json slugs,
block style names, pattern slugs, template and part names, a few classes, and filters.
There is no framework API to learn. Versioning rules: [RELEASES.md](RELEASES.md).

The fixture child in `tests/fixtures/oogle-fixture-child/` uses this contract and nothing
else, and CI (`tests/child-theme.php`, `tests/child-theme-http.sh`) refuses a release that
breaks it.

## Public

### theme.json presets (slugs) → `--wp--preset--*`

| Kind | Slugs | CSS variable |
|---|---|---|
| Colour | `base` `surface` `contrast` `contrast-soft` `muted` `line` `primary` `primary-deep` `primary-tint` `accent` `accent-deep` `accent-tint` | `--wp--preset--color--{slug}` |
| Gradient | `scrim` `scrim-strong` | `--wp--preset--gradient--{slug}` |
| Font family | `heading` `body` `mono` | `--wp--preset--font-family--{slug}` |
| Font size | `x-small` `small` `medium` `large` `x-large` `xx-large` `xxx-large` `xxxx-large` `display` | `--wp--preset--font-size--{slug}` |
| Spacing | `10` `20` `30` `40` `50` `60` `70` `80` | `--wp--preset--spacing--{slug}` |
| Shadow | `sm` `md` `lg` | `--wp--preset--shadow--{slug}` |

Roles and contrast rules per slug: [TOKENS.md](TOKENS.md). A child changes **values**. Preset
arrays in a child's theme.json *replace* the parent's, so a child that declares `palette`
(or `fontFamilies`, `fontSizes`, `spacingSizes`, `shadow.presets`, `gradients`) declares
every slug of that array.

### theme.json custom tokens → `--wp--custom--*`

`settings.custom` **merges key by key**: a child sets only the keys it changes.

| Key | Variable | Controls |
|---|---|---|
| `color.error` | `--wp--custom--color--error` | form validation colour |
| `radius.sm` `.md` `.lg` `.full` | `--wp--custom--radius--*` | the radius scale |
| `border.width` | `--wp--custom--border--width` | hairline width |
| `card.background` `.borderColor` `.radius` `.shadow` `.padding` *(1.1)* | `--wp--custom--card--*` | the Card / Card (flush media) block styles |
| `control.radius` *(1.1)* | `--wp--custom--control--radius` | buttons, the search field, Gravity Forms fields and buttons |
| `section.tight` `.base` `.loose` | `--wp--custom--section--*` | fluid section padding (Cover uses `base`) |
| `measure.prose` | `--wp--custom--measure--prose` | available to children; not applied by the parent |
| `transition.fast` `.base` | `--wp--custom--transition--*` | UI transitions |
| `font.weight.regular` `.medium` `.semibold` `.bold` | `--wp--custom--font--weight--*` | weights used by theme.json and CSS |
| `target.min` | `--wp--custom--target--min` | minimum touch target (44px) |
| `z.header` `.overlay` | `--wp--custom--z--*` | stacking |

Also public: `settings.layout.contentSize` / `wideSize` (values are child-owned), and the
editor restrictions the contract relies on (no custom colours, sizes or spacing; core
default palettes off).

### Section styles

`section-tint`, `section-reversed`, `section-primary` (Group block, `is-style-{slug}`),
defined in `styles/section-*.json`. A child file with the **same file name** in its own
`styles/` replaces the parent's; other files add new section styles.

### Block style names

| Block | Styles |
|---|---|
| `core/group` | `card`, `card-flush`, `mosaic`, `section-head`, `numbered` |
| `core/paragraph` | `eyebrow`, `lead` |
| `core/list` | `checklist`, `inline` |
| `core/quote` | `testimonial` |
| `core/button` | `text` (plus core's `fill`, `outline`, restyled) |
| `core/columns` | `reverse-on-stack` |
| `core/categories` | `inline` |
| `core/post-template` | `editorial` |
| `core/image` | `rounded` (theme.json variation) |

### Classes

Put on blocks through *Advanced → Additional CSS class(es)* or in pattern markup.

| Class | Effect |
|---|---|
| `oogle-dark` *(1.1)* | Marks a dark surface (e.g. a dark header or footer Group). Descendants get the parent's on-dark treatment: light focus ring, legible outline/text buttons, recoloured lead and eyebrow. The parent's `section-reversed`, `section-primary` and the Cover block get it automatically. |
| `oogle-header`, `oogle-header__cta`, `oogle-header--collapse-lg` | Sticky-header hairline and z-index; CTA hidden below 600px; collapse the navigation below 1200px instead of 1024px |
| `oogle-footer`, `oogle-overlay` | Footer and overlay-menu hooks (the overlay styles its navigation) |
| `oogle-section` | Marker for a full-width section in patterns |
| `oogle-lcp` | On a Cover / Image / Post Featured Image: its image loads eagerly with `fetchpriority="high"` |
| `oogle-defer` | On an Image: lazy, low-priority loading |
| `oogle-sticky` | Stays in view beside a taller sibling |
| `oogle-zoom-hover`, `oogle-drift` | CSS-only image motion (hover zoom; scroll-driven drift) |
| `oogle-reveal`, `oogle-reveal--stagger` `--clip` `--scale` `--left` `--right` | Scroll entrance (script module, loaded only when used) |
| `oogle-rotator` | Crossfading photo stack with pause/play control (script module) |
| `oogle-mosaic--captions` | Captions over mosaic tiles |
| `oogle-tile` | Bare (non-card) Query Loop item |
| `oogle-filter-row` | Row of filter links styled like the inline Categories style |
| `oogle-breadcrumbs`, `oogle-post-meta`, `oogle-comments` | Wrappers in the parent's parts |

The DOM event `oogle:reveal` (dispatched on inserted markup) registers dynamic content with
the reveal module.

### Patterns

Inserter patterns: `oogle/hero-cover`, `oogle/hero-split`, `oogle/section-split`,
`oogle/cards-grid`, `oogle/testimonials`, `oogle/faq`, `oogle/cta-panel`; categories
`oogle-sections`, `oogle-components`.

Template patterns (used by the parent's templates, not in the inserter):
`oogle/hidden-404` (404 body), `oogle/hidden-blog-heading` (posts-index H1),
`oogle/hidden-no-results`, `oogle/query-cards` (index, archive and search listings).

**Override by slug.** WordPress registers the child's patterns first and skips a parent
pattern whose slug is already registered, so a child file declaring `Slug: oogle/query-cards`
replaces the listing on every archive, search and index page without copying a template.
This is supported for the template patterns above. Content already inserted from an
inserter pattern keeps its markup; overriding one only changes future insertions.

### Templates and template parts

Templates: `index`, `page`, `page-no-title`, `page-narrow`, `blank`, `single`, `archive`,
`search`, `404` (custom templates `page-no-title`, `page-narrow`, `blank` are selectable per
page). Parts: `header`, `footer`, `navigation-overlay` (areas header / footer /
navigation-overlay), `breadcrumbs`, `post-meta`, `comments`.

The **template contract** within 1.x: each template is `header` part → `<main>` → content
→ `footer` part (`blank` has no header/footer), template copy comes from the template
patterns above, and every listed part keeps its name and area. A child overrides any
template or part by shipping a file with the same name; WordPress resolves child first.

### PHP

| API | Contract |
|---|---|
| Style handle `oogle-base` | The parent's global stylesheet. Declare it as a dependency so the child's CSS prints after it. |
| Constant `OOGLE_VERSION` | Parent version (read-only). Defined when the **parent's** `functions.php` loads, which is *after* the child's — read it inside a hook (`after_setup_theme` or later). |
| Constant `OOGLE_GITHUB_TOKEN` | Optional, set in `wp-config.php` by the site (raises the GitHub API rate limit). |
| Text domain `oogle` | Parent strings. |

### Filters

| Filter | Arguments → return | Use |
|---|---|---|
| `oogle/block_styles` | `array $styles` (triples: block, name, label) → array | Add or remove registered block styles. The child styles what it adds. |
| `oogle/editor/allowed_blocks` | `string[] $blocks, WP_Block_Editor_Context $context` → `string[]\|true` | Widen or narrow the curated inserter (e.g. add `gravityforms/form`); `true` lifts the theme's restriction. Never broadens an upstream restriction. |
| `oogle/editor/restrict_post_type` | `bool $restricted, WP_Post_Type $type, WP_Block_Editor_Context $context` → bool | Exempt a post type from the curated inserter. |
| `oogle/assets/block_styles` | `array<string,string> $map` (block ⇒ file in the **parent's** `assets/css/blocks/`) → array | Remove (or re-point to another parent file) a parent per-block stylesheet. Entries naming a file the parent does not ship are skipped; a child adds its own with `wp_enqueue_block_style()`. |
| `oogle/assets/script_modules` | `array $modules` (handle ⇒ file, style, class; files in the **parent**) → array | Remove a parent module or change its trigger class. A child registers its own with `wp_register_script_module()`. |
| `oogle/images/avif` | `bool` (default false) → bool | AVIF instead of WebP sub-sizes for JPEG uploads. |
| `oogle/cleanup/emoji` | `bool` (default true) → bool | Keep core's emoji loader. |
| `oogle/updates/enabled` | `bool` → bool | Switch the GitHub updater off (git-deployed sites). Site mu-plugin, not the child. |
| `oogle/updates/allowed_major` | `int\|null $major, string $installed` → `int\|null` | Opt in to another major once the child has been migrated. Site mu-plugin. |
| `oogle/updates/request_args` | `array $args, string $url` → array | Proxy/timeout settings for update checks (cannot re-enable redirects or re-target the token). |

## Internal — do not depend on

- The test hooks `oogle/updates/api_url` and `oogle/updates/style_url` (they point the
  updater at a fixture in the repository's own suites). Not a contract; never use them in
  a child or on a production site.
- Every PHP function (`oogle_*`). Change behaviour through the filters above; do not call
  or `remove_action()` parent functions.
- `OOGLE_DIR`, `OOGLE_URI` (use `get_template_directory()` / `get_template_directory_uri()`).
- Handles other than `oogle-base`: per-block style handles (`oogle-core-*`), script module
  handles and their stylesheets, `oogle-gravity-forms`.
- File paths and file names under `assets/`, `inc/`; the split of CSS across files; the
  selectors, their specificity and their order. Style through tokens, block styles and your
  own CSS at no more than the parent's specificity.
- State classes set by scripts (`oogle-reveal-ready`, `oogle-js-reveal`, `is-visible`,
  `is-shown`, `is-active`, `oogle-rotator__toggle`), script-module data, the updater's
  transients and error codes.
- Pattern markup and placeholder copy (slugs are public; their inner markup may improve).
- The exact default token **values** (the parent's palette, sizes, radii): children set their
  own. Defaults can be tuned in a minor release when the change is visually safe for
  children that rely on them; slug and variable names never change within a major.

### Reserved child file paths

`assets/css/base.css` and `assets/css/editor.css` in a child are **reserved**. The parent
registers its editor styles by those relative paths, and core resolves them child-first in
the block editor, so a child file at either path silently replaces the parent's editor
styles. Name child files anything else (the fixture uses `assets/css/fixture.css`).
