# Installation

## Requirements

WordPress 7.0 or later (7.1 tested), PHP 8.1 or later. No Node, no Composer, no build.

## Install the parent

1. Copy `oogle-theme/` to `wp-content/themes/oogle-theme/` (or upload the release zip).
2. Do **not** activate it directly on a client site. Activate a child theme.
3. Set permalinks to "Post name".

## Create a child theme

See [CHILD-THEMES.md](CHILD-THEMES.md). Minimum: `style.css` with `Template: oogle-theme`,
a `theme.json` that declares the **complete** palette/font/size sets, and (optionally) your
own `parts/header.html` and `parts/footer.html`.

## Running the local lab (no Docker)

The theme was validated with **WordPress Playground CLI** (PHP-in-WASM, SQLite). It needs
only Node:

```sh
npx @wp-playground/cli@latest server --port=9400 --php=8.3 --wp=latest \
  --mount="$PWD/oogle-theme:/wordpress/wp-content/themes/oogle-theme" \
  --mount="$PWD/precisionkb/wp-content/themes/precisionkb:/wordpress/wp-content/themes/precisionkb" \
  --mount="$PWD/precisionkb/wp-content/mu-plugins:/wordpress/wp-content/mu-plugins" \
  --blueprint=blueprint.json
```

A blueprint can activate the theme, set options and run `wp-cli` steps (including
`wp eval-file` to create fixtures). The Playground database is in memory: every restart is a
clean install, which is a feature for framework validation. Mounted directories are live —
edit a file, reload the page.

Limits worth knowing: no Imagick (GD only — WebP works, AVIF does not), no real HTTP cache,
and `--login` does not log the browser in; use REST with an application password or
cookie auth from a script.

## Deploying to a host

Deploy `oogle-theme/`, the child theme directory and `wp-content/mu-plugins/` as files
(rsync/SFTP/git). Never edit theme files on the server. `DISALLOW_FILE_EDIT` belongs in
`wp-config.php`.
