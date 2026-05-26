# Rechat WordPress Plugin – User Guide

The Rechat WordPress Plugin allows you to seamlessly integrate your Rechat agents, offices, regions, and listings into your WordPress website. This guide explains how to use the plugin, customize its templates, and utilize available shortcodes.

## 🔄 Automatic Data Sync

The plugin automatically fetches and updates the following data from your Rechat account every 12 hours:

- Agents
- Offices
- Regions
- Listings

No manual syncing is required.

## 🖥️ Default Templates & Customization

All data views (agents, regions, offices, listings) are rendered using default plugin templates. However, you can override these templates by creating a custom folder in your active theme.

### Custom Templates

To customize the layout or appearance of Rechat pages:

1. In your active theme (or child theme) directory, create a folder named `rechat`.

2. Inside the `rechat` folder, you can add any of the following files to override the corresponding plugin template:

| File Name | Description |
|-----------|-------------|
| agents-archive-custom.php | Overrides the archive view for agents |
| agents-single-custom.php | Overrides the single agent page |
| regions-archive-custom.php | Overrides the archive view for regions |
| regions-single-custom.php | Overrides the single region page |
| offices-archive-custom.php | Overrides the archive view for offices |
| offices-single-custom.php | Overrides the single office page |
| listing-item.php | Customizes the listing item (box) |
| listing-single-custom.php | Overrides the single listing page |

**Important**: Always copy the original template files from the plugin's templates folder into your theme's `rechat` folder before editing.

## 🔧 Listings Shortcode

You can display listings anywhere on your site using the `[listings]` shortcode. It outputs the Rechat web components (`<rechat-root>` and `<rechat-listings>`). Most attributes are passed through to the SDK; see the [Rechat Listings (Web Components) reference](https://sdk.rechat.com/classes/Listings.html) for the underlying attribute names.

**Example:**

```
[listings minimum_price="100000" maximum_price="500000" listing_per_page="12" filter_search_limit="200" filter_pool="true"]
```

### Available attributes (`[listings]`)

| Attribute | Description | Type / example |
|------------|-------------|----------------|
| `listing_per_page` | How many listing cards to load per page (maps to SDK `filter_pagination_limit`) | String number, e.g. `20` |
| `minimum_price` / `maximum_price` | Price range in dollars (maps to `filter_minimum_price` / `filter_maximum_price`) | String number |
| `minimum_square_feet` / `maximum_square_feet` | Interior size in square feet | String number |
| `minimum_lot_square_feet` / `maximum_lot_square_feet` | Lot size in square feet | String number |
| `minimum_bathrooms` / `maximum_bathrooms` | Bath count (maps to `filter_minimum_bathrooms` / `filter_maximum_bathrooms`) | String number |
| `filter_baths` | Exact number of bathrooms (SDK `filter_baths`) | String number |
| `minimum_bedrooms` / `maximum_bedrooms` | Bedroom count | String number |
| `minimum_parking_spaces` | Minimum parking spaces (SDK `filter_minimum_parking_spaces`) | String number |
| `minimum_year_built` / `maximum_year_built` | Year built range | String number |
| `minimum_sold_date` | Minimum sold date, Unix time in **milliseconds** (SDK `filter_minimum_sold_date`) | String number |
| `sort_by` | Sort, e.g. `-price`, `-list_date` (SDK `filter_sort_by`) | String |
| `listing_statuses` | Comma-separated statuses, e.g. `Active` or `Active,Sold` (SDK `filter_listing_statuses`) | String |
| `property_types` | Comma-separated types (e.g. `Residential,Commercial`); also accepts mapped labels in the Gutenberg block | Comma-separated string |
| `property_subtypes` | Comma-separated subtypes (SDK `filter_property_subtypes`) | Comma-separated string |
| `architectural_styles` | Comma-separated styles (SDK `filter_architectural_styles`) | Comma-separated string |
| `filter_address` | Initial address/area for map boundary search (SDK `filter_address`) | String |
| `filter_search_limit` | **Cap on total search results** returned (SDK `filter_search_limit`); not the same as per-page | String number |
| `filter_suggestions_limit` | Max address suggestions in search (default in SDK is often `5`) | String number |
| `filter_pagination_offset` | Start listing results at this index (0-based) | String number |
| `filter_open_houses` | Only open houses (SDK `filter_open_houses`) | `true` or `false` |
| `office_exclusive` | Office-exclusives only (SDK `filter_office_exclusives`) | `true` or `false` |
| `filter_pool` | Pool only (SDK `filter_pool`, use `true` to enable) | `true` or `false` |
| `filter_agents` | Comma-separated Rechat agent IDs (SDK `filter_agents`) | Comma-separated string |
| `list_offices` | Comma-separated office IDs (SDK `filter_list_offices`) | Comma-separated string |
| `filter_brand_id` | Override brand for this list (`filter_brand_id` on `<rechat-listings>`); `brand_id` on `<rechat-root>` still comes from settings | String |
| `own_listing` | When `true`, scopes listings to the configured brand (`filter_brand_id` on `<rechat-listings>`) | `true` or `false` (default: `false`) |
| `map_latitude` / `map_longitude` | Default map center (used with `map_default_center`) | Decimal string |
| `map_zoom` | Initial map zoom (SDK `map_zoom`) | String number, e.g. `12` |
| `map_id` | Google **Cloud** map style ID, if you use one (SDK `map_id`) | String |
| `layout_style` | Markup order: `default` (map left), `layout2` (list left, map right), or `layout3` | String |
| `disable_sort` | Hides the `<rechat-listings-sort>` control | `true` or `false` |
| `disable_filter_address` | Hides the address control; filter can still be set by attributes/URL (SDK `disable_filter_address`) | `true` or `false` |
| `disable_filter_price` | (SDK `disable_filter_price`) | `true` or `false` |
| `disable_filter_beds` | (SDK `disable_filter_beds`) | `true` or `false` |
| `disable_filter_baths` | (SDK `disable_filter_baths`) | `true` or `false` |
| `disable_filter_property_types` | (SDK `disable_filter_property_types`) | `true` or `false` |
| `disable_filter_advanced` | (SDK `disable_filter_advanced`) | `true` or `false` |
| `disable_filter_loading_indicator` | (SDK `disable_filter_loading_indicator`) | `true` or `false` |
| `listing_hyperlink_href` | Custom listing detail URL; use `{id}` in the path if supported by the SDK (plugin default includes `{street_address}`) | URL string |
| `listing_hyperlink_target` | e.g. `_blank` (SDK `listing_hyperlink_target`) | String |

Google Maps API key is set in the plugin options; the shortcode does not need to pass a map key.

### Gutenberg support

The same controls are available in the **Listing** block, including an **Additional Rechat filters (optional)** panel for many of the fields above. Place the block or use the `[listings]` shortcode in a Shortcode block or classic editor.

## 📩 Lead Form Shortcode

Use the following shortcode to place a customizable lead capture form anywhere on your site:

```
[rch_leads_form form_title="Contact Agent" show_first_name="true" show_last_name="false" show_phone_number="false" show_email="true" show_note="false"]
```

### Lead Form Attributes:

| Attribute | Description | Type |
|-----------|-------------|------|
| form_title | Title displayed on top of the form | Text |
| show_first_name | Show First Name field | true or false |
| show_last_name | Show Last Name field | true or false |
| show_phone_number | Show Phone Number field | true or false |
| show_email | Show Email field | true or false |
| show_note | Show Message/Note field | true or false |

## 📝 Notes

- Make sure your API credentials and configuration are set up correctly in the plugin settings.
- Templates must be placed in `wp-content/themes/your-theme/rechat/`, not the root directory of the theme.
- Use a child theme for template customizations to avoid losing changes on theme updates.
- If you need further help, contact the plugin developer or refer to the documentation included in the plugin folder.

---

## `rch_latest_listings` shortcode

The `rch_latest_listings` shortcode shows a compact list of listings using the Rechat web components. You can use **`display_type`**: `swiper` (default), `normal` (list + Rechat pagination), or `grid` (list only). The same [Rechat Listings / `rechat-listings` filters](https://sdk.rechat.com/classes/Listings.html) as the main listings shortcode are supported where listed below—values are passed through to `rch_get_rechat_listings_attributes()` in PHP.

**Basic usage**

```text
[rch_latest_listings listing_statuses="Sale" limit="10" display_type="swiper" filter_search_limit="200"]
```

### Core attributes (`rch_latest_listings`)

| Attribute | Description | Type | Default |
|-----------|-------------|------|---------|
| `display_type` | `swiper` \| `normal` \| `grid` | string | `swiper` |
| `limit` | Shorthand for `listing_per_page` (per-page / `filter_pagination_limit`) | string | (see `listing_per_page` in code) |
| `listing_per_page` | Listings per page (same meaning as in `[listings]`) | string | `10` in defaults |
| `listing_statuses` | Status filter; can use **mapping** values like `Active`, `Closed`, or comma-separated | string | `` |
| `property_types` | Property type filter; can use **mapping** labels (e.g. `Sale`, `All Listings`) or a raw CSV list | string | `` |
| `sort_by` / `order_by` | Sort order; `order_by` maps to known labels, or set `sort_by` directly | string | `-list_date` |
| `own_listing` | Whether to scope to the site brand (same as `[listings]`) | boolean | `false` |
| `open_houses_only` | Legacy: open house filter; combined with `filter_open_houses` for the SDK | boolean | `false` |
| `template` | CSS class suffix for the `grid` layout container | string | `` |
| `content` | (Reserved / advanced) | string | `` |
| `map_points` | (Reserved; not used the same as old AJAX) | string | `` |
| `map_latitude` / `map_longitude` / `map_zoom` / `map_id` | Map options when you use a map field in the flow | string | `` |
| `filter_address` | Initial area/address for search | string | `` |
| `minimum_price`, `maximum_price`, bedroom/bath/year/sqft fields | Same as `[listings]`; see the table above for SDK names | strings | `` |

### Rechat SDK pass-through (same as `[listings]`)

The following (and the rest of the `[listings]` table above) can be set on `rch_latest_listings` and are whitelisted in the shortcode:  
`filter_search_limit`, `filter_suggestions_limit`, `filter_pagination_offset`, `property_subtypes`, `architectural_styles`, `filter_baths`, `minimum_parking_spaces`, `minimum_sold_date`, `filter_pool`, `filter_agents`, `list_offices`, `filter_brand_id`, `filter_open_houses`, `office_exclusive`, and all `disable_filter_*` flags, plus `listing_hyperlink_href` / `listing_hyperlink_target` when you need custom listing URLs.

### Swiper options (`display_type="swiper"` only)

| Attribute | Description | Type | Default |
|-----------|-------------|------|---------|
| `slides_per_view` | Swiper `slidesPerView` (e.g. `auto` or a number) | string | `auto` |
| `space_between` | Spacing in px | string | `32` |
| `loop` | Swiper loop | boolean | `false` |
| `breakpoints` | JSON for Swiper breakpoints | JSON string | `` |
| `pagination` / `navigation` / `pagination_clickable` / `pagination_type` | Swiper UI | mixed | `false` |
| `centered_slides` | Center slides | boolean | `false` |
| `speed` | Swiper speed (ms) | string | `300` |
| `effect` | Swiper effect (e.g. `slide`, `coverflow`) | string | `slide` |
| `grab_cursor` / `simulate_touch` | Interaction | boolean | `` |
| `autoplay` | JSON for autoplay | JSON string | `` |

### `listing_statuses` and `property_types` mapping

The shortcode maps a small set of **labels** to the comma-separated values the Rechat SDK expects. If you pass a value that is not a known label, it is sent as-is (for example a full CSV like `Residential,Commercial`).

**Property type labels (examples)**

- `All Listings` → `Residential,Residential Lease,Lots & Acreage,Commercial,Multi-Family`
- `Sale` → `Residential,Lots & Acreage,Commercial,Multi-Family`
- `Lease` → `Residential Lease`
- `Lots & Acreage` → `Lots & Acreage`
- `Commercial` → `Commercial`

**Status labels (examples)** — e.g. `Active` maps to a bundle of live statuses; see `latest-listing-shortcode.php` for the full list.

**Implementation:** HTML is built in `includes/shortcodes/latest-listing-shortcode.php` with `<rechat-root>` and `<rechat-listings>`. For Swiper, the script listens for `rechat-listings:fetched` and then initializes Swiper. Enqueued assets include the Rechat SDK and Swiper from the plugin.

### Examples

1) Swiper carousel for Sale listings (10 items per page, cap 100 results)

```text
[rch_latest_listings listing_statuses="Sale" limit="10" display_type="swiper" filter_search_limit="100"]
```

2) Grid for Lease listings with a template class

```text
[rch_latest_listings listing_statuses="Lease" display_type="grid" template="lease-template" limit="6"]
```

3) Custom comma-separated property types

```text
[rch_latest_listings listing_statuses="Residential,Commercial" limit="8"]
```

### Implementation notes

- `includes/shortcodes/latest-listing-shortcode.php` — shortcode output, property/status mapping, and `rch_get_rechat_listings_attributes()` for the SDK.
- `includes/helper.php` — `rch_get_rechat_listings_attributes()` builds the attribute string for `<rechat-listings>`; keep in sync with the [Rechat SDK](https://sdk.rechat.com/classes/Listings.html).
- For custom Swiper or grid styling, use your theme CSS; the `template` argument adds a class to the grid wrapper (e.g. `lease-template-grid`).
