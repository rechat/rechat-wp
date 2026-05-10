# Listing Template Parts

This folder contains modular template parts for the single listing detail page. Each file handles a specific section of the listing display, enabling clean code organization and easy theme customization.

## 📁 Template Parts Overview

### 1. **listing-gallery.php**
**Purpose:** Property image gallery with status badge and responsive layout  
**Location:** Top of page  
**Features:**
- Cover image display
- Thumbnail grid (4 images on desktop, responsive on mobile)
- Status badge (Active, Pending, Sold, etc.)
- "View all Photos" button
- Single-image layout detection

**Variables Used:**
- `$listing_detail['gallery_image_urls']`
- `$listing_detail['cover_image_url']`
- `$listing_detail['status']`
- `RCH_PLUGIN_ASSETS_URL_IMG`

---

### 2. **listing-header.php**
**Purpose:** Displays price, full address, and MLS number  
**Location:** Below gallery  
**Features:**
- Formatted price display
- Full street address with city/state
- MLS number in parentheses

**Variables Used:**
- `$listing_detail['formatted']['price']['text']`
- `$listing_detail['formatted']['full_address']['text']`
- `$listing_detail['mls_number']`

---

### 3. **listing-summary.php**
**Purpose:** Key property metrics grid  
**Location:** Top of left column  
**Features:**
- Total bedrooms
- Total bathrooms
- Square footage
- Lot size (acres if > 43,560 sqft, otherwise sqft)
- Price per sqft
- Year built
- Property type
- County name

**Variables Used:**
- `$listing_detail['formatted']['bedroom_count']`
- `$listing_detail['formatted']['total_bathroom_count']`
- `$listing_detail['formatted']['square_meters']`
- `$listing_detail['formatted']['lot_square_meters']`
- `$listing_detail['formatted']['year_built']`
- `$listing_detail['formatted']['property_type']`
- `$listing_detail['formatted']['county_name']`

**Logic:**
- Calculates lot acres: `lot_sqft / 43560`
- Calculates price per sqft: `price / sqft`

---

### 4. **listing-description.php**
**Purpose:** Property description with show more/less toggle  
**Location:** Below summary  
**Features:**
- Collapsible description text
- "Show More" button (if > 200px height)
- Smooth scroll on collapse

**Variables Used:**
- `$listing_detail['formatted']['description']`

**JavaScript:** Show more/less handled in `listing-scripts.php`

---

### 5. **listing-open-houses.php**
**Purpose:** Displays scheduled open house dates and times  
**Location:** Below description  
**Features:**
- Timezone display
- Date formatting (e.g., "Sat, Dec 28, 2024")
- Time ranges
- Open house type

**Variables Used:**
- `$listing_detail['open_houses']` (array)
  - `start_time`
  - `end_time`
  - `open_house_type`

**Logic:**
- Timezone conversion using `wp_timezone()`
- Date formatting: `l, M d, Y` format

---

### 6. **listing-features.php**
**Purpose:** Comprehensive property features organized by category  
**Location:** Below open houses  
**Features:** 5 major sections:

#### 6.1 Facilities & Features
- Application fee
- Appliances
- Garage spaces
- Parking features
- Fireplaces
- Heating/Cooling

#### 6.2 Amenities & Utilities
- Internet
- Flooring
- Water source
- Sewer type
- Laundry features

#### 6.3 Interior Features
- Bedroom count
- Bedroom features
- Bathroom count
- Basement
- Interior details

#### 6.4 Exterior Features
- Views
- Lot features
- Roof type
- Foundation type
- Exterior walls
- Fence
- Patio/Porch

#### 6.5 Parking
- Parking spots
- Parking type
- Garage spaces
- Garage type
- Covered spaces

**Variables Used:**
- `$listing_detail['formatted']['*']` (all feature-related fields)

**Logic:**
- Each section checks for data existence before rendering
- Arrays converted to comma-separated strings
- Empty values filtered out

---

### 7. **listing-agents.php**
**Purpose:** Displays agent information, local logic widgets, and MLS disclaimer  
**Location:** Below features  
**Features:**
- Listing agent display (multiple agents supported)
- Seller agent display (multiple agents supported)
- Agent photos, license numbers, phone, email
- Local Logic widgets (if enabled)
- Courtesy text (if no agents)
- MLS disclaimer with dynamic year

**Variables Used:**
- `$agent_posts` (array of WP_Post objects)
- `$seller_agent_posts` (array of WP_Post objects)
- `$listing_detail['formatted']['courtesy']['text']`
- `$listing_detail['mls_info']['disclaimer']`
- `get_option('rch_rechat_local_logic_features')`

**Post Meta:**
- `profile_image_url`
- `license_number`
- `phone_number`
- `email`

**Logic:**
- Loops through listing agents and seller agents separately
- Displays local logic widgets based on admin settings
- Replaces `{{currentYear}}` in MLS disclaimer

---

### 8. **listing-contact-form.php**
**Purpose:** Lead capture form for property inquiries  
**Location:** Right sidebar  
**Features:**
- First name (required)
- Last name (required)
- Phone number (required)
- Email (required)
- Note (optional)
- Loading spinner
- Success/error messages
- AJAX submission

**Variables Used:**
- `$post_id`
- `$listing_detail['address']`
- `wp_create_nonce('rechat_save_listing_lead_nonce')`

**Form Action:** `rechat_save_listing_lead`

**JavaScript:** Form handling in `listing-scripts.php`

---

### 9. **listing-modal.php**
**Purpose:** Image gallery lightbox with Swiper slider  
**Location:** After footer (modal overlay)  
**Features:**
- Full-screen modal
- Main image slider
- Thumbnail navigation slider
- Next/Previous buttons
- Close button (X)
- Click outside to close

**Variables Used:**
- `$listing_detail['gallery_image_urls']`

**Dependencies:**
- Swiper.js (loaded separately)

**JavaScript:** Modal handlers in `listing-scripts.php`

---

### 10. **listing-scripts.php**
**Purpose:** All JavaScript functionality for listing pages  
**Location:** After footer  
**Features:**

#### Rechat SDK
- SDK initialization
- Lead tracking
- Lead capture handling

#### Swiper Sliders
- Thumbnail slider (responsive: 3-8 slides)
- Main image slider with navigation
- Linked thumb/main sliders

#### Modal Controls
- Open modal on image click
- Close on X click
- Close on outside click
- Navigate to specific slide

#### Show More/Less
- Toggle description expansion
- Smooth scroll on collapse
- Height detection (200px threshold)

**Variables Used:**
- `$listing_detail['id']`
- `$listing_detail['mls_number']`
- `get_option('rch_lead_channels')`
- `get_option('rch_selected_tags')`

**External Dependencies:**
- `@rechat/sdk@latest`
- Swiper.js

---

## 🎨 Theme Customization

### Overriding Template Parts

To customize any template part in your theme:

1. Create folder structure in your theme:
   ```
   your-theme/
   └── rechat-plugin/
       └── templates/
           └── single/
               └── template-parts/
                   └── listing/
                       └── listing-gallery.php
   ```

2. Copy the template part you want to customize

3. Modify as needed - plugin updates won't override your changes

### Important Notes

⚠️ **DO NOT** modify files in the plugin directory  
✅ **DO** copy to theme and modify there  
⚠️ **DO NOT** remove template part includes from main template  
✅ **DO** ensure all template parts are included in correct order

---

## 📊 Template Architecture

```
listing-single-custom.php (138 lines - main template)
├── listing-gallery.php (cover + thumbnails)
├── listing-header.php (price + address)
├── Left Column
│   ├── listing-summary.php (key metrics grid)
│   ├── listing-description.php (about property)
│   ├── listing-open-houses.php (scheduled dates)
│   ├── listing-features.php (5 feature sections)
│   └── listing-agents.php (agents + disclaimer)
├── Right Column
│   └── listing-contact-form.php (lead capture)
├── listing-modal.php (image lightbox)
└── listing-scripts.php (all JavaScript)
```

---

## 🔧 Variables Reference

### Required Variables (passed from main template)
- `$listing_detail` - Full listing data array from Rechat API
- `$agent_posts` - Array of listing agent WP_Post objects
- `$seller_agent_posts` - Array of seller agent WP_Post objects
- `$post_id` - Current WordPress post ID

### WordPress Constants
- `RCH_PLUGIN_DIR` - Plugin root directory path
- `RCH_PLUGIN_INCLUDES` - Includes directory path
- `RCH_PLUGIN_ASSETS_URL_IMG` - Images URL
- `RCH_VERSION` - Plugin version

### Functions Used
- `get_the_ID()`
- `wp_create_nonce()`
- `wp_timezone()`
- `esc_url()`, `esc_attr()`, `esc_html()`, `esc_js()`
- `sanitize_text_field()`
- `wp_kses_post()`
- `get_option()`
- `get_post_meta()`
- `get_the_title()`
- `get_permalink()`

---

## 📝 Maintenance

### Adding New Template Parts

1. Create new file: `listing-your-feature.php`
2. Add PHP doc block with purpose and variables
3. Include in main template at appropriate position
4. Update this README with documentation

### Debugging

- Check PHP errors: `get_errors` tool in VS Code
- Verify variables: `var_dump($listing_detail)` in template
- Test responsiveness: Check Swiper breakpoints
- Validate HTML: Check modal/form IDs match JavaScript

---

## 📈 Performance

**Before Modularization:** 1,139 lines  
**After Modularization:** 138 lines (main template) + 10 focused modules  
**Reduction:** 92% in main template complexity

**Benefits:**
- Easier debugging (single responsibility)
- Theme overrides without blocking plugin updates
- Reusable components
- Better code organization
- Faster team collaboration

---

## 🐛 Common Issues

### Modal not opening
- Check `listing-scripts.php` is included after footer
- Verify Swiper.js is loaded
- Ensure gallery images exist

### Form not submitting
- Verify nonce is valid
- Check AJAX action `rechat_save_listing_lead` exists
- Confirm Rechat SDK loaded

### Features not displaying
- Check `$listing_detail['formatted']` array structure
- Verify data exists before rendering
- Ensure array handling logic works

---

## 📚 Related Files

**Main Template:** `templates/single/listing-single-custom.php`  
**Backup (Old Version):** `templates/single/listing-single-custom-backup.php`  
**Helper Functions:** `includes/helper.php`  
**Shortcodes:** `includes/shortcodes/listing-shortcodes.php`

---

**Version:** 1.0.0  
**Last Updated:** March 2024  
**Total Template Parts:** 10  
**Total Lines:** ~1,200 lines across all modules
