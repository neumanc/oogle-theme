# Installation

## Requirements

WordPress 7.0 or later (tested on 7.0.4 and 7.1), PHP 8.4 or later. No Node, no Composer, no build.

## Install the parent

1. Copy `oogle-theme/` to `wp-content/themes/oogle-theme/` (or upload the release zip).
2. Do **not** activate it directly on a client site. Activate a child theme.
3. Set permalinks to "Post name".

## Create a child theme

See [CHILD-THEMES.md](CHILD-THEMES.md). Minimum: `style.css` with `Template: oogle-theme`,
a `theme.json` that declares the **complete** palette/font/size sets, and (optionally) your
own `parts/header.html` and `parts/footer.html`.

## Running a local lab

**Docker** (used for the 1.0.0 release gate): the official `wordpress` and `mariadb`
images plus `wordpress:cli`. Mount the theme read-only and copy the production file set in
(so the lab sees exactly what a release zip contains), install with `wp core install`,
activate, seed content with `wp eval-file`. Pick the WordPress/PHP combination with the
image tag (`wordpress:7.1-php8.4`, `wordpress:php8.4`, …).

**WordPress Playground CLI** (PHP-in-WASM, SQLite; needs only Node) is quicker for a look:

```sh
npx @wp-playground/cli@latest server --port=9400 --php=8.4 --wp=latest \
  --mount="$PWD/oogle-theme:/wordpress/wp-content/themes/oogle-theme" \
  --mount="$PWD/<site>/wp-content/themes/<site>:/wordpress/wp-content/themes/<site>" \
  --mount="$PWD/<site>/wp-content/mu-plugins:/wordpress/wp-content/mu-plugins" \
  --blueprint=blueprint.json
```

Playground limits: no Imagick (GD only — WebP works, AVIF does not), no real HTTP cache,
and `--login` does not log the browser in; use REST with an application password or
cookie auth from a script.

## Deploying to a host

First install: upload the release `oogle-theme.zip` (Appearance → Themes → Add New →
Upload) or deploy the directory as files (rsync/SFTP/git); the child theme directory and
`wp-content/mu-plugins/` go as files. Later versions arrive through the built-in updater
([../UPGRADE.md](../UPGRADE.md)). Never edit theme files on the server; `DISALLOW_FILE_EDIT`
belongs in `wp-config.php`. The directory name must stay `oogle-theme`.
