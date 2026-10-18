<?php
/**
 * Listing Unavailable Template
 *
 * Shown inside the active theme (header + footer) when a /listing-detail/ page
 * cannot be displayed: listing not found, removed, invalid ID, or Rechat API error.
 *
 * Theme override: copy to your theme as rechat/listing-not-found.php
 *
 * Available variables:
 * @var string $rch_notice_reason  not_found | removed | invalid | missing | error
 * @var string $rch_notice_title   Heading text.
 * @var string $rch_notice_message Body text.
 * @var string $rch_notice_search  URL of a listings/search page ('' when none found).
 *
 * @package Rechat
 */

if (! defined('ABSPATH')) {
    exit;
}

get_header();
?>

<div class="container">
    <div id="primary" class="content-area rch-primary-content">
        <main id="main" class="site-main content-container site-container">
            <div class="rch-listing-notice rch-listing-notice--<?php echo esc_attr($rch_notice_reason); ?>" role="alert">
                <span class="rch-listing-notice__icon" aria-hidden="true">
                    <?php if ($rch_notice_reason === 'error') : ?>
                        <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="7" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    <?php else : ?>
                        <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><line x1="9.5" y1="13.5" x2="14.5" y2="18.5"/><line x1="14.5" y1="13.5" x2="9.5" y2="18.5"/></svg>
                    <?php endif; ?>
                </span>
                <h1 class="rch-listing-notice__title"><?php echo esc_html($rch_notice_title); ?></h1>
                <p class="rch-listing-notice__message"><?php echo esc_html($rch_notice_message); ?></p>
                <div class="rch-listing-notice__actions">
                    <?php if ($rch_notice_reason === 'error') : ?>
                        <a class="rch-listing-notice__btn rch-listing-notice__btn--primary" href="<?php echo esc_url(add_query_arg([])); ?>"><?php esc_html_e('Try again', 'rechat-plugin'); ?></a>
                    <?php elseif ($rch_notice_search !== '') : ?>
                        <a class="rch-listing-notice__btn rch-listing-notice__btn--primary" href="<?php echo esc_url($rch_notice_search); ?>"><?php esc_html_e('Browse listings', 'rechat-plugin'); ?></a>
                    <?php endif; ?>
                    <a class="rch-listing-notice__btn" href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Back to home', 'rechat-plugin'); ?></a>
                </div>
            </div>
        </main>
    </div>
</div>

<?php get_footer(); ?>
