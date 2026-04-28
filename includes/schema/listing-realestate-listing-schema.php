<?php
if (! defined('ABSPATH')) {
    exit();
}

/**
 * Build Schema.org RealEstateListing JSON-LD from Rechat listing API payload.
 *
 * @param array  $listing Listing data from API.
 * @param string $listing_url Canonical URL for this listing page.
 * @return array<string,mixed>
 */
function rch_build_realestate_listing_schema(array $listing, $listing_url)
{
    $listing_url = esc_url_raw($listing_url);
    if ($listing_url === '') {
        return [];
    }

    $name = '';
    if (! empty($listing['formatted']['full_address']['text'])) {
        $name = wp_strip_all_tags((string) $listing['formatted']['full_address']['text']);
    } elseif (! empty($listing['formatted']['street_address']['text'])) {
        $name = wp_strip_all_tags((string) $listing['formatted']['street_address']['text']);
    }
    if ($name === '') {
        $name = __('Property listing', 'rechat-plugin');
    }

    $description = '';
    if (! empty($listing['property']['description']) && is_string($listing['property']['description'])) {
        $description = wp_strip_all_tags($listing['property']['description']);
        if (function_exists('mb_substr')) {
            $description = mb_substr($description, 0, 8000);
        } else {
            $description = substr($description, 0, 8000);
        }
    }

    $images = [];
    if (! empty($listing['cover_image_url']) && is_string($listing['cover_image_url']) && filter_var($listing['cover_image_url'], FILTER_VALIDATE_URL)) {
        $images[] = esc_url_raw($listing['cover_image_url']);
    }
    if (! empty($listing['gallery_image_urls']) && is_array($listing['gallery_image_urls'])) {
        foreach ($listing['gallery_image_urls'] as $img) {
            if (! is_string($img) || ! filter_var($img, FILTER_VALIDATE_URL)) {
                continue;
            }
            $u = esc_url_raw($img);
            if ($u !== '' && ! in_array($u, $images, true) && count($images) < 24) {
                $images[] = $u;
            }
        }
    }

    $price      = null;
    $price_curr = 'USD';
    if (isset($listing['formatted']['price']['value']) && is_numeric($listing['formatted']['price']['value'])) {
        $price = (float) $listing['formatted']['price']['value'];
    }
    if (! empty($listing['formatted']['price']['currency']) && is_string($listing['formatted']['price']['currency'])) {
        $price_curr = strtoupper(sanitize_text_field($listing['formatted']['price']['currency']));
    }

    if (! empty($listing['property']['address']) && is_array($listing['property']['address'])) {
        $a            = $listing['property']['address'];
        $street_parts = [];
        foreach (['street_number', 'street_name', 'street_suffix', 'unit_number'] as $k) {
            if (! empty($a[$k]) && is_scalar($a[$k])) {
                $street_parts[] = trim((string) $a[$k]);
            }
        }
        $street = implode(' ', array_filter($street_parts));
        $city = $a['city'] ?? null;
        if (is_array($city)) {
            $city = $city['name'] ?? $city['title'] ?? $city['text'] ?? '';
        }
        $region = $a['state'] ?? $a['province'] ?? null;
        if (is_array($region)) {
            $region = $region['name'] ?? $region['title'] ?? $region['text'] ?? '';
        }
        $zip = $a['postal_code'] ?? $a['zip'] ?? null;
        if (is_array($zip)) {
            $zip = $zip['name'] ?? $zip['text'] ?? '';
        }
        $country = $a['country'] ?? $a['country_code'] ?? 'US';
        if (is_array($country)) {
            $country = $country['name'] ?? $country['code'] ?? 'US';
        }

        $postal = [
            '@type'           => 'PostalAddress',
            'addressCountry'  => is_string($country) ? sanitize_text_field($country) : 'US',
        ];
        if ($street !== '') {
            $postal['streetAddress'] = sanitize_text_field($street);
        }
        if (is_string($city) && $city !== '') {
            $postal['addressLocality'] = sanitize_text_field($city);
        }
        if (is_string($region) && $region !== '') {
            $postal['addressRegion'] = sanitize_text_field($region);
        }
        if (is_string($zip) && $zip !== '') {
            $postal['postalCode'] = sanitize_text_field($zip);
        }
    } else {
        $postal = null;
    }

    $lat = null;
    $lng = null;
    if (! empty($listing['property']['address']['location']) && is_array($listing['property']['address']['location'])) {
        $loc = $listing['property']['address']['location'];
        if (isset($loc['latitude']) && is_numeric($loc['latitude'])) {
            $lat = (float) $loc['latitude'];
        }
        if (isset($loc['longitude']) && is_numeric($loc['longitude'])) {
            $lng = (float) $loc['longitude'];
        }
    }

    $beds = null;
    if (isset($listing['formatted']['bedroom_count']['value']) && is_numeric($listing['formatted']['bedroom_count']['value'])) {
        $beds = (float) $listing['formatted']['bedroom_count']['value'];
    }
    $baths = null;
    if (isset($listing['formatted']['total_bathroom_count']['value']) && is_numeric($listing['formatted']['total_bathroom_count']['value'])) {
        $baths = (float) $listing['formatted']['total_bathroom_count']['value'];
    } elseif (isset($listing['formatted']['bathrooms']['value']) && is_numeric($listing['formatted']['bathrooms']['value'])) {
        $baths = (float) $listing['formatted']['bathrooms']['value'];
    }
    $sqft = null;
    if (isset($listing['formatted']['square_feet']['value']) && is_numeric($listing['formatted']['square_feet']['value'])) {
        $sqft = (float) $listing['formatted']['square_feet']['value'];
    }

    $year_built = null;
    if (! empty($listing['property']['year_built']) && is_scalar($listing['property']['year_built'])) {
        $y = preg_replace('/\D/', '', (string) $listing['property']['year_built']);
        if ($y !== '' && strlen($y) === 4) {
            $year_built = (int) $y;
        }
    }

    $about = [
        '@type' => 'SingleFamilyResidence',
        'name'  => $name,
        'url'   => $listing_url,
    ];
    if ($postal !== null) {
        $about['address'] = $postal;
    }
    if ($lat !== null && $lng !== null) {
        $about['geo'] = [
            '@type'     => 'GeoCoordinates',
            'latitude'  => $lat,
            'longitude' => $lng,
        ];
    }
    if ($beds !== null) {
        $about['numberOfBedrooms'] = $beds;
    }
    if ($baths !== null) {
        $about['numberOfBathroomsTotal'] = $baths;
    }
    if ($sqft !== null && $sqft > 0) {
        $about['floorSize'] = [
            '@type'    => 'QuantitativeValue',
            'value'    => $sqft,
            'unitCode' => 'SQF',
        ];
    }
    if ($year_built !== null) {
        $about['yearBuilt'] = $year_built;
    }

    $schema = [
        '@context' => 'https://schema.org',
        '@type'    => 'RealEstateListing',
        'name'     => $name,
        'url'      => $listing_url,
        'about'    => $about,
    ];

    if ($description !== '') {
        $schema['description'] = $description;
    }
    if (! empty($images)) {
        $schema['image'] = count($images) === 1 ? $images[0] : $images;
    }

    if ($price !== null && $price > 0) {
        $schema['offers'] = [
            '@type'         => 'Offer',
            'url'           => $listing_url,
            'price'         => $price,
            'priceCurrency' => $price_curr,
        ];
    }

    if (! empty($listing['mls_number']) && is_scalar($listing['mls_number'])) {
        $schema['identifier'] = sanitize_text_field((string) $listing['mls_number']);
    }

    return $schema;
}

/**
 * Echo RealEstateListing JSON-LD when a listing detail page is rendering.
 *
 * @return void
 */
function rch_output_listing_realestate_listing_jsonld()
{
    if (! get_query_var('listing_detail')) {
        return;
    }
    if (empty($GLOBALS['rch_listing_detail_for_jsonld']) || ! is_array($GLOBALS['rch_listing_detail_for_jsonld'])) {
        return;
    }

    $listing = $GLOBALS['rch_listing_detail_for_jsonld'];
    $url = isset($GLOBALS['rch_listing_canonical_url']) && is_string($GLOBALS['rch_listing_canonical_url'])
        ? $GLOBALS['rch_listing_canonical_url']
        : '';
    if ($url === '') {
        $path = isset($_SERVER['REQUEST_URI']) ? wp_parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '';
        $url  = is_string($path) && $path !== '' ? home_url(user_trailingslashit($path)) : home_url('/');
    }

    $schema = rch_build_realestate_listing_schema($listing, $url);

    /**
     * Filter the RealEstateListing JSON-LD array before output.
     *
     * @param array $schema    Schema.org graph node.
     * @param array $listing   Raw listing payload from API.
     * @param string $listing_url Canonical listing URL.
     */
    $schema = apply_filters('rch_realestate_listing_schema', $schema, $listing, $url);

    if (empty($schema) || ! is_array($schema)) {
        return;
    }

    $json = wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (! is_string($json) || $json === '') {
        return;
    }

    echo '<script type="application/ld+json">' . $json . '</script>' . "\n";

    unset($GLOBALS['rch_listing_detail_for_jsonld'], $GLOBALS['rch_listing_canonical_url']);
}
add_action('wp_head', 'rch_output_listing_realestate_listing_jsonld', 6);
