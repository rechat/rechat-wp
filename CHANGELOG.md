# Changelog

## 7.0.93

- **Fix: Agent/Office wizard showed lead channel IDs instead of names.** The Lead
  Channel dropdown in the wizard (e.g. "Theme Agent Talk Lead Channel") listed raw
  IDs like `96b5a021-…` because it read a `title` field Rechat doesn't return. It now
  shows the channel name from Rechat (e.g. **Zillow**, **Default Route**), the same
  names as the theme option panel. Saved values are still channel IDs, so existing
  selections are unchanged.

## 7.0.92

- **Fix: Listing block preview stayed blank right after adding it in the editor.**
  When you added a Listing block in the Gutenberg editor, nothing showed until you
  saved the page and reloaded. The editor shows blocks inside an iframe, and the
  Rechat SDK was only loaded into it when the *saved* page already had a Listing
  block, so a newly added block had no SDK to render it.
- The SDK now always loads in the editor, so the listings preview appears as soon as
  the block is added. The live site is unchanged: the SDK still loads only on pages
  that use the block.

## 7.0.91

- **Fix: agent sync created duplicate agents.** The scheduled (cron) sync decides
  "update or add" by looking up agents that already have a Rechat ID. That lookup
  went through normal WordPress queries, so a theme filter that hides agents set to
  **Visibility: Hide** also hid them from the sync. Each sync then added a new,
  visible copy of every hidden agent. Draft, pending and private agents were
  skipped the same way and re-added too.
- The sync now reads existing agents straight from the database, across every
  status except trash, so theme or plugin query filters can't affect it. If an agent
  already has duplicates, the sync updates the oldest post (the original).
- Existing duplicates are **not** removed automatically. Delete the extra copies by
  hand (keep the oldest one).

## 7.0.90

- **New: User Guide inside the plugin.** A new **Rechat → User Guide** tab shows
  a full guide for site admins: connecting to Rechat, syncing, listings / blocks /
  shortcodes, Multisite agent websites step by step (network settings, creating
  sites, assigning themes, Agent wizard, agent logins), a reference of every
  Multisite and wizard button, settings, and troubleshooting.
- **Download or print it.** The tab has **Download guide (HTML)**, **Download
  Markdown** and **Print / Save as PDF** buttons. The Plugins screen has a new
  **User Guide** link next to Settings, and README.md links to the guide on GitHub.
- **Source:** `docs/user-guide.md`. After editing it, run `npm run build:guide` to
  regenerate `docs/user-guide-body.html` (tab) and `docs/user-guide.html` (download).

## 7.0.88

- **Fix: agent sub-sites kept showing old agent data after a Rechat sync.** Wizard
  tags like `{$email}` / `{$phone_number}` and agent-bound fields are meant to
  repaint on every sync, but two bugs stopped it on sub-sites whose theme differs
  from the network "Default theme for agent sub-sites":
  - Deploy looked up the agent's sub-site link after switching into the sub-site,
    where that hub meta doesn't exist, so the destination theme's own option keys
    were dropped and never written.
  - The sync refresh read the saved deploy recipe filtered to the network default
    theme's keys only, so the recipe looked empty and the refresh silently skipped.
  Both now use the wizard theme + the sub-site's own theme keys.
- **Page cache is purged after a sub-site deploy/refresh.** Kinsta full-page cache
  kept serving stale HTML after theme options changed. Deploys and sync refreshes
  now purge once per request (Kinsta MU plugin when present) and fire
  `rch_agent_wizard_purge_page_cache` with the affected blog IDs for other caches.
- **After updating:** run the Agent site wizard once with "All agent sub-sites" so
  sites deployed under the bug get their missing fields; later syncs keep them current.

## 7.0.87

- **Data sync status on the Connect tab.** Added a "Data sync status" card to
  `?page=rechat-setting&tab=connect-to-rechat` showing when agents/offices/regions/
  branding last synced, whether it succeeded, which trigger ran it (background cron
  vs manual "Sync now"), the per-type add/update summary, and the next scheduled
  cron run. Every sync now writes an `rch_last_data_sync` option (recorded at all
  return points of `rch_update_agents_offices_regions_data()`, so cron, manual, and
  settings-page syncs are all captured), mirroring the existing OAuth refresh log.
- **Explains WHY the automatic sync isn't running.** The card surfaces warnings when
  the cron event isn't scheduled, when `DISABLE_WP_CRON` is on (host system cron must
  hit wp-cron.php — normal on Kinsta, but worth verifying), when the last successful
  sync is overdue (>24h — WP-Cron is traffic-driven and low-traffic sites lag), and
  when the last run failed (shows the failure reason, e.g. an expired token). Before
  this, sync outcomes only went to `error_log` with no admin-visible surface.

## 7.0.64

- **Fix: Testimonials editor preview failed with a bad request.** The srcDoc
  iframe from 7.0.63 had no hostname (`about:srcdoc` origin), and the Rechat SDK
  resolves the portal by `window.location.hostname` — so it sent an empty
  hostname and the API rejected it ("Too small: expected string to have >=1
  characters"), leaving the preview blank. The preview now loads from an
  admin-ajax endpoint (`rch_testimonials_preview`) on the site host, so the SDK
  gets a real hostname and behaves exactly like the front-end. The endpoint is
  nonce- and capability-guarded and mirrors the `[rch_testimonials]` output.

## 7.0.63

- **Fix: Testimonials editor preview showed nothing.** The previous approach
  rendered the SDK `<rechat-root>` inline in the editor, but the Rechat SDK mounts
  the component by scanning the DOM when its script first runs — and in the editor
  the block is inserted after the SDK already initialised, so the inline element
  was never mounted (blank). The preview now renders inside a self-contained
  iframe (`srcDoc`) that loads the SDK with the markup already present,
  reproducing the front-end load order so the component always mounts. The iframe
  auto-sizes to its content and reloads when the title/count/color mode change.

## 7.0.62

- **New: live Testimonials preview in the editor.** The Testimonials block now
  renders the real Rechat SDK `<rechat-testimonials>` web component inside the
  block editor instead of a static placeholder. The SDK CSS/JS + brand id are
  passed to the editor (`rchTestimonialsPreview`) and the SDK is injected into
  the block's own document, so the custom element upgrades whether the canvas is
  iframed (WP 6.x) or not (Meta Boxes present). Changing the title, count, or
  color mode remounts the component to re-fetch. Falls back to the placeholder
  when no Rechat account is connected (no brand id). Front-end output unchanged.

## 7.0.61

- **Fix: Testimonials block was invisible in the editor.** The block previewed
  via `ServerSideRender`, which emits the Rechat SDK `<rechat-testimonials>` web
  component — but the SDK JS isn't loaded in the editor iframe, so the block
  rendered blank. Editors couldn't tell it was inserted and re-added it multiple
  times, which stacked duplicate testimonial sections on the published page. The
  editor now shows a clear static placeholder (title + settings summary) instead
  of the empty SSR preview; front-end output (the `[rch_testimonials]` shortcode)
  is unchanged. NOTE: pages that already have duplicate blocks must have the
  extra blocks removed by hand.

## 7.0.60

- **New: auto-register the site domain on the Rechat portal.** On OAuth connect
  and on every 12-hour data sync, the plugin checks the connected brand's portal
  hostnames via the Rechat API (`GET /brands/:brand/portal`) and, if this site's
  domain (`home_url()` host) is missing, POSTs it as the default hostname
  (`POST /brands/:brand/portal/hostnames`, `is_default=true`). Ensures every
  connected account has the correct site domain registered. New module
  `includes/portal/portal-hostname.php`; hostname filterable via
  `rch_portal_hostname`.
- **Change: default agent profile image is now `image-placeholder.jpg`.** Agent
  sync previously fell back to `assets/images/image-placeholder.svg` for agents
  with no `profile_image_url`; now uses `image-placeholder.jpg`.

## 7.0.59

- **Fix: Agent role can now edit Rank Math SEO.** The custom `agent` role never
  received Rank Math's `rank_math_*` capabilities (Rank Math only grants those to
  built-in roles), so agents couldn't edit the SEO meta box or open Rank Math
  admin pages (SEO Analyzer, Titles & Meta, Sitemap, etc.). The role now gets the
  full Rank Math capability set (filterable via `rch_agent_rank_math_caps`).
  Existing agent users pick this up automatically on the next admin load. Harmless
  when Rank Math is not installed.

## 7.0.58

- **Fix: Off Market single "View all photos" gallery rendered broken.** The
  off-market single page never enqueued `swiper-bundle.min.css`, so the modal
  Swiper (`rch-houses-mySwiper2` / `rch-houses-mySwiper`) fell back to
  `display:block` and slides stacked vertically. Enqueue the `rch-swiper` handle
  on `is_singular('off_market')` so the gallery slides horizontally like the
  listing-detail page. JS/Swiper init untouched.
- **New: Off Market leads route to the selected agent.** On the off-market single
  page, the lead-capture form now sets `assignee_email` to the Agent chosen in the
  listing's meta box (`agent_id` → that Agent's `email`), mirroring
  agents-single. Falls back to the previous default when no agent is selected.

## 7.0.57

- **New: Off Market Gutenberg block.** Wraps the `[rch_off_market]` shortcode as a
  block (`Off Market Block`) so editors configure it from the panel instead of
  typing shortcode attributes. Controls for display type (grid/swiper), title,
  status filter, limit, columns, order, and swiper options (space between, loop,
  autoplay, autoplay delay). Live preview via ServerSideRender; block CSS loads
  in the editor iframe.
- **New: grid pagination.** `[rch_off_market]` gains a `pagination` attribute
  (grid only, exposed as a block toggle). When on, `limit` becomes the per-page
  count and numbered page links render via `?om_page=N` (server-side, no JS).
- **Fix: theme `single.css` `.single img/video` leak on the Off Market single
  page.** The theme's generic image margins bled into the off-market single body
  (`body.single`) and the "View all photos" modal slider. Scoped overrides in
  `rch-off-market.css` and `rch-rechat-listing.css` neutralize the leak so the
  page and modal match the listing-detail UI; listing-detail pages are unaffected
  (not `body.single`).

## 7.0.51

- **New feature: Off Market listings (`off_market` CPT).** Manually-entered
  listings with **no Rechat API dependency** — an admin adds them under
  **Off Market → Add New** and they appear on the archive and via shortcode.
  - Fields (registry-driven meta boxes): status, rechat_id, list/sold date,
    price, currency, address (line/city/state/postal), latitude/longitude,
    bedrooms/bathrooms/square_feet, gallery, and an **Agent picked from the
    site's Agents**. Description uses the post editor.
  - **Gallery** uses the WordPress Media Library (multi-select, drag reorder),
    with a Featured Image fallback.
  - **Single page** reuses the existing listing-detail template parts (an adapter
    reshapes post meta into the API-shaped `$listing_detail`), so it matches the
    normal listing detail UI — including the agent card and the LocalLogic
    LocalContent widget when latitude/longitude are set. Empty fields never
    render (no empty labels/values/icons/containers).
  - **Status badge** uses "Privately" wording (Active/Coming Soon/Pending/Sold
    Privately) on the gallery and cards.
  - **Archive** at `/off-market/` — static, server-rendered responsive-grid
    cards. Theme-overridable via `rechat/off-market-archive-custom.php` and
    `rechat/off-market-single-custom.php`.
  - **New shortcode `[rch_off_market]`** — filterable grid or Swiper carousel.
    Attributes: `display_type` (normal|swiper), `status`, `limit`, `columns`,
    `orderby`, `order`, `title`, plus swiper options (`space_between`, `loop`,
    `autoplay`, `autoplay_delay`). Swiper mode self-loads Swiper 11 so it works
    on any page/theme. Full parameter docs in README.

## 7.0.48

- **Testimonial export — agent email column.** New `email` column carries the
  owning agent's profile email (from the linked hub `agents` post) on agent
  subsites; blank on the main site and office subsites.

## 7.0.47

- **Testimonial export — agent Rechat ID on agent subsites.** For agent subsites,
  the `brand_id` column now holds the owning agent's Rechat ID (`api_id` of the
  linked hub `agents` post) instead of the site brand. Main site and office
  subsites keep their brand id. Resolved cache-free so it stays correct across
  the per-site export loop.

## 7.0.46

- **Testimonial CSV export improvements.**
  - On multisite, exports testimonials from **every site in the network**, not just the current site.
  - Added three columns: `brand_id` (each site's `rch_rechat_brand_id`), `domain` (site host), and `url` (site home URL).
  - Name column strips a leading dash (e.g. `– Caroline Wood` → `Caroline Wood`).
  - Testimonial column is flattened to plain text (HTML stripped, entities decoded, whitespace collapsed).

## 7.0.45

- **Testimonials Gutenberg block.** New "Testimonials Block" renders the
  `[rch_testimonials]` shortcode (Rechat SDK web component) with inspector
  controls for title, count, and color mode.
- **Testimonials show all by default.** `[rch_testimonials]` and the block now
  omit the `limit` attribute when unset (0/empty) so the SDK returns every
  testimonial; set a number to cap.
- **Testimonial CPT import/export.** New CSV import/export on the Rechat
  Settings → Import / Export tab: export all `testimonial` posts
  (name, text, stars, link) and import the same columns (create or update by
  post_id).

## 7.0.44

- **Testimonials shortcode → web component.** `[rch_testimonials]` now renders the
  Rechat SDK web component (`<rechat-root><rechat-testimonials>`) instead of the old
  client-side JS fetch. `brand_id` comes from the site setting (`rch_rechat_brand_id`),
  consistent with every other Rechat shortcode. Attributes: `limit`, `title`, `color_mode`.
- **Broadcast ACF media.** ACF image/file/gallery fields now broadcast correctly to
  subsites. Their attachments are copied and the child post's meta is remapped to the
  new attachment IDs (ThreeWP Broadcast core only copies attached children + the featured
  image, so ACF media previously broadcast as a broken source-site ID).
- **Agent listings per page 10 → 12.** `rch_get_agent_listings_attrs()` sets
  `filter_pagination_limit="12"`.
- **Cleanup.** Removed the now-unused `assets/js/rch-testimonials.js` and
  `assets/css/rch-testimonials.css` and their registrations.
