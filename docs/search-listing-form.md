# Rechat Search Listing Form

This feature adds a reusable search form shortcode that can be placed anywhere on your website. When users submit the form, they are redirected to a listings page with their search parameters applied.

The search form works with both the `[listings]` shortcode and the Listing block in Gutenberg. The filter parameters are passed via GET request to the target page and automatically applied to the listings display.

## How to Use

### Basic Usage

Add the shortcode to any page or post:

```php
[rch_search_listing_form]
```

### Custom Options

The shortcode supports the following attributes:

```php
[rch_search_listing_form target_page="/your-listings-page/" title="Find Your Dream Home" show_title="true" compact="false"]
```

#### Available Attributes

- `target_page`: The URL where the form will submit to (default: "/listings/")
- `title`: The heading displayed above the search form (default: "Find Your Perfect Home")
- `show_title`: Whether to show the title (true/false, default: true)
- `compact`: Whether to use a compact layout (true/false, default: false)

### Setting Up the Target Page

On the target page, you can use either:

1. The listings shortcode:

```php
[listings]
```

1. OR the Gutenberg Listing block

When users submit the search form, the filters they choose will automatically be applied to either the shortcode or the block.

## Technical Details

- The search form submits its values via GET parameters
- The plugin automatically passes these GET parameters to the [listings] shortcode
- All filter values are properly sanitized and validated

## Example

Place this shortcode on your homepage:

```php
[rch_search_listing_form target_page="/properties/" title="Search Available Properties"]
```

Create a page at `/properties/` with this shortcode:

```php
[listings show_filter_bar="true"]
```

Users will be able to search from the homepage and see filtered results on the properties page.

## Troubleshooting

### URL Parameters Are Not Applied

If you find that URL parameters are not being applied to the listings, check:

1. Make sure you're using the correct parameter names in the form:
   - `minimum_price`, `maximum_price`
   - `minimum_bedrooms`, `maximum_bedrooms`
   - `minimum_bathrooms` (for bathroom buttons)
   - etc.

2. For the bathroom buttons, make sure the hidden input field with the name `minimum_bathrooms` is properly updated when a button is clicked. The value of this field should match the `data-value` attribute of the active button.

3. Verify that the target page is properly set up with either:
   - The `[listings]` shortcode, or
   - The Gutenberg Listing block

4. Check the URL after form submission to ensure parameters are being passed correctly:
   - Example: `/listings/?minimum_price=500000&minimum_bedrooms=3&minimum_bathrooms=2`

### How Parameters Are Applied

URL parameters take precedence over the default values set in the shortcode or block. This allows the search form to override any default filters that might be set.
