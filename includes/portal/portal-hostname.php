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
 * @return array{success:bool, code?:int, body?:string, message?:string, url:string, method:string, request_body:string}
 */
function rch_portal_register_hostname(string $brand, string $token, string $hostname, bool $is_default = true): array
{
    $url  = rtrim(RECHAT_API_BASE_URL, '/') . '/brands/' . rawurlencode($brand) . '/portal/hostnames';
    $json = wp_json_encode(array(
        'hostname'   => $hostname,
        'is_default' => $is_default,
    ));

    $meta = array('url' => $url, 'method' => 'POST', 'request_body' => (string) $json);

    $response = wp_remote_post($url, array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/json',
        ),
        'body'    => $json,
        'timeout' => (int) apply_filters('rch_api_request_timeout', 20, $url),
    ));

    if (is_wp_error($response)) {
        return array_merge($meta, array('success' => false, 'message' => $response->get_error_message()));
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    $body = (string) wp_remote_retrieve_body($response);

    return array_merge($meta, array(
        'success' => $code >= 200 && $code < 300,
        'code'    => $code,
        'body'    => $body,
    ));
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
        rch_portal_log('brand', array('action' => 'skip: no token or brand id', 'brand_id' => $brand));
        return false;
    }

    $hostname = rch_get_site_hostname();
    if ($hostname === '') {
        rch_portal_log('brand', array('action' => 'skip: could not resolve site hostname', 'brand_id' => $brand));
        return false;
    }

    // Re-check the portal via the API on every run (do not trust a local cache).
    $existing = rch_portal_fetch_hostnames($brand, $token);
    if (is_array($existing) && in_array($hostname, $existing, true)) {
        update_option('rch_portal_hostname_registered', $hostname, false);
        rch_portal_log('brand', array('action' => 'skip: already registered', 'brand_id' => $brand, 'hostname' => $hostname));
        return true;
    }

    $result = rch_portal_register_hostname($brand, $token, $hostname, true);
    $code   = (int) ($result['code'] ?? 0);
    $body   = (string) ($result['body'] ?? $result['message'] ?? '');
    $req    = array(
        'req_method' => (string) ($result['method'] ?? 'POST'),
        'req_url'    => (string) ($result['url'] ?? ''),
        'req_body'   => (string) ($result['request_body'] ?? ''),
    );

    if (! empty($result['success'])) {
        update_option('rch_portal_hostname_registered', $hostname, false);
        error_log('Rechat Plugin: Registered portal hostname "' . $hostname . '" for brand ' . $brand);
        rch_portal_log('brand', array_merge(array('action' => 'registered', 'brand_id' => $brand, 'hostname' => $hostname, 'http_code' => $code, 'body' => $body), $req));
        return true;
    }

    error_log(sprintf(
        'Rechat Plugin: Failed to register portal hostname "%s" (HTTP %d): %s',
        $hostname,
        $code,
        $body
    ));
    rch_portal_log('brand', array_merge(array('action' => 'FAILED', 'brand_id' => $brand, 'hostname' => $hostname, 'http_code' => $code, 'body' => $body), $req));

    return false;
}

/**
 * Register each Multisite agent subsite's domain on that agent's Rechat portal.
 *
 * For every `agents` post on the main site that (a) has a mapped child brand id
 * (`brand_id` meta, set by "Map agent brands") and (b) is linked to a multisite
 * subsite, POST the subsite's hostname to that brand's portal. The `:brand` path
 * segment carries the mapped child `brand_id`; auth uses the brokerage main-site
 * token.
 *
 * Triggered by the "Map agent brands" admin button. Idempotent — agents flagged
 * (`_rch_portal_hostname_registered` == current hostname) are skipped.
 *
 * @param bool   $dry     When true, resolve every agent but do NOT POST — used
 *                        for the diagnostic preview.
 * @param string $trigger Context label recorded in the log ('sync','connect',
 *                        'manual',…).
 * @return array<int, array<string, mixed>> Per-agent report (also returned in
 *                   normal runs; callers may ignore it). Empty array = no-op
 *                   (single-site or no token).
 */
function rch_portal_register_agent_hostnames(bool $dry = false, string $trigger = 'manual'): array
{
    if (! is_multisite()) {
        rch_portal_log('run', array('action' => 'skip: not multisite', 'trigger' => $trigger, 'dry' => $dry));
        return array();
    }

    // Brokerage main-site access token — reused for every agent's portal call.
    $token = (string) get_option('rch_rechat_access_token', '');
    if ($token === '') {
        rch_portal_log('run', array('action' => 'skip: no access token stored', 'trigger' => $trigger, 'dry' => $dry));
        return array();
    }

    $agent_ids = get_posts(array(
        'post_type'   => 'agents',
        'numberposts' => -1,
        'fields'      => 'ids',
    ));

    rch_portal_log('run', array(
        'action'  => 'start: ' . count($agent_ids) . ' agent posts',
        'trigger' => $trigger,
        'dry'     => $dry,
    ));

    $report = array();

    foreach ($agent_ids as $post_id) {
        $row = rch_portal_process_agent((int) $post_id, $token, $dry);
        $report[] = $row;

        // Persist every decision + API response so it can be reviewed in wp-admin.
        rch_portal_log('agent', array_merge(array('trigger' => $trigger, 'dry' => $dry), $row));

        if ($row['action'] === 'registered') {
            error_log('Rechat Plugin: Registered agent portal hostname "' . $row['hostname'] . '" for agent ' . $row['agent_id']);
        } elseif ($row['action'] === 'FAILED') {
            error_log(sprintf(
                'Rechat Plugin: Failed to register agent portal hostname "%s" for agent %s (HTTP %d): %s',
                $row['hostname'],
                $row['agent_id'],
                (int) $row['http_code'],
                (string) $row['body']
            ));
        }
    }

    return $report;
}

/**
 * Meta key: hostname already registered on this agent's portal (skip flag).
 *
 * @return string
 */
function rch_portal_agent_registered_meta_key(): string
{
    return '_rch_portal_hostname_registered';
}

/**
 * Resolve and (unless dry) register one agent's subsite hostname on its portal.
 *
 * The portal is keyed by the agent's mapped **child brand id** (`brand_id` meta,
 * set by the "Map agent brands" step) — NOT the agent's Rechat id. Agents that
 * already have their current hostname registered (per the `_rch_portal_hostname_
 * registered` flag) are skipped.
 *
 * @param int    $post_id Agent post id (main-site `agents` CPT).
 * @param string $token   Brokerage access token.
 * @param bool   $dry     When true, do not POST.
 * @return array<string, mixed> Report row.
 */
function rch_portal_process_agent(int $post_id, string $token, bool $dry): array
{
    $row = array(
        'post_id'     => $post_id,
        'title'       => get_the_title($post_id),
        'agent_id'    => '',
        'brand_id'    => '',
        'subsite_url' => '',
        'hostname'    => '',
        'action'      => '',
        'http_code'   => null,
        'body'        => '',
        'req_method'  => '',
        'req_url'     => '',
        'req_body'    => '',
    );

    // Rechat agent id (reference only; kept in the log for readability).
    $row['agent_id'] = (string) get_post_meta($post_id, 'api_id', true);

    // Child brand id mapped by "Map agent brands" — used as the portal path segment.
    $brand_id = (string) get_post_meta($post_id, 'brand_id', true);
    $row['brand_id'] = $brand_id;
    if ($brand_id === '') {
        $row['action'] = 'skip: no brand_id (run “Map agent brands” first)';
        return $row;
    }

    // Domain of the agent's linked subsite (empty when not linked/enabled).
    $subsite_url = function_exists('rch_get_agent_subsite_url')
        ? rch_get_agent_subsite_url($post_id)
        : '';
    $row['subsite_url'] = $subsite_url;
    if ($subsite_url === '') {
        $row['action'] = 'skip: no linked/enabled subsite';
        return $row;
    }

    $hostname = wp_parse_url($subsite_url, PHP_URL_HOST);
    $hostname = is_string($hostname) ? strtolower($hostname) : '';
    $row['hostname'] = $hostname;
    if ($hostname === '') {
        $row['action'] = 'skip: could not parse hostname from subsite url';
        return $row;
    }

    // Skip agents already flagged as registered for this exact hostname.
    $flag = (string) get_post_meta($post_id, rch_portal_agent_registered_meta_key(), true);
    if ($flag !== '' && strtolower($flag) === $hostname) {
        $row['action'] = 'skip: already registered (flag)';
        return $row;
    }

    $req_url = rtrim(RECHAT_API_BASE_URL, '/') . '/brands/' . rawurlencode($brand_id) . '/portal/hostnames';

    if ($dry) {
        // The request the live run WOULD send (shown as copy-as-cURL in the log).
        $row['req_method'] = 'POST';
        $row['req_url']    = $req_url;
        $row['req_body']   = (string) wp_json_encode(array('hostname' => $hostname, 'is_default' => true));
        $row['action']     = 'would POST (dry run)';
        return $row;
    }

    // POST to /brands/:brand_id/portal/hostnames (mapped child brand id in the path).
    $result = rch_portal_register_hostname($brand_id, $token, $hostname, true);
    $row['http_code']  = (int) ($result['code'] ?? 0);
    $row['body']       = (string) ($result['body'] ?? $result['message'] ?? '');
    $row['action']     = ! empty($result['success']) ? 'registered' : 'FAILED';
    $row['req_method'] = (string) ($result['method'] ?? 'POST');
    $row['req_url']    = (string) ($result['url'] ?? '');
    $row['req_body']   = (string) ($result['request_body'] ?? '');

    // On success, flag the agent so future runs skip it.
    if ($row['action'] === 'registered') {
        update_post_meta($post_id, rch_portal_agent_registered_meta_key(), $hostname);
    }

    return $row;
}

/**
 * Admin-only diagnostic: dump the agent-portal hostname report as JSON.
 *
 * Visit (logged in as an admin on the MAIN site):
 *   /wp-admin/?rch_debug_agent_portals=1        -> live run (POSTs missing ones)
 *   /wp-admin/?rch_debug_agent_portals=1&dry=1  -> dry run (no POST, preview only)
 *
 * Shows, per agent: api_id, subsite url, parsed hostname, the portal's current
 * hostnames (null = GET failed / no portal), the action taken, and — on a real
 * POST — the HTTP status code and raw response body from the Rechat API.
 * Remove this handler once debugging is done.
 */
add_action('admin_init', 'rch_portal_debug_agent_hostnames');
function rch_portal_debug_agent_hostnames(): void
{
    if (! isset($_GET['rch_debug_agent_portals'])) {
        return;
    }
    if (! current_user_can('manage_options')) {
        wp_die('Insufficient permissions.');
    }

    $dry = ! empty($_GET['dry']);

    $out = array(
        'is_multisite'    => is_multisite(),
        'is_main_site'    => is_main_site(),
        'blog_id'         => get_current_blog_id(),
        'has_token'       => get_option('rch_rechat_access_token', '') !== '',
        'brand_id'        => (string) get_option('rch_rechat_brand_id', ''),
        'api_base'        => defined('RECHAT_API_BASE_URL') ? RECHAT_API_BASE_URL : '(undefined)',
        'mode'            => $dry ? 'dry-run' : 'live',
        'agents'          => rch_portal_register_agent_hostnames($dry),
    );

    header('Content-Type: application/json; charset=utf-8');
    echo wp_json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}
