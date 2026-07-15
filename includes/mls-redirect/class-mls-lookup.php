<?php
/**
 * MLS redirect: listing lookup.
 *
 * Single responsibility: resolve a normalized MLS number to a listing-detail URL,
 * using the Rechat API and caching the result to protect the API and stay fast on
 * large datasets.
 *
 * @package Rechat
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Resolves an MLS number to a listing-detail permalink (with caching).
 */
class RCH_MLS_Lookup
{
    /**
     * Transient key prefix for resolved MLS -> URL mappings.
     */
    const CACHE_PREFIX = 'rch_mls_url_v4_';

    /**
     * Sentinel stored for "looked up, but no listing exists" (negative cache).
     */
    const NEGATIVE = '0';

    /**
     * Resolve an MLS number to a listing-detail URL.
     *
     * @param string $mls    Normalized MLS number.
     * @param string $source Optional MLS source/board (listing `mls` field, e.g. "NTREIS").
     *                       Disambiguates numbers that collide across MLS boards.
     * @return string Listing-detail URL, or '' when no listing matches.
     */
    public function resolve($mls, $source = '')
    {
        $mls    = (string) $mls;
        $source = (string) $source;
        if ($mls === '') {
            return '';
        }

        $cache_key = self::CACHE_PREFIX . md5($source . '|' . $mls);
        $cached    = get_transient($cache_key);

        if ($cached === self::NEGATIVE) {
            return '';
        }
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $listing = $this->fetch_listing_by_mls($mls, $source);
        $url     = $listing ? $this->build_listing_url($listing) : '';

        if ($url !== '') {
            set_transient($cache_key, $url, $this->positive_ttl());
        } else {
            // Short negative cache so bots hammering unknown IDs cannot flood the API.
            set_transient($cache_key, self::NEGATIVE, $this->negative_ttl());
        }

        return $url;
    }

    /**
     * Positive cache lifetime (seconds).
     *
     * @return int
     */
    protected function positive_ttl()
    {
        return (int) apply_filters('rch_mls_cache_ttl', 12 * HOUR_IN_SECONDS);
    }

    /**
     * Negative cache lifetime (seconds).
     *
     * @return int
     */
    protected function negative_ttl()
    {
        return (int) apply_filters('rch_mls_negative_cache_ttl', 5 * MINUTE_IN_SECONDS);
    }

    /**
     * Query Rechat for a single listing matching the MLS number.
     *
     * Uses the same `/valerts` search endpoint + brand/auth headers as the archive
     * listing fetch. Returns the first matching listing array, or null.
     *
     * @param string $mls    Normalized MLS number.
     * @param string $source Optional MLS source/board (listing `mls` field).
     * @return array<string, mixed>|null
     */
    protected function fetch_listing_by_mls($mls, $source = '')
    {
        $token = (string) get_option('rch_rechat_access_token', '');
        $brand = (string) get_option('rch_rechat_brand_id', '');

        // Without a token we cannot look anything up; treat as "not found".
        if ($token === '') {
            return null;
        }

        // 1) Brand-scoped valerts search (future-proof, fast for the brand's own listings).
        $rows = ($brand !== '') ? $this->fetch_valerts_rows($mls, $source, $token, $brand) : array();
        if (! empty($rows)) {
            if ($source !== '') {
                foreach ($rows as $row) {
                    if (isset($row['mls']) && strtoupper((string) $row['mls']) === $source) {
                        return $row;
                    }
                }
            }
            return $rows[0];
        }

        // 2) Fallback: documented "Get by MLS Number". valerts is brand-scoped and
        //    misses cross-brand / lease listings (and may reject the query outright),
        //    so this always runs when valerts yields nothing. No brand header — like
        //    GET /listings/{uuid}, it resolves any listing globally.
        return $this->fetch_listing_via_search($mls, $token);
    }

    /**
     * Brand-scoped valerts search rows for an MLS number ([] on any failure).
     *
     * @param string $mls    Normalized MLS number.
     * @param string $source Normalized MLS source (may be '').
     * @param string $token  OAuth access token.
     * @param string $brand  Brand id.
     * @return array<int, array<string, mixed>>
     */
    protected function fetch_valerts_rows($mls, $source, $token, $brand)
    {
        $endpoint = rtrim(RECHAT_API_BASE_URL, '/') . '/valerts';

        $default_body = array(
            'mls_number' => array($mls),
            'limit'      => $source !== '' ? 10 : 1,
            'offset'     => 0,
        );

        /**
         * Filter the search body sent to Rechat for an MLS lookup.
         *
         * @param array  $body   The request body.
         * @param string $mls    Normalized MLS number.
         * @param string $source Normalized MLS source (may be '').
         */
        $body = apply_filters('rch_mls_lookup_request_body', $default_body, $mls, $source);

        $response = wp_remote_post(
            $endpoint,
            array(
                'method'  => 'POST',
                'timeout' => 15,
                'headers' => array(
                    'Content-Type'   => 'application/json',
                    'X-RECHAT-BRAND' => $brand,
                    'Authorization'  => 'Bearer ' . $token,
                ),
                'body'    => wp_json_encode($body),
            )
        );

        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            return array();
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (! is_array($data) || empty($data['data']) || ! is_array($data['data'])) {
            return array();
        }

        return array_values(array_filter($data['data'], 'is_array'));
    }

    /**
     * Fallback lookup via GET /listings/search?mls_number= (the documented
     * "Get by MLS Number" endpoint). Marked for deprecation 2026-01-01, but it is
     * the only call that resolves an arbitrary listing by its MLS number today.
     * Sent without a brand header so cross-brand listings resolve, matching how
     * GET /listings/{uuid} is fetched.
     *
     * @param string $mls   Normalized MLS number.
     * @param string $token OAuth access token.
     * @return array<string, mixed>|null
     */
    protected function fetch_listing_via_search($mls, $token)
    {
        if (! function_exists('rch_api_request')) {
            return null;
        }

        $url = rtrim(RECHAT_API_BASE_URL, '/') . '/listings/search?mls_number=' . rawurlencode($mls);
        $response = rch_api_request($url, $token);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'Rechat MLS: /listings/search?mls_number=%s -> success=%s http=%s keys=%s',
                $mls,
                ! empty($response['success']) ? '1' : '0',
                isset($response['response_code']) ? (string) $response['response_code'] : '?',
                (isset($response['data']) && is_array($response['data'])) ? implode(',', array_keys($response['data'])) : 'none'
            ));
        }

        if (empty($response['success']) || ! isset($response['data']) || ! is_array($response['data'])) {
            return null;
        }

        $payload = isset($response['data']['data']) ? $response['data']['data'] : null;
        if (! is_array($payload)) {
            return null;
        }

        // Endpoint may return a single listing object or a list.
        if (isset($payload['id'])) {
            return $payload;
        }

        $rows = array_values(array_filter($payload, 'is_array'));
        return empty($rows) ? null : $rows[0];
    }

    /**
     * Build a listing-detail URL from a Rechat listing array.
     *
     * Prefers the canonical `/listing-detail/{city}/{street}/{id}/`. When the city
     * is unavailable it falls back to the legacy `/listing-detail/{street}/{id}/`
     * form, which {@see fetch-single-listing.php} 301-redirects to the canonical
     * URL — so we never duplicate the address-slug logic.
     *
     * @param array<string, mixed> $listing Listing array from Rechat.
     * @return string URL, or '' when the listing has no id.
     */
    protected function build_listing_url($listing)
    {
        $id = isset($listing['id']) ? sanitize_text_field((string) $listing['id']) : '';
        if ($id === '') {
            return '';
        }

        $street = '';
        if (! empty($listing['formatted']['street_address']['text'])) {
            $street = (string) $listing['formatted']['street_address']['text'];
        }

        $city = $this->extract_city($listing);

        $street_slug = $street !== '' ? sanitize_title($street) : 'listing';

        if ($city !== '') {
            $path = '/listing-detail/' . sanitize_title($city) . '/' . $street_slug . '/' . rawurlencode($id) . '/';
        } else {
            // Legacy two-segment form; the single-listing template canonicalizes it.
            $path = '/listing-detail/' . $street_slug . '/' . rawurlencode($id) . '/';
        }

        return home_url($path);
    }

    /**
     * Best-effort city name from a listing array (mirrors fetch-single-listing.php).
     *
     * @param array<string, mixed> $listing Listing array.
     * @return string City name or ''.
     */
    protected function extract_city($listing)
    {
        if (! empty($listing['property']['address']['city'])) {
            return (string) $listing['property']['address']['city'];
        }
        if (! empty($listing['address']['city'])) {
            return (string) $listing['address']['city'];
        }
        if (! empty($listing['formatted']['full_address']['text'])) {
            $parts = array_map('trim', explode(',', (string) $listing['formatted']['full_address']['text']));
            if (count($parts) >= 2) {
                return $parts[1];
            }
        }

        return '';
    }
}
