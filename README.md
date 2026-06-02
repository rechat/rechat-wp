# Rechat Plugin (WordPress) — Documentation

Rechat plugin pulls data from Rechat (agents/offices/regions + listing search) and renders pages/blocks/shortcodes using Rechat Web Components (`<rechat-root>`, `<rechat-listings>`, etc.).

This README is user-facing: install, setup, features, templates, multisite, and **all shortcodes + accepted parameters**.

---

## Features (what plugin does)

- **OAuth connection to Rechat**
  - Connect in WP admin, store `access_token` + `refresh_token`, auto-refresh.
  - Saves **brand id**, primary color, logo for UI usage.
- **Automatic data sync (cron)**
  - Scheduled sync every **12 hours** (Agents / Offices / Regions / Branding).
  - Manual trigger exists (admin AJAX).
- **Custom Post Types (CPT)**
  - `agents` — agent profiles (archive + single).
  - `offices` — office profiles (archive + single) + **Office ID** meta from Rechat when synced.
  - `regions` — region pages (archive + single).
  - `neighborhoods` — neighborhood pages + cards, optional broadcast to agent subsites.
- **Listings experience**
  - `[listings]` shortcode and Listing block render full listing search experience.
  - Listing detail route rewrite: `/listing-detail/{city}/{street}/{id}/` (legacy route also supported).
- **Lead capture**
  - `[rch_leads_form]` shortcode and Leads Form block.
  - Sends leads via Rechat SDK integration.
- **Search form**
  - `[rch_search_listing_form]` shortcode renders property search form that redirects to a listings page.
- **Gutenberg blocks**
  - Agents block
  - Offices/Regions block
  - Listing block
  - Leads Form block
- **Template override system**
  - Copy plugin templates into your theme folder `rechat/` to customize output.
- **WordPress Multisite support (optional)**
  - Auto-create **agent sub-sites** and **office sub-sites**.
  - Agent subsites auto-scope listings to that agent.
  - Office subsites auto-scope listings to that office brand id (Office ID).
  - Theme sync / bulk provision / URL migration / cleanup tools in Network Admin.

---

## Requirements

- WordPress with permalinks enabled.
- Rechat account.
- For maps: Google Maps API key (configured in plugin settings).
- For multisite provisioning: WordPress Multisite + Broadcast (ThreeWP Broadcast) network-active (when agent/office subsites enabled).

---

## Install + setup

### Install

1. Upload plugin folder to `wp-content/plugins/rechat-plugin/`
2. Activate from WP Admin → Plugins.

### Connect to Rechat (OAuth)

WP Admin → Rechat → **Connect to Rechat** tab.

After success, plugin stores:

- `rch_rechat_access_token`
- `rch_rechat_refresh_token`
- `rch_rechat_brand_id` (brand UUID for SDK auth)
- optional appearance values (primary color, logo)

---

## Automatic data sync

Plugin schedules a cron job (every 12 hours) that syncs:

- Agents
- Offices
- Regions
- Branding / associations

Notes:

- Cron hook: `rch_data_sync_hook`
- Interval: `rch_every_12_hours`

---

## Custom post types (CPT) + what they represent

- **Agents** (`agents`)
  - Used for agent profile pages, agent blocks, and agent multisite subsites.
- **Offices** (`offices`)
  - Has `office_id` meta when pulled from Rechat API.
  - Locally created offices may not have `office_id` (no Rechat UUID).
- **Regions** (`regions`)
  - Region pages + region blocks.
- **Neighborhoods** (`neighborhoods`)
  - Neighborhood pages, optionally linked to offices and broadcast to agent subsites (multisite).

---

## Template overrides (theme customization)

All data views (agents/regions/offices/listings) have default plugin templates. Override by adding files to your theme:

1. In your active theme (or child theme) create folder: `rechat/`
2. Copy the original templates from plugin into that folder and edit.

Supported override filenames:

| File name | What it overrides |
| --- | --- |
| `agents-archive-custom.php` | Agents archive page |
| `agents-single-custom.php` | Agent single page |
| `regions-archive-custom.php` | Regions archive page |
| `regions-single-custom.php` | Region single page |
| `offices-archive-custom.php` | Offices archive page |
| `offices-single-custom.php` | Office single page |
| `listing-item.php` | Listing card/item template (legacy AJAX listing renderer) |
| `listing-single-custom.php` | Listing detail page template |

Important:

- Put files in `wp-content/themes/your-theme/rechat/` (not theme root).
- Use child theme for safe updates.

---

## Gutenberg blocks

Blocks are registered by plugin and render server-side.

- **Listing block**: `rch-rechat-plugin/listing-block`
  - Same filter capabilities as `[listings]` (see shortcode section).
- **Agents block**: `rch-rechat-plugin/agents-block`
- **Offices/Regions block**: registered via `block-offices-regions.php`
- **Leads form block**: `block-lead-form.php`

Tip:

- Listing block + `[listings]` share the same PHP renderer. Most attributes map 1:1.

---

## WordPress Multisite (agents + offices)

If WordPress Multisite enabled, plugin can provision a network sub-site for each agent and office.

### What gets created

- **Agent subsite**
  - Linked to hub agent post via `_rch_agent_site_id`
  - URL can be subdomain or subdirectory depending on network
- **Office subsite**
  - Linked to hub office post via `_rch_office_site_id`
  - Uses `o-` prefix in slug to avoid collisions with agent slugs

### Listing scope behavior on subsites

- **Agent subsite**
  - Auto-inject `filter_agents="<csv>"` into `<rechat-listings>` for:
    - `[listings]`
    - Listing block
    - `[rch_latest_listings]`
- **Office subsite**
  - Auto-inject `filter_brand_id="<office_id>"` into `<rechat-listings>` for:
    - `[listings]`
    - Listing block
    - `[rch_latest_listings]`
  - Value comes from hub office CPT meta `office_id` (Rechat Office brand UUID). If office is locally created with no `office_id`, scope is skipped.

### Where to manage it

Network Admin / hub site → Rechat Settings → **Multisite** tab:

- enable/disable subsite creation
- bulk provision
- apply themes (agent sites / office sites)
- migrate agent URL slugs
- cleanup duplicates

---

## Shortcodes (all + parameters)

All shortcodes can be used in:

- WP classic editor
- Gutenberg “Shortcode” block
- Theme templates (via `do_shortcode()` / plugin helper `rch_do_shortcode()` when available)

### 1) `[listings]` — full listing search experience

Renders `<rechat-root>` + `<rechat-listings>` + filters + map + grid + pagination.

Basic example:

```text
[listings minimum_price="100000" maximum_price="500000" listing_per_page="12" filter_search_limit="200" filter_pool="true"]
```

Accepted parameters (from `rch_get_listings_default_atts()`):

| Attribute | Type | Notes |
| --- | --- | --- |
| `minimum_price` / `maximum_price` | string number | dollars |
| `minimum_square_feet` / `maximum_square_feet` | string number | |
| `minimum_lot_square_feet` / `maximum_lot_square_feet` | string number | |
| `minimum_bathrooms` / `maximum_bathrooms` | string number | |
| `minimum_bedrooms` / `maximum_bedrooms` | string number | |
| `minimum_year_built` / `maximum_year_built` | string number | |
| `minimum_parking_spaces` | string number | |
| `minimum_sold_date` | string number | unix ms |
| `listing_per_page` | string number | maps to SDK pagination limit |
| `brand` | string | set from settings in render |
| `listing_statuses` | string or csv | may be expanded from groups |
| `own_listing` | boolean | sets `filter_brand_id` to brand |
| `property_types` | string or csv | |
| `filter_open_houses` | boolean | |
| `office_exclusive` | boolean | |
| `filter_pool` | boolean | |
| `disable_sort` | boolean | hide sort UI |
| `map_latitude` / `map_longitude` | string | |
| `map_zoom` | string number | default `12` in code |
| `map_style` / `map_style_url` / `map_id` | string | |
| `filter_address` | string | |
| `filter_search_limit` | string number | cap total results |
| `filter_suggestions_limit` | string number | |
| `filter_pagination_offset` | string number | |
| `property_subtypes` | string | csv |
| `architectural_styles` | string | csv |
| `filter_baths` | string number | exact baths |
| `filter_agents` | string | csv of Rechat agent UUIDs |
| `list_offices` | string | csv of office UUIDs |
| `filter_brand_id` | string | overrides scope for this listing surface |
| `disable_filter_address` | boolean | |
| `disable_filter_price` | boolean | |
| `disable_filter_beds` | boolean | |
| `disable_filter_baths` | boolean | |
| `disable_filter_property_types` | boolean | |
| `disable_filter_advanced` | boolean | |
| `disable_filter_loading_indicator` | boolean | |
| `sort_by` | string | default `-list_date` |
| `filter_boundary_country` | string | ISO 2-letter in some flows |
| `filter_boundary_state` | string | state title |

Notes:

- Google Maps API key comes from settings, not from shortcode.
- Most filters become attributes on `<rechat-listings>`. For SDK meaning reference: `https://sdk.rechat.com/classes/Listings.html`

### 2) `[rch_latest_listings]` — compact latest listings widget

Renders `<rechat-root>` + `<rechat-listings>` using one of 3 layouts:

- `swiper` (default)
- `normal` (list + SDK pagination)
- `grid` (simple grid)

Basic example:

```text
[rch_latest_listings property_types="Residential" listing_statuses="Active" filter_search_limit="200"]
```

Core parameters (from defaults):

| Attribute | Type | Default | Notes |
| --- | ---: | ---: | --- |
| `display_type` | string | `swiper` | `swiper` \| `normal` \| `grid` |
| `listing_per_page` | string | `10` | per page |
| `limit` | string | (empty) | alias for `listing_per_page` |
| `listing_statuses` | string | (empty) | supports group labels (Active/Pending/Closed/Archived) |
| `expand_status_aliases` | boolean | `true` | expands status groups |
| `property_types` | string | (empty) | supports labels: `Sale`, `Lease`, `All Listings`, etc. |
| `sort_by` | string | `-list_date` | |
| `order_by` | string | (empty) | maps to known labels in code |
| `own_listing` | boolean | `false` | scopes to brand |
| `open_houses_only` | boolean | `false` | legacy alias |
| `filter_open_houses` | boolean | `false` | |
| `office_exclusive` | boolean | `false` | |
| `filter_pool` | boolean | `false` | |
| `filter_agents` | string | (empty) | csv |
| `list_offices` | string | (empty) | csv |
| `filter_brand_id` | string | (empty) | office subsites may auto-set |
| `map_default_center` | string | (empty) | `"lat,lng"` string |
| `map_latitude` / `map_longitude` / `map_zoom` / `map_id` | string | (empty) | |
| `minimum_price` / `maximum_price` etc. | mixed | (empty) | same family as `[listings]` |
| `disable_filter_*` | boolean | `false` | same names as `[listings]` |
| `listing_hyperlink_href` / `listing_hyperlink_target` | string | (empty) | |

Swiper-only parameters:

| Attribute | Type | Default |
| --- | ---: | ---: |
| `slides_per_view` | string | `auto` |
| `space_between` | string | `32` |
| `loop` | boolean | `false` |
| `centered_slides` | boolean | `false` |
| `speed` | string | `300` |
| `effect` | string | `slide` |
| `grab_cursor` | boolean | `true` |
| `simulate_touch` | boolean | `true` |
| `autoplay` | JSON string | (empty) |
| `breakpoints` | JSON string | (empty) |
| `pagination` | boolean | `false` |
| `pagination_clickable` | boolean | `false` |
| `pagination_type` | string | `bullets` |
| `navigation` | boolean | `false` |

Examples:

```text
[rch_latest_listings display_type="swiper" listing_statuses="Active" limit="10" filter_search_limit="100"]
```

```text
[rch_latest_listings display_type="grid" property_types="Sale" limit="6" sort_by="-list_date"]
```

### 3) `[rch_leads_form]` — lead capture form

Basic example:

```text
[rch_leads_form form_title="Contact Us" show_first_name="true" show_last_name="true" show_phone_number="true" show_email="true" show_note="true"]
```

Accepted parameters (from `rch_parse_shortcode_attributes()`):

| Attribute | Type | Default | Notes |
| --- | ---: | ---: | --- |
| `form_title` | string | `Contact Us` | |
| `show_first_name` | boolean | `true` | |
| `show_last_name` | boolean | `true` | |
| `show_phone_number` | boolean | `true` | |
| `show_email` | boolean | `true` | |
| `show_note` | boolean | `true` | |
| `lead_channel` | string | (empty) | falls back to option `rch_lead_channels` |
| `assignee_email` | string | (empty) | agent subsites may auto-fill when empty |
| `tags` | string csv | (empty) | falls back to option `rch_selected_tags` |

### 4) `[rch_search_listing_form]` — search bar that redirects to listings page

This shortcode renders `<rechat-property-search-form>` inside `<rechat-listings>`. On submit it redirects (GET) to `target_page` with query parameters.

Basic example:

```text
[rch_search_listing_form target_page="/listings/" show_background="false"]
```

Accepted parameters (from `shortcode_atts` defaults):

| Attribute | Type | Default | Notes |
| --- | ---: | ---: | --- |
| `target_page` | string | `/listings/` | page path or URL path |
| `brand_id` | string | (empty) | falls back to option `rch_rechat_brand_id` |
| `map_zoom` | string | (empty) | |
| `map_api_key` | string | option | default from option `rch_rechat_google_map_api_key` |
| `map_default_center` | string | (empty) | `"lat,lng"` |
| `filter_address` | string | (empty) | |
| `disable_filter_address` | boolean | `false` | |
| `disable_filter_price` | boolean | `false` | |
| `disable_filter_beds` | boolean | `false` | |
| `disable_filter_baths` | boolean | `false` | |
| `disable_filter_property_types` | boolean | `false` | |
| `disable_filter_advanced` | boolean | `false` | |
| `disable_filter_loading_indicator` | boolean | `false` | |
| `filter_minimum_price` | string | (empty) | used as initial min price |
| `filter_minimum_bathrooms` | string | (empty) | |
| `filter_minimum_bedrooms` | string | (empty) | |
| `filter_maximum_bedrooms` | string | (empty) | |
| `filter_maximum_year_built` | string | (empty) | |
| `filter_listing_statuses` | string | (empty) | |
| `show_background` | boolean | `false` | adds background image layer |
| `background_image` | string URL | (empty) | used only when `show_background=true` |

---

## Notes + troubleshooting

- **Permalinks / rewrite**
  - After activation or URL changes, re-save WP Settings → Permalinks.
- **No data / empty pages**
  - Confirm OAuth connected and brand id exists in settings.
  - Check WP cron runs (or server cron).
- **Multisite provisioning not working**
  - Must be WordPress Multisite.
  - If subsite creation enabled, Broadcast must be network-active.
- **Office subsite listing scope missing**
  - Hub office must have `office_id` meta (synced from Rechat). Locally added offices have no id.
