# Changelog

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
