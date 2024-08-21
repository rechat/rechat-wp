<?php 
function rch_enqueue_frontend_styles() {
    wp_register_style('rch-front-css-agents', RCH_PLUGIN_URL . 'assets/css/frontend-styles.css', [], '1.0.0');
    wp_register_script('rch-ajax-front', RCH_PLUGIN_URL . 'assets/js/rch-ajax-front.js', array('jquery'), null, true);
    wp_localize_script('rch-ajax-front', 'rch_ajax_front_params', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('rch_ajax_front_nonce')
    ));
    
    if (is_post_type_archive('agents') || is_singular('agents')) {
        wp_enqueue_style('rch-front-css-agents');
        wp_enqueue_script('rch-ajax-front');
    }
}
add_action('wp_enqueue_scripts', 'rch_enqueue_frontend_styles');
?>