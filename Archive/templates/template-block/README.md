# Template Block Files

This directory contains template block files and template logic files for the Rechat plugin.

## Structure

### Template Block Files
- `rch-agents-block-template.php` - Gutenberg block template for agents
- `rch-offices-block-template.php` - Gutenberg block template for offices
- `rch-regions-block-template.php` - Gutenberg block template for regions

### Template Logic Files
~~Moved to `templates/single/template-parts/` for better organization~~

## Purpose

Template logic files in this directory contain core functionality that is loaded directly from the plugin, even when template files are overridden in themes.

### The Problem

When users customize templates by copying them to `yourtheme/rechat/`, the theme file takes precedence over the plugin's template file. This creates an issue:

**When we release plugin updates with new features or bug fixes, sites using theme overrides won't receive those updates.**

### The Solution

We've separated complex logic from template markup:

- **Template files** (in `templates/single/`, `templates/archive/`) contain only HTML structure and basic markup
- **Logic files** (in `templates/template-block/`) contain the core functionality and are loaded directly from the plugin

This approach ensures:
- ✅ Plugin updates are always applied, even with theme overrides
- ✅ Users can still customize the layout and HTML structure
- ✅ Template files remain clean and easy to understand
- ✅ Complex logic stays maintained and up-to-date

## Template Logic Files

### agents-listings-section.php

Contains the listing rendering logic for agent single pages using the new Rechat SDK structure.

**Functions:**
- `rch_render_agent_listings_section($post_id)` - Main function to render agent listings
- `rch_get_agent_listings_attrs($brand_id, $agents_string, $property_subtypes, $property_types, $listing_statuses)` - Generates attributes for rechat-listings component
- `rch_render_combined_listings($agent_title, $root_attrs, $combined_attrs)` - Renders combined (active) listings
- `rch_render_separate_listings($agent_title, $root_attrs, $active_attrs, $sold_attrs)` - Renders active and sold listings separately
- `rch_render_active_only_listings($agent_title, $root_attrs, $active_attrs)` - Renders active listings only
- `rch_render_sold_only_listings($agent_title, $root_attrs, $sold_attrs)` - Renders sold listings only

Uses the new SDK structure with `<rechat-root>` and `<rechat-listings>` components.

## Usage in Templates

Templates load these logic files like this:

```php
<?php
$listings_section_file = RCH_PLUGIN_DIR . 'templates/template-block/agents-listings-section.php';
if (file_exists($listings_section_file)) {
    require_once $listings_section_file;
    rch_render_agent_listings_section($post_id);
}
?>
```

This ensures:
1. The logic file is always loaded from the plugin (not from theme overrides)
2. The function is called with the necessary parameters
3. Updates to the logic are automatically applied

## For Developers

When adding new features that require complex logic:

1. Create a new logic file in `templates/template-block/`
2. Define clear, documented functions
3. Load the file in the template using `RCH_PLUGIN_DIR . 'templates/template-block/filename.php'`
4. Document the integration in template file comments

## For Theme Developers

When customizing templates:

1. Copy the template file to `yourtheme/rechat/`
2. Customize the HTML structure as needed
3. **Do NOT remove** the logic file loading sections
4. Keep the `rch_render_*` function calls intact

This ensures you get layout customization while still receiving plugin updates.

## File Organization

This folder combines related functionality:
- **Block templates** - For Gutenberg blocks
- **Logic files** - For complex rendering logic that should always come from the plugin

This organization prevents confusion and keeps related files together.
