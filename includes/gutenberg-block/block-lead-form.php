<?php
if (! defined('ABSPATH')) {
    exit();
}
/*******************************
 * Lead Form Gutenberg block + Rechat POST /leads (server-side token).
 ******************************/

/**
 * Resolve assignee email: agent subsite uses linked hub agent profile; else explicit value.
 *
 * @param string $explicit Email from block attr, shortcode, or POST.
 * @return string Valid email or empty.
 */
function rch_leads_form_resolve_assignee_email($explicit = '')
{
    if (function_exists('rch_multisite_get_linked_agent_profile_email')) {
        $linked = rch_multisite_get_linked_agent_profile_email();
        if ($linked !== '') {
            return $linked;
        }
    }

    $explicit = is_string($explicit) ? sanitize_email(trim($explicit)) : '';

    return is_email($explicit) ? $explicit : '';
}

/**
 * Agent theme option bag (Acropolis-agent uses pentama_options_agent_website).
 *
 * @return array<string, mixed>
 */
function rch_leads_form_get_agent_theme_options(): array
{
    $opts = get_option('pentama_options_agent_website', []);
    if (! is_array($opts)) {
        $opts = [];
    }

    /**
     * @param array<string, mixed> $opts
     */
    return apply_filters('rch_leads_form_agent_theme_options', $opts);
}

/**
 * Normalize talk selected-tags from theme options to a string list.
 *
 * @param mixed $raw Option value.
 * @return list<string>
 */
function rch_leads_form_normalize_talk_tags($raw): array
{
    if (is_string($raw)) {
        $decoded = json_decode($raw, true);
        $raw     = is_array($decoded) ? $decoded : [];
    }
    if (! is_array($raw)) {
        return [];
    }

    $out = [];
    foreach ($raw as $tag) {
        $tag = sanitize_text_field((string) $tag);
        if ($tag !== '' && ! in_array($tag, $out, true)) {
            $out[] = $tag;
        }
    }

    return $out;
}

/**
 * Build [rch_leads_form …] for the Talk section from theme option keys.
 *
 * @param array<string, mixed> $opts Theme options row.
 * @return string Shortcode text.
 */
function rch_leads_form_build_talk_shortcode_from_options(array $opts): string
{
    $attrs = [
        'show_first_name'   => 'true',
        'show_last_name'    => 'true',
        'show_phone_number' => 'true',
        'show_email'        => 'true',
        'show_note'         => 'true',
    ];

    $title = trim((string) ($opts['rch-theme-agent-talk-title'] ?? ''));
    if ($title !== '') {
        $attrs['form_title'] = $title;
    }

    $channel = trim((string) ($opts['rch-theme-agent-talk-lead-channel'] ?? ''));
    if ($channel === '') {
        $channel = (string) get_option('rch_lead_channels', '');
    }
    if ($channel !== '') {
        $attrs['lead_channel'] = $channel;
    }

    $tags = rch_leads_form_normalize_talk_tags($opts['rch-theme-agent-talk-selected-tags'] ?? []);
    if ($tags !== []) {
        $attrs['tags'] = implode(',', $tags);
    }

    if (function_exists('rch_leads_form_resolve_assignee_email')) {
        $assignee = rch_leads_form_resolve_assignee_email('');
        if ($assignee !== '') {
            $attrs['assignee_email'] = $assignee;
        }
    }

    $parts = [];
    foreach ($attrs as $key => $value) {
        $parts[] = $key . '="' . esc_attr((string) $value) . '"';
    }

    return '[rch_leads_form ' . implode(' ', $parts) . ']';
}

/**
 * Keep rch-theme-agent-talk-shortcode in sync with talk lead channel + tags options.
 *
 * @param array<string, mixed> $opts Theme options row (mutated in place).
 * @return array<string, mixed>
 */
function rch_leads_form_sync_talk_options_row(array $opts): array
{
    $touch = array_key_exists('rch-theme-agent-talk-lead-channel', $opts)
        || array_key_exists('rch-theme-agent-talk-selected-tags', $opts)
        || array_key_exists('rch-theme-agent-talk-title', $opts);

    if ($touch) {
        $opts['rch-theme-agent-talk-shortcode'] = rch_leads_form_build_talk_shortcode_from_options($opts);
    }

    return $opts;
}

/**
 * Merge Talk theme options into parsed [rch_leads_form] attributes when shortcode omits them.
 *
 * @param array<string, mixed> $atts Parsed shortcode attributes.
 * @return array<string, mixed>
 */
function rch_leads_form_apply_talk_theme_options(array $atts): array
{
    $opts = rch_leads_form_get_agent_theme_options();

    if (empty($atts['lead_channel'])) {
        $channel = trim((string) ($opts['rch-theme-agent-talk-lead-channel'] ?? ''));
        if ($channel === '') {
            $channel = (string) get_option('rch_lead_channels', '');
        }
        if ($channel !== '') {
            $atts['lead_channel'] = sanitize_text_field($channel);
        }
    }

    if (empty($atts['tags'])) {
        $tags = rch_leads_form_normalize_talk_tags($opts['rch-theme-agent-talk-selected-tags'] ?? []);
        if ($tags !== []) {
            $atts['tags'] = implode(',', $tags);
        }
    }

    return $atts;
}

/**
 * REST: linked agent email on current agent subsite (block editor prefill).
 *
 * @return WP_REST_Response
 */
function rch_rest_leads_form_linked_agent()
{
    $email = function_exists('rch_multisite_get_linked_agent_profile_email')
        ? rch_multisite_get_linked_agent_profile_email()
        : '';

    return rest_ensure_response(
        array(
            'email'   => $email,
            'linked'  => $email !== '',
        )
    );
}

/**
 * REST: agents with email for block editor dropdown (editors only).
 *
 * @return WP_REST_Response
 */
function rch_rest_leads_form_agents()
{
    $query = new WP_Query(
        array(
            'post_type'      => 'agents',
            'post_status'    => 'publish',
            'posts_per_page' => 500,
            'orderby'        => 'title',
            'order'          => 'ASC',
        )
    );

    $agents = array();
    foreach ($query->posts as $post) {
        if (! $post instanceof WP_Post) {
            continue;
        }
        $email = get_post_meta($post->ID, 'email', true);
        $email = is_string($email) ? trim($email) : '';
        if ($email === '' || ! is_email($email)) {
            continue;
        }
        $agents[] = array(
            'id'    => (string) $post->ID,
            'name'  => get_the_title($post->ID),
            'email' => $email,
        );
    }

    return rest_ensure_response(array('agents' => $agents));
}

/**
 * @return void
 */
function rch_register_leads_form_rest_routes()
{
    register_rest_route(
        'rch/v1',
        '/leads-form-agents',
        array(
            'methods'             => 'GET',
            'callback'            => 'rch_rest_leads_form_agents',
            'permission_callback' => static function () {
                return current_user_can('edit_posts');
            },
        )
    );

    register_rest_route(
        'rch/v1',
        '/leads-form-linked-agent',
        array(
            'methods'             => 'GET',
            'callback'            => 'rch_rest_leads_form_linked_agent',
            'permission_callback' => static function () {
                return current_user_can('edit_posts');
            },
        )
    );
}
add_action('rest_api_init', 'rch_register_leads_form_rest_routes');

/**
 * AJAX: submit lead to Rechat API (keeps OAuth token server-side).
 *
 * @return void
 */
function rch_ajax_submit_lead_rechat_api()
{
    if (! isset($_POST['rch_lead_nonce_field']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['rch_lead_nonce_field'])), 'rch_lead_form')) {
        wp_send_json_error(array('message' => 'Invalid security token.'), 403);
    }

    $lead_channel = isset($_POST['lead_channel']) ? sanitize_text_field(wp_unslash($_POST['lead_channel'])) : '';
    if ($lead_channel === '') {
        wp_send_json_error(array('message' => 'Lead channel is required.'), 400);
    }

    $assignee_email = rch_leads_form_resolve_assignee_email(
        isset($_POST['assignee_email']) ? (string) wp_unslash($_POST['assignee_email']) : ''
    );
    if ($assignee_email === '') {
        wp_send_json_error(array('message' => 'A valid agent assignee email is required.'), 400);
    }

    $first_name   = isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '';
    $last_name    = isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '';
    $phone_number = isset($_POST['phone_number']) ? sanitize_text_field(wp_unslash($_POST['phone_number'])) : '';
    $email        = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
    $note         = isset($_POST['note']) ? sanitize_textarea_field(wp_unslash($_POST['note'])) : '';

    $use_mortgage_lead_source = isset($_POST['use_mortgage_question_lead_source'])
        && sanitize_text_field(wp_unslash((string) $_POST['use_mortgage_question_lead_source'])) === '1';
    if ($use_mortgage_lead_source) {
        $lead_source = 'Mortgage Question From';
    } else {
        $lead_source = isset($_POST['lead_source_custom'])
            ? sanitize_text_field(wp_unslash((string) $_POST['lead_source_custom']))
            : '';
    }

    $tags = array();
    if (isset($_POST['tags_json'])) {
        $decoded = json_decode(wp_unslash((string) $_POST['tags_json']), true);
        if (is_array($decoded)) {
            foreach ($decoded as $t) {
                $t = sanitize_text_field((string) $t);
                if ($t !== '') {
                    $tags[] = $t;
                }
            }
        }
    }

    $access_token = (string) get_option('rch_rechat_access_token', '');
    $brand_id     = (string) get_option('rch_rechat_brand_id', '');
    if ($access_token === '' || $brand_id === '') {
        wp_send_json_error(array('message' => 'Rechat is not connected (missing token or brand).'), 503);
    }

    $body = array(
        'metadata' => array(
            'lead_channel' => $lead_channel,
        ),
        'lead'     => array(
            'first_name'   => $first_name,
            'last_name'    => $last_name,
            'email'        => $email,
            'phone_number' => $phone_number,
            'source_type'  => 'Website',
            'lead_source'  => $lead_source,
            'note'         => $note,
            'tag'          => $tags,
            'assignees'    => array(
                array(
                    'email' => $assignee_email,
                ),
            ),
        ),
    );

    $response = wp_remote_post(
        rtrim(RECHAT_API_BASE_URL, '/') . '/leads',
        array(
            'timeout' => 25,
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $access_token,
                'X-RECHAT-BRAND' => $brand_id,
            ),
            'body'    => wp_json_encode($body),
        )
    );

    if (is_wp_error($response)) {
        wp_send_json_error(array('message' => $response->get_error_message()), 502);
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    $raw  = (string) wp_remote_retrieve_body($response);
    $data = json_decode($raw, true);

    if ($code >= 200 && $code < 300) {
        wp_send_json_success(
            array(
                'status' => $code,
                'data'   => is_array($data) ? $data : null,
            )
        );
    }

    $message = is_array($data) && isset($data['message']) ? (string) $data['message'] : $raw;
    wp_send_json_error(
        array(
            'message' => $message !== '' ? $message : 'Lead request failed.',
            'status'  => $code,
        ),
        $code >= 400 && $code < 600 ? $code : 502
    );
}
add_action('wp_ajax_rch_submit_lead_rechat_api', 'rch_ajax_submit_lead_rechat_api');
add_action('wp_ajax_nopriv_rch_submit_lead_rechat_api', 'rch_ajax_submit_lead_rechat_api');

/*******************************
 * Register Leads form block in php
 ******************************/
function rch_register_block_assets_leads_form()
{
    register_block_type(
        'rch-rechat-plugin/leads-form-block',
        array(
            'editor_script'   => 'rch-gutenberg-js',
            'attributes'      => array(
                'formTitle'           => array(
                    'type'    => 'string',
                    'default' => '',
                ),
                'leadChannel'         => array(
                    'type'    => 'string',
                    'default' => '',
                ),
                'leadChannelName'     => array(
                    'type'    => 'string',
                    'default' => '',
                ),
                'assigneeAgentEmail'  => array(
                    'type'    => 'string',
                    'default' => '',
                ),
                'useMortgageQuestionLeadSource' => array(
                    'type'    => 'boolean',
                    'default' => true,
                ),
                'leadSource'          => array(
                    'type'    => 'string',
                    'default' => '',
                ),
                'showFirstName'       => array(
                    'type'    => 'boolean',
                    'default' => true,
                ),
                'showLastName'        => array(
                    'type'    => 'boolean',
                    'default' => true,
                ),
                'showPhoneNumber'     => array(
                    'type'    => 'boolean',
                    'default' => true,
                ),
                'showEmail'           => array(
                    'type'    => 'boolean',
                    'default' => true,
                ),
                'showNote'            => array(
                    'type'    => 'boolean',
                    'default' => true,
                ),
                'selectedTagsFrom'    => array(
                    'type'    => 'array',
                    'default' => array(),
                ),
                'submitButtonText'    => array(
                    'type'    => 'string',
                    'default' => 'Submit Request',
                ),
            ),
            'render_callback' => 'rch_render_leads_form_block',
        )
    );
}
add_action('init', 'rch_register_block_assets_leads_form');

/**
 * @param array<string,mixed> $attributes Block attributes.
 * @return string
 */
function rch_render_leads_form_block($attributes)
{
    $form_title          = isset($attributes['formTitle']) ? $attributes['formTitle'] : '';
    $lead_channel        = isset($attributes['leadChannel']) ? $attributes['leadChannel'] : '';
    $lead_channel_name   = isset($attributes['leadChannelName']) ? (string) $attributes['leadChannelName'] : '';
    $assignee_agent_email = rch_leads_form_resolve_assignee_email(
        isset($attributes['assigneeAgentEmail']) ? (string) $attributes['assigneeAgentEmail'] : ''
    );
    $use_mortgage_question_lead_source = isset($attributes['useMortgageQuestionLeadSource'])
        ? (bool) $attributes['useMortgageQuestionLeadSource']
        : true;
    $lead_source_custom  = isset($attributes['leadSource']) ? (string) $attributes['leadSource'] : '';
    $show_first_name     = isset($attributes['showFirstName']) ? (bool) $attributes['showFirstName'] : true;
    $show_last_name      = isset($attributes['showLastName']) ? (bool) $attributes['showLastName'] : true;
    $show_phone_number   = isset($attributes['showPhoneNumber']) ? (bool) $attributes['showPhoneNumber'] : true;
    $show_email          = isset($attributes['showEmail']) ? (bool) $attributes['showEmail'] : true;
    $show_note           = isset($attributes['showNote']) ? (bool) $attributes['showNote'] : true;
    $selected_tags       = isset($attributes['selectedTagsFrom']) && is_array($attributes['selectedTagsFrom'])
        ? $attributes['selectedTagsFrom']
        : array();
    $submit_button_text  = isset($attributes['submitButtonText']) && $attributes['submitButtonText'] !== ''
        ? $attributes['submitButtonText']
        : 'Submit Request';

    $is_editor = defined('REST_REQUEST') && REST_REQUEST && isset($_GET['context']) && $_GET['context'] === 'edit';

    $form_uid = wp_unique_id('rch-lead-form-');
    $nonce    = wp_create_nonce('rch_lead_form');

    ob_start();
    ?>
    <div class="rch-leads-form-block" data-rch-lead-form="1">
        <form id="<?php echo esc_attr($form_uid); ?>" class="rch-leads-form-block__form" method="post" action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>">
            <input type="hidden" name="action" value="rch_submit_lead_rechat_api" />
            <input type="hidden" name="rch_lead_nonce_field" value="<?php echo esc_attr($nonce); ?>" />
            <input type="hidden" name="lead_channel" value="<?php echo esc_attr($lead_channel); ?>" />
            <input type="hidden" name="assignee_email" value="<?php echo esc_attr($assignee_agent_email); ?>" />
            <input type="hidden" name="use_mortgage_question_lead_source" value="<?php echo $use_mortgage_question_lead_source ? '1' : '0'; ?>" />
            <input type="hidden" name="lead_source_custom" value="<?php echo esc_attr($lead_source_custom); ?>" />
            <input type="hidden" name="tags_json" value="<?php echo esc_attr(wp_json_encode(array_values($selected_tags))); ?>" />

            <?php if ($form_title) : ?>
                <h2 class="rch-leads-form-block__title"><?php echo esc_html($form_title); ?></h2>
            <?php endif; ?>


            <?php if ($show_first_name) : ?>
                <div class="form-group">
                    <label for="<?php echo esc_attr($form_uid); ?>-first_name"><?php esc_html_e('First Name', 'rechat-plugin'); ?></label>
                    <input type="text" id="<?php echo esc_attr($form_uid); ?>-first_name" name="first_name" placeholder="<?php esc_attr_e('Enter your first name', 'rechat-plugin'); ?>" required />
                </div>
            <?php endif; ?>
            <?php if ($show_last_name) : ?>
                <div class="form-group">
                    <label for="<?php echo esc_attr($form_uid); ?>-last_name"><?php esc_html_e('Last Name', 'rechat-plugin'); ?></label>
                    <input type="text" id="<?php echo esc_attr($form_uid); ?>-last_name" name="last_name" placeholder="<?php esc_attr_e('Enter your last name', 'rechat-plugin'); ?>" required />
                </div>
            <?php endif; ?>
            <?php if ($show_phone_number) : ?>
                <div class="form-group">
                    <label for="<?php echo esc_attr($form_uid); ?>-phone_number"><?php esc_html_e('Phone Number', 'rechat-plugin'); ?></label>
                    <input type="tel" id="<?php echo esc_attr($form_uid); ?>-phone_number" name="phone_number" placeholder="<?php esc_attr_e('Enter your phone number', 'rechat-plugin'); ?>" required />
                </div>
            <?php endif; ?>
            <?php if ($show_email) : ?>
                <div class="form-group">
                    <label for="<?php echo esc_attr($form_uid); ?>-email"><?php esc_html_e('Email Address', 'rechat-plugin'); ?></label>
                    <input type="email" id="<?php echo esc_attr($form_uid); ?>-email" name="email" placeholder="<?php esc_attr_e('Enter your email address', 'rechat-plugin'); ?>" required />
                </div>
            <?php endif; ?>
            <?php if ($show_note) : ?>
                <div class="form-group">
                    <label for="<?php echo esc_attr($form_uid); ?>-note"><?php esc_html_e('Note', 'rechat-plugin'); ?></label>
                    <textarea id="<?php echo esc_attr($form_uid); ?>-note" name="note" placeholder="<?php esc_attr_e('Write your note here', 'rechat-plugin'); ?>" required></textarea>
                </div>
            <?php endif; ?>

            <button type="submit" class="rch-leads-form-block__submit" <?php echo $is_editor ? 'disabled' : ''; ?>><?php echo esc_html($submit_button_text); ?></button>
        </form>
        <div id="<?php echo esc_attr($form_uid); ?>-loading" class="rch-loading-spinner-form" style="display: none;" aria-hidden="true"></div>
        <div id="<?php echo esc_attr($form_uid); ?>-success" class="rch-success-box-listing" style="display: none;" role="status">
            <?php esc_html_e('Thank you! Your data has been successfully sent.', 'rechat-plugin'); ?>
        </div>
        <div id="<?php echo esc_attr($form_uid); ?>-error" class="rch-error-box-listing" style="display: none;" role="alert">
            <?php esc_html_e('Something went wrong. Please try again.', 'rechat-plugin'); ?>
        </div>
    </div>

    <?php if (! $is_editor) : ?>
        <script>
        (function () {
            var form = document.getElementById(<?php echo wp_json_encode($form_uid); ?>);
            if (!form) { return; }
            var loading = document.getElementById(<?php echo wp_json_encode($form_uid . '-loading'); ?>);
            var successEl = document.getElementById(<?php echo wp_json_encode($form_uid . '-success'); ?>);
            var errorEl = document.getElementById(<?php echo wp_json_encode($form_uid . '-error'); ?>);
            var ajaxUrl = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;

            form.addEventListener('submit', function (event) {
                event.preventDefault();
                if (loading) { loading.style.display = 'block'; }
                if (successEl) { successEl.style.display = 'none'; }
                if (errorEl) { errorEl.style.display = 'none'; }

                var fd = new FormData(form);
                fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
                    .then(function (res) { return res.json(); })
                    .then(function (json) {
                        if (loading) { loading.style.display = 'none'; }
                        if (json && json.success) {
                            if (successEl) { successEl.style.display = 'block'; }
                            form.reset();
                        } else {
                            if (errorEl) { errorEl.style.display = 'block'; }
                            if (window.console && json && json.data && json.data.message) {
                                console.error(json.data.message);
                            }
                        }
                    })
                    .catch(function (err) {
                        if (loading) { loading.style.display = 'none'; }
                        if (errorEl) { errorEl.style.display = 'block'; }
                        if (window.console) { console.error(err); }
                    });
            });
        })();
        </script>
    <?php endif; ?>
    <?php
    return ob_get_clean();
}
