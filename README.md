# LightCMS

A lightweight CMS scaffold built on **CodeIgniter 4.7**, implementing the
core architecture described in `PRD.md` — SEO tooling, a JSON block-based
content editor, a swappable theme system, and an admin panel — plus the
"zero configuration" automation layer from `PRD_ADDENDUM.md`: auto SEO
fill-in, an image processing pipeline, sitemap/robots.txt automation,
auto-redirects, scheduled cleanup, and dashboard notifications. All in
plain PHP with vanilla-JS front ends (no build step required).

This is a **working scaffold**, not a feature-complete product. Every
piece listed under "What's implemented" below actually runs — it was
built and then verified end-to-end against a real MySQL database and a
live PHP server, not just written and left untested. "What's stubbed /
deviates from the PRD" is an honest list of the gaps.

## Quick start

```bash
composer install                       # if vendor/ isn't already present
cp env .env                            # then edit database.default.* and app.baseURL
php spark migrate                      # creates all 19 tables
php spark db:seed InitialSeeder        # role/user/theme/menus/settings + robots.txt + sitemap.xml
php spark serve                        # http://localhost:8080
```

For scheduled cleanup (PRD ADDENDUM §10), add a real cron entry — there is
no in-app scheduler:

```
0 3 * * * cd /path/to/lightcms && php spark lightcms:cleanup >> writable/logs/cleanup.log 2>&1
```

Default admin login (created by the seeder): **admin / ChangeMe123!**
— change it immediately in any real deployment, at **admin → Profile**
(`/admin/profile`).

Requires PHP 8.2+, MySQL 8/MariaDB 10.6+, and the `intl`, `mbstring`,
`json`, `mysqlnd`, `gd` or `imagick`, `xml`, `curl` extensions (per the PRD).

## Project layout

```
app/Controllers/Admin/     Dashboard, Post/Media/SEO/Theme/Settings CRUD, Auth, Profile
app/Controllers/Frontend/  HomeController, PostController (single/page/category/404)
app/Models/                One model per table in the PRD §4 schema + notifications/not_found_logs
app/Libraries/SEO/         Analyzer, MetaBuilder, SchemaGenerator, SitemapGenerator,
                            AutoSeoGenerator, RobotsTxtGenerator
app/Libraries/Theme/       Theme (static registry), ThemeEngine, TemplateLoader, AssetManager
app/Libraries/Editor/      BlockParser, BlockRenderer
app/Libraries/Media/       ImageProcessor (resize/WebP/thumbnails/auto-alt-text)
app/Libraries/Cache/       CacheManager (tag-based invalidation), QueryCache
app/Commands/               CleanupCommand (`php spark lightcms:cleanup`)
app/Helpers/                seo_helper, theme_helper, content_helper
app/Database/Migrations/   19 tables, PRD §4 plus notifications/not_found_logs/media.variants
app/Database/Seeds/        InitialSeeder
app/Views/admin/           Admin UI (no framework); posts/form.php is the block editor screen
public/assets/admin/       admin-ui.css (built: Tailwind + daisyUI) + admin.js;
                            admin.css/block-editor.* stay for the block editor screen
resources/css/admin.css    Source for the admin stylesheet (npm run build:css)
tailwind.config.js         Tailwind 3 + daisyUI 4 config, custom lightcms themes
public/themes/default/     The one shipped native theme (layouts, templates, assets)
app/Libraries/WordPress/   WordPress compatibility layer (api/ = the WP functions,
                            Stubs/ = WP_Post/WP_Query/wpdb/..., Bridge/ = data mapping)
public/wp-content/         WordPress themes/ and plugins/ live here
```

`Config/LightCMS.php` holds the app-specific settings (theme path, cache
TTLs, SEO score thresholds, memory budget) that the PRD calls out as
tunable. `Config/Services.php` wires every library above into CI4's
service container (`Services::seoAnalyzer()`, `theme_engine()`, etc.).

## What's implemented and verified

- **DB schema**: all 16 tables from PRD §4, migrated and seeded successfully.
- **Auth**: session-based admin login, `AdminAuthFilter` gating `/admin/*`,
  bcrypt password hashing, activity logging.
- **Posts/Pages CRUD**: create, edit, trash/restore, delete, publish
  scheduling, categories/tags, featured image — confirmed via a full
  create → edit → frontend-render round trip.
- **Block editor**: a Gutenberg-style visual editor (`public/assets/admin/js/block-editor.js`,
  ~99 KB unminified, zero dependencies) on top of the JSON-blocks-in/HTML-out
  contract of `BlockParser`/`BlockRenderer` — 18 block types (paragraph,
  heading, list, quote, code, image, gallery, video, audio, button, table,
  columns, separator, spacer, embed, custom HTML, accordion/FAQ, CTA). See
  "Block editor" below.
- **SEO analyzer**: real-time AJAX scoring in the post editor and a
  score persisted to `seo_meta.seo_score` on save; checks keyword-in-title,
  keyword-in-meta-description, density, content length, heading structure,
  image alt text, internal links, and Flesch readability.
- **SEO output**: meta title/description/canonical/robots, Open Graph,
  Twitter Card, and JSON-LD (Article, Breadcrumb, FAQ, Product,
  LocalBusiness) via `SchemaGenerator`.
- **Sitemap**: `POST /admin/seo/sitemap` writes `public/sitemap.xml`
  (or a sitemap index + chunks past 50k URLs).
- **Redirect manager**: exact + regex 301/302/307 redirects with hit
  counting, checked before falling through to a real 404.
- **Theme system**: `theme.json` + `functions.php` + layouts/templates
  hierarchy, one shipped `default` theme, admin theme list/activate.
- **Caching**: tag-based page/query cache (`CacheManager`, `QueryCache`)
  using CI4's file cache handler; guests get a cached homepage, logged-in
  admins always see fresh data.
- **Frontend**: home (paginated), single post/page, category archive,
  404 — all through the theme's own template hierarchy.

## Automation (PRD ADDENDUM) — implemented and verified

All of the following were exercised end-to-end (real save/upload/request,
not just written): create a post, watch its `seo_meta` row and
`notifications` table populate; rename its slug, watch a redirect appear
and the old URL 301 through; upload an oversized JPEG, watch it get
resized + a WebP copy + three thumbnail sizes on disk.

- **Auto SEO fill-in** (`AutoSeoGenerator`): a blank `meta_description` is
  generated from the post's own rendered text (sentence-aware, ~155 chars);
  `seo_meta.schema_data` is always populated — Article schema, plus an
  FAQPage merged in via `@graph` when the content has 2+ heading-then-
  paragraph pairs. A manually-typed meta description always wins.
- **Image pipeline** (`ImageProcessor`, used by `MediaController::upload()`):
  auto-resize/compress to `LightCMS::$imageMaxWidth`/`$imageQuality`, a
  WebP copy, thumbnail/medium/large crops, and alt text suggested from the
  filename (`sunset-over-bali.jpg` → "Sunset Over Bali") when the uploader
  leaves it blank. Only JPEG/PNG/GIF are processed; everything else
  (SVG, PDF, audio, video) is stored untouched.
- **robots.txt**: regenerated automatically on every settings save, and
  once by the seeder on install; a manual "Regenerate" button also exists.
- **Sitemap automation**: `PostModel` regenerates `sitemap.xml`
  automatically (via `afterInsert`/`afterUpdate`/`afterDelete` model
  callbacks) whenever a write could change what belongs in it — publish,
  unpublish, edit-while-published, trash, or delete.
- **Auto-redirect on slug change**: also a `PostModel` callback — renaming
  a published post's slug creates a 301 from the old slug automatically,
  so existing links and search results don't 404.
- **404 tracking + spike notification**: every unmatched frontend request
  (after the redirect table is checked) is logged to `not_found_logs`;
  the 404 page suggests similarly-named published slugs; a dashboard
  notification fires once per window if distinct misses spike.
- **Scheduled cleanup**: `php spark lightcms:cleanup` purges old trash/
  spam/activity-logs/404-logs and runs `OPTIMIZE TABLE` on everything —
  wire it to a real cron (see Quick start above); nothing in this repo
  schedules it for you.
- **Dashboard notifications**: a `notifications` table + panel, with a
  "mark all read" action. Currently fired for low-SEO-score-on-publish
  and 404 spikes, deduplicated so the same notice doesn't repeat within
  its window.

**Deliberately not built**: the first-run setup wizard (site-type
detection, GSC/Bing verification meta tags) and search-engine ping-on-
publish — both were explicitly descoped when this automation pass was
requested. Pinging Google/Bing on every publish is a real outward-facing
call to a third party; add it behind an explicit, default-off setting if
you want it, rather than always-on.

## Block editor (PRD §3.2) — implemented and verified

The post/page edit screen (`app/Views/admin/posts/form.php`) is a
full-screen, Gutenberg-inspired editor written from scratch in vanilla
JS — nothing is copied from WordPress, so there's no GPL entanglement and
the whole admin JS bundle stays ~100 KB (PRD budget: 150 KB). It talks to
the exact same `PostController` endpoints the earlier JSON textarea did;
the hidden `<input name="content">` is kept in sync with `[{type, attrs,
content}]` on every change, so the server side didn't need to know an
editor exists.

What it does:

- **Canvas**: block list with hover/selected outlines, a floating block
  toolbar (move up/down, drag handle, type-specific controls, inline
  formatting, transform-to menu, more-menu), in-between "+" inserters and
  a default appender, nested canvases inside Columns.
- **Inserter**: top-bar "+" and gap buttons open a searchable, categorised
  block picker; typing `/` in an empty paragraph opens it as a slash menu
  filtered live by what you type.
- **Writing**: contenteditable rich text with bold/italic/link/strike/
  inline-code (⌘B/⌘I/⌘K); Enter splits into a new paragraph, ⇧Enter
  inserts a line break, Backspace at the start merges into the previous
  text block, ↑/↓ at block edges move between blocks; multi-paragraph
  paste splits into paragraphs. Output is sanitized to a small inline
  allow-list (`strong/em/u/s/a/code/mark/sub/sup/br`) with `javascript:`
  hrefs dropped.
- **Undo/redo** (⌘Z / ⇧⌘Z), duplicate (⇧⌘D), remove (⌥⇧Z), ⌘S to save.
- **Sidebar**: *Post* tab (status, publish date, permalink preview,
  comments, featured image with media picker, excerpt, categories, tags,
  SEO panel with live score) and *Block* tab (per-type inspector fields).
- **Media picker**: Upload (drag & drop → `admin/media/upload`, so the
  image pipeline runs), Media Library (`admin/media/list` JSON), Insert
  from URL — used by image/gallery/video/audio blocks and the featured /
  social-share image fields.
- **Linked images (backlinks)**: the image block's toolbar has a link
  button (and the Block sidebar a Link section) — URL, "open in a new
  tab", `rel` (with a one-click `nofollow`), plus "link to the image
  file". A bare `example.com` is upgraded to `https://`; anything that
  isn't http(s)/mailto:/tel:/site-relative is refused. `noopener
  noreferrer` is added automatically to new-tab links, and the rendered
  `href`/`rel` are plain text rather than entity-escaped, so crawlers and
  regex-based SEO tools read them correctly. The renderer re-validates
  independently of the editor, so a `javascript:` URL injected through an
  import or the API still can't reach the page.
- **Code editor mode** (raw JSON with validation on switching back),
  desktop/tablet/mobile canvas widths, distraction-free mode, word count
  + reading time, autosave to `localStorage` every 30 s with a
  "restore unsaved draft?" bar, and a `beforeunload` guard.
- **SEO score** re-runs automatically ~1.5 s after content/title/keyword
  edits (plus an "Analyze now" button), driving both the top-bar and
  sidebar badges.

Verified in a real headless Chrome via a raw DevTools-protocol script
(`node` only, no test framework): login → open a saved post → select a
block → insert a heading from the inserter → type → Enter → `/quote` →
⌘Z → code-mode round trip → media picker → link an image → SEO analyze →
Update → reload → frontend markup — **33/33 checks, zero JS exceptions**,
alongside 18 PHPUnit tests covering the renderer's link/escaping rules
(`tests/unit/BlockRendererTest.php`, `vendor/bin/phpunit`).

Those runs caught three real bugs before you would have: init crashed on
*published* posts (the "Save draft" button is intentionally absent
there); empty `attrs` round-tripped from PHP as `[]` rather than `{}`, so
newly-set attributes vanished on save; and a post whose stored `content`
wasn't valid JSON opened as a blank canvas, meaning one save would have
destroyed it — it now opens in code mode on the raw value, with a warning
and saving blocked until it parses.

Known limits: Embed blocks store the URL only (no oEmbed preview fetch);
inline links use a `prompt()` rather than a popover; there's no list-view
outline or multi-block selection; drag-and-drop works between any two
lists (including into/out of columns) but there's no keyboard reorder
beyond the move buttons.

## Admin UI (Tailwind CSS 3 + daisyUI 4)

Every admin screen — login, dashboard, posts/pages, media, SEO & redirects,
themes, plugins, settings, profile and the WordPress plugin screens — is
built with daisyUI components on Tailwind 3 (the same pairing the JHG
WordPress themes use; daisyUI 5 needs Tailwind 4).

**No build step is needed to run LightCMS.** The compiled stylesheet
`public/assets/admin/css/admin-ui.css` is committed. You only need npm when
you change an admin view or the CSS source:

```bash
npm install          # once
npm run build:css    # rebuild after editing app/Views/admin/** or resources/css/admin.css
npm run dev:css      # or watch while working
```

- **Source**: `resources/css/admin.css`, config in `tailwind.config.js`.
- **Themes**: two custom daisyUI themes, `lightcms` (light, keeps the
  original #3498db/#f4f6f8 palette) and `lightcmsdark`. The switch in the
  top bar writes the choice to `localStorage`, and an inline script in
  `_header.php` applies it before first paint so there is no flash of the
  wrong theme.
- **Navigation** is declared once as `$navItems` in
  `app/Views/admin/_header.php` and rendered twice — sidebar on desktop, a
  dropdown on mobile.
- **WordPress plugin screens** echo wp-admin markup we do not control
  (`.wrap`, `.form-table`, `.button`, `.notice`). Tailwind's preflight would
  leave that unstyled, so `resources/css/admin.css` maps those classes onto
  daisyUI components under `.lcms-wp-screen` — a plugin settings page ends up
  looking like the rest of the admin without touching the plugin.
- **The block editor is deliberately excluded.** `app/Views/admin/posts/form.php`
  keeps its own hand-written `admin.css` + `block-editor.css`; 1.8k lines of
  editor JS bind to those class names and Tailwind's preflight would fight its
  layout. It is a separate full-screen surface, like Gutenberg next to wp-admin.

Weight: `admin-ui.css` is ~115 KB minified (~15 KB gzipped) and replaces the
old hand-written stylesheet on every screen except the editor.

## Account & profile (`/admin/profile`)

The signed-in user's own account screen, in two forms:

- **Profile** — display name, username, email and avatar. Changing the
  *username or email* requires the current password, so a stolen session
  cannot quietly move the recovery address; changing only the display name
  or avatar does not. Uniqueness is enforced against other accounts but not
  against your own row.
- **Change password** — current password, new password, confirmation.
  The new password must be 8–72 characters (bcrypt ignores anything past 72
  bytes, so a longer one would be weaker than it looks), must differ from
  the current one, and must not be the username or the email. On success the
  hash is replaced and the session id is regenerated, so a session captured
  earlier stops being useful.

Both actions are written to `activity_logs` (`update_profile`,
`change_password`). The password hash is never selected into the view, and
the header's user chip links here. Covered by
`tests/database/ProfileTest.php` (16 request-level tests: the auth gate, the
current-password gate, uniqueness, every rejected password rule, and that
the old password stops authenticating afterwards).

## WordPress compatibility — themes and plugins

LightCMS can run **classic WordPress themes and plugins** directly, without
bundling WordPress. `app/Libraries/WordPress/` re-implements the WordPress
runtime API (hooks, the loop, template hierarchy, options, meta, enqueue,
menus, sidebars, shortcodes, Settings API, AJAX, REST registration, cron)
on top of the LightCMS tables. No WordPress code is copied, so this
codebase stays GPL-free; the themes and plugins you install keep their own
licence.

### Using it

```bash
# 1. drop a classic theme in, then check it before switching
cp -R ~/my-theme public/wp-content/themes/my-theme
php spark wp:doctor theme my-theme          # lists calls the layer does not implement
php spark wp:doctor path /any/other/dir     # audit a theme/plugin before installing it

# 2. activate from the admin
#    Themes  -> "WordPress themes" -> Activate
#    Plugins -> Activate   (plugins live in public/wp-content/plugins/)
```

A working example of each ships in the repo and doubles as the test
fixture: `public/wp-content/themes/lightcms-classic` (template hierarchy,
loop, menus, widgets, enqueue, Customizer) and
`public/wp-content/plugins/lightcms-hello` (settings page, shortcode,
`the_content` filter, AJAX action, REST route). Both are written exactly
as they would be for WordPress.

### What works

- **Themes**: the classic template hierarchy (`front-page`, `home`,
  `single-{type}-{slug}` … `index.php`), child themes (stylesheet wins over
  template, child `functions.php` loads first), `get_header/footer/sidebar`,
  `get_template_part`, the loop with `have_posts()/the_post()`, every
  common template tag, conditional tags, `body_class`/`post_class`,
  `wp_head`/`wp_footer` with a real dependency-ordered asset queue,
  `wp_nav_menu` (custom `Walker_Nav_Menu` subclasses included), sidebars and
  `WP_Widget`, `.mo` translations.
- **Plugins**: activation/deactivation with activation hooks, the full
  action/filter API, shortcodes, options and transients, post/user/term
  meta, `add_menu_page()` screens rendered inside the LightCMS admin at
  `/admin/wp/<slug>`, the Settings API (`register_setting` →
  `/admin/wp/options`), `admin-ajax.php`, `admin-post.php`,
  `register_rest_route()` under `/wp-json/`, and WP-Cron events (run by
  `php spark lightcms:cleanup`, not by a loopback request).
- **`$wpdb`** reads through WP-shaped SQL **views** over the LightCMS tables
  (`wp_posts`, `wp_users`, `wp_terms`, `wp_term_taxonomy`,
  `wp_term_relationships`, `wp_comments`), so plugins that run raw SQL see
  real content. The views are **read-only**: writes must go through the API
  (`wp_insert_post`, `update_post_meta`, …), which routes to the LightCMS
  models so sitemaps, redirects and cache invalidation keep working.
  `wp_options` and the three `*meta` tables are real, writable tables.

### Data mapping

| WordPress            | LightCMS                                                      |
|----------------------|---------------------------------------------------------------|
| `WP_Post`            | `posts` row; block JSON is rendered to HTML for `post_content`, the raw JSON stays in `post_content_filtered` |
| `post_status`        | `publish/draft/future/trash` ⇄ `published/draft/scheduled/trash` |
| featured image       | `posts.featured_image` (plus `media` for size variants)        |
| `category`/`post_tag`| `categories` / `tags`; tag term ids are offset by 1,000,000 so one term space stays unambiguous |
| `get_option('blogname')` etc. | the `settings` table — one value, both CMSes           |
| other options        | `wp_options`                                                  |
| post/user/term meta  | `wp_postmeta` / `wp_usermeta` / `wp_termmeta` (new tables)      |
| `wp_nav_menu`        | `menus` / `menu_items`, matched by theme location              |
| capabilities         | LightCMS roles, mapped in `Config\WordPress::$capabilityMap`   |

### What is **not** supported

- **Block (FSE) themes** — `theme.json`, block templates, global styles,
  and Gutenberg blocks are not rendered. Classic PHP themes only.
- **The wp-admin UI itself** — LightCMS keeps its own admin. Plugin screens
  are rendered inside it; the post-editing, media-modal, widget and
  Customizer-preview screens of WordPress are not reproduced (the
  Customizer is a plain settings form at `/admin/wp/customize`).
- **Ecosystem plugins that are themselves platforms** — WooCommerce, ACF,
  Elementor and friends assume far more of core than this layer provides.
  `wp:doctor` flags a theme that depends on them.
- **Core `wp/v2` REST endpoints**, XML-RPC, multisite, the plugin/theme
  installer, and rewrite rules (`add_rewrite_rule()` logs and no-ops —
  add the route in `app/Config/Routes.php`).
- Custom **taxonomies** register (so `taxonomy_exists()` is honest) but have
  no term storage; custom **post types** register and are queryable via
  `posts.post_type`, with no admin UI of their own.

### Security notes

- `/wp-admin/*`, `/wp-json/*` and `/admin/wp/options` are exempt from
  CodeIgniter's CSRF filter because they authenticate the WordPress way,
  with `wp_verify_nonce()`. Everything else in `/admin` still uses the CI
  token.
- Third-party code runs in-process, with your database credentials. Treat
  activating a plugin exactly as you would on WordPress: read it first.
  `wp:doctor` tells you what it calls; it does not tell you what it does.
- A plugin that fatals on load is deactivated automatically and reported as
  an admin notice rather than white-screening the site.
- `Config\WordPress` can disable the layer entirely (`$enabled`), block
  outbound HTTP from theme/plugin code (`$allowRemoteRequests`), and set a
  timeout on every `wp_remote_*` call.

### Extending it

Everything is plain functions grouped by area in
`app/Libraries/WordPress/api/`. When `wp:doctor` reports a missing
function, add it there (`19-extras.php` is the catch-all), re-run the
command, and the report should come back clean. Tests live in
`tests/unit/WordPressCompatTest.php` (no database) and
`tests/database/WordPressContentTest.php` (needs a MySQL `tests`
connection — the LightCMS schema is MySQL-specific, so those tests skip
themselves on the default SQLite test connection).

## Deviates from the PRD (and why)

- **REST API (PRD §5) is not built.** The admin/frontend controllers
  cover the same functionality server-rendered; the `api/*` route prefix
  is reserved (CSRF is already exempted for it in `Config/Filters.php`)
  for whenever those endpoints are added.
- **RBAC is data-only.** `roles.permissions` is seeded with reasonable
  defaults per PRD §3.5.A, but no controller currently checks them beyond
  "is someone logged in" — every logged-in user can reach every screen.
  Add per-action permission checks in the Admin controllers before
  exposing this to non-trusted users.
- **2FA, GA4/Search Console integration, keyword rank tracking, and the
  one-click web installer (PRD §10.3)** are not implemented — they're
  external-integration or first-run-wizard features layered on top of
  what's here, not core architecture.
- **Comments have no public submission form** — the `comments` table,
  model, and single-post display of approved comments exist; posting a
  new comment as a visitor is not wired up.
- **Internal-linking suggestions (PRD ADDENDUM §5) are not built** —
  the keyword-extraction/link-suggestion loop needs its own review UI
  (a `link_suggestions` table and an admin screen to accept/reject), which
  didn't fit this pass; everything else from the addendum did.
- **Performance monitoring/alerting (PRD ADDENDUM §11) is not built** —
  logging every request's load time/query count/memory and emailing on
  threshold breaches needs a decision on where that log lives at scale
  (it grows unbounded fast); left out rather than shipped half-right.

## A note on how this was built

Every controller, model, and library in here was exercised against a
real database and a running server, not just written — that process
caught (and fixed) several bugs that only show up at runtime:

- `SettingModel::set()` collided with CI4's own `Model::set()` (renamed
  to `setValue()`).
- Cache keys using `:` as a separator hit CI4's file-cache reserved-character
  check (switched to `.`).
- `protected $casts` needs the file's own PHP-side type hint (`array
  $casts`) to satisfy CI4's typed parent property.
- CI4's `is_unique[table.field,id,{id}]` placeholder only resolves when
  `id` is both present in the row being validated *and* has its own
  validation rule — `PostModel` now sets both up deliberately.
- JSON columns that are nullable in the DB need a `?json-array` cast
  (not `json-array`) or CI4 throws on any row where that column is `NULL`.

The automation pass added a few more of the same flavor:

- `IncomingRequest::getPath()` (route-relative, always correct) is not
  the same as `$request->getUri()->getPath()` (reflects the raw URI,
  which includes `index.php` when pretty-URL rewriting isn't active) —
  `AdminAuthFilter` used the wrong one and let the login-page exemption
  silently fail under `php spark serve`.
- `$seoMeta['key'] ?: $fallback` throws when `$seoMeta` itself is `null`
  (no row yet); `??` short-circuits that check safely, `?:` does not —
  `($seoMeta['key'] ?? '') ?: $fallback` is the pattern that's actually safe.
- A checkbox field absent from `$_POST` means "unchecked", not "leave the
  setting alone" — a naive `if (getPost($key) !== null)` loop over both
  text and checkbox settings can never turn a checkbox back off once set.
- CI4 regenerates the CSRF token on **every** request by default, so a
  page that fires several AJAX POSTs (SEO analyze, media upload) and then
  submits a form gets a 403 on every call after the first. The block
  editor needed `Config\Security::$regenerate = false` (one token per
  session — CI4's own documented setting for AJAX-heavy screens).
- PHP's `json_encode([])` is `[]`, not `{}` — a block whose `attrs` were
  empty round-trips back into JS as an *array*, and any attributes later
  `Object.assign()`'d onto it silently vanish in `JSON.stringify`. The
  editor normalizes `attrs` to an object on load.
- CI4's `esc($v, 'attr')` is for *unquoted* attributes: it turns every
  space and slash into an entity, so `rel="nofollow noopener"` ships as
  `rel="nofollow&#x20;noopener"`. Browsers decode it, but crawlers and
  regex-based SEO tools reading `href`/`rel` may not. Everything the block
  renderer emits is double-quoted, so plain `esc($v)` is both safe and
  legible — worth caring about precisely because these are backlinks.

If you extend this scaffold, running `php spark serve` and clicking
through the actual flow beats trusting `php -l` alone — several of the
above only fail when a real request hits them.
