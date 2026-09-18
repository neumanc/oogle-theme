# Oogle Theme

Oogle's reusable WordPress **block theme** foundation: the parent theme every Oogle client
site is built on. It ships a stable design-token contract (`theme.json`), thin templates, a
small set of generic patterns, a handful of block styles with per-block CSS, and two tiny
optional script modules. Every client site is a **child theme** that supplies its own token
values, fonts, header/footer content and site patterns; site functionality lives in a site
plugin. No page builder, no build step, no jQuery, no CSS or JS framework.

- **Version:** 1.0.0 — see [CHANGELOG.md](CHANGELOG.md)
- **Requires:** WordPress 7.0+ (tested on 7.0 and 7.1), PHP 8.1+ (tested on 8.1, 8.3, 8.4)
- **License:** [GPL-2.0-or-later](LICENSE)
- **Production dependencies:** none. WordPress core only.

## What it is, in one screen

```
WordPress core blocks + theme.json engine
   └── oogle-theme (parent)   tokens (slugs + neutral values) · templates · parts · patterns
        │                      block styles · per-block CSS · reveal/rotator modules · GitHub updater
        └── client child       token values · fonts · header/footer/overlay content · site patterns
             └── site mu-plugin   post types · taxonomies · integrations · tracking (never presentation)
```

```
oogle-theme/
├── style.css          theme header only (Version, Requires, Update URI)
├── theme.json         the token contract; neutral values
├── functions.php      loads inc/*.php
├── inc/               setup · assets · block-styles · editor · images · cleanup · a11y · updates
├── templates/         index · page · page-no-title · page-narrow · blank · single · archive · search · 404
├── parts/             header · footer · navigation-overlay · breadcrumbs · post-meta · comments
├── patterns/          hero-cover · hero-split · section-split · cards-grid · testimonials · faq · cta-panel
│                      query-cards · hidden-404 · hidden-blog-heading · hidden-no-results (template use)
├── styles/            section-tint · section-reversed · section-primary (Group section styles)
├── assets/css/        base.css · editor.css · blocks/*.css · reveal.css · rotator.css · integrations/
├── assets/js/         reveal.js · rotator.js (ES modules, loaded only when a block asks)
├── assets/img/        placeholder.svg
├── docs/              architecture, tokens, CSS, JS, patterns, accessibility, releases (not shipped)
└── tools/             payload.sh (dev only, not shipped)
```

## Installation

1. Download `oogle-theme.zip` from the latest [GitHub Release](https://github.com/oogle/oogle-theme/releases)
   (or build it: `git archive --format=zip --prefix=oogle-theme/ v1.0.0 -o oogle-theme.zip`).
2. Appearance → Themes → Add New → Upload, or unzip into `wp-content/themes/oogle-theme/`.
   The directory **must** be named `oogle-theme`; child themes reference it by that name.
3. Do not activate the parent on a client site. Create and activate a child theme
   ([docs/CHILD-THEMES.md](docs/CHILD-THEMES.md)); activating the parent directly is fine for
   a lab or a plain blog.
4. Create the primary menu (Appearance → Editor → Navigation) **before** the first front-end
   render, or delete the "Navigation" menu core auto-creates from the page list.

Details, including the local lab: [docs/INSTALLATION.md](docs/INSTALLATION.md).

## Updates

`style.css` declares `Update URI: https://github.com/oogle/oogle-theme`. `inc/updates.php`
answers WordPress's own update check by reading the repository's latest GitHub Release, so
new versions appear in Appearance → Themes, Dashboard → Updates, auto-updates and
`wp theme update` like any other theme. Nothing a site owns lives inside the parent
directory, so an update cannot overwrite client work. Procedure, guarantees and the
tested upgrade: [UPGRADE.md](UPGRADE.md). Release mechanics: [docs/RELEASES.md](docs/RELEASES.md).

## Customization strategy

The parent owns **slugs** (colors, font families, sizes, spacing, custom tokens), block
style names, pattern slugs, template and part names, `oogle-*` class names and `oogle/*`
filters. A child changes **values** and content. Renaming or removing anything the parent
owns is a breaking change (major version).

| Layer | Owns | Example |
|---|---|---|
| Parent theme | presentation framework | `theme.json` slugs, `is-style-card`, `oogle/cta-panel`, `parts/header.html` default |
| Child theme | this site's look and copy | palette values, `fontFace`, `parts/header.html`, `patterns/site-*.php` |
| Site mu-plugin | functionality that must survive a theme switch | post types, taxonomies, CRM bridge, analytics |
| Server / CDN | HTTP, TLS, caching, security headers, WAF | `.htaccess`, LiteSpeed, Cloudflare |

Read: [docs/TOKENS.md](docs/TOKENS.md) (the contract), [docs/CHILD-THEMES.md](docs/CHILD-THEMES.md),
[docs/PATTERNS.md](docs/PATTERNS.md), [docs/CSS.md](docs/CSS.md), [docs/JAVASCRIPT.md](docs/JAVASCRIPT.md).

## Development

There is nothing to build. Edit files, reload. Optional tooling, all dev-only:

- **PHP**: `phpcs` with WordPress Coding Standards (`phpcs.xml.dist`), PHPCompatibility 8.1+, `php -l`.
- **Everything else**: `jq` for JSON, `node --check` for the modules, a browser for the pattern
  validation snippet in docs/PATTERNS.md.
- **CI** (`.github/workflows/ci.yml`) runs the same checks on every push and pull request.
- **Local WordPress**: Docker (`wordpress` + `mariadb` images) or WordPress Playground CLI;
  see docs/INSTALLATION.md.

Coding rules for humans and AI agents: [AGENTS.md](AGENTS.md). Contributing: [CONTRIBUTING.md](CONTRIBUTING.md).
Security reports: [SECURITY.md](SECURITY.md).

## Release process

1. Move CHANGELOG entries from *Unreleased* to a dated version; bump `Version:` in `style.css`
   and `Stable tag:` in `readme.txt`.
2. Commit, tag `vX.Y.Z`, push the tag.
3. `.github/workflows/release.yml` builds `oogle-theme.zip` with `git archive` (dev files
   excluded by `.gitattributes export-ignore`), publishes a GitHub Release with the zip and its
   SHA-256, and the updater picks it up on every site within six hours (or immediately via
   Dashboard → Updates → Check again).

Full detail: [docs/RELEASES.md](docs/RELEASES.md).

## Browser support

Evergreen Chrome, Edge, Firefox and Safari (current and previous major). Modern CSS
(`clamp()`, logical properties, `:is()`/`:where()`, `:has()`, `color-mix()`, `dvh`) is used
where the fallback is acceptable; `text-wrap`, scroll-driven animations and `view()` are
progressive enhancements that degrade to static output. Scripts are ES modules, so browsers
without module support (none in scope) load no theme JavaScript and see fully working pages.

## Accessibility

Target: WCAG 2.2 AA. Landmarks, heading discipline, a visible focus ring on every control,
44 px targets, reduced-motion handling and core's own focus-trapped overlay/lightbox are
decided in the parent; palette contrast and alt text are decided in the child. Known
limitations and the QA checklist: [docs/ACCESSIBILITY.md](docs/ACCESSIBILITY.md).

## Performance

Measured on the clean install (1.0.0): the parent adds one stylesheet (`base.css`, 5.5 KB,
~2 KB gzip), per-block CSS inlined only for blocks on the page (all block files together
21 KB, ~7 KB gzip), and **no JavaScript** unless a block opts into `reveal` (1.9 KB) or
`rotator` (3.0 KB). No external fonts, no icon font, no third-party requests. Numbers and
method: [docs/CSS.md](docs/CSS.md), [docs/JAVASCRIPT.md](docs/JAVASCRIPT.md), `tools/payload.sh`.
