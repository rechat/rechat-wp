# Changelog

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
