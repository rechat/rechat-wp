<?php
/**
 * Agent sub-site deploy wizard (Rechat → tab).
 *
 * @package Rechat
 */

if (! defined('ABSPATH')) {
    exit;
}

$agents = get_posts([
    'post_type'      => 'agents',
    'posts_per_page' => -1,
    'post_status'    => 'publish',
    'orderby'        => 'title',
    'order'          => 'ASC',
    'fields'         => 'ids',
]);

$bulk_agent_count = function_exists('rch_agent_wizard_count_agents_with_subsites')
    ? rch_agent_wizard_count_agents_with_subsites()
    : 0;
$manual_rows      = function_exists('rch_agent_wizard_manual_field_defs')
    ? rch_agent_wizard_manual_field_defs()
    : [];
$metabox_defs     = function_exists('rch_agent_wizard_importable_field_defs_resolved')
    ? rch_agent_wizard_importable_field_defs_resolved()
    : [];

$wz_resolved = function_exists('rch_multisite_resolve_theme_network_default_for_agents')
    ? rch_multisite_resolve_theme_network_default_for_agents()
    : ['stylesheet' => (string) wp_get_theme()->get_stylesheet()];
$wz_stylesheet = (string) ($wz_resolved['stylesheet'] ?? '');
$wz_theme_obj = wp_get_theme($wz_stylesheet);
$wz_theme_name = $wz_theme_obj->exists() ? (string) $wz_theme_obj->get('Name') : $wz_stylesheet;
$wz_storage    = function_exists('rch_agent_wizard_resolve_storage_config')
    ? rch_agent_wizard_resolve_storage_config($wz_stylesheet)
    : ['primary' => '', 'mirror' => null];
$wz_opt_primary = isset($wz_storage['primary']) ? (string) $wz_storage['primary'] : '';
$wz_opt_mirror  = (isset($wz_storage['mirror']) && is_string($wz_storage['mirror'])) ? $wz_storage['mirror'] : '';

?>
<div class="tab-content rch-agent-wizard" id="rch-agent-site-wizard">
    <div class="rch-wz-shell">

        <header class="rch-wz-hero">
            <p class="rch-wz-hero__eyebrow"><?php esc_html_e('Multisite', 'rechat-plugin'); ?></p>
            <h2 class="rch-wz-hero__title"><?php esc_html_e('Agent sub-site data wizard', 'rechat-plugin'); ?></h2>
            <p class="rch-wz-hero__lead">
                <?php
                printf(
                    /* translators: 1: theme name, 2: stylesheet directory slug */
                    esc_html__(
                        'Map main-site agent data into %1$s theme options (%2$s), using the same keys as that theme’s Theme Settings screen. Nothing is saved on sub-sites until you deploy.',
                        'rechat-plugin'
                    ),
                    esc_html($wz_theme_name),
                    esc_html($wz_stylesheet)
                );
                ?>
            </p>
            <?php if ($wz_opt_primary !== '') : ?>
                <p class="rch-wz-hero__meta">
                    <?php
                    $opts = $wz_opt_primary;
                    if ($wz_opt_mirror !== '') {
                        $opts .= ' + ' . $wz_opt_mirror;
                    }
                    printf(
                        /* translators: 1: comma-separated option names (e.g. pentama_options_v2) */
                        esc_html__('On this network default, values merge into: %s.', 'rechat-plugin'),
                        esc_html($opts)
                    );
                    ?>
                </p>
            <?php endif; ?>
            <p class="rch-wz-hero__hint">
                <?php
                esc_html_e(
                    'Field list is built from the theme option data on this site (and optional rechat-agent-wizard.json in the theme). Override option names via the rch_agent_wizard_storage_config filter or the rch_agent_wizard_theme_storage_map network option (JSON object keyed by stylesheet).',
                    'rechat-plugin'
                );
                ?>
            </p>
        </header>

        <nav class="rch-wz-stepnav" aria-label="<?php esc_attr_e('Wizard steps', 'rechat-plugin'); ?>">
            <ol class="rch-wz-stepnav__list">
                <li class="rch-wz-stepnav__item">
                    <button type="button" class="rch-wz-stepnav__btn rch-wz-goto" data-step="1">
                        <span class="rch-wz-stepnav__num" aria-hidden="true">1</span>
                        <span class="rch-wz-stepnav__label"><?php esc_html_e('Scope & agent', 'rechat-plugin'); ?></span>
                    </button>
                </li>
                <li class="rch-wz-stepnav__item rch-wz-stepnav__item--sep" aria-hidden="true"></li>
                <li class="rch-wz-stepnav__item">
                    <button type="button" class="rch-wz-stepnav__btn rch-wz-goto" data-step="2">
                        <span class="rch-wz-stepnav__num" aria-hidden="true">2</span>
                        <span class="rch-wz-stepnav__label"><?php esc_html_e('Theme options', 'rechat-plugin'); ?></span>
                    </button>
                </li>
                <li class="rch-wz-stepnav__item rch-wz-stepnav__item--sep" aria-hidden="true"></li>
                <li class="rch-wz-stepnav__item">
                    <button type="button" class="rch-wz-stepnav__btn rch-wz-goto" data-step="3">
                        <span class="rch-wz-stepnav__num" aria-hidden="true">3</span>
                        <span class="rch-wz-stepnav__label"><?php esc_html_e('Preview & deploy', 'rechat-plugin'); ?></span>
                    </button>
                </li>
            </ol>
        </nav>

        <div class="rch-agent-wizard-panel rch-wz-card" data-step-panel="1">
            <div class="rch-wz-card__head">
                <h3 class="rch-wz-card__title"><?php esc_html_e('Scope & agent', 'rechat-plugin'); ?></h3>
                <p class="rch-wz-card__subtitle"><?php esc_html_e('Choose one agent or push the same configuration to every agent sub-site.', 'rechat-plugin'); ?></p>
            </div>
            <div class="rch-wz-card__body">
                <fieldset class="rch-wz-fieldset rch-agent-wizard-scope">
                    <legend class="screen-reader-text"><?php esc_html_e('Deployment scope', 'rechat-plugin'); ?></legend>
                    <label class="rch-wz-choice">
                        <input type="radio" name="rch_wz_scope" id="rch-wz-scope-single" value="single" checked class="rch-wz-choice__input" />
                        <span class="rch-wz-choice__box">
                            <span class="rch-wz-choice__title"><?php esc_html_e('Single agent', 'rechat-plugin'); ?></span>
                            <span class="rch-wz-choice__desc"><?php esc_html_e('Load one profile for preview and deploy only to that agent’s sub-site.', 'rechat-plugin'); ?></span>
                        </span>
                    </label>
                    <label class="rch-wz-choice">
                        <input type="radio" name="rch_wz_scope" id="rch-wz-scope-all" value="all" class="rch-wz-choice__input" />
                        <span class="rch-wz-choice__box">
                            <span class="rch-wz-choice__title"><?php esc_html_e('All agent sub-sites', 'rechat-plugin'); ?></span>
                            <span class="rch-wz-choice__desc">
                                <?php
                                printf(
                                    /* translators: %d: number of agents with a sub-site */
                                    esc_html__('Run the same rules on every linked site (%d ready). Metabox values still come from each agent.', 'rechat-plugin'),
                                    (int) $bulk_agent_count
                                );
                                ?>
                            </span>
                        </span>
                    </label>
                </fieldset>

                <div id="rch-wz-single-agent-wrap" class="rch-wz-stack">
                    <div class="rch-wz-field">
                        <label class="rch-wz-field__label" for="rch-wz-agent-select"><?php esc_html_e('Agent', 'rechat-plugin'); ?></label>
                        <div class="rch-wz-field__row">
                            <select id="rch-wz-agent-select" class="rch-wz-select rch-wz-select--grow">
                                <option value=""><?php esc_html_e('Choose an agent…', 'rechat-plugin'); ?></option>
                                <?php foreach ($agents as $aid) : ?>
                                    <option value="<?php echo esc_attr((string) $aid); ?>"><?php echo esc_html(get_the_title($aid)); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="button rch-wz-btn-secondary" id="rch-wz-load-agent"><?php esc_html_e('Load profile', 'rechat-plugin'); ?></button>
                            <span class="spinner" id="rch-wz-agent-spinner"></span>
                        </div>
                    </div>
                    <div id="rch-wz-agent-summary" class="rch-wz-alert rch-wz-alert--info" hidden></div>
                </div>
            </div>
            <div class="rch-wz-card__footer">
                <button type="button" class="button rch-wz-btn-ghost" id="rch-wz-load-draft"><?php esc_html_e('Restore draft', 'rechat-plugin'); ?></button>
                <button type="button" class="button button-primary rch-wz-btn-primary rch-wz-next" data-next="2"><?php esc_html_e('Continue', 'rechat-plugin'); ?></button>
            </div>
        </div>

        <div class="rch-agent-wizard-panel rch-wz-card" data-step-panel="2" hidden>
            <div class="rch-wz-card__head">
                <h3 class="rch-wz-card__title"><?php esc_html_e('Theme options', 'rechat-plugin'); ?></h3>
                <p class="rch-wz-card__subtitle">
                    <?php
                    esc_html_e(
                        'Each row is one theme option key for the network default agent theme. Pick a source: leave unchanged, enter a value manually, or bind an agent profile field. Nothing is written until you deploy.',
                        'rechat-plugin'
                    );
                    ?>
                </p>
            </div>
            <div class="rch-wz-card__body rch-wz-card__body--flush">
                <?php if (empty($manual_rows)) : ?>
                    <div class="rch-wz-preview-empty" style="margin:16px;">
                        <p class="rch-wz-preview-empty__text">
                            <?php
                            esc_html_e(
                                'No theme options discovered for the selected default agent theme. This usually means the theme does not store its settings in a WordPress option array, or it has never been saved yet. Add a rechat-agent-wizard.json manifest to the theme, or configure option names via rch_agent_wizard_storage_config / rch_agent_wizard_theme_storage_map.',
                                'rechat-plugin'
                            );
                            ?>
                        </p>
                    </div>
                <?php else : ?>
                    <div class="rch-agent-wizard-manual-scroll rch-wz-theme-scroll">
                        <div class="rch-wz-theme-list">
                            <?php foreach ($manual_rows as $mr) : ?>
                            <?php
                            $fid = 'rch-wz-man-' . preg_replace('/[^a-zA-Z0-9_-]/', '', $mr['key']);
                            ?>
                            <div class="rch-wz-theme-row" data-theme-key="<?php echo esc_attr($mr['key']); ?>">
                                <div class="rch-wz-theme-row__label">
                                    <span class="rch-wz-theme-label"><?php echo esc_html($mr['label']); ?></span>
                                </div>
                                <div class="rch-wz-theme-row__controls">
                                    <div class="rch-wz-field">
                                        <label class="rch-wz-field__label rch-wz-field__label--sm"><?php esc_html_e('Source', 'rechat-plugin'); ?></label>
                                        <select class="rch-wz-select rch-wz-row-mode" data-theme-key="<?php echo esc_attr($mr['key']); ?>">
                                            <option value="skip"><?php esc_html_e('Do not change', 'rechat-plugin'); ?></option>
                                            <option value="manual"><?php esc_html_e('Set manually', 'rechat-plugin'); ?></option>
                                            <option value="meta"><?php esc_html_e('Agent profile (metabox)', 'rechat-plugin'); ?></option>
                                        </select>
                                    </div>
                                    <div class="rch-wz-row-meta-wrap rch-wz-stack" hidden>
                                        <div class="rch-wz-field">
                                            <label class="rch-wz-field__label rch-wz-field__label--sm"><?php esc_html_e('Profile field', 'rechat-plugin'); ?></label>
                                            <select class="rch-wz-select rch-wz-row-meta" data-theme-key="<?php echo esc_attr($mr['key']); ?>">
                                                <option value=""><?php esc_html_e('Choose field…', 'rechat-plugin'); ?></option>
                                                <?php foreach ($metabox_defs as $mk => $md) : ?>
                                                    <option value="<?php echo esc_attr($mk); ?>"><?php echo esc_html($md['label']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <p class="rch-wz-meta-preview rch-wz-hint"></p>
                                    </div>
                                    <div class="rch-wz-row-manual-wrap rch-wz-stack" hidden>
                                        <?php if ($mr['type'] === 'textarea' || $mr['type'] === 'textarea_json') : ?>
                                            <textarea
                                                class="rch-wz-textarea rch-wz-row-value"
                                                rows="<?php echo $mr['type'] === 'textarea_json' ? '3' : '4'; ?>"
                                                id="<?php echo esc_attr($fid); ?>"
                                                data-theme-key="<?php echo esc_attr($mr['key']); ?>"
                                            ></textarea>
                                            <?php if ($mr['type'] === 'textarea_json') : ?>
                                                <p class="rch-wz-hint"><?php esc_html_e('JSON array of tag strings.', 'rechat-plugin'); ?></p>
                                            <?php endif; ?>
                                        <?php else : ?>
                                            <input
                                                type="<?php echo esc_attr($mr['type'] === 'number' ? 'number' : ($mr['type'] === 'url' ? 'url' : 'text')); ?>"
                                                class="rch-wz-input rch-wz-row-value"
                                                id="<?php echo esc_attr($fid); ?>"
                                                data-theme-key="<?php echo esc_attr($mr['key']); ?>"
                                            />
                                        <?php endif; ?>
                                        <?php if ($mr['media'] === 'image') : ?>
                                            <button type="button" class="button rch-wz-btn-secondary rch-wz-media" data-target="<?php echo esc_attr($fid); ?>"><?php esc_html_e('Media library', 'rechat-plugin'); ?></button>
                                        <?php elseif ($mr['media'] === 'video') : ?>
                                            <button type="button" class="button rch-wz-btn-secondary rch-wz-media rch-wz-media-video" data-target="<?php echo esc_attr($fid); ?>"><?php esc_html_e('Media library', 'rechat-plugin'); ?></button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="rch-wz-card__footer">
                <button type="button" class="button rch-wz-btn-ghost rch-wz-prev" data-prev="1"><?php esc_html_e('Back', 'rechat-plugin'); ?></button>
                <button type="button" class="button button-primary rch-wz-btn-primary rch-wz-next" data-next="3"><?php esc_html_e('Continue', 'rechat-plugin'); ?></button>
            </div>
        </div>

        <div class="rch-agent-wizard-panel rch-wz-card" data-step-panel="3" hidden>
            <div class="rch-wz-card__head">
                <h3 class="rch-wz-card__title"><?php esc_html_e('Preview & deploy', 'rechat-plugin'); ?></h3>
                <p class="rch-wz-card__subtitle"><?php esc_html_e('Review what will be written, then save to each target sub-site.', 'rechat-plugin'); ?></p>
            </div>
            <div class="rch-wz-card__body">
                <div id="rch-wz-deploy-summary" class="rch-wz-summary"></div>
                <div id="rch-wz-preview-readable" class="rch-wz-preview-readable" aria-live="polite"></div>
                <details class="rch-wz-tech" id="rch-wz-tech-details">
                    <summary class="rch-wz-tech__summary"><?php esc_html_e('Show technical JSON (advanced)', 'rechat-plugin'); ?></summary>
                    <pre id="rch-wz-json-preview" class="rch-agent-wizard-json rch-wz-code"></pre>
                </details>
            </div>
            <div class="rch-wz-card__footer rch-wz-card__footer--split">
                <button type="button" class="button rch-wz-btn-ghost rch-wz-prev" data-prev="2"><?php esc_html_e('Back', 'rechat-plugin'); ?></button>
                <div class="rch-wz-card__footer-actions">
                    <button type="button" class="button rch-wz-btn-secondary" id="rch-wz-refresh-preview"><?php esc_html_e('Refresh preview', 'rechat-plugin'); ?></button>
                    <button type="button" class="button rch-wz-btn-secondary" id="rch-wz-save-draft"><?php esc_html_e('Save draft', 'rechat-plugin'); ?></button>
                    <button type="button" class="button button-primary rch-wz-btn-primary" id="rch-wz-deploy"><?php esc_html_e('Deploy now', 'rechat-plugin'); ?></button>
                    <span class="spinner" id="rch-wz-deploy-spinner"></span>
                </div>
            </div>
            <div id="rch-wz-deploy-result" class="rch-wz-deploy-result"></div>
        </div>

    </div>
</div>
