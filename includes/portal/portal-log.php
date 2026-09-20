<?php
/**
 * Rechat portal request log.
 *
 * A small, persistent, admin-viewable log of every portal hostname operation
 * (brand-level and per-agent): the decision taken (registered / skipped / failed
 * and WHY) plus the raw Rechat API response (HTTP code + body) so failures can be
 * diagnosed from wp-admin without hunting through PHP error logs.
 *
 * Storage: a single capped option on the current site (`rch_portal_log`). On
 * Multisite the portal calls run on the main/hub site, so the log lives there and
 * the "Portal Log" settings tab (also hub-only) reads the same option.
 */

if (! defined('ABSPATH')) {
    exit();
}

/**
 * Option name holding the log (array of entries, oldest first).
 */
function rch_portal_log_option_name(): string
{
    return 'rch_portal_log';
}

/**
 * Max entries kept. Older entries are trimmed off the front.
 */
function rch_portal_log_max(): int
{
    return (int) apply_filters('rch_portal_log_max', 300);
}

/**
 * Append one entry to the portal log.
 *
 * @param string               $event Short event key, e.g. 'agent', 'brand', 'run'.
 * @param array<string, mixed> $data  Arbitrary fields (agent_id, hostname, action,
 *                                     http_code, body, url, trigger, dry, …).
 * @return void
 */
function rch_portal_log(string $event, array $data = array()): void
{
    $name = rch_portal_log_option_name();
    $log  = get_option($name, array());
    if (! is_array($log)) {
        $log = array();
    }

    // Truncate any long response body so the option stays small.
    if (isset($data['body']) && is_string($data['body']) && strlen($data['body']) > 2000) {
        $data['body'] = substr($data['body'], 0, 2000) . '…[truncated]';
    }

    $entry = array_merge(
        array(
            'time'  => current_time('mysql'),
            'event' => $event,
        ),
        $data
    );

    $log[] = $entry;

    $max = rch_portal_log_max();
    if (count($log) > $max) {
        $log = array_slice($log, -$max);
    }

    // autoload = false: only read on the settings screen / during a portal run.
    update_option($name, $log, false);
}

/**
 * Return all log entries (oldest first).
 *
 * @return array<int, array<string, mixed>>
 */
function rch_portal_log_get(): array
{
    $log = get_option(rch_portal_log_option_name(), array());
    return is_array($log) ? $log : array();
}

/**
 * Clear the log.
 *
 * @return void
 */
function rch_portal_log_clear(): void
{
    delete_option(rch_portal_log_option_name());
}

/**
 * Handle the "Clear log" form submit (admin-post).
 *
 * @return void
 */
function rch_portal_log_handle_clear(): void
{
    if (! current_user_can('manage_options')) {
        wp_die(esc_html__('Insufficient permissions.', 'rechat-plugin'));
    }
    check_admin_referer('rch_portal_log_clear', 'rch_portal_log_clear_nonce');

    rch_portal_log_clear();

    wp_safe_redirect(add_query_arg(
        array('page' => 'rechat-setting', 'tab' => 'portal-log', 'cleared' => '1'),
        admin_url('admin.php')
    ));
    exit;
}
add_action('admin_post_rch_portal_log_clear', 'rch_portal_log_handle_clear');

/**
 * Build a copy-pasteable cURL command reproducing a logged portal request.
 *
 * The live access token is injected here at render time (from options) — it is
 * NOT stored in the log entry, so secrets never persist in the DB. Returns ''
 * when the entry recorded no request (e.g. a pre-request skip).
 *
 * @param array<string, mixed> $e     Log entry.
 * @param string               $token Live access token to embed.
 * @return string
 */
function rch_portal_log_build_curl(array $e, string $token): string
{
    $url = (string) ($e['req_url'] ?? '');
    if ($url === '') {
        return '';
    }

    $method = strtoupper((string) ($e['req_method'] ?? 'POST'));
    $body   = (string) ($e['req_body'] ?? '');

    // Single-quote for POSIX shells, escaping any embedded single quote.
    $q = static function (string $s): string {
        return "'" . str_replace("'", "'\\''", $s) . "'";
    };

    $parts   = array();
    $parts[] = 'curl -X ' . $method . ' ' . $q($url);
    $parts[] = '-H ' . $q('Authorization: Bearer ' . ($token !== '' ? $token : '<ACCESS_TOKEN>'));
    if ($body !== '') {
        $parts[] = '-H ' . $q('Content-Type: application/json');
        $parts[] = '-d ' . $q($body);
    }

    return implode(" \\\n  ", $parts);
}

/**
 * Render the "Portal Log" settings tab.
 *
 * @return void
 */
function rch_portal_log_render_tab(): void
{
    if (! current_user_can('manage_options')) {
        return;
    }

    $entries = rch_portal_log_get();
    $entries = array_reverse($entries); // newest first for display

    // Live token injected into the copy-as-cURL commands (not stored in the log).
    $curl_token = (string) get_option('rch_rechat_access_token', '');

    // Total agents (all non-trashed statuses) for the batch-run progress bar.
    $agent_counts = (array) wp_count_posts('agents');
    $total_agents = 0;
    foreach (array('publish', 'draft', 'pending', 'private', 'future') as $st) {
        $total_agents += isset($agent_counts[$st]) ? (int) $agent_counts[$st] : 0;
    }
    ?>
    <div class="tab-content">
        <div class="rch-tab-intro">
            <h2>
                <span class="dashicons dashicons-list-view" aria-hidden="true"></span>
                <?php esc_html_e('Portal request log', 'rechat-plugin'); ?>
            </h2>
            <p><?php esc_html_e('Every brand and agent portal-hostname operation is recorded here: what was attempted, why it was skipped, and the raw Rechat API response (HTTP code + body) when it failed.', 'rechat-plugin'); ?></p>
        </div>

        <?php if (isset($_GET['cleared'])) : ?>
            <div class="notice notice-success inline" style="margin:0 0 16px;"><p><?php esc_html_e('Log cleared.', 'rechat-plugin'); ?></p></div>
        <?php endif; ?>
        <?php if (isset($_GET['ran'])) : ?>
            <div class="notice notice-success inline" style="margin:0 0 16px;"><p><?php esc_html_e('Portal hostname run completed. See entries below.', 'rechat-plugin'); ?></p></div>
        <?php endif; ?>

        <div class="rch-card">
            <div class="rch-card__body">
                <p style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                    <button type="button" class="button button-secondary" id="rch-portal-run-dry" data-dry="1">
                        <span class="dashicons dashicons-search" aria-hidden="true" style="margin-top:4px;"></span>
                        <?php esc_html_e('Run diagnostic (dry — no API write)', 'rechat-plugin'); ?>
                    </button>
                    <button type="button" class="button button-primary" id="rch-portal-run-live" data-dry="0">
                        <span class="dashicons dashicons-update" aria-hidden="true" style="margin-top:4px;"></span>
                        <?php esc_html_e('Run now (live — registers hostnames)', 'rechat-plugin'); ?>
                    </button>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                        <input type="hidden" name="action" value="rch_portal_log_clear">
                        <?php wp_nonce_field('rch_portal_log_clear', 'rch_portal_log_clear_nonce'); ?>
                        <button type="submit" class="button" onclick="return confirm('<?php echo esc_js(__('Clear the portal log?', 'rechat-plugin')); ?>');">
                            <span class="dashicons dashicons-trash" aria-hidden="true" style="margin-top:4px;"></span>
                            <?php esc_html_e('Clear log', 'rechat-plugin'); ?>
                        </button>
                    </form>
                </p>

                <p class="description" style="margin:0 0 8px;">
                    <?php
                    printf(
                        /* translators: %d: agent count */
                        esc_html__('Runs in small batches (20 agents at a time) so large sites don’t time out. %d agent(s) total.', 'rechat-plugin'),
                        (int) $total_agents
                    );
                    ?>
                </p>

                <div id="rch-portal-run-progress"
                     data-total="<?php echo esc_attr((string) $total_agents); ?>"
                     data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
                     data-nonce="<?php echo esc_attr(wp_create_nonce('rch_ajax_nonce')); ?>"
                     style="display:none;margin:0 0 12px;max-width:520px;">
                    <div style="background:#e2e4e7;border-radius:4px;overflow:hidden;height:18px;">
                        <div id="rch-portal-run-bar" style="height:100%;width:0;background:#2271b1;transition:width .2s;"></div>
                    </div>
                    <p id="rch-portal-run-msg" style="margin:6px 0 0;font-weight:600;"></p>
                </div>

                <script>
                document.addEventListener('DOMContentLoaded', function () {
                    var box = document.getElementById('rch-portal-run-progress');
                    if (!box) { return; }
                    var bar = document.getElementById('rch-portal-run-bar');
                    var msg = document.getElementById('rch-portal-run-msg');
                    var dryBtn = document.getElementById('rch-portal-run-dry');
                    var liveBtn = document.getElementById('rch-portal-run-live');
                    var total = parseInt(box.getAttribute('data-total'), 10) || 0;
                    var AJAX_URL = box.getAttribute('data-ajax-url');
                    var NONCE = box.getAttribute('data-nonce');
                    var BATCH = 20;

                    function setEnabled(on) { if (dryBtn) dryBtn.disabled = !on; if (liveBtn) liveBtn.disabled = !on; }

                    function run(dry) {
                        box.style.display = 'block';
                        bar.style.width = '0';
                        setEnabled(false);
                        var offset = 0, reg = 0, fail = 0, skip = 0;

                        function step() {
                            var d = new URLSearchParams();
                            d.append('action', 'rch_portal_run_batch');
                            d.append('nonce', NONCE);
                            d.append('offset', offset);
                            d.append('batch', BATCH);
                            d.append('dry', dry);
                            fetch(AJAX_URL, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: d.toString() })
                                .then(function (r) { return r.json(); })
                                .then(function (res) {
                                    if (!res || !res.success) {
                                        msg.style.color = '#b32d2e';
                                        msg.textContent = 'Error: ' + ((res && res.data) || 'unknown');
                                        setEnabled(true);
                                        return;
                                    }
                                    var x = res.data;
                                    offset = x.next_offset; reg += x.registered; fail += x.failed; skip += x.skipped;
                                    var pct = total > 0 ? Math.min(100, Math.round(offset / total * 100)) : 100;
                                    bar.style.width = pct + '%';
                                    msg.style.color = '#1d2327';
                                    msg.textContent = 'Processed ' + offset + (total ? (' / ' + total) : '') + ' — registered ' + reg + ', failed ' + fail + ', skipped ' + skip;
                                    if (x.done) {
                                        bar.style.width = '100%';
                                        msg.textContent += ' — DONE. Reloading…';
                                        setEnabled(true);
                                        setTimeout(function () { window.location.href = '<?php echo esc_js(admin_url('admin.php?page=rechat-setting&tab=portal-log&ran=1')); ?>'; }, 1500);
                                    } else {
                                        step();
                                    }
                                })
                                .catch(function () { msg.style.color = '#b32d2e'; msg.textContent = 'Request failed (network).'; setEnabled(true); });
                        }
                        step();
                    }

                    if (dryBtn) dryBtn.addEventListener('click', function () { run(1); });
                    if (liveBtn) liveBtn.addEventListener('click', function () {
                        if (window.confirm('<?php echo esc_js(__('Create portals and register hostnames for all agents now?', 'rechat-plugin')); ?>')) { run(0); }
                    });
                });
                </script>

                <p class="description" style="margin:8px 0 0;">
                    <span class="dashicons dashicons-warning" aria-hidden="true" style="color:#b32d2e;"></span>
                    <?php esc_html_e('The “Copy cURL” commands embed your live Rechat access token — treat them as a secret and do not paste them where others can see.', 'rechat-plugin'); ?>
                </p>

                <?php if (empty($entries)) : ?>
                    <p><em><?php esc_html_e('No log entries yet. Run a sync, connect, or use “Run diagnostic” above.', 'rechat-plugin'); ?></em></p>
                <?php else : ?>
                    <table class="widefat striped" style="margin-top:12px;">
                        <thead>
                            <tr>
                                <th style="width:150px;"><?php esc_html_e('Time', 'rechat-plugin'); ?></th>
                                <th><?php esc_html_e('Scope', 'rechat-plugin'); ?></th>
                                <th><?php esc_html_e('Hostname', 'rechat-plugin'); ?></th>
                                <th><?php esc_html_e('Target id', 'rechat-plugin'); ?></th>
                                <th><?php esc_html_e('Action', 'rechat-plugin'); ?></th>
                                <th style="width:70px;"><?php esc_html_e('HTTP', 'rechat-plugin'); ?></th>
                                <th><?php esc_html_e('API response / detail', 'rechat-plugin'); ?></th>
                                <th style="width:120px;"><?php esc_html_e('Request', 'rechat-plugin'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($entries as $e) :
                                $action = (string) ($e['action'] ?? '');
                                $failed = (stripos($action, 'fail') !== false);
                                $ok     = ($action === 'registered');
                                $row_bg = $failed ? 'background:#fcf0f1;' : ($ok ? 'background:#f0f6ec;' : '');
                                $code   = $e['http_code'] ?? null;
                                $detail = (string) ($e['body'] ?? $e['detail'] ?? '');
                            ?>
                            <tr style="<?php echo esc_attr($row_bg); ?>">
                                <td><?php echo esc_html((string) ($e['time'] ?? '')); ?></td>
                                <td>
                                    <?php echo esc_html((string) ($e['event'] ?? '')); ?>
                                    <?php if (! empty($e['trigger'])) : ?>
                                        <br><small style="color:#666;"><?php echo esc_html((string) $e['trigger']); ?><?php echo ! empty($e['dry']) ? ' · dry' : ''; ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><code><?php echo esc_html((string) ($e['hostname'] ?? '')); ?></code></td>
                                <td>
                                    <?php if (! empty($e['agent_id'])) : ?>
                                        <code style="font-size:11px;"><?php echo esc_html((string) $e['agent_id']); ?></code>
                                        <?php if (! empty($e['title'])) : ?><br><small><?php echo esc_html((string) $e['title']); ?></small><?php endif; ?>
                                    <?php elseif (! empty($e['brand_id'])) : ?>
                                        <code style="font-size:11px;"><?php echo esc_html((string) $e['brand_id']); ?></code>
                                    <?php else : ?>—<?php endif; ?>
                                </td>
                                <td>
                                    <strong style="<?php echo $failed ? 'color:#b32d2e;' : ($ok ? 'color:#3a7d34;' : ''); ?>">
                                        <?php echo esc_html($action); ?>
                                    </strong>
                                </td>
                                <td><?php echo $code !== null && $code !== '' ? esc_html((string) $code) : '—'; ?></td>
                                <td>
                                    <?php if ($detail !== '') : ?>
                                        <code style="word-break:break-all;display:block;white-space:pre-wrap;max-height:8em;overflow:auto;font-size:11px;"><?php echo esc_html($detail); ?></code>
                                    <?php else : ?>—<?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $curl = rch_portal_log_build_curl($e, $curl_token);
                                    if ($curl !== '') : ?>
                                        <button type="button" class="button button-small rch-copy-curl"><?php esc_html_e('Copy cURL', 'rechat-plugin'); ?></button>
                                        <details style="margin-top:6px;">
                                            <summary style="cursor:pointer;font-size:11px;"><?php esc_html_e('view', 'rechat-plugin'); ?></summary>
                                            <textarea class="rch-curl-text" readonly rows="7" style="width:100%;font-family:monospace;font-size:11px;margin-top:4px;"><?php echo esc_textarea($curl); ?></textarea>
                                        </details>
                                    <?php else : ?>—<?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script>
    (function () {
        document.querySelectorAll('.rch-copy-curl').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var cell = btn.closest('td');
                var ta = cell ? cell.querySelector('.rch-curl-text') : null;
                if (!ta) { return; }
                var text = ta.value;
                var done = function () {
                    var old = btn.textContent;
                    btn.textContent = '<?php echo esc_js(__('Copied!', 'rechat-plugin')); ?>';
                    setTimeout(function () { btn.textContent = old; }, 1500);
                };
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(done, function () {
                        ta.select(); document.execCommand('copy'); done();
                    });
                } else {
                    ta.select(); document.execCommand('copy'); done();
                }
            });
        });
    })();
    </script>
    <?php
}

/**
 * Handle the "Run now" / "Run diagnostic" button (admin-post).
 *
 * @return void
 */
function rch_portal_log_handle_run(): void
{
    if (! current_user_can('manage_options')) {
        wp_die(esc_html__('Insufficient permissions.', 'rechat-plugin'));
    }
    check_admin_referer('rch_portal_log_run', 'rch_portal_log_run_nonce');

    $dry = ! empty($_GET['dry']);

    if (function_exists('rch_ensure_portal_hostname') && ! $dry) {
        rch_ensure_portal_hostname(); // brand-level too, so the log shows both
    }
    if (function_exists('rch_portal_register_agent_hostnames')) {
        rch_portal_register_agent_hostnames($dry, 'manual');
    }

    wp_safe_redirect(add_query_arg(
        array('page' => 'rechat-setting', 'tab' => 'portal-log', 'ran' => '1'),
        admin_url('admin.php')
    ));
    exit;
}
add_action('admin_post_rch_portal_log_run', 'rch_portal_log_handle_run');

/**
 * AJAX: process ONE batch of agents for portal setup (avoids 504 on large sites).
 *
 * The admin JS calls this repeatedly with an advancing offset until `done`, so no
 * single request runs long. On the first live batch (offset 0) the brand-level
 * hostname is registered once. Each agent still does PUT-create + POST-hostname
 * (or is skipped if already flagged), and every result is written to the log.
 *
 * POST: nonce (rch_ajax_nonce), offset (int), batch (int), dry (0|1).
 * @return void
 */
function rch_portal_run_batch(): void
{
    if (! check_ajax_referer('rch_ajax_nonce', 'nonce', false)) {
        wp_send_json_error(__('Security check failed.', 'rechat-plugin'));
    }
    if (! current_user_can('manage_options')) {
        wp_send_json_error(__('Insufficient permissions.', 'rechat-plugin'));
    }

    $dry    = ! empty($_POST['dry']);
    $offset = isset($_POST['offset']) ? max(0, (int) $_POST['offset']) : 0;
    $batch  = isset($_POST['batch']) ? (int) $_POST['batch'] : 20;
    $batch  = max(1, min(100, $batch));

    $token = (string) get_option('rch_rechat_access_token', '');
    if ($token === '') {
        wp_send_json_error(__('Not connected to Rechat (no access token).', 'rechat-plugin'));
    }

    // Brand-level hostname once, at the start of a live run.
    if ($offset === 0 && ! $dry && function_exists('rch_ensure_portal_hostname')) {
        rch_ensure_portal_hostname();
    }

    $ids = get_posts(array(
        'post_type'      => 'agents',
        'post_status'    => array('publish', 'draft', 'pending', 'private', 'future'),
        'orderby'        => 'ID',
        'order'          => 'ASC',
        'posts_per_page' => $batch,
        'offset'         => $offset,
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ));

    $registered = 0;
    $failed     = 0;
    $skipped    = 0;

    foreach ($ids as $pid) {
        $row = rch_portal_process_agent((int) $pid, $token, $dry);
        rch_portal_log('agent', array_merge(array('trigger' => 'manual-batch', 'dry' => $dry), $row));

        if ($row['action'] === 'registered') {
            $registered++;
        } elseif ($row['action'] === 'FAILED') {
            $failed++;
        } else {
            $skipped++;
        }
    }

    $count = count($ids);

    wp_send_json_success(array(
        'processed'   => $count,
        'next_offset' => $offset + $count,
        'done'        => ($count < $batch), // short (or empty) batch => no more agents
        'registered'  => $registered,
        'failed'      => $failed,
        'skipped'     => $skipped,
    ));
}
add_action('wp_ajax_rch_portal_run_batch', 'rch_portal_run_batch');
