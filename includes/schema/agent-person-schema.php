<?php
if (! defined('ABSPATH')) {
    exit();
}

/**
 * Output Schema.org Person JSON-LD on single agent pages.
 *
 * @return void
 */
function rch_output_agent_person_jsonld()
{
    if (! is_singular('agents')) {
        return;
    }

    $post_id = (int) get_queried_object_id();
    if ($post_id < 1) {
        return;
    }

    $name = get_the_title($post_id);
    if ($name === '') {
        return;
    }

    $permalink = get_permalink($post_id);
    if (! $permalink) {
        return;
    }

    $image = get_post_meta($post_id, 'profile_image_url', true);
    if (empty($image)) {
        $image = get_the_post_thumbnail_url($post_id, 'full');
    }

    $phone   = get_post_meta($post_id, 'phone_number', true);
    $email   = get_post_meta($post_id, 'email', true);
    $job     = get_post_meta($post_id, 'designation', true);
    $license = get_post_meta($post_id, 'license_number', true);

    $same_as_keys = ['website', 'linkedin', 'twitter', 'instagram', 'youtube', 'facebook'];
    $same_as      = [];
    foreach ($same_as_keys as $key) {
        $url = get_post_meta($post_id, $key, true);
        if (empty($url) || ! is_string($url)) {
            continue;
        }
        $url = trim($url);
        if ($url === '') {
            continue;
        }
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            $same_as[] = esc_url_raw($url);
        }
    }
    $same_as = array_values(array_unique($same_as));

    $schema = [
        '@context' => 'https://schema.org',
        '@type'    => 'Person',
        'name'     => wp_strip_all_tags($name),
        'url'      => esc_url_raw($permalink),
    ];

    if (! empty($image) && is_string($image) && filter_var($image, FILTER_VALIDATE_URL)) {
        $schema['image'] = esc_url_raw($image);
    }

    if (! empty($phone) && is_string($phone)) {
        $schema['telephone'] = sanitize_text_field($phone);
    }

    if (! empty($email) && is_string($email) && is_email($email)) {
        $schema['email'] = sanitize_email($email);
    }

    if (! empty($job) && is_string($job)) {
        $schema['jobTitle'] = sanitize_text_field($job);
    }

    if (! empty($license) && is_string($license)) {
        $schema['identifier'] = sanitize_text_field($license);
    }

    if (! empty($same_as)) {
        $schema['sameAs'] = $same_as;
    }

    /**
     * Filter the Person JSON-LD array before output.
     *
     * @param array $schema  Schema.org graph node.
     * @param int   $post_id Agent post ID.
     */
    $schema = apply_filters('rch_agent_person_schema', $schema, $post_id);

    if (empty($schema) || ! is_array($schema)) {
        return;
    }

    $json = wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (! is_string($json) || $json === '') {
        return;
    }

    echo '<script type="application/ld+json">' . $json . '</script>' . "\n";
}
add_action('wp_head', 'rch_output_agent_person_jsonld', 5);
