<?php

/**
 * Lead capture form shortcode — Rechat SDK integration.
 *
 * Usage: [rch_leads_form form_title="Contact" lead_channel="..." assignee_email="agent@example.com" tags="a,b" show_email="true"]
 */

if (! defined('ABSPATH')) {
    exit();
}

/**
 * Enqueue Rechat SDK, shortcode CSS/JS, and strings (once per request).
 */
function rch_enqueue_lead_form_assets()
{
    static $localized = false;

    wp_enqueue_style('rechat-sdk-css');
    wp_enqueue_script('rechat-sdk-js');
    wp_enqueue_style('rch-lead-capture-shortcode-css');
    wp_enqueue_script('rch-lead-capture-shortcode');

    if (! $localized) {
        wp_localize_script(
            'rch-lead-capture-shortcode',
            'rchLeadCaptureL10n',
            [
                'invalidPhone' => __('Please enter a valid phone number', 'rechat-plugin'),
                'invalidEmail' => __('Please enter a valid email address', 'rechat-plugin'),
            ]
        );
        $localized = true;
    }
}

/**
 * Queue per-form bootstrap (runs after main script in footer).
 *
 * @param string $form_id        DOM id of the form element
 * @param string $lead_channel   Rechat lead channel
 * @param array  $selected_tags  Tag strings
 * @param string $assignee_email Rechat assignee email (agent subsite auto-filled when empty).
 */
function rch_lead_capture_enqueue_instance_script($form_id, $lead_channel, array $selected_tags, $assignee_email = '')
{
    $config = [
        'formId'       => $form_id,
        'leadChannel'  => $lead_channel,
        'tags'         => array_values($selected_tags),
        'assigneeEmail' => $assignee_email,
    ];

    wp_add_inline_script(
        'rch-lead-capture-shortcode',
        'window.rchLeadCaptureInitInstance(' . wp_json_encode($config) . ');',
        'after'
    );
}

/**
 * @param array $atts Shortcode attributes
 * @return string HTML output
 */
function rch_render_leads_form_shortcode($atts)
{
    rch_enqueue_lead_form_assets();

    $atts = rch_parse_shortcode_attributes($atts);

    $lead_channel = rch_get_lead_channel($atts);
    $selected_tags = rch_get_selected_tags($atts);

    $form_id = 'leadCaptureForm_' . uniqid('', false);

    ob_start();
    rch_render_form_html($atts, $form_id);
    $html = ob_get_clean();

    $assignee_email = function_exists('rch_leads_form_resolve_assignee_email')
        ? rch_leads_form_resolve_assignee_email($atts['assignee_email'] ?? '')
        : sanitize_email((string) ($atts['assignee_email'] ?? ''));

    rch_lead_capture_enqueue_instance_script($form_id, $lead_channel, $selected_tags, $assignee_email);

    return $html;
}

/**
 * @param array  $atts Raw shortcode attributes
 * @return array Sanitized attributes
 */
function rch_parse_shortcode_attributes($atts)
{
    $defaults = [
        'form_title'          => __('Contact Us', 'rechat-plugin'),
        'show_first_name'     => 'true',
        'show_last_name'      => 'true',
        'show_phone_number'   => 'true',
        'show_email'          => 'true',
        'show_note'           => 'true',
        'lead_channel'        => '',
        'assignee_email'      => '',
        'tags'                => '',
    ];

    $atts = shortcode_atts($defaults, $atts, 'rch_leads_form');

    $boolean_fields = ['show_first_name', 'show_last_name', 'show_phone_number', 'show_email', 'show_note'];
    foreach ($boolean_fields as $field) {
        $atts[$field] = filter_var($atts[$field], FILTER_VALIDATE_BOOLEAN);
    }

    $atts['form_title']    = sanitize_text_field($atts['form_title']);
    $atts['lead_channel']  = sanitize_text_field($atts['lead_channel']);
    $atts['assignee_email'] = sanitize_email((string) $atts['assignee_email']);
    $atts['tags']          = sanitize_text_field($atts['tags']);

    if (function_exists('rch_leads_form_resolve_assignee_email')) {
        $atts['assignee_email'] = rch_leads_form_resolve_assignee_email($atts['assignee_email']);
    }

    return $atts;
}

/**
 * Agent theme Talk options → lead_channel / tags when omitted from shortcode string.
 */
add_filter(
    'shortcode_atts_rch_leads_form',
    static function ($out, $pairs, $atts) {
        unset($pairs, $atts);
        if (! function_exists('rch_leads_form_apply_talk_theme_options')) {
            return $out;
        }

        return rch_leads_form_apply_talk_theme_options($out);
    },
    15,
    3
);

/**
 * @param array $atts Shortcode attributes
 * @return string
 */
function rch_get_lead_channel($atts)
{
    if (! empty($atts['lead_channel'])) {
        return sanitize_text_field($atts['lead_channel']);
    }

    return sanitize_text_field(get_option('rch_lead_channels', ''));
}

/**
 * @param array $atts Shortcode attributes
 * @return array
 */
function rch_get_selected_tags($atts)
{
    if (! empty($atts['tags'])) {
        $tags = array_map('trim', explode(',', $atts['tags']));
    } else {
        $saved = get_option('rch_selected_tags', []);
        $tags = is_array($saved) ? $saved : [];
    }

    return array_map('sanitize_text_field', array_filter($tags));
}

/**
 * @param array  $atts    Form attributes
 * @param string $form_id Unique form id
 */
function rch_render_form_html($atts, $form_id)
{
    ?>
    <div class="rch-leads-form-shortcode">
        <form id="<?php echo esc_attr($form_id); ?>" class="rch-lead-capture-form" method="post">
            <?php wp_nonce_field('rch_lead_capture_nonce', 'rch_nonce_field'); ?>

            <?php if (! empty($atts['form_title'])) : ?>
                <h2><?php echo esc_html($atts['form_title']); ?></h2>
            <?php endif; ?>

            <?php if ($atts['show_first_name']) : ?>
                <div class="form-group">
                    <label for="<?php echo esc_attr($form_id); ?>_first_name"><?php esc_html_e('First Name', 'rechat-plugin'); ?></label>
                    <input
                        type="text"
                        id="<?php echo esc_attr($form_id); ?>_first_name"
                        name="first_name"
                        placeholder="<?php esc_attr_e('Enter your first name', 'rechat-plugin'); ?>"
                        required
                        maxlength="100"
                        pattern="[A-Za-z\s\-']+">
                </div>
            <?php endif; ?>

            <?php if ($atts['show_last_name']) : ?>
                <div class="form-group">
                    <label for="<?php echo esc_attr($form_id); ?>_last_name"><?php esc_html_e('Last Name', 'rechat-plugin'); ?></label>
                    <input
                        type="text"
                        id="<?php echo esc_attr($form_id); ?>_last_name"
                        name="last_name"
                        placeholder="<?php esc_attr_e('Enter your last name', 'rechat-plugin'); ?>"
                        required
                        maxlength="100"
                        pattern="[A-Za-z\s\-']+">
                </div>
            <?php endif; ?>

            <?php if ($atts['show_phone_number']) : ?>
                <div class="form-group">
                    <label for="<?php echo esc_attr($form_id); ?>_phone_number"><?php esc_html_e('Phone Number', 'rechat-plugin'); ?></label>
                    <input
                        type="tel"
                        id="<?php echo esc_attr($form_id); ?>_phone_number"
                        name="phone_number"
                        placeholder="<?php esc_attr_e('Enter your phone number', 'rechat-plugin'); ?>"
                        required
                        maxlength="20">
                </div>
            <?php endif; ?>

            <?php if ($atts['show_email']) : ?>
                <div class="form-group">
                    <label for="<?php echo esc_attr($form_id); ?>_email"><?php esc_html_e('Email Address', 'rechat-plugin'); ?></label>
                    <input
                        type="email"
                        id="<?php echo esc_attr($form_id); ?>_email"
                        name="email"
                        placeholder="<?php esc_attr_e('Enter your email address', 'rechat-plugin'); ?>"
                        required
                        maxlength="255">
                </div>
            <?php endif; ?>

            <?php if ($atts['show_note']) : ?>
                <div class="form-group">
                    <label for="<?php echo esc_attr($form_id); ?>_note"><?php esc_html_e('Note', 'rechat-plugin'); ?></label>
                    <textarea
                        id="<?php echo esc_attr($form_id); ?>_note"
                        name="note"
                        placeholder="<?php esc_attr_e('Write your note here', 'rechat-plugin'); ?>"
                        required
                        maxlength="1000"></textarea>
                </div>
            <?php endif; ?>

            <button type="submit" class="rch-submit-btn"><?php esc_html_e('Submit Request', 'rechat-plugin'); ?></button>
        </form>

        <div id="<?php echo esc_attr($form_id); ?>_loading" class="rch-loading-spinner-form" aria-hidden="true"></div>
        <div id="<?php echo esc_attr($form_id); ?>_success" class="rch-success-box-listing" role="status">
            <?php esc_html_e('Thank you! Your data has been successfully sent.', 'rechat-plugin'); ?>
        </div>
        <div id="<?php echo esc_attr($form_id); ?>_error" class="rch-error-box-listing" role="alert">
            <?php esc_html_e('Something went wrong. Please try again.', 'rechat-plugin'); ?>
        </div>
    </div>
    <?php
}

add_shortcode('rch_leads_form', 'rch_render_leads_form_shortcode');
