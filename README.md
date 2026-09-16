# Oogle

Oogle's reusable WordPress **block theme** foundation. Neutral by design: it ships a stable
token contract, thin templates, a small pattern set and per-block CSS — and every client
site is a **child theme** that supplies its own values, fonts, header/footer content and
site-specific patterns. No page builder, no build step, no jQuery, no framework CSS.

- **Requires:** WordPress 7.0+, PHP 8.1+. Developed and tested on WordPress 7.1 / PHP 8.3.
- **Version:** 0.1.0 (pre-1.0 — see [docs/RELEASES.md](docs/RELEASES.md) for what "1.0" means).
- **License:** GPL-2.0-or-later.

## Read in this order

| Doc | What it answers |
|---|---|
| [docs/PHILOSOPHY.md](docs/PHILOSOPHY.md) | Why the theme is built this way, and what we refuse to add |
| [docs/INSTALLATION.md](docs/INSTALLATION.md) | Installing the parent, creating a child, running the local lab |
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | What lives where: files, templates, parts, patterns, PHP modules |
| [docs/TOKENS.md](docs/TOKENS.md) | The `theme.json` slug contract — the parent/child API |
| [docs/CHILD-THEMES.md](docs/CHILD-THEMES.md) | How a client site customizes without forking |
| [docs/PATTERNS.md](docs/PATTERNS.md) | Pattern rules, the initial set, how to add one |
| [docs/CSS.md](docs/CSS.md) | CSS conventions, specificity rules, what is banned |
| [docs/JAVASCRIPT.md](docs/JAVASCRIPT.md) | When JS is allowed and how it is loaded |
| [docs/ACCESSIBILITY.md](docs/ACCESSIBILITY.md) | WCAG 2.2 AA expectations and the QA checklist |
| [docs/RELEASES.md](docs/RELEASES.md) | Versioning, what counts as breaking, release steps, update distribution |
| [docs/WORDPRESS-NOTES.md](docs/WORDPRESS-NOTES.md) | Verified behaviour of WordPress 7.1 that shaped decisions |
| [CHANGELOG.md](CHANGELOG.md) | What changed, per version |

## At a glance

```
oogle-theme/
├── style.css          theme header only
├── theme.json         the token contract (neutral values)
├── functions.php      loads inc/*.php
├── inc/               setup · assets · block-styles · editor · images · cleanup · a11y
├── templates/         index · page · page-no-title · page-narrow · blank · single · archive · search · 404
├── parts/             header · footer · breadcrumbs · post-meta
├── patterns/          hero-cover · section-split · cards-grid · cta-panel · testimonials · query-cards
├── styles/            section-tint · section-reversed · section-primary (section styles)
├── assets/css/        base.css · editor.css · blocks/*.css · integrations/gravity-forms.css
├── assets/img/        placeholder.svg
├── tools/             payload.sh (dev only)
└── docs/
```

Theme JavaScript at 0.1.0: **none**. Core's Interactivity API modules (navigation overlay,
accordion, image lightbox) load themselves only on pages that use those blocks.
