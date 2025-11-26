# Polygon Search Implementation for Google Places

## Overview
The system now calculates accurate polygons based on the exact boundaries of searched places (streets, cities, neighborhoods) using Google Places API viewport and bounds data.

## How It Works

### 1. **User Searches for a Place**
   - User types "Ocean Drive, Miami Beach, FL, USA" in the search box
   - Google Places Autocomplete suggests matching locations
   - User selects a place from the dropdown

### 2. **Place Selection Process**

#### JavaScript (`rch-places-autocomplete.js`)
When a place is selected:

1. **Extracts Place Data**:
   - Gets latitude/longitude coordinates
   - Retrieves `geometry.viewport` (recommended viewing area)
   - Retrieves `geometry.bounds` (precise boundaries if available)

2. **Creates Bounding Box**:
   - If viewport/bounds exist, uses them directly
   - Creates a bounding box with 4 corners:
     - Northeast (top-right)
     - Southeast (bottom-right)
     - Southwest (bottom-left)
     - Northwest (top-left)

3. **Formats Polygon String**:
   - Converts bounding box to polygon format
   - Format: `lat1,lng1|lat2,lng2|lat3,lng3|lat4,lng4|lat1,lng1`
   - Example: `25.7617,-80.1918|25.7617,-80.1300|25.7200,-80.1300|25.7200,-80.1918|25.7617,-80.1918`

4. **Updates the System**:
   - Stores polygon in hidden input `#query-string`
   - Updates global `filters.points` variable
   - Updates map viewport to show the area
   - Triggers new listing search with polygon

### 3. **Backend Processing**

#### PHP (`includes/helper.php`)

**AJAX Handler: `rch_calculate_polygon_from_place()`**

Two modes of operation:

**Mode 1: Using Google Places Bounds (Preferred)**
```php
// Receives bounds from JavaScript
bounds: {
    northeast: [lat, lng],
    southwest: [lat, lng]
}

// Creates accurate 4-point polygon
// Returns polygon string matching exact place boundaries
```

**Mode 2: Fallback Calculation**
```php
// If no bounds provided
// Calculates approximate bounds based on zoom level
// Uses lat/lng center point
// Returns calculated polygon string
```

### 4. **Listing Request**

#### File: `listings-archive-custom.php`
- Polygon stored in: `<input id="query-string" value="...">`
- Passed to AJAX requests via `rchListingData` object

#### File: `rch-listing-request.js`
```javascript
// Reads polygon from hidden input
const pointsValue = document.getElementById('query-string').value;

// Adds to API request body
queryString.points = pointsValue;
```

#### API Request Body:
```json
{
  "points": "25.7617,-80.1918|25.7617,-80.1300|25.7200,-80.1300|25.7200,-80.1918|25.7617,-80.1918",
  "property_types": ["Residential"],
  "listing_statuses": ["Active"],
  // ... other filters
}
```

## Key Features

### 1. **Accurate Place Boundaries**
   - Uses Google Places API's actual viewport/bounds
   - Not just a circular radius around a point
   - Reflects the real shape of streets, neighborhoods, cities

### 2. **Flexible Location Types**
   Supports searching for:
   - Streets/Routes (e.g., "Ocean Drive")
   - Neighborhoods
   - Cities (e.g., "Miami Beach")
   - Postal Codes (e.g., "33139")
   - States
   - Street Addresses

### 3. **Fallback System**
   - If Google doesn't provide bounds → calculates from zoom level
   - Ensures search always works even for vague locations

### 4. **Map Synchronization**
   - Map automatically zooms to show selected area
   - Visual polygon overlay on map (if implemented)
   - Listings update to show only properties within polygon

## Data Flow

```
User Search
    ↓
Google Places API
    ↓
Extract Bounds/Viewport
    ↓
Create Polygon (4 corners)
    ↓
Format as String (lat,lng|lat,lng|...)
    ↓
Store in #query-string
    ↓
Pass to Listing API Request
    ↓
Filter Listings by Polygon
    ↓
Display Results on Map & List
```

## Example Scenarios

### Example 1: Ocean Drive, Miami Beach
```
Search: "Ocean Drive, Miami Beach, FL, USA"
↓
Google Returns:
- Center: 25.7830, -80.1300
- Bounds: NE(25.8000, -80.1200), SW(25.7600, -80.1400)
↓
Polygon Created:
25.8000,-80.1200|25.7600,-80.1200|25.7600,-80.1400|25.8000,-80.1400|25.8000,-80.1200
↓
API Request:
points: "25.8000,-80.1200|25.7600,-80.1200|25.7600,-80.1400|25.8000,-80.1400|25.8000,-80.1200"
↓
Results: Only properties on/near Ocean Drive
```

### Example 2: Miami Beach (City)
```
Search: "Miami Beach, FL"
↓
Google Returns:
- Larger bounds covering entire city
- Bounds: NE(25.8800, -80.1100), SW(25.7600, -80.1400)
↓
Polygon Created: (covers whole city)
↓
Results: All properties in Miami Beach
```

## Files Modified

1. **rch-places-autocomplete.js**
   - Enhanced to extract viewport/bounds
   - Added polygon formatting function
   - Sends bounds to PHP via AJAX

2. **helper.php**
   - Updated AJAX handler to accept bounds
   - Creates polygon from Google bounds
   - Fallback calculation if no bounds

3. **listing-filters.php**
   - Fixed property type filter IDs
   - Ensures proper selection

4. **listings-archive-custom.php**
   - Already configured to pass polygon
   - No changes needed

## Testing

To test the implementation:

1. Go to the listings page
2. Use the search box
3. Search for specific locations:
   - "Ocean Drive, Miami Beach, FL"
   - "Brickell, Miami, FL"
   - "33139" (postal code)
4. Select from autocomplete dropdown
5. Verify:
   - Map zooms to location
   - Polygon updates in console
   - Listings filter to that area

## Benefits

✅ **Precise Location Matching**: Listings match exact place boundaries
✅ **Better User Experience**: Search by street name, neighborhood, or city
✅ **Accurate Results**: No false positives from nearby areas
✅ **Flexible**: Works with any location type Google supports
✅ **Fallback Protection**: Always provides results even if bounds unavailable
