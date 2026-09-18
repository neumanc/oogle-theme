# Contributing

Oogle Theme is the parent theme for several unrelated client sites, so every change is a
change to all of them. Read [AGENTS.md](AGENTS.md) first — it holds the architecture rules
and the pre-pull-request checklist — then:

1. Open an issue or a short design note for anything larger than a bug fix. Say which
   client need motivates it and why a second, unrelated site would need the same thing.
2. Branch from `main`. One concern per pull request.
3. Follow the WordPress Coding Standards (`phpcs.xml.dist`); `phpcbf` fixes formatting.
   PHP 8.4 is the floor and the mandatory test version; no compatibility shims for older PHP.
4. Add a line to `CHANGELOG.md` under *Unreleased* (Keep a Changelog: Added / Changed /
   Fixed / Removed). Note any renamed or removed slug, style, pattern, part or filter as
   **breaking** with a search-and-replace recipe.
5. Test on a clean install and on at least one child theme before requesting review; say in
   the pull request exactly what you tested and how.
6. Do not bump the version or tag; releases are cut by a maintainer (docs/RELEASES.md).

By contributing you agree that your contribution is licensed under GPL-2.0-or-later.
