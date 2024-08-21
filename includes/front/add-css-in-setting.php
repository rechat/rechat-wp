<?php

function rch_add_custom_color_css()
{
    $custom_color = get_option('_rch_primary_color', '#2271b1');
?>
    <style type="text/css">
        .rch-agents-rechat ul.rch-archive-agents li .rch-archive-end-line a,
        .rch-top-filter form#rch-agent-search-form div#rch-agent-search-results::-webkit-scrollbar-thumb,
        .rch-single-call a {
            background: <?php echo esc_attr($custom_color); ?>;
        }
        .rch-agents-rechat ul.rch-archive-agents li .rch-archive-end-line a:first-child {
            border: solid 1px <?php echo esc_attr($custom_color); ?>;
            color: <?php echo esc_attr($custom_color); ?>;
        }
        .rch-loader {
            border-right-color: <?php echo esc_attr($custom_color); ?>;
        }
        .rch-pagination .rch-pagination-container .current {
            color: <?php echo esc_attr($custom_color); ?>;
        }
    </style>
<?php
}
add_action('wp_head', 'rch_add_custom_color_css'); // For frontend
add_action('admin_head', 'rch_add_custom_color_css'); // For admin