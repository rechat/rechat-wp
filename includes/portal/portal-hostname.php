<?php
/**
 * Rechat portal hostname registration.
 *
 * Ensures the connected Rechat brand's portal has this site's domain registered
 * as a hostname. Runs on OAuth connect and on every data sync: it checks the
 * portal's current hostnames via the API and, if this site's domain is missing,
 * POSTs it to add it.
 *
 * API:
 *   GET  /brands/:brand/portal            -> data.hostnames[]
 *   POST /brands/:brand/portal/hostnames  -> { hostname, is_default }
 */

if (! defined('ABSPATH')) {
    exit();
}

/**
 * Site domain (host only) to register as the portal hostname.
 *
 * Uses home_url() (public Site Address). Filterable via `rch_portal_hostname`.
 *
 * @return string Lowercased host, or '' when unresolvable.
 */
function rch_get_site_hostname(): string
{
    $host = wp_parse_url(home_url('/'), PHP_URL_HOST);
    $host = is_string($host) ? strtolower($host) : '';

    return (string) apply_filters('rch_portal_hostname', $host);
}

/**
 * Fetch the current hostnames registered on the brand's portal.
 *
 * @param string $brand Rechat brand id.
 * @param string $token Access token.
 * @return array<string>|null Lowercased hostnames, or null on request failure.
 */
function rch_portal_fetch_hostnames(string $brand, string $token): ?array
{
    if ($brand === '' || $token === '') {
        return null;
    }

    $url = rtrim(RECHAT_API_BASE_URL, '/') . '/brands/' . rawurlencode($brand) . '/portal';
    $res = rch_api_request($url, $token);

    if (empty($res['success']) || ! is_array($res['data'])) {
        return null;
    }

    // API wraps payload in a "data" envelope: { code, data: { hostnames: [] } }.
    $payload   = $res['data'];
    $hostnames = $payload['data']['hostnames'] ?? $payload['hostnames'] ?? null;

    if (! is_array($hostnames)) {
        return null;
    }

    return array_map('strtolower', array_map('strval', $hostnames));
}

/**
 * POST a hostname to the brand's portal.
 *
 * @param string $brand      Rechat brand id.
 * @param string $token      Access token.
 * @param string $hostname   Hostname to add.
 * @param bool   $is_default Whether this hostname is the portal default.
 * @return array{success:bool, code?:int, body?:string, message?:string}
 */
function rch_portal_register_hostname(string $brand, string $token, string $hostname, bool $is_default = true): array
{
    $url = rtrim(RECHAT_API_BASE_URL, '/') . '/brands/' . rawurlencode($brand) . '/portal/hostnames';

    $response = wp_remote_post($url, array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/json',
        ),
        'body'    => wp_json_encode(array(
            'hostname'   => $hostname,
            'is_default' => $is_default,
        )),
        'timeout' => (int) apply_filters('rch_api_request_timeout', 20, $url),
    ));

    if (is_wp_error($response)) {
        return array('success' => false, 'message' => $response->get_error_message());
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    $body = (string) wp_remote_retrieve_body($response);

    return array(
        'success' => $code >= 200 && $code < 300,
        'code'    => $code,
        'body'    => $body,
    );
}

/**
 * Ensure this site's domain is registered on the connected brand's portal.
 *
 * Checks the portal's current hostnames via the API; if this site's domain is
 * absent, POSTs it (as the default hostname). Safe to call repeatedly — it is a
 * no-op once the domain is present.
 *
 * @return bool True when the domain is registered (already or just added).
 */
function rch_ensure_portal_hostname(): bool
{
    $token = (string) get_option('rch_rechat_access_token', '');
    $brand = (string) get_option('rch_rechat_brand_id', '');

    if ($token === '' || $brand === '') {
        return false;
    }

    $hostname = rch_get_site_hostname();
    if ($hostname === '') {
        return false;
    }

    // Re-check the portal via the API on every run (do not trust a local cache).
    $existing = rch_portal_fetch_hostnames($brand, $token);
    if (is_array($existing) && in_array($hostname, $existing, true)) {
        update_option('rch_portal_hostname_registered', $hostname, false);
        return true;
    }

    $result = rch_portal_register_hostname($brand, $token, $hostname, true);

    if (! empty($result['success'])) {
        update_option('rch_portal_hostname_registered', $hostname, false);
        error_log('Rechat Plugin: Registered portal hostname "' . $hostname . '" for brand ' . $brand);
        return true;
    }

    error_log(sprintf(
        'Rechat Plugin: Failed to register portal hostname "%s" (HTTP %d): %s',
        $hostname,
        (int) ($result['code'] ?? 0),
        (string) ($result['body'] ?? $result['message'] ?? '')
    ));

    return false;
}

/**
 * Register each Multisite agent subsite's domain on that agent's Rechat portal.
 *
 * For every `agents` post on the main site that (a) has a Rechat ID (`api_id`
 * meta — locally-added agents have none) and (b) is linked to a multisite
 * subsite, POST the subsite's hostname to the portal. Unlike the brand-level
 * call above, the `:brand` path segment carries the AGENT's Rechat ID instead
 * of the brand id (per requirement); auth uses the brokerage main-site token.
 *
 * Runs on OAuth connect and on every data sync. Idempotent — checks the agent
 * portal's current hostnames via the API and skips ones already present.
 *
 * @return void No-op on single-site or when no token is stored.
 */
function rch_portal_register_agent_hostnames(): void
{
    if (! is_multisite()) {
        return;
    }

    // Brokerage main-site access token — reused for every agent's portal call.
    $token = (string) get_option('rch_rechat_access_token', '');
    if ($token === '') {
        return;
    }

    $agent_ids = get_posts(array(
        'post_type'   => 'agents',
        'numberposts' => -1,
        'fields'      => 'ids',
    ));

    foreach ($agent_ids as $post_id) {
        // Rechat agent id from the "Rechat ID (not available for locally added
        // agents)" meta field. Skip locally-added agents (no id).
        $agent_id = (string) get_post_meta($post_id, 'api_id', true);
        if ($agent_id === '') {
            continue;
        }

        // Domain of the agent's linked subsite (empty when not linked/enabled).
        $subsite_url = function_exists('rch_get_agent_subsite_url')
            ? rch_get_agent_subsite_url((int) $post_id)
            : '';
        if ($subsite_url === '') {
            continue;
        }

        $hostname = wp_parse_url($subsite_url, PHP_URL_HOST);
        $hostname = is_string($hostname) ? strtolower($hostname) : '';
        if ($hostname === '') {
            continue;
        }

        // Skip when this hostname is already on the agent portal (idempotent).
        $existing = rch_portal_fetch_hostnames($agent_id, $token);
        if (is_array($existing) && in_array($hostname, $existing, true)) {
            continue;
        }

        // POST to /brands/:agent_id/portal/hostnames (agent id in the path).
        $result = rch_portal_register_hostname($agent_id, $token, $hostname, true);

        if (! empty($result['success'])) {
            error_log('Rechat Plugin: Registered agent portal hostname "' . $hostname . '" for agent ' . $agent_id);
            continue;
        }

        error_log(sprintf(
            'Rechat Plugin: Failed to register agent portal hostname "%s" for agent %s (HTTP %d): %s',
            $hostname,
            $agent_id,
            (int) ($result['code'] ?? 0),
            (string) ($result['body'] ?? $result['message'] ?? '')
        ));
    }
}
