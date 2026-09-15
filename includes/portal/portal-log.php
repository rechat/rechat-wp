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

    $run_dry_url = wp_nonce_url(
        admin_url('admin-post.php?action=rch_portal_log_run&dry=1'),
        'rch_portal_log_run',
        'rch_portal_log_run_nonce'
    );
    $run_live_url = wp_nonce_url(
        admin_url('admin-post.php?action=rch_portal_log_run&dry=0'),
        'rch_portal_log_run',
        'rch_portal_log_run_nonce'
    );
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
                    <a href="<?php echo esc_url($run_dry_url); ?>" class="button button-secondary">
                        <span class="dashicons dashicons-search" aria-hidden="true" style="margin-top:4px;"></span>
                        <?php esc_html_e('Run diagnostic (dry — no API write)', 'rechat-plugin'); ?>
                    </a>
                    <a href="<?php echo esc_url($run_live_url); ?>" class="button button-primary">
                        <span class="dashicons dashicons-update" aria-hidden="true" style="margin-top:4px;"></span>
                        <?php esc_html_e('Run now (live — registers hostnames)', 'rechat-plugin'); ?>
                    </a>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                        <input type="hidden" name="action" value="rch_portal_log_clear">
                        <?php wp_nonce_field('rch_portal_log_clear', 'rch_portal_log_clear_nonce'); ?>
                        <button type="submit" class="button" onclick="return confirm('<?php echo esc_js(__('Clear the portal log?', 'rechat-plugin')); ?>');">
                            <span class="dashicons dashicons-trash" aria-hidden="true" style="margin-top:4px;"></span>
                            <?php esc_html_e('Clear log', 'rechat-plugin'); ?>
                        </button>
                    </form>
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
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
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
