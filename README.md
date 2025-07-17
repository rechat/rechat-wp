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
