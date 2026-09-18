# Philosophy

Oogle is an **agency foundation**, not a page builder. It exists so that every Oogle client
site starts from the same small, standards-based base and stays maintainable for a decade.

## What we optimise for

1. **Boring, stable technology.** `theme.json`, core blocks, HTML templates, plain CSS, plain
   ES modules. If WordPress or the browser provides something, we use it rather than wrap it.
2. **The editor approximates the front end.** A block theme is the only theme type where the
   same tokens and the same CSS drive both. Clients should not have to imagine the result.
3. **Small payloads by default.** The parent adds ~2 KB (gzip) of global CSS, per-block CSS
   only for blocks on the page, and no JavaScript unless a block opts into one of two tiny
   modules. It should be hard to accidentally build a slow site.
4. **Accessibility as architecture.** Landmarks, headings, focus, targets and motion are
   decided once in the theme, not patched per page.
5. **Reuse through a contract, not through copying.** A child theme changes token *values*;
   the parent owns the *slugs*, block styles, patterns and templates. Renaming a slug is a
   breaking change.

## What we refuse to add

- Page builders and builder-generated markup (GeneratePress/GenerateBlocks, Bricks, Elementor, Divi).
- Utility CSS frameworks (Tailwind, Bootstrap) or a home-grown utility layer.
- jQuery for anything Oogle writes. React on the front end.
- Custom blocks that reproduce what a core block plus a block style can do.
- A build toolchain in the theme. If a custom block ever becomes unavoidable it gets its own
  scoped build; the theme stays plain files.
- Speculative abstractions: hooks nobody calls, options nobody sets, "helpers" with one caller.
- Anything client-specific: colours, copy, business data, post types, schema, tracking.

## How to decide

When choosing between a clever abstraction and a boring standard, pick the standard unless
the abstraction clearly removes real, recurring work. When a pattern needs a look no block
setting provides, add a **block style** (a stable public name), not a one-off class. When a
client needs something the parent cannot express, put it in the child first; promote it to
the parent only when a second, unrelated site needs the same thing.
