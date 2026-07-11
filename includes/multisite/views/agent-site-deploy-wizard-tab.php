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

$wz_broadcast_step = function_exists('rch_agent_wizard_broadcast_step_enabled') && rch_agent_wizard_broadcast_step_enabled();

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
                <?php if ($wz_broadcast_step) : ?>
                <li class="rch-wz-stepnav__item rch-wz-stepnav__item--sep" aria-hidden="true"></li>
                <li class="rch-wz-stepnav__item">
                    <button type="button" class="rch-wz-stepnav__btn rch-wz-goto" data-step="3">
                        <span class="rch-wz-stepnav__num" aria-hidden="true">3</span>
                        <span class="rch-wz-stepnav__label"><?php esc_html_e('Broadcast content', 'rechat-plugin'); ?></span>
                    </button>
                </li>
                <li class="rch-wz-stepnav__item rch-wz-stepnav__item--sep" aria-hidden="true"></li>
                <li class="rch-wz-stepnav__item">
                    <button type="button" class="rch-wz-stepnav__btn rch-wz-goto" data-step="4">
                        <span class="rch-wz-stepnav__num" aria-hidden="true">4</span>
                        <span class="rch-wz-stepnav__label"><?php esc_html_e('Menus & widgets', 'rechat-plugin'); ?></span>
                    </button>
                </li>
                <li class="rch-wz-stepnav__item rch-wz-stepnav__item--sep" aria-hidden="true"></li>
                <li class="rch-wz-stepnav__item">
                    <button type="button" class="rch-wz-stepnav__btn rch-wz-goto" data-step="5">
                        <span class="rch-wz-stepnav__num" aria-hidden="true">5</span>
                        <span class="rch-wz-stepnav__label"><?php esc_html_e('Preview & deploy', 'rechat-plugin'); ?></span>
                    </button>
                </li>
                <?php else : ?>
                <li class="rch-wz-stepnav__item rch-wz-stepnav__item--sep" aria-hidden="true"></li>
                <li class="rch-wz-stepnav__item">
                    <button type="button" class="rch-wz-stepnav__btn rch-wz-goto" data-step="3">
                        <span class="rch-wz-stepnav__num" aria-hidden="true">3</span>
                        <span class="rch-wz-stepnav__label"><?php esc_html_e('Menus & widgets', 'rechat-plugin'); ?></span>
                    </button>
                </li>
                <li class="rch-wz-stepnav__item rch-wz-stepnav__item--sep" aria-hidden="true"></li>
                <li class="rch-wz-stepnav__item">
                    <button type="button" class="rch-wz-stepnav__btn rch-wz-goto" data-step="4">
                        <span class="rch-wz-stepnav__num" aria-hidden="true">4</span>
                        <span class="rch-wz-stepnav__label"><?php esc_html_e('Preview & deploy', 'rechat-plugin'); ?></span>
                    </button>
                </li>
                <?php endif; ?>
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

                <div class="rch-wz-testimonials-sync rch-wz-stack" id="rch-wz-testimonials-sync">
                    <h4 class="rch-wz-testimonials-sync__title"><?php esc_html_e('Testimonials', 'rechat-plugin'); ?></h4>
                    <p class="rch-wz-hint">
                        <?php
                        esc_html_e(
                            'Copy agent testimonials from the main site into each sub-site testimonial post (one post per row). Re-run to update or remove posts deleted on the main site. Saving an agent on the main site also syncs automatically.',
                            'rechat-plugin'
                        );
                        ?>
                    </p>
                    <p class="rch-wz-testimonials-sync__count" id="rch-wz-testimonials-count" aria-live="polite"></p>
                    <div class="rch-wz-field__row">
                        <button type="button" class="button button-secondary" id="rch-wz-sync-testimonials">
                            <?php esc_html_e('Import testimonials to sub-site(s)', 'rechat-plugin'); ?>
                        </button>
                        <span class="spinner" id="rch-wz-sync-testimonials-spinner"></span>
                    </div>
                    <div id="rch-wz-sync-testimonials-result" class="rch-wz-deploy-result" aria-live="polite"></div>
                    <div class="rch-wz-field__row">
                        <button type="button" class="button rch-wz-btn-danger" id="rch-wz-delete-testimonials">
                            <?php esc_html_e('Delete all testimonials (main site + sub-site)', 'rechat-plugin'); ?>
                        </button>
                        <span class="spinner" id="rch-wz-delete-testimonials-spinner"></span>
                    </div>
                    <p class="rch-wz-hint rch-wz-hint--danger">
                        <?php esc_html_e('Permanently removes every testimonial post on the sub-site and clears the agent testimonial list on the main site. Cannot be undone.', 'rechat-plugin'); ?>
                    </p>
                    <div id="rch-wz-delete-testimonials-result" class="rch-wz-deploy-result" aria-live="polite"></div>
                </div>
            </div>
            <div class="rch-wz-card__footer">
                <button type="button" class="button rch-wz-btn-ghost" id="rch-wz-load-draft"><?php esc_html_e('Restore my draft', 'rechat-plugin'); ?></button>
                <button type="button" class="button button-primary rch-wz-btn-primary rch-wz-next" data-next="2"><?php esc_html_e('Continue', 'rechat-plugin'); ?></button>
            </div>
        </div>

        <div class="rch-agent-wizard-panel rch-wz-card" data-step-panel="2" hidden>
            <div class="rch-wz-card__head">
                <h3 class="rch-wz-card__title"><?php esc_html_e('Theme options', 'rechat-plugin'); ?></h3>
                <p class="rch-wz-card__subtitle">
                    <?php
                    esc_html_e(
                        'Fields are loaded automatically from the active agent theme (themeoption.php keys and Theme Setting labels). When you add or remove options in the theme, reload this page to refresh the list. Pick a source: leave unchanged, set manually, or bind an agent profile field. Nothing is written until you deploy.',
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
                                    <?php if (! empty($mr['help'])) : ?>
                                        <p class="rch-wz-theme-help"><?php echo esc_html((string) $mr['help']); ?></p>
                                    <?php endif; ?>
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
                                        <?php if ($mr['type'] === 'select') : ?>
                                            <select
                                                class="rch-wz-select rch-wz-row-value"
                                                id="<?php echo esc_attr($fid); ?>"
                                                data-theme-key="<?php echo esc_attr($mr['key']); ?>"
                                            >
                                                <option value=""><?php esc_html_e('— Select —', 'rechat-plugin'); ?></option>
                                                <?php foreach ($mr['options'] ?? [] as $opt) : ?>
                                                    <?php if (! isset($opt['value'], $opt['label'])) { continue; } ?>
                                                    <option value="<?php echo esc_attr((string) $opt['value']); ?>"><?php echo esc_html((string) $opt['label']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <p class="rch-wz-hint"><?php esc_html_e('Same choices as the theme option panel (Rechat API).', 'rechat-plugin'); ?></p>
                                        <?php elseif ($mr['type'] === 'tags') : ?>
                                            <div class="rch-wz-tags-wrap" id="<?php echo esc_attr($fid); ?>-tags-wrap">
                                                <select class="rch-wz-select rch-wz-tag-add" aria-label="<?php esc_attr_e('Add tag', 'rechat-plugin'); ?>">
                                                    <option value=""><?php esc_html_e('— Add tag —', 'rechat-plugin'); ?></option>
                                                    <?php foreach ($mr['options'] ?? [] as $opt) : ?>
                                                        <?php if (! isset($opt['value'], $opt['label'])) { continue; } ?>
                                                        <option value="<?php echo esc_attr((string) $opt['value']); ?>"><?php echo esc_html((string) $opt['label']); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <div class="rch-wz-tag-chips" aria-live="polite"></div>
                                                <input type="hidden" class="rch-wz-row-value" id="<?php echo esc_attr($fid); ?>" data-theme-key="<?php echo esc_attr($mr['key']); ?>" value="[]" />
                                                <p class="rch-wz-hint"><?php esc_html_e('Pick tags like the theme panel; stored as a JSON array of strings.', 'rechat-plugin'); ?></p>
                                            </div>
                                        <?php elseif ($mr['type'] === 'textarea' || $mr['type'] === 'textarea_json') : ?>
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
                                        <?php if (in_array($mr['type'], ['text', 'textarea'], true)) : ?>
                                            <div class="rch-wz-tokens" aria-label="<?php esc_attr_e('Insert agent placeholders', 'rechat-plugin'); ?>">
                                                <div class="rch-wz-tokens__label"><?php esc_html_e('Placeholders', 'rechat-plugin'); ?></div>
                                                <div class="rch-wz-tokens__list">
                                                    <button type="button" class="rch-wz-token" data-token="{$post_title}"><?php esc_html_e('Agent name', 'rechat-plugin'); ?></button>
                                                    <?php foreach ($metabox_defs as $mk => $md) : ?>
                                                        <button type="button" class="rch-wz-token" data-token="<?php echo esc_attr('{$' . $mk . '}'); ?>">
                                                            <?php echo esc_html($md['label']); ?>
                                                        </button>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
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

        <?php if ($wz_broadcast_step) : ?>
        <div class="rch-agent-wizard-panel rch-wz-card" data-step-panel="3" hidden>
            <div class="rch-wz-card__head">
                <h3 class="rch-wz-card__title"><?php esc_html_e('Broadcast content', 'rechat-plugin'); ?></h3>
                <p class="rch-wz-card__subtitle"><?php esc_html_e('Push selected posts and pages from the main Broadcast source site to your sub-sites. Pick a target scope below; use “All sub-sites” only when you intend every site.', 'rechat-plugin'); ?></p>
            </div>
            <div class="rch-wz-card__body">
                <fieldset class="rch-wz-fieldset rch-wz-bc-targets">
                    <legend class="rch-wz-field__label"><?php esc_html_e('Target blogs', 'rechat-plugin'); ?></legend>
                    <label class="rch-wz-choice rch-wz-choice--compact">
                        <input type="radio" name="rch_wz_bc_target" id="rch-wz-bc-target-agent" value="agent_only" checked class="rch-wz-choice__input" />
                        <span class="rch-wz-choice__box">
                            <span class="rch-wz-choice__title"><?php esc_html_e('Agent sub-sites only', 'rechat-plugin'); ?></span>
                        </span>
                    </label>
                    <label class="rch-wz-choice rch-wz-choice--compact">
                        <input type="radio" name="rch_wz_bc_target" id="rch-wz-bc-target-office" value="office_only" class="rch-wz-choice__input" />
                        <span class="rch-wz-choice__box">
                            <span class="rch-wz-choice__title"><?php esc_html_e('Office sub-sites only', 'rechat-plugin'); ?></span>
                        </span>
                    </label>
                    <label class="rch-wz-choice rch-wz-choice--compact">
                        <input type="radio" name="rch_wz_bc_target" id="rch-wz-bc-target-all" value="all_subsites" class="rch-wz-choice__input" />
                        <span class="rch-wz-choice__box">
                            <span class="rch-wz-choice__title"><?php esc_html_e('All sub-sites (except source)', 'rechat-plugin'); ?></span>
                        </span>
                    </label>
                </fieldset>
                <p class="rch-wz-hint" id="rch-wz-bc-target-summary"></p>
                <div class="rch-wz-field rch-wz-bc-search-row">
                    <label class="rch-wz-field__label" for="rch-wz-bc-search"><?php esc_html_e('Search', 'rechat-plugin'); ?></label>
                    <div class="rch-wz-field__row">
                        <input type="search" id="rch-wz-bc-search" class="rch-wz-input rch-wz-input--grow" placeholder="<?php esc_attr_e('Search titles…', 'rechat-plugin'); ?>" />
                        <button type="button" class="button rch-wz-btn-secondary" id="rch-wz-bc-load"><?php esc_html_e('Load list', 'rechat-plugin'); ?></button>
                        <span class="spinner" id="rch-wz-bc-spinner"></span>
                    </div>
                </div>
                <p class="rch-wz-hint" id="rch-wz-bc-pageinfo"></p>
                <div class="rch-wz-bc-table-wrap">
                    <table class="rch-wz-bc-table widefat striped" id="rch-wz-bc-table">
                        <thead>
                            <tr>
                                <th class="rch-wz-bc-col-check" scope="col"><span class="screen-reader-text"><?php esc_html_e('Select', 'rechat-plugin'); ?></span></th>
                                <th scope="col"><?php esc_html_e('Title', 'rechat-plugin'); ?></th>
                                <th scope="col"><?php esc_html_e('Type', 'rechat-plugin'); ?></th>
                                <th scope="col"><?php esc_html_e('Status', 'rechat-plugin'); ?></th>
                                <th scope="col"><?php esc_html_e('Broadcasted', 'rechat-plugin'); ?></th>
                                <th scope="col"><?php esc_html_e('Modified', 'rechat-plugin'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="rch-wz-bc-tbody">
                            <tr class="rch-wz-bc-placeholder"><td colspan="6"><?php esc_html_e('Click “Load list” to show posts and pages from the source site.', 'rechat-plugin'); ?></td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="rch-wz-bc-pager">
                    <button type="button" class="button" id="rch-wz-bc-prev" disabled><?php esc_html_e('Previous', 'rechat-plugin'); ?></button>
                    <button type="button" class="button" id="rch-wz-bc-next" disabled><?php esc_html_e('Next', 'rechat-plugin'); ?></button>
                    <button type="button" class="button rch-wz-btn-secondary" id="rch-wz-bc-selall"><?php esc_html_e('Select all on page', 'rechat-plugin'); ?></button>
                    <button type="button" class="button rch-wz-btn-secondary" id="rch-wz-bc-selnone"><?php esc_html_e('Clear page', 'rechat-plugin'); ?></button>
                </div>
                <div id="rch-wz-bc-result" class="rch-wz-deploy-result" aria-live="polite"></div>
            </div>
            <div class="rch-wz-card__footer rch-wz-card__footer--split">
                <button type="button" class="button rch-wz-btn-ghost rch-wz-prev" data-prev="2"><?php esc_html_e('Back', 'rechat-plugin'); ?></button>
                <div class="rch-wz-card__footer-actions">
                    <button type="button" class="button button-primary rch-wz-btn-primary" id="rch-wz-bc-run"><?php esc_html_e('Broadcast selected', 'rechat-plugin'); ?></button>
                    <span class="spinner" id="rch-wz-bc-run-spinner"></span>
                    <button type="button" class="button rch-wz-btn-secondary rch-wz-next" data-next="4"><?php esc_html_e('Continue to menus & widgets', 'rechat-plugin'); ?></button>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="rch-agent-wizard-panel rch-wz-card" data-step-panel="<?php echo esc_attr($wz_broadcast_step ? '4' : '3'); ?>" hidden>
            <div class="rch-wz-card__head">
                <h3 class="rch-wz-card__title"><?php esc_html_e('Menus & widgets', 'rechat-plugin'); ?></h3>
                <p class="rch-wz-card__subtitle"><?php esc_html_e('Copy navigation menus from the template site to many sub-sites, optionally replace all widget areas with the same configuration. Menu links that pointed to pages on the template site become custom URLs.', 'rechat-plugin'); ?></p>
            </div>
            <div class="rch-wz-card__body">
                <fieldset class="rch-wz-fieldset rch-wz-mw-targets">
                    <legend class="rch-wz-field__label"><?php esc_html_e('Target blogs', 'rechat-plugin'); ?></legend>
                    <label class="rch-wz-choice rch-wz-choice--compact">
                        <input type="radio" name="rch_wz_mw_target" id="rch-wz-mw-target-agent" value="agent_only" checked class="rch-wz-choice__input" />
                        <span class="rch-wz-choice__box">
                            <span class="rch-wz-choice__title"><?php esc_html_e('Agent sub-sites only', 'rechat-plugin'); ?></span>
                        </span>
                    </label>
                    <label class="rch-wz-choice rch-wz-choice--compact">
                        <input type="radio" name="rch_wz_mw_target" id="rch-wz-mw-target-office" value="office_only" class="rch-wz-choice__input" />
                        <span class="rch-wz-choice__box">
                            <span class="rch-wz-choice__title"><?php esc_html_e('Office sub-sites only', 'rechat-plugin'); ?></span>
                        </span>
                    </label>
                    <label class="rch-wz-choice rch-wz-choice--compact">
                        <input type="radio" name="rch_wz_mw_target" id="rch-wz-mw-target-all" value="all_subsites" class="rch-wz-choice__input" />
                        <span class="rch-wz-choice__box">
                            <span class="rch-wz-choice__title"><?php esc_html_e('All sub-sites (except template)', 'rechat-plugin'); ?></span>
                        </span>
                    </label>
                </fieldset>
                <p class="rch-wz-hint" id="rch-wz-mw-target-summary"></p>
                <div class="rch-wz-field rch-wz-field__row">
                    <button type="button" class="button rch-wz-btn-secondary" id="rch-wz-mw-load"><?php esc_html_e('Load menus & sidebars', 'rechat-plugin'); ?></button>
                    <span class="spinner" id="rch-wz-mw-spinner"></span>
                </div>

                <div class="rch-wz-mb-panel">
                    <h4 class="rch-wz-mb-heading"><?php esc_html_e('Build new menu for targets', 'rechat-plugin'); ?></h4>
                    <p class="rch-wz-hint"><?php esc_html_e('Add links from template posts/pages or custom URLs, choose display locations from the template theme, then create the same menu on every site in the target scope above.', 'rechat-plugin'); ?></p>
                    <?php if ($wz_broadcast_step) : ?>
                    <div class="rch-wz-mb-broadcasted" id="rch-wz-mb-broadcasted-wrap">
                        <p class="rch-wz-field__label"><?php esc_html_e('Broadcasted posts & pages', 'rechat-plugin'); ?></p>
                        <p class="rch-wz-hint"><?php esc_html_e('Content already pushed to sub-sites via Broadcast. Select items and add them to the menu below; each target site will use its own copy of the page or post.', 'rechat-plugin'); ?></p>
                        <div class="rch-wz-field rch-wz-bc-search-row">
                            <label class="rch-wz-field__label screen-reader-text" for="rch-wz-mb-bc-search"><?php esc_html_e('Search broadcasted content', 'rechat-plugin'); ?></label>
                            <div class="rch-wz-field__row">
                                <input type="search" id="rch-wz-mb-bc-search" class="rch-wz-input rch-wz-input--grow" placeholder="<?php esc_attr_e('Search titles…', 'rechat-plugin'); ?>" />
                                <button type="button" class="button rch-wz-btn-secondary" id="rch-wz-mb-bc-load"><?php esc_html_e('Load broadcasted list', 'rechat-plugin'); ?></button>
                                <span class="spinner" id="rch-wz-mb-bc-spinner"></span>
                            </div>
                        </div>
                        <p class="rch-wz-hint" id="rch-wz-mb-bc-pageinfo"></p>
                        <div class="rch-wz-bc-table-wrap">
                            <table class="rch-wz-bc-table widefat striped" id="rch-wz-mb-bc-table">
                                <thead>
                                    <tr>
                                        <th class="rch-wz-bc-col-check" scope="col"><span class="screen-reader-text"><?php esc_html_e('Select', 'rechat-plugin'); ?></span></th>
                                        <th scope="col"><?php esc_html_e('Title', 'rechat-plugin'); ?></th>
                                        <th scope="col"><?php esc_html_e('Type', 'rechat-plugin'); ?></th>
                                        <th scope="col"><?php esc_html_e('Sub-sites', 'rechat-plugin'); ?></th>
                                        <th scope="col"><?php esc_html_e('Modified', 'rechat-plugin'); ?></th>
                                        <th scope="col"><?php esc_html_e('Add', 'rechat-plugin'); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="rch-wz-mb-bc-tbody">
                                    <tr class="rch-wz-bc-placeholder"><td colspan="6"><?php esc_html_e('Click “Load broadcasted list” to show posts and pages that have already been broadcast.', 'rechat-plugin'); ?></td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="rch-wz-bc-pager">
                            <button type="button" class="button" id="rch-wz-mb-bc-prev" disabled><?php esc_html_e('Previous', 'rechat-plugin'); ?></button>
                            <button type="button" class="button" id="rch-wz-mb-bc-next" disabled><?php esc_html_e('Next', 'rechat-plugin'); ?></button>
                            <button type="button" class="button rch-wz-btn-secondary" id="rch-wz-mb-bc-selall"><?php esc_html_e('Select all on page', 'rechat-plugin'); ?></button>
                            <button type="button" class="button rch-wz-btn-secondary" id="rch-wz-mb-bc-selnone"><?php esc_html_e('Clear page', 'rechat-plugin'); ?></button>
                            <button type="button" class="button button-primary" id="rch-wz-mb-bc-add-selected"><?php esc_html_e('Add selected to menu', 'rechat-plugin'); ?></button>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="rch-wz-field">
                        <label class="rch-wz-field__label" for="rch-wz-mb-name"><?php esc_html_e('New menu name', 'rechat-plugin'); ?></label>
                        <input type="text" id="rch-wz-mb-name" class="rch-wz-input rch-wz-input--grow" maxlength="100" placeholder="<?php esc_attr_e('e.g. Main navigation', 'rechat-plugin'); ?>" autocomplete="off" />
                    </div>
                    <div class="rch-wz-mb-two-col">
                        <div class="rch-wz-mb-col">
                            <p class="rch-wz-field__label"><?php esc_html_e('Add from template content', 'rechat-plugin'); ?></p>
                            <div class="rch-wz-field__row">
                                <input type="search" id="rch-wz-mb-search" class="rch-wz-input rch-wz-input--grow" placeholder="<?php esc_attr_e('Search titles…', 'rechat-plugin'); ?>" />
                                <button type="button" class="button rch-wz-btn-secondary" id="rch-wz-mb-search-btn"><?php esc_html_e('Search', 'rechat-plugin'); ?></button>
                            </div>
                            <div id="rch-wz-mb-search-results" class="rch-wz-mb-search-results" aria-live="polite"></div>
                            <div class="rch-wz-mb-pager">
                                <button type="button" class="button" id="rch-wz-mb-search-prev" disabled><?php esc_html_e('Previous', 'rechat-plugin'); ?></button>
                                <button type="button" class="button" id="rch-wz-mb-search-next" disabled><?php esc_html_e('Next', 'rechat-plugin'); ?></button>
                            </div>
                        </div>
                        <div class="rch-wz-mb-col">
                            <p class="rch-wz-field__label"><?php esc_html_e('Custom link', 'rechat-plugin'); ?></p>
                            <input type="url" id="rch-wz-mb-custom-url" class="rch-wz-input" placeholder="<?php esc_attr_e('https://…', 'rechat-plugin'); ?>" />
                            <input type="text" id="rch-wz-mb-custom-title" class="rch-wz-input" placeholder="<?php esc_attr_e('Link text', 'rechat-plugin'); ?>" />
                            <button type="button" class="button rch-wz-btn-secondary" id="rch-wz-mb-custom-add"><?php esc_html_e('Add custom link', 'rechat-plugin'); ?></button>
                        </div>
                    </div>
                    <fieldset class="rch-wz-fieldset">
                        <legend class="rch-wz-field__label"><?php esc_html_e('Menu structure', 'rechat-plugin'); ?></legend>
                        <div class="rch-wz-mb-table-wrap">
                            <table class="rch-wz-mb-table widefat">
                                <thead>
                                    <tr>
                                        <th scope="col"><?php esc_html_e('Link text', 'rechat-plugin'); ?></th>
                                        <th scope="col"><?php esc_html_e('URL', 'rechat-plugin'); ?></th>
                                        <th class="rch-wz-mb-col-actions" scope="col"><?php esc_html_e('Actions', 'rechat-plugin'); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="rch-wz-mb-items"></tbody>
                            </table>
                            <p class="rch-wz-hint" id="rch-wz-mb-items-empty"><?php esc_html_e('No links yet. Search or add a custom link.', 'rechat-plugin'); ?></p>
                        </div>
                    </fieldset>
                    <fieldset class="rch-wz-fieldset">
                        <legend class="rch-wz-field__label"><?php esc_html_e('Theme display locations (template)', 'rechat-plugin'); ?></legend>
                        <p class="rch-wz-hint" id="rch-wz-mb-locs-empty"><?php esc_html_e('Load menus & sidebars above to load locations, or your theme may not register menu locations.', 'rechat-plugin'); ?></p>
                        <div id="rch-wz-mb-locs" class="rch-wz-mw-checklist"></div>
                    </fieldset>
                    <div class="rch-wz-field rch-wz-field__row">
                        <button type="button" class="button button-primary" id="rch-wz-mb-create"><?php esc_html_e('Create menu on all targets', 'rechat-plugin'); ?></button>
                        <span class="spinner" id="rch-wz-mb-spinner"></span>
                    </div>
                    <div id="rch-wz-mb-result" class="rch-wz-deploy-result" aria-live="polite"></div>
                </div>

                <fieldset class="rch-wz-fieldset">
                    <legend class="rch-wz-field__label"><?php esc_html_e('Navigation menus to copy', 'rechat-plugin'); ?></legend>
                    <div id="rch-wz-mw-menus" class="rch-wz-mw-checklist">
                        <p class="rch-wz-hint"><?php esc_html_e('Click “Load menus & sidebars” first.', 'rechat-plugin'); ?></p>
                    </div>
                </fieldset>
                <fieldset class="rch-wz-fieldset">
                    <legend class="rch-wz-field__label"><?php esc_html_e('Widgets', 'rechat-plugin'); ?></legend>
                    <label class="rch-wz-choice rch-wz-choice--compact">
                        <input type="radio" name="rch_wz_mw_widget_mode" id="rch-wz-mw-widgets-none" value="none" class="rch-wz-choice__input" checked />
                        <span class="rch-wz-choice__box">
                            <span class="rch-wz-choice__title"><?php esc_html_e('Don’t copy widgets', 'rechat-plugin'); ?></span>
                            <span class="rch-wz-choice__desc"><?php esc_html_e('Leave each target site’s widget areas untouched.', 'rechat-plugin'); ?></span>
                        </span>
                    </label>
                    <label class="rch-wz-choice rch-wz-choice--compact">
                        <input type="radio" name="rch_wz_mw_widget_mode" id="rch-wz-mw-widgets-asis" value="asis" class="rch-wz-choice__input" />
                        <span class="rch-wz-choice__box">
                            <span class="rch-wz-choice__title"><?php esc_html_e('Copy widgets as-is (links stay on the template site)', 'rechat-plugin'); ?></span>
                            <span class="rch-wz-choice__desc"><?php esc_html_e('Overwrites sidebars_widgets and every widget_* option on each target. Menu and media links keep pointing at the template site.', 'rechat-plugin'); ?></span>
                        </span>
                    </label>
                    <?php if ($wz_broadcast_step) : ?>
                    <label class="rch-wz-choice rch-wz-choice--compact">
                        <input type="radio" name="rch_wz_mw_widget_mode" id="rch-wz-mw-widgets-broadcast" value="broadcast" class="rch-wz-choice__input" />
                        <span class="rch-wz-choice__box">
                            <span class="rch-wz-choice__title"><?php esc_html_e('Copy widgets + broadcast linked pages (links point to each sub-site’s own copy)', 'rechat-plugin'); ?></span>
                            <span class="rch-wz-choice__desc"><?php esc_html_e('Copies widgets, then for every page/post linked in the copied menus broadcasts it to each target sub-site (re-using existing copies — no duplicates) and rewrites the menu links to that sub-site’s own page.', 'rechat-plugin'); ?></span>
                        </span>
                    </label>
                    <?php endif; ?>
                    <div id="rch-wz-mw-sidebars-note" class="rch-wz-hint" hidden></div>
                </fieldset>
                <div id="rch-wz-mw-result" class="rch-wz-deploy-result" aria-live="polite"></div>
            </div>
            <div class="rch-wz-card__footer rch-wz-card__footer--split">
                <button type="button" class="button rch-wz-btn-ghost rch-wz-prev" data-prev="<?php echo esc_attr($wz_broadcast_step ? '3' : '2'); ?>"><?php esc_html_e('Back', 'rechat-plugin'); ?></button>
                <div class="rch-wz-card__footer-actions">
                    <button type="button" class="button button-primary rch-wz-btn-primary" id="rch-wz-mw-apply"><?php esc_html_e('Apply to target sites', 'rechat-plugin'); ?></button>
                    <span class="spinner" id="rch-wz-mw-apply-spinner"></span>
                    <button type="button" class="button rch-wz-btn-secondary rch-wz-next" data-next="<?php echo esc_attr($wz_broadcast_step ? '5' : '4'); ?>"><?php esc_html_e('Continue to preview & deploy', 'rechat-plugin'); ?></button>
                </div>
            </div>
        </div>

        <div class="rch-agent-wizard-panel rch-wz-card" data-step-panel="<?php echo esc_attr($wz_broadcast_step ? '5' : '4'); ?>" hidden>
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
                <button type="button" class="button rch-wz-btn-ghost rch-wz-prev" data-prev="<?php echo esc_attr($wz_broadcast_step ? '4' : '3'); ?>"><?php esc_html_e('Back', 'rechat-plugin'); ?></button>
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
