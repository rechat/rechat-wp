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

You can display listings anywhere on your site using the `[listings]` shortcode. The shortcode supports various filtering attributes:

**Example:**
```
[listings minimum_price="100000" maximum_price="500000" listing_per_page="12"]
```

### Available Attributes:

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

You can display listings anywhere on your site using the `[listings]` shortcode. The shortcode supports various filtering attributes:

**Example:**
```
[listings minimum_price="100000" maximum_price="500000" listing_per_page="12"]
```

### Available Attributes:

| Attribute | Description | Type |
|-----------|-------------|------|
| listing_per_page | Number of listings per page | Integer |
| minimum_price / maximum_price | Price range filter | Integer |
| minimum_lot_square_meters / maximum_lot_square_meters | Lot size in square meters | Integer |
| minimum_square_meters / maximum_square_meters | Property size | Integer |
| minimum_bathrooms / maximum_bathrooms | Number of bathrooms | Integer |
| minimum_bedrooms / maximum_bedrooms | Number of bedrooms | Integer |
| minimum_year_built / maximum_year_built | Year built range | Integer |
| listing_statuses | Filter by status. Accepts: Active, Incoming, Coming Soon, Pending, Sold, Leased, Withdrawn, Expired | Comma-separated values |
| show_filter_bar | Show/hide the frontend filter bar | true or false |
| own_listing | Show only current agent's listings | true or false |
| property_types | Filter by type. Accepts: Residential, Residential Lease, Lots & Acreage, Commercial, Multi-Family | Comma-separated values |

### Gutenberg Support

The plugin also includes a Gutenberg block for listings, allowing you to insert and configure listing views visually within the editor.

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

## 📣 `rch_latest_listings` shortcode

The `rch_latest_listings` shortcode renders the latest listings and supports a Swiper carousel or a simple grid layout. It accepts several attributes for filtering, layout, and behavior. The shortcode also supports a convenient `listing_statuses` attribute to select common property-type groups.

Basic usage

```php
[rch_latest_listings listing_statuses="Sale" limit="10" template="my-template" display_type="swiper"]
```

Attributes

| Attribute | Description | Type | Default |
|-----------|-------------|------|---------|
| display_type | Presentation mode: `swiper` or `grid` | string | `swiper` |
| limit | Listings per page | integer | `7` |
| template | Template name to render each listing (plugin searches theme `rechat/{template}.php`) | string | `` |
| content | Text filter passed to endpoint | string | `` |
| map_points | Polygon points string in `lat,lng|lat,lng|...` format | string | `` |
| listing_statuses | Filter by property type(s). See mapping below. | string | `` |
| slides_per_view | Swiper slidesPerView value | float | `3.5` |
| space_between | Swiper spaceBetween in px | integer | `16` |
| loop | Swiper loop | boolean | `true` |
| breakpoints | JSON string for Swiper breakpoints | JSON string | `` |
| pagination | Swiper pagination | boolean | `false` |
| navigation | Swiper navigation arrows | boolean | `false` |
| centered_slides | Center slides in Swiper | boolean | `false` |
| speed | Swiper speed in ms | integer | `` |
| effect | Swiper effect (e.g. `fade`) | string | `` |
| grab_cursor | Show grab cursor | boolean | `false` |
| simulate_touch | Enable touch simulation | boolean | `true` |
| autoplay | JSON string for autoplay settings | JSON string | `` |

listing_statuses mapping

To make common filters easier, the shortcode maps a small set of tokens into the property-type strings expected by the backend. When a token is used it will be converted server-side before the value is sent to the AJAX endpoint.

- `All Listings` → `Residential,Residential Lease,Lots & Acreage,Commercial,Multi-Family`
- `Sale` → `Residential,Lots & Acreage,Commercial,Multi-Family`
- `Lease` → `Residential Lease`
- `Lots & Acreage` → `Lots & Acreage`
- `Commercial` → `Commercial`

If you provide any other value (for example, a comma-separated list like `Residential,Commercial`) it will be passed through unchanged.

AJAX request details

- The shortcode's frontend JS makes a POST to `admin-ajax.php` with `action=rch_fetch_listing`.
- Parameters included in the POST body:
	- `listing_per_page` - from `limit`
	- `template` - template name
	- `content` - content filter
	- `brand` - plugin option `rch_rechat_brand_id`
	- `points` - `map_points` value
	- `listing_statuses` - mapped or raw string from attribute

Server-side handling

- `rch_fetch_listing_ajax()` (file: `includes/load-listing/fetch-archive-listings.php`) calls `rch_get_filters()` which parses request parameters into a filters array. `listing_statuses` is already supported and will be converted to an array by `rch_get_filters()`.
- `rch_fetch_listing()` uses the filters to call the Rechat API and returns rendered HTML using the requested template.

Examples

1) Swiper carousel for Sale listings (10 items)

```php
[rch_latest_listings listing_statuses="Sale" limit="10" display_type="swiper"]
```

2) Grid for Lease listings using a custom template

```php
[rch_latest_listings listing_statuses="Lease" display_type="grid" template="lease-template" limit="6"]
```

3) Custom comma-separated property types

```php
[rch_latest_listings listing_statuses="Residential,Commercial" limit="8"]
```

Implementation notes

- The shortcode now maps `listing_statuses` server-side and sets a JS variable that is posted to the AJAX endpoint (see `includes/shortcodes/latest-listing-shortcode.php`).
- If you need deterministic agent slugs or other post insert behavior, consider passing `post_name` when calling `wp_insert_post()` in `rch_process_agents_data`.
- To override templates, place the file `rechat/{template}.php` inside your theme directory.

Files modified / relevant

- `includes/shortcodes/latest-listing-shortcode.php` — Added `listing_statuses` attribute, mapping logic, and passes parameter to AJAX body.
- `includes/helper.php` — `rch_get_filters()` handles `listing_statuses` and converts it into an array for the API.
- `includes/load-listing/fetch-archive-listings.php` — Endpoint that uses filters to fetch and render listings.
