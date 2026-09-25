# The Oogle WordPress platform — what goes where

This page is for whoever builds the next site: a developer, a Claude session or a Codex
session. It answers "where does this belong?" for every piece of a site built on Oogle
Theme. Theme-level detail: [CHILD-THEMES.md](CHILD-THEMES.md) (building a child) and
[EXTENSION-API.md](EXTENSION-API.md) (what a child may depend on).

## Layers

```
WordPress core (blocks, theme.json, Site Editor, upgrader)
├── wp-content/themes/oogle-theme/     parent — presentation framework, updated from GitHub
│   └── wp-content/themes/<site>/      child — the site's identity; deployed, never updated remotely
├── wp-content/plugins/oogle-core/     reusable functionality and structured data (modules) — see "Oogle Core" below
├── wp-content/plugins/oogle-manager/  optional control plane: installs/updates Oogle products from GitHub
├── wp-content/mu-plugins/<site>…      functionality only this one site needs
├── Rank Math Pro                      SEO: titles, descriptions, canonicals, robots, OG/X, sitemaps, schema, redirects
├── Gravity Forms (+ SMTP2GO)          forms: building, validation, entries, notifications; mail delivery
└── database / uploads                 content: pages, menus, media, Site Editor edits, settings
```

| Layer | Owns | Must not own |
|---|---|---|
| **Oogle Theme** | Layout and container system, theme.json foundation and token slugs, typography/spacing infrastructure, templates and parts, reusable patterns, block styles, per-block CSS, accessibility baseline, performance defaults (LCP/defer, per-block assets, WebP), minimal progressive-enhancement JS, the GitHub updater for itself | Any client's data, copy, colours or fonts; post types, taxonomies, meta; SEO output or schema; form processing; analytics/CRM; anything that must survive a theme switch |
| **Child theme** | Token values, fonts, header/footer/overlay parts, site patterns, templates whose structure differs, a small amount of site CSS, editor tweaks for that site | Reusable functionality, content models, integrations (a theme switch would drop them) |
| **Oogle Core** (plugin) | Reusable, theme-independent functionality and structured data, as optional modules: content models shared by several sites (e.g. accommodation), integration glue for Rank Math/Gravity Forms where the content model needs it, shared utilities | Presentation tied to a theme (`get_stylesheet_directory*` assets, theme CSS classes); business logic of one site; a second SEO or forms system; anything a PMS owns |
| **Site mu-plugin** | Functionality specific to one site (a one-off post type, redirects, a site integration) | Presentation; anything a second site needs (promote to Core) |
| **WordPress content** | Everything an editor should manage: pages, copy, images, menus, reusable sections (synced patterns), room descriptions | Structure and styling decisions that belong to templates and tokens |
| **Rank Math Pro** | Titles, descriptions, canonicals, robots, Open Graph / X, XML sitemaps, ordinary schema (Organization, LocalBusiness/LodgingBusiness, WebPage, Article, BreadcrumbList, FAQ), redirects, Local SEO | — |
| **Gravity Forms / SMTP2GO** | Form building, validation, conditional logic, entries, notifications, anti-spam add-ons; SMTP2GO delivers the mail | — |

## Decision guide

**Put it in Oogle Theme** when it is reusable presentation or framework behaviour that every
site benefits from unchanged: a block style, a pattern, a token, a template improvement, an
accessibility or performance fix. Promote from a child only when a second, unrelated site
needs it unchanged. Adding is a minor release; changing a public name is a major one.

**Put it in Oogle Core** when it is functionality or structured data that must survive a
theme change and is useful to more than one site: a content model (rooms), a validated
settings record, integration glue that Rank Math or Gravity Forms cannot provide itself.
Make it an optional module, off unless the site enables it.

**Put it in the child theme** when it is about how this one site looks: colours, fonts,
header, footer, hero composition, photography treatment, a room-page layout for this site.

**Put it in the site mu-plugin** when it is functionality only this site needs and nobody
else will: a one-off redirect map, a site-specific integration.

**Put it in WordPress content** when an editor should be able to change it without a
deploy: copy, images, menus, FAQs, room descriptions, opening hours text.

**Put it in Rank Math** when it is ordinary SEO metadata or schema Rank Math already
produces or can be configured to produce. Oogle code only *adds to Rank Math's graph* for
data Rank Math cannot model (see below) — it never prints a second graph.

**Put it in Gravity Forms** when it is form creation, validation, submission handling,
notifications or entry storage. Oogle code may style forms (the theme maps tokens onto GF's
variables) or add small, specific behaviours via GF hooks; it never re-implements forms.

When two answers seem to fit, prefer the one that survives a redesign: data outlives
themes, content outlives code.

## Rank Math boundary

- The theme prints **no** meta tags, canonicals, robots directives or JSON-LD (verified: no
  `ld+json`, `og:`, `wp_robots` or Rank Math hooks in the parent). Its Breadcrumbs part uses
  core's Breadcrumbs block, which is visual only; BreadcrumbList schema is Rank Math's.
- Structured data that Rank Math cannot derive (for example `HotelRoom` with bed and
  occupancy details from room meta) is added by the Core module that owns the data, **inside
  Rank Math's graph** via the `rank_math/json_ld` filter, only on the pages it describes, and
  only when the same `@type` is not already present for that page. Never a standalone
  `<script type="application/ld+json">`.
- Rank Math Pro's Local SEO module owns the property/business entity (name, address, phone,
  geo, opening hours, `LodgingBusiness`/`Hotel` type). Core does not duplicate it.

## Gravity Forms boundary

- The theme only styles forms: `assets/css/integrations/gravity-forms.css` maps tokens onto
  GF's CSS variables and loads only when a form renders.
- Allowed Core additions, as optional modules if a second site needs them: configuration
  validation (a form's notification has a verified sender; SMTP2GO is the active mailer),
  delivery-health visibility, logging of notification failures. Not allowed: a form builder,
  a submission pipeline, an entry store, a mail transport.

## Oogle Core — current state (audited 25 September 2026)

Three different things currently answer to "Core". Keep them apart:

1. **oogle.net's site plugin**, `Plugin Name: Oogle Core`, 0.4.0, basename
   `oogle-core/oogle-core.php` (source in the oogle.net workspace, not under git; an older
   copy sits beside this repository). It is **oogle.net's business logic**, not a reusable
   layer: the offer and price model, ~70 server-rendered `oogle/*` blocks keyed to the
   oogle.net GeneratePress child's CSS (seven `get_stylesheet_directory*()` calls load art
   and images from that child), oogle.net navigation data, the work/case-study post type,
   CSP/cache headers, routing and redirects for oogle.net, Stripe hooks. oogle.net does not
   run Oogle Theme; it runs GeneratePress with its own child. Reusable ideas inside it
   (publish-only-live-links navigation, the Rank Math analyser helper for dynamic blocks,
   hash-based CSP, the external booking-URL setting) are candidates for Core modules later,
   rewritten without the oogle.net coupling. Two schema observations for when oogle.net
   moves: its FAQ block prints a standalone FAQPage JSON-LD outside Rank Math's graph
   (duplicate risk if Rank Math's FAQ schema is also used), and its BreadcrumbList relies on
   Rank Math's breadcrumbs setting staying off.
2. **Oogle Manager** (`github.com/neumanc/oogle-manager`, formerly the `oogle-core`
   repository): an admin control plane that installs and updates Oogle products from GitHub
   Releases. It is not a functionality layer and has no presentation. It also answers
   `update_themes_github.com`; from theme 1.1.0 (unreleased) the theme keeps the final say on its own
   offer (UPGRADE.md), so Manager can no longer offer a release the theme declined.
3. **A reusable Oogle Core functionality plugin does not exist yet.** Nothing today
   depends on one: neither codebase above uses any Oogle Theme function, constant, class
   or filter, and the theme depends on neither.

**Decision needed before a reusable Core ships:** the basename `oogle-core/oogle-core.php`
is taken by oogle.net's site plugin. Either rename oogle.net's plugin (e.g. `oogle-net`)
when oogle.net is next migrated, or give the reusable plugin a different directory. Two
plugins with the same basename cannot coexist on one site and would confuse any updater.

No code was moved between layers in this pass: nothing in the theme belongs in Core, and
moving oogle.net code into a reusable Core now would carry its coupling along.

## Accommodation — the proposed Core module (not built)

The next two client sites, both small inns (8 and 10 rooms), need rooms as structured
content. That is two unrelated consumers of the same model, so it belongs in
**Core, as an optional module** (`accommodation`), not in either child and not in the theme.
It is deliberately not built in this pass: the field list below should be checked against
both inns' real room data first, and the reusable plugin's name is undecided (above).

**Boundary.** Guestroom Genie (or another PMS/booking engine) owns inventory, availability,
rates, reservations, payments and channel management. The module never stores or displays
any of those; it describes rooms and hands the guest to the booking engine.

**Data model.**

| Concept | Storage | Fields |
|---|---|---|
| Property | one settings record per site (a `property` post only if a real multi-property site appears) | check-in / check-out times, breakfast (included? description), policies (children, pets, smoking, cancellation text as content), property amenities (terms), booking-engine base URL and property ID |
| Room / suite | post type `oogle_room` (public, REST, block editor; slug e.g. `rooms`) | title, content (description), featured image + gallery (attachment IDs), `beds` (list of {type, count}, e.g. king ×1, twin ×2), `max_occupancy` (adults, children, total), optional size and floor/view, accessibility features, sort order, `booking_room_id` (the PMS's room/rate-plan identifier), optional per-room booking URL override |
| Amenity | taxonomy `oogle_amenity` shared by rooms and the property record | term name, optional icon slug |

Meta is registered with `register_post_meta()` (typed, `show_in_rest`, sanitised), so the
Site Editor, REST and MCP tools can read it.

**Presentation stays in the theme layer.** Core exposes the data through WordPress's
**Block Bindings API** (a `oogle/room` binding source for beds, occupancy, size and the
booking link), so templates bind core Paragraph/Button blocks to room data with no custom
front-end blocks. The parent may then ship a neutral `single-oogle_room.html` and a
room-card pattern (two sites need them); each child styles or overrides them.

**Booking integration.** A booking link builder with a filter per provider:
`oogle/accommodation/booking_url( $url, $room, $property )`. The Guestroom Genie adapter
only formats a deep link (property ID, optional room ID). No availability calls, no
pricing, no iframes injected by Core (a site may embed the provider's widget as content).

**SEO.** Rank Math Local SEO owns the property as `LodgingBusiness`/`Hotel`. The module
adds `HotelRoom` nodes (bed, occupancy, amenityFeature) to Rank Math's graph on room pages
only, linked to Rank Math's business entity by `@id`, and skips a page whose graph
already contains a `HotelRoom`. Room pages go into Rank Math's sitemap as a normal public
post type.

**Out of scope, permanently:** inventory, availability, rate display from the PMS,
reservations, payments, guest accounts, channel management.
