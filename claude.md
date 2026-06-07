# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

WordPress plugin that syncs Rechat data (agents, offices, regions, listings), renders listing/search/lead experiences via Rechat Web Components (`<rechat-root>`, `<rechat-listings>`, `<rechat-map>`, etc.), and optionally provisions multisite agent/office subsites with Broadcast content sync.

## Commands

```bash
# Install JS dependencies
npm install

# Build Gutenberg blocks (src/ → build/index.js) — run after any src/blocks/*.js edit
npm run build:scripts

# Watch mode during block development
npm run start-build
```

No test suite exists (`npm test` exits with error). No PHP linter configured.

## Stack

| Layer | Technology |
| --- | --- |
| CMS | WordPress (single-site or Multisite) |
| Backend | PHP 7.4+ (procedural + small classes), WordPress APIs (CPT, cron, REST, AJAX) |
| Frontend (public) | Rechat SDK (`@rechat/sdk` via CDN), vanilla JS, Swiper 11 |
| Editor | Gutenberg blocks (`@wordpress/scripts`), React in `src/blocks/` |
| Data | WordPress DB (`wp_posts`, post meta, options); Rechat REST API (`api.rechat.com`) |
| Auth | OAuth2 (tokens in `wp_options`) |
| Multisite extras | ThreeWP Broadcast (network-active when subsites enabled) |
| Build | `npm run build:scripts` → `build/index.js` |
| Local dev | XAMPP / typical LAMP |
| Deploy | Standard WP plugin zip / Git; bump `RCH_VERSION` in `index.php` on release |

## Architecture

### Directories

- `index.php` → Plugin bootstrap: constants, rewrite rules, require chain. **Do not** put feature logic here.
- `includes/` → All PHP application code.
  - `admin/` → Settings UI, CPT registration, admin enqueue, CSV import.
  - `front/` → Front-end asset registration (`enqueue-front.php`).
  - `shortcodes/` → `[listings]`, `[rch_latest_listings]`, `[rch_leads_form]`, `[rch_search_listing_form]`.
  - `gutenberg-block/` → Block registration + `render_callback` PHP (pairs with `src/blocks/`).
  - `helper.php` → Shared helpers: Rechat attribute builders, listing filters HTML, block attribute schemas, status mappings. **Large file — search before adding duplicates.**
  - `load-agents-regions-offices/` → Rechat API sync for CPT data.
  - `load-listing/` → Listing archive/single fetch helpers.
  - `cron-job/` → 12-hour sync schedule.
  - `oauth2/` → OAuth connect/refresh.
  - `multisite/` → Agent/office subsites, Broadcast integration, deploy wizard, listing scope on subsites. Loaded always; many functions no-op when `! is_multisite()`.
  - `metabox/` → Agent/office/region/neighborhood admin meta boxes.
  - `schema/`, `seo/` → Structured data and auto meta tags.
  - `local-logic/` → LocalLogic neighborhood widgets (hero, map, schools, demographics, market-trends, etc.) and listing-page `LocalContent` widget.
  - `roles/` → Custom `rch_agent` WP user role registration.
- `src/` → Gutenberg block **source** (edit components, inspector controls). Five blocks: `listing-block`, `agents-block`, `offices-block`, `regions-block`, `leads-form-block`.
- `build/` → **Compiled** block bundle (`index.js`). Commit after build; do not hand-edit.
- `assets/` → Plugin CSS/JS/images (front + admin). Not block bundle.
- `templates/` → Default PHP templates for archives/singles/listing detail. Overridable from theme `rechat/` folder.

### Key files

| File | Role |
| --- | --- |
| `includes/shortcodes/listing-shortcodes.php` | `[listings]` renderer: filters, sort, map, grid shell |
| `includes/shortcodes/latest-listing-shortcode.php` | `[rch_latest_listings]` swiper/normal/grid |
| `includes/gutenberg-block/block-listing.php` | Listing block REST + render → `rch_render_listing_list()` |
| `includes/front/enqueue-front.php` | Registers/enqueues SDK, shortcode assets |
| `assets/js/rch-latest-listings-swiper.js` | Swiper init after `rechat-listings:fetched` |
| `assets/js/rch-latest-listings-empty.js` | Hide empty latest-listings sections |
| `includes/multisite/broadcast-integration.php` | Broadcast to new subsites; child-post lookup helpers |
| `includes/multisite/agent-site-deploy-wizard.php` | Network admin wizard (broadcast, menus, deploy) |
| `includes/multisite/agent-listing-scope.php` | Subsite `filter_brand_id` / agent scope for listings |

### Key helper.php functions

| Function | Purpose |
| --- | --- |
| `rch_get_listing_block_attributes()` | Canonical block attribute schema (shared by all 5 blocks) |
| `rch_get_listings_default_atts()` | Default shortcode attribute values |
| `rch_prepare_listing_atts_from_block()` | Maps block attrs → shortcode attrs (bool/string lists) |
| `rch_get_filters()` | Builds `<rechat-filter-*>` HTML from atts |
| `rch_apply_listing_boundary_site_defaults()` | Injects site-level boundary/brand defaults into atts |
| `rch_register_rechat_sdk_assets()` | Registers SDK CSS/JS handles (call before enqueue) |
| `rch_rechat_public_api_get()` | Authenticated GET to `api.rechat.com` |

### SDK version management

`RCH_RECHAT_SDK_VERSION` in `index.php` pins the unpkg CDN version for production. On localhost (detected via `rch_is_localhost_environment()`), both CSS and JS load from `sdk.rechat.com/builder/dist/` (always latest builder). To upgrade SDK on production, bump `RCH_RECHAT_SDK_VERSION`.

### Listing-detail URL pattern

Rewrite rules (registered in `index.php` and on `init`):
- New: `/listing-detail/{city}/{street}/{uuid}/` → `listing_detail=1&listing_city=…&listing_street=…&listing_id=…`
- Legacy: `/listing-detail/{street}/{uuid}/` (kept for redirects)

### Data flow (listings)

1. Shortcode or block → `rch_render_listing_list()` / latest-listings render.
2. PHP outputs `<rechat-root brand_id="…">` + `<rechat-listings …attrs>` + child components.
3. `rechat-sdk-js` loads from CDN; SDK fetches listings client-side.
4. Custom JS listens for `rechat-listings:fetched` (window or element) for Swiper/empty-state logic.

## Rules

- **Minimal diff.** Fix the requested surface only. No drive-by refactors in `helper.php` or unrelated shortcodes.
- **Block JS:** edit `src/blocks/*.js`, then run `npm run build:scripts`. Never edit `build/index.js` by hand.
- **Block + shortcode parity:** New listing attributes need all of: `src/blocks/listing-block.js` attributes, `rch_get_listing_block_attributes()` in `helper.php`, `rch_get_listings_default_atts()`, `rch_prepare_listing_atts_from_block()` bool/string lists, and render logic in `listing-shortcodes.php`.
- **Rechat SDK:** Do not enable Swiper `observer` / `observeParents` on SDK-managed listing slides — breaks React inside SDK.
- **Broadcast:** Before `broadcast_children()`, filter targets with `rch_multisite_broadcast_unlinked_target_blog_ids()` — rebroadcasting creates duplicate posts on subsites.
- **Multisite listing scope:** Office subsites use hub `office_id` → `filter_brand_id`; agent subsites use agent Rechat ID — see `agent-listing-scope.php`. Do not reintroduce removed patterns (e.g. `filter_property_subtypes` on agent profile listings) without explicit request.
- **Empty listings UI:** Count real listing cards (`.listing-card`, listing-detail links), not skeleton `.rechat-listings-list__item` nodes. Map fetch events to the correct instance via `composedPath()` / element listeners — never FIFO-assign window events across multiple shortcodes.
- **Few slides (<4):** Use static flex centering in `rch-latest-listings-swiper.js`; do not fight Swiper with manual `setTranslate()`.
- **Display toggles on `[listings]`:** `hide_filters`, `disable_sort`, `hide_map` omit the corresponding web components — do not leave orphaned SDK nodes.
- **Global CSS (themes):** Acropolis themes use their own `style.css` / `index.css`. Plugin global styles live in `assets/css/rch-global.css` and shortcode-scoped CSS files — do not assume theme `:root` rules apply inside plugin shortcodes.
- **Secrets:** `RCH_OAUTH_CLIENT_SECRET` in `index.php` is placeholder/dev — treat as sensitive in production docs.
- **IMPORTANT:** Do not mark a latest-listings instance `loaded` when only skeleton slides exist or when another instance's empty `rechat-listings:fetched` event fires. Always verify instance identity and real DOM cards before showing/hiding theme sections.

## Workflow

- **Approach:** Read existing code in the touched shortcode/block/multisite file first. Match naming (`rch_*`), attribute patterns, and enqueue hooks already used nearby.
- **Investigate:** Use grep for attribute names, hook names, and SDK event handlers before adding parallel systems.
- **Test mentally:** Multisite vs single-site, block vs raw shortcode, 0 vs 1 vs many listings, two `[rch_latest_listings]` on one page (active + sold).
- **Commits:** Only when the user explicitly asks. No `git push` unless asked. Follow existing commit message style (short imperative summary).
- **PRs:** Use `gh pr create` with Summary + Test plan when requested.
- **Ask vs act:** Ask when behavior is ambiguous across subsites, Broadcast scope, or breaking SDK contract. Act when the path is clear from README + existing helpers.
- **Docs:** Update `README.md` only if the user asks for documentation changes.

## Out of scope

- **Acropolis themes** (`wp-content/themes/Acropolis-agent`, `Acropolis-office`, etc.) unless the task explicitly names a theme file.
- **Third-party plugins** (`threewp-broadcast`, TGMPA) — integrate via hooks/API only; do not fork vendor code.
- **`build/` manual edits** — always rebuild from `src/`.
- **`README.md`** — user-facing; not updated by default.
- **Vendor assets:** `assets/js/swiper-bundle.min.js`, TGMPA library copies.
- **Database migrations / one-off cleanup** on production without explicit instruction.
- **Version bumps** in `index.php` unless releasing or user requests.

## Quick reference — main shortcodes

| Shortcode | PHP entry |
| --- | --- |
| `[listings]` | `includes/shortcodes/listing-shortcodes.php` |
| `[rch_latest_listings]` | `includes/shortcodes/latest-listing-shortcode.php` |
| `[rch_leads_form]` | `includes/shortcodes/lead-capture-shortcode.php` |
| `[rch_search_listing_form]` | `includes/shortcodes/search_listing_shortcode.php` |

SDK docs: https://sdk.rechat.com/classes/Listings.html
