<?php
/**
 * Lead form anti-spam.
 *
 * Server-side spam defenses shared by every lead form (shortcode, block, listing
 * single, agent single). Layers: honeypot, time-trap, field validation, per-IP
 * rate limit, origin check, disposable-email block, and CAPTCHA (Cloudflare
 * Turnstile or reCAPTCHA v3). All run in one place so every form is protected the
 * same way.
 *
 * @package RechatPlugin
 */

if (! defined('ABSPATH')) {
    exit;
}

/** Hidden honeypot field name (looks plausible so bots fill it). */
const RCH_LEAD_HP_FIELD = 'rch_website_url';

/** Signed render-time field name (time-trap). */
const RCH_LEAD_TS_FIELD = 'rch_form_ts';

/**
 * CAPTCHA settings from options.
 *
 * @return array{provider:string, site_key:string, secret_key:string}
 */
function rch_lead_antispam_captcha_config(): array
{
    $provider = (string) get_option('rch_lead_captcha_provider', 'none');
    if (! in_array($provider, ['turnstile', 'recaptcha_v3'], true)) {
        $provider = 'none';
    }

    if ($provider === 'turnstile') {
        $site   = (string) get_option('rch_lead_turnstile_site_key', '');
        $secret = (string) get_option('rch_lead_turnstile_secret_key', '');
    } elseif ($provider === 'recaptcha_v3') {
        $site   = (string) get_option('rch_lead_recaptcha_site_key', '');
        $secret = (string) get_option('rch_lead_recaptcha_secret_key', '');
    } else {
        $site   = '';
        $secret = '';
    }

    return ['provider' => $provider, 'site_key' => $site, 'secret_key' => $secret];
}

/** CAPTCHA is active only when a provider is chosen and both keys are set. */
function rch_lead_antispam_captcha_active(): bool
{
    $c = rch_lead_antispam_captcha_config();
    return $c['provider'] !== 'none' && $c['site_key'] !== '' && $c['secret_key'] !== '';
}

/**
 * Signed value for the time-trap hidden field (timestamp + HMAC).
 *
 * @return string
 */
function rch_lead_antispam_timestamp_value(): string
{
    $t   = (string) time();
    $sig = hash_hmac('sha256', $t, wp_salt('nonce'));
    return $t . '.' . $sig;
}

/**
 * Verify the time-trap value: valid signature, and submitted no faster than the
 * minimum and no older than the maximum window.
 *
 * @param string $value Raw field value.
 * @return bool
 */
function rch_lead_antispam_verify_timestamp(string $value): bool
{
    $parts = explode('.', $value, 2);
    if (count($parts) !== 2) {
        return false;
    }
    [$t, $sig] = $parts;
    if ($t === '' || ! ctype_digit($t)) {
        return false;
    }
    $expected = hash_hmac('sha256', $t, wp_salt('nonce'));
    if (! hash_equals($expected, (string) $sig)) {
        return false;
    }

    $elapsed = time() - (int) $t;
    $min     = (int) apply_filters('rch_lead_antispam_min_seconds', 3);
    // Generous max so full-page-cached pages (Kinsta) still submit; the signed
    // timestamp is what matters, and the min-time check is what catches bots.
    $max     = (int) apply_filters('rch_lead_antispam_max_seconds', WEEK_IN_SECONDS);

    // Clock skew (future timestamp) is not a bot signal; treat as just-past the min.
    if ($elapsed < 0) {
        $elapsed = $min;
    }

    return $elapsed >= $min && $elapsed <= $max;
}

/**
 * Best-effort client IP (honors a single proxy hop; Kinsta forwards the real IP).
 *
 * @return string
 */
function rch_lead_antispam_client_ip(): string
{
    $candidates = [];
    if (! empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $candidates[] = $_SERVER['HTTP_CF_CONNECTING_IP'];
    }
    if (! empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);
        $candidates[] = trim($parts[0]);
    }
    if (! empty($_SERVER['REMOTE_ADDR'])) {
        $candidates[] = $_SERVER['REMOTE_ADDR'];
    }

    foreach ($candidates as $ip) {
        $ip = trim((string) $ip);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }

    return '';
}

/**
 * Per-IP rate limit. Returns true when the request is under the limit (and counts it).
 *
 * @return bool
 */
function rch_lead_antispam_rate_limit_ok(): bool
{
    $limit = (int) apply_filters('rch_lead_antispam_rate_limit', 30);
    if ($limit <= 0) {
        return true; // Rate limiting disabled.
    }

    $ip = rch_lead_antispam_client_ip();
    if ($ip === '') {
        return true; // Cannot identify; do not block.
    }

    $key   = 'rch_lead_rl_' . md5($ip);
    $count = (int) get_transient($key);
    if ($count >= $limit) {
        return false;
    }

    set_transient($key, $count + 1, HOUR_IN_SECONDS);
    return true;
}

/** Request Origin/Referer host matches this site's host. */
function rch_lead_antispam_origin_ok(): bool
{
    if (! apply_filters('rch_lead_antispam_check_origin', true)) {
        return true;
    }

    $site_host = wp_parse_url(home_url(), PHP_URL_HOST);
    $source    = '';
    if (! empty($_SERVER['HTTP_ORIGIN'])) {
        $source = (string) $_SERVER['HTTP_ORIGIN'];
    } elseif (! empty($_SERVER['HTTP_REFERER'])) {
        $source = (string) $_SERVER['HTTP_REFERER'];
    }

    if ($source === '') {
        // Some privacy setups strip Origin/Referer; do not hard-block on absence.
        return true;
    }

    $source_host = wp_parse_url($source, PHP_URL_HOST);
    return is_string($source_host) && strcasecmp((string) $site_host, $source_host) === 0;
}

/**
 * Disposable / throwaway email domains (filterable).
 *
 * @return string[]
 */
function rch_lead_antispam_disposable_domains(): array
{
    return (array) apply_filters('rch_lead_antispam_disposable_domains', [
        'mailinator.com', 'guerrillamail.com', 'guerrillamail.info', 'sharklasers.com',
        '10minutemail.com', 'tempmail.com', 'temp-mail.org', 'trashmail.com',
        'yopmail.com', 'getnada.com', 'dispostable.com', 'maildrop.cc',
        'fakeinbox.com', 'throwawaymail.com', 'mohmal.com', 'emailondeck.com',
    ]);
}

/** True when the email's domain is a known disposable provider. */
function rch_lead_antispam_is_disposable_email(string $email): bool
{
    $at = strrpos($email, '@');
    if ($at === false) {
        return false;
    }
    $domain = strtolower(substr($email, $at + 1));
    return in_array($domain, rch_lead_antispam_disposable_domains(), true);
}

/** True when the text contains a URL (common in link spam). */
function rch_lead_antispam_has_link(string $text): bool
{
    return (bool) preg_match('#https?://|www\.|\[url|<a\s#i', $text);
}

/**
 * Verify a CAPTCHA token with the configured provider.
 *
 * @param string $token Client CAPTCHA response token.
 * @param string $ip    Client IP.
 * @return bool
 */
function rch_lead_antispam_verify_captcha(string $token, string $ip): bool
{
    $config = rch_lead_antispam_captcha_config();
    if ($token === '') {
        return false;
    }

    if ($config['provider'] === 'turnstile') {
        $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    } elseif ($config['provider'] === 'recaptcha_v3') {
        $url = 'https://www.google.com/recaptcha/api/siteverify';
    } else {
        return true; // No provider = nothing to verify.
    }

    $response = wp_remote_post($url, [
        'timeout' => 10,
        'body'    => [
            'secret'   => $config['secret_key'],
            'response' => $token,
            'remoteip' => $ip,
        ],
    ]);

    if (is_wp_error($response)) {
        // Fail open on transport error so a CAPTCHA outage never blocks real leads.
        return (bool) apply_filters('rch_lead_antispam_captcha_fail_open', true);
    }

    $data = json_decode((string) wp_remote_retrieve_body($response), true);
    if (! is_array($data) || empty($data['success'])) {
        return false;
    }

    // reCAPTCHA v3 returns a score; enforce a minimum threshold.
    if ($config['provider'] === 'recaptcha_v3' && isset($data['score'])) {
        $threshold = (float) apply_filters('rch_lead_antispam_recaptcha_threshold', 0.5);
        return (float) $data['score'] >= $threshold;
    }

    return true;
}

/**
 * Run every anti-spam layer against a submitted lead.
 *
 * @param array $post Raw request data ($_POST).
 * @return array{ok:bool, silent?:bool, code?:string, message?:string}
 *   ok=true → let it through. ok=false + silent=true → drop but fake success
 *   (honeypot/timing, so bots do not learn). ok=false + silent=false → show error.
 */
function rch_lead_antispam_check(array $post): array
{
    // 1) Honeypot — a real user never fills a hidden field. Silent drop.
    if (! empty($post[ RCH_LEAD_HP_FIELD ])) {
        return ['ok' => false, 'silent' => true, 'code' => 'honeypot'];
    }

    // 2) Time-trap — too fast / stale / forged. Silent drop.
    $ts = isset($post[ RCH_LEAD_TS_FIELD ]) ? (string) $post[ RCH_LEAD_TS_FIELD ] : '';
    if (! rch_lead_antispam_verify_timestamp($ts)) {
        return ['ok' => false, 'silent' => true, 'code' => 'timing'];
    }

    // 3) Origin check.
    if (! rch_lead_antispam_origin_ok()) {
        return ['ok' => false, 'silent' => true, 'code' => 'origin'];
    }

    // 4) Field validation.
    $email = isset($post['email']) ? sanitize_email(wp_unslash((string) $post['email'])) : '';
    if ($email === '' || ! is_email($email)) {
        return ['ok' => false, 'silent' => false, 'code' => 'email', 'message' => __('Please enter a valid email address.', 'rechat-plugin')];
    }
    if (rch_lead_antispam_is_disposable_email($email)) {
        return ['ok' => false, 'silent' => false, 'code' => 'disposable', 'message' => __('Please use a non-disposable email address.', 'rechat-plugin')];
    }

    $first = isset($post['first_name']) ? trim((string) wp_unslash($post['first_name'])) : '';
    $last  = isset($post['last_name']) ? trim((string) wp_unslash($post['last_name'])) : '';
    $note  = isset($post['note']) ? trim((string) wp_unslash($post['note'])) : '';

    // Links in a name = spam. Silent drop.
    if (rch_lead_antispam_has_link($first) || rch_lead_antispam_has_link($last)) {
        return ['ok' => false, 'silent' => true, 'code' => 'name_link'];
    }
    // Excessive length or many links in note.
    if (mb_strlen($note) > (int) apply_filters('rch_lead_antispam_max_note_length', 2000)) {
        return ['ok' => false, 'silent' => true, 'code' => 'note_length'];
    }
    if (preg_match_all('#https?://#i', $note, $m) && count($m[0]) > (int) apply_filters('rch_lead_antispam_max_note_links', 2)) {
        return ['ok' => false, 'silent' => true, 'code' => 'note_links'];
    }

    // 5) CAPTCHA (only when configured).
    if (rch_lead_antispam_captcha_active()) {
        $token = '';
        foreach (['cf-turnstile-response', 'g-recaptcha-response', 'rch_captcha_token'] as $field) {
            if (! empty($post[ $field ])) {
                $token = (string) wp_unslash($post[ $field ]);
                break;
            }
        }
        if (! rch_lead_antispam_verify_captcha($token, rch_lead_antispam_client_ip())) {
            return ['ok' => false, 'silent' => false, 'code' => 'captcha', 'message' => __('Anti-spam verification failed. Please try again.', 'rechat-plugin')];
        }
    }

    // 6) Rate limit (last, so it only counts requests that passed everything else).
    if (! rch_lead_antispam_rate_limit_ok()) {
        return ['ok' => false, 'silent' => false, 'code' => 'rate_limit', 'message' => __('Too many submissions. Please try again later.', 'rechat-plugin')];
    }

    return ['ok' => true];
}

/**
 * Output the hidden anti-spam fields (honeypot + signed timestamp) inside a form.
 * Call within every lead <form>.
 *
 * @return void
 */
function rch_lead_antispam_render_fields(): void
{
    // Honeypot: off-screen, not a tab stop, autocomplete off. Bots fill it; humans never see it.
    printf(
        '<div aria-hidden="true" style="position:absolute!important;left:-9999px!important;top:auto;width:1px;height:1px;overflow:hidden;">'
        . '<label>%1$s<input type="text" name="%2$s" tabindex="-1" autocomplete="off" value="" /></label>'
        . '</div>',
        esc_html__('Leave this field empty', 'rechat-plugin'),
        esc_attr(RCH_LEAD_HP_FIELD)
    );

    printf(
        '<input type="hidden" name="%1$s" value="%2$s" />',
        esc_attr(RCH_LEAD_TS_FIELD),
        esc_attr(rch_lead_antispam_timestamp_value())
    );
}

/**
 * Output the CAPTCHA widget markup for a form (no-op when CAPTCHA is inactive).
 *
 * Turnstile renders a widget that injects a `cf-turnstile-response` input into the
 * form. reCAPTCHA v3 is invisible; the token is fetched on submit by the form JS.
 *
 * @return void
 */
function rch_lead_antispam_render_captcha(): void
{
    if (! rch_lead_antispam_captcha_active()) {
        return;
    }
    $config = rch_lead_antispam_captcha_config();

    if ($config['provider'] === 'turnstile') {
        printf(
            '<div class="cf-turnstile rch-lead-captcha" data-sitekey="%s"></div>',
            esc_attr($config['site_key'])
        );
    }
    // reCAPTCHA v3 needs no markup; the token is obtained via grecaptcha.execute().
}

/**
 * Register + enqueue the CAPTCHA provider script and expose config to JS.
 * Safe to call on every front-end page that renders a lead form.
 *
 * @return void
 */
function rch_lead_antispam_enqueue_captcha(): void
{
    if (! rch_lead_antispam_captcha_active()) {
        return;
    }
    $config = rch_lead_antispam_captcha_config();

    if ($config['provider'] === 'turnstile') {
        wp_enqueue_script(
            'rch-turnstile',
            'https://challenges.cloudflare.com/turnstile/v0/api.js',
            [],
            null,
            true
        );
    } elseif ($config['provider'] === 'recaptcha_v3') {
        wp_enqueue_script(
            'rch-recaptcha',
            'https://www.google.com/recaptcha/api.js?render=' . rawurlencode($config['site_key']),
            [],
            null,
            true
        );
    }

    // Config for the shared submit helper (window.rchLeadCaptchaCfg).
    $inline = 'window.rchLeadCaptchaCfg = ' . wp_json_encode([
        'provider' => $config['provider'],
        'siteKey'  => $config['site_key'],
    ]) . ';';
    wp_register_script('rch-lead-captcha-cfg', '');
    wp_enqueue_script('rch-lead-captcha-cfg');
    wp_add_inline_script('rch-lead-captcha-cfg', $inline, 'before');
    wp_add_inline_script('rch-lead-captcha-cfg', rch_lead_antispam_token_helper_inline(), 'after');
}

/**
 * JS token helper source (window.rchLeadToken(form) → Promise<string>).
 * Turnstile's token is already in the form (cf-turnstile-response); reCAPTCHA v3
 * is fetched on demand. Returns '' when no CAPTCHA is configured.
 *
 * @return string
 */
function rch_lead_antispam_token_helper_inline(): string
{
    return <<<'JS'
window.rchLeadToken = function (form) {
    var cfg = window.rchLeadCaptchaCfg || {};
    return new Promise(function (resolve) {
        if (cfg.provider === 'recaptcha_v3' && window.grecaptcha && cfg.siteKey) {
            try {
                window.grecaptcha.ready(function () {
                    window.grecaptcha.execute(cfg.siteKey, { action: 'lead' })
                        .then(resolve).catch(function () { resolve(''); });
                });
            } catch (e) { resolve(''); }
            return;
        }
        resolve(''); // Turnstile token is already posted via the form field.
    });
};
JS;
}

/**
 * Output the hidden fields every rerouted lead form needs: action, nonce, config
 * (channel/assignee/tags/listing/mls), honeypot, timestamp, and CAPTCHA widget.
 *
 * @param array $args { lead_channel, assignee_email, tags_json, listing_id, mlsid }
 * @return void
 */
function rch_lead_form_hidden_fields(array $args = array()): void
{
    echo '<input type="hidden" name="action" value="rch_submit_lead_rechat_api" />';
    wp_nonce_field('rch_lead_form', 'rch_lead_nonce_field');

    foreach (array('lead_channel', 'assignee_email', 'tags_json', 'listing_id', 'mlsid') as $key) {
        if (isset($args[$key]) && $args[$key] !== '') {
            printf(
                '<input type="hidden" name="%s" value="%s" />',
                esc_attr($key),
                esc_attr((string) $args[$key])
            );
        }
    }

    rch_lead_antispam_render_fields();
    rch_lead_antispam_render_captcha();
}
