<?php
/**
 * MLS redirect: request parsing + validation.
 *
 * Single responsibility: turn the current front-end request into a normalized,
 * validated MLS number (or an empty string when the request is not an MLS lookup).
 *
 * @package Rechat
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Captures and validates the MLS number for the current request.
 */
class RCH_MLS_Request
{
    /**
     * Query var populated by the /mls/ and /id/ rewrite rules.
     */
    const QUERY_VAR = 'rch_mls';

    /**
     * Normalize + validate a raw MLS candidate.
     *
     * MLS numbers are letters, digits and optional hyphens, case-insensitive.
     * We normalize to uppercase and require at least one digit and a sane length
     * so that ordinary word slugs (e.g. "about", "contact") can never be treated
     * as MLS numbers on the bare `/#{mls}` route.
     *
     * @param string $raw Raw value from the URL.
     * @return string Normalized MLS number, or '' when invalid.
     */
    public function normalize($raw)
    {
        $raw = is_scalar($raw) ? (string) $raw : '';
        // Strip slashes, decode, and remove anything that is not part of an MLS id.
        $candidate = sanitize_text_field(wp_unslash($raw));
        $candidate = trim($candidate);

        if ($candidate === '') {
            return '';
        }

        $candidate = strtoupper($candidate);

        // Allowed characters only: A-Z, 0-9, hyphen.
        if (! preg_match('/^[A-Z0-9\-]{5,32}$/', $candidate)) {
            return '';
        }

        // Must contain at least one digit (every real MLS number does). This keeps
        // the bare `/#{mls}` fallback from matching alphabetic page slugs.
        if (! preg_match('/[0-9]/', $candidate)) {
            return '';
        }

        /**
         * Filter the normalized MLS number before lookup.
         *
         * @param string $candidate Normalized MLS number.
         * @param string $raw       Original raw value.
         */
        return (string) apply_filters('rch_mls_normalize', $candidate, $raw);
    }

    /**
     * MLS number coming from an explicit /mls/ or /id/ route (query var).
     *
     * @return string Normalized MLS number or ''.
     */
    public function from_query_var()
    {
        return $this->normalize(get_query_var(self::QUERY_VAR, ''));
    }

    /**
     * Normalize + validate an MLS source/board name (e.g. "NTREIS", the listing `mls` field).
     *
     * Source names are letter-led alphanumerics. Requiring a leading letter keeps a
     * numeric first segment from being mistaken for a source on the two-segment route.
     *
     * @param string $raw Raw value from the URL.
     * @return string Normalized source, or '' when invalid.
     */
    public function normalize_source($raw)
    {
        $raw = is_scalar($raw) ? (string) $raw : '';
        $candidate = strtoupper(trim(sanitize_text_field(wp_unslash($raw))));

        if (! preg_match('/^[A-Z][A-Z0-9]{1,15}$/', $candidate)) {
            return '';
        }

        return $candidate;
    }

    /**
     * Current unresolved (404) request path split into trimmed segments.
     *
     * @return string[] Segments, or [] when unavailable.
     */
    protected function unresolved_segments()
    {
        if (empty($GLOBALS['wp']) || ! isset($GLOBALS['wp']->request)) {
            return array();
        }

        $path = trim((string) $GLOBALS['wp']->request, '/');
        if ($path === '') {
            return array();
        }

        return explode('/', $path);
    }

    /**
     * MLS number from a bare top-level 404 request (e.g. /A12053930).
     *
     * Only trusted when WordPress could not resolve the URL to any route, so real
     * pages, posts, and taxonomy terms are never intercepted.
     *
     * @return string Normalized MLS number or ''.
     */
    public function from_unresolved_path()
    {
        $segments = $this->unresolved_segments();
        if (count($segments) !== 1) {
            return '';
        }

        return $this->normalize($segments[0]);
    }

    /**
     * MLS source + number from a two-segment 404 request (e.g. /NTREIS/21191513).
     *
     * First segment = MLS source (listing `mls` field), second = MLS number. Both
     * must validate. Only trusted on an otherwise-404 request.
     *
     * @return array{source:string, mls:string}|array{} Empty array when it does not qualify.
     */
    public function from_unresolved_source_pair()
    {
        $segments = $this->unresolved_segments();
        if (count($segments) !== 2) {
            return array();
        }

        $source = $this->normalize_source($segments[0]);
        $mls    = $this->normalize($segments[1]);

        if ($source === '' || $mls === '') {
            return array();
        }

        return array('source' => $source, 'mls' => $mls);
    }
}
