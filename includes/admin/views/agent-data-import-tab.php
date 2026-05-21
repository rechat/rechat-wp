<?php
/**
 * Agent CSV import tab markup.
 *
 * @package RechatPlugin
 */

if (! defined('ABSPATH')) {
    exit;
}

$match_options = rch_agent_import_allowed_match_by();
$sample_urls         = function_exists('rch_agent_import_get_sample_urls') ? rch_agent_import_get_sample_urls() : ['full' => '', 'simple' => ''];
$sample_url          = $sample_urls['full'];
$sample_simple_url   = $sample_urls['simple'];
?>
<div class="tab-content rch-agent-import">
    <div class="rch-agent-import__hero">
        <div>
            <p class="rch-agent-import__eyebrow"><?php esc_html_e('Agents', 'rechat-plugin'); ?></p>
            <h2><?php esc_html_e('Import bios & testimonials', 'rechat-plugin'); ?></h2>
            <p class="rch-agent-import__lead">
                <?php esc_html_e('Each row updates one agent. You can put bio and a testimonial on the same row, or split them across several rows for the same agent.', 'rechat-plugin'); ?>
            </p>
        </div>
        <div class="rch-agent-import__hero-actions">
            <a class="button button-secondary" href="<?php echo esc_url($sample_url); ?>">
                <?php esc_html_e('Sample CSV (multi-row)', 'rechat-plugin'); ?>
            </a>
            <a class="button button-link" href="<?php echo esc_url($sample_simple_url); ?>">
                <?php esc_html_e('Simple CSV (one row)', 'rechat-plugin'); ?>
            </a>
        </div>
    </div>

    <section class="rch-agent-import__guide" aria-labelledby="rch-import-guide-title">
        <h3 id="rch-import-guide-title"><?php esc_html_e('How to read the CSV', 'rechat-plugin'); ?></h3>
        <div class="rch-agent-import__guide-grid">
            <div class="rch-agent-import__guide-block">
                <h4><?php esc_html_e('Columns', 'rechat-plugin'); ?></h4>
                <dl class="rch-agent-import__dl">
                    <dt><code>agent_match</code></dt>
                    <dd><?php esc_html_e('Who to update: WordPress agent post ID (number), Rechat ID (api_id), or exact agent name.', 'rechat-plugin'); ?></dd>
                    <dt><code>match_by</code></dt>
                    <dd><?php esc_html_e('Must be the word api_id, post_id, title, or auto — not the Rechat ID itself. Leave blank to use the form default (api_id). If you pasted the UUID in both columns, the importer still fixes that automatically.', 'rechat-plugin'); ?></dd>
                    <dt><code>bio</code></dt>
                    <dd><?php esc_html_e('Agent biography → saved in the agent post content (main editor).', 'rechat-plugin'); ?></dd>
                    <dt><code>title</code></dt>
                    <dd><?php esc_html_e('Agent display title (e.g. Licensed Real Estate Agent) → saved as agent_title meta.', 'rechat-plugin'); ?></dd>
                    <dt><code>testimonial_name</code></dt>
                    <dd><?php esc_html_e('Person who gave the quote (client name). Use - if unknown.', 'rechat-plugin'); ?></dd>
                    <dt><code>testimonial_description</code></dt>
                    <dd><?php esc_html_e('The testimonial text.', 'rechat-plugin'); ?></dd>
                    <dt><code>testimonial_rank</code></dt>
                    <dd><?php esc_html_e('Star rating (free text: 4.7, 4.8, 5, etc.). Aliases: testimonial_stars, stars, rating.', 'rechat-plugin'); ?></dd>
                    <dt><code>testimonial_link</code></dt>
                    <dd><?php esc_html_e('URL for this testimonial (e.g. RateMyAgent review link).', 'rechat-plugin'); ?></dd>
                </dl>
            </div>
            <div class="rch-agent-import__guide-block">
                <h4><?php esc_html_e('Empty cells', 'rechat-plugin'); ?></h4>
                <p><?php esc_html_e('Leave a cell blank if that row does not set that field. Blank bio cells do not erase an existing bio. Blank testimonial cells mean “no testimonial on this row”.', 'rechat-plugin'); ?></p>
                <p><strong><?php esc_html_e('Same agent, multiple rows:', 'rechat-plugin'); ?></strong>
                <?php esc_html_e('Repeat agent_match on each row, or leave agent_match blank on follow-up rows to attach testimonials to the previous agent (as in your bulk export).', 'rechat-plugin'); ?></p>
            </div>
        </div>
        <h4 class="rch-agent-import__example-title"><?php esc_html_e('Example from the sample file (agent post ID 42)', 'rechat-plugin'); ?></h4>
        <div class="rch-agent-import__table-wrap rch-agent-import__table-wrap--guide">
            <table class="widefat rch-agent-import__table rch-agent-import__table--guide">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Row', 'rechat-plugin'); ?></th>
                        <th>agent_match</th>
                        <th>match_by</th>
                        <th>bio</th>
                        <th>title</th>
                        <th>testimonial_name</th>
                        <th>testimonial_rank</th>
                        <th><?php esc_html_e('What happens', 'rechat-plugin'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>1</td>
                        <td><code>42</code></td>
                        <td>post_id</td>
                        <td class="rch-agent-import__cell--fill"><?php esc_html_e('Bio…', 'rechat-plugin'); ?></td>
                        <td><?php esc_html_e('Licensed RE Agent', 'rechat-plugin'); ?></td>
                        <td class="rch-agent-import__cell--empty">—</td>
                        <td class="rch-agent-import__cell--empty">—</td>
                        <td><?php esc_html_e('Bio + agent title', 'rechat-plugin'); ?></td>
                    </tr>
                    <tr>
                        <td>2</td>
                        <td class="rch-agent-import__cell--empty">—</td>
                        <td class="rch-agent-import__cell--empty">—</td>
                        <td class="rch-agent-import__cell--empty">—</td>
                        <td class="rch-agent-import__cell--empty">—</td>
                        <td>Sarah M.</td>
                        <td>5</td>
                        <td><?php esc_html_e('Testimonial #1 (blank agent_match)', 'rechat-plugin'); ?></td>
                    </tr>
                    <tr>
                        <td>3</td>
                        <td class="rch-agent-import__cell--empty">—</td>
                        <td class="rch-agent-import__cell--empty">—</td>
                        <td class="rch-agent-import__cell--empty">—</td>
                        <td class="rch-agent-import__cell--empty">—</td>
                        <td>Tom R.</td>
                        <td>4.7</td>
                        <td><?php esc_html_e('Testimonial #2 (same agent)', 'rechat-plugin'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p class="description rch-agent-import__guide-foot">
            <?php esc_html_e('Result for agent 42: one bio + two testimonials. Lines starting with # in the CSV are comments and are ignored.', 'rechat-plugin'); ?>
        </p>
    </section>

    <form id="rch-agent-import-form" class="rch-agent-import__form" enctype="multipart/form-data" novalidate>
        <div class="rch-agent-import__grid">
            <section class="rch-agent-import__card">
                <h3><?php esc_html_e('1. Match agents', 'rechat-plugin'); ?></h3>
                <p class="description">
                    <?php esc_html_e('Required column: agent_match. Optional per-row match_by overrides the default below.', 'rechat-plugin'); ?>
                </p>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="rch-import-match-by"><?php esc_html_e('Default match by', 'rechat-plugin'); ?></label>
                        </th>
                        <td>
                            <select id="rch-import-match-by" name="match_by">
                                <?php foreach ($match_options as $value => $label) : ?>
                                    <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>
                <details class="rch-agent-import__columns">
                    <summary><?php esc_html_e('Accepted column names', 'rechat-plugin'); ?></summary>
                    <ul>
                        <li><code>agent_match</code> — <?php esc_html_e('post ID, Rechat api_id, or exact agent title', 'rechat-plugin'); ?></li>
                        <li><code>bio</code>, <code>title</code> (agent_title meta)</li>
                        <li><code>testimonial_name</code>, <code>testimonial_description</code>, <code>testimonial_rank</code>, <code>testimonial_link</code></li>
                        <li><?php esc_html_e('Aliases: BIO, Title, Testimonial_rank, stars, link, …', 'rechat-plugin'); ?></li>
                    </ul>
                </details>
            </section>

            <section class="rch-agent-import__card">
                <h3><?php esc_html_e('2. What to import', 'rechat-plugin'); ?></h3>
                <fieldset class="rch-agent-import__checks">
                    <label class="rch-agent-import__check">
                        <input type="checkbox" name="import_bio" id="rch-import-bio" value="1" checked />
                        <?php esc_html_e('Import bio → agent post content', 'rechat-plugin'); ?>
                    </label>
                    <label class="rch-agent-import__check">
                        <input type="checkbox" name="import_agent_title" id="rch-import-agent-title" value="1" checked />
                        <?php esc_html_e('Import agent title (title column → agent_title meta)', 'rechat-plugin'); ?>
                    </label>
                    <label class="rch-agent-import__check">
                        <input type="checkbox" name="import_testimonials" id="rch-import-testimonials" value="1" checked />
                        <?php esc_html_e('Import testimonials (name, text, stars, link)', 'rechat-plugin'); ?>
                    </label>
                </fieldset>
                <fieldset class="rch-agent-import__radios">
                    <legend><?php esc_html_e('Testimonials', 'rechat-plugin'); ?></legend>
                    <label>
                        <input type="radio" name="testimonial_mode" value="replace" checked />
                        <?php esc_html_e('Replace existing testimonials', 'rechat-plugin'); ?>
                    </label>
                    <label>
                        <input type="radio" name="testimonial_mode" value="merge" />
                        <?php esc_html_e('Merge with existing testimonials', 'rechat-plugin'); ?>
                    </label>
                </fieldset>
            </section>

            <section class="rch-agent-import__card rch-agent-import__card--file">
                <h3><?php esc_html_e('3. Upload CSV', 'rechat-plugin'); ?></h3>
                <div class="rch-agent-import__dropzone" id="rch-import-dropzone">
                    <input type="file" name="csv_file" id="rch-import-csv-file" accept=".csv,text/csv" />
                    <label for="rch-import-csv-file" class="rch-agent-import__file-label">
                        <span class="rch-agent-import__file-icon" aria-hidden="true">📄</span>
                        <span class="rch-agent-import__file-text" id="rch-import-file-name">
                            <?php esc_html_e('Click to choose a .csv file or drag it here', 'rechat-plugin'); ?>
                        </span>
                    </label>
                </div>
                <div class="rch-agent-import__actions">
                    <button type="button" class="button button-secondary" id="rch-import-preview-btn">
                        <?php esc_html_e('Preview import', 'rechat-plugin'); ?>
                    </button>
                    <button type="button" class="button button-primary" id="rch-import-run-btn" disabled>
                        <?php esc_html_e('Run import', 'rechat-plugin'); ?>
                    </button>
                </div>
            </section>
        </div>

        <div id="rch-import-feedback" class="rch-agent-import__feedback" hidden></div>

        <section id="rch-import-results" class="rch-agent-import__results" hidden>
            <div class="rch-agent-import__results-head">
                <h3><?php esc_html_e('Preview', 'rechat-plugin'); ?></h3>
                <div id="rch-import-summary" class="rch-agent-import__summary"></div>
            </div>
            <div class="rch-agent-import__table-wrap">
                <table class="widefat striped rch-agent-import__table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Agent', 'rechat-plugin'); ?></th>
                            <th><?php esc_html_e('ID', 'rechat-plugin'); ?></th>
                            <th><?php esc_html_e('Bio', 'rechat-plugin'); ?></th>
                            <th><?php esc_html_e('Title', 'rechat-plugin'); ?></th>
                            <th><?php esc_html_e('Testimonials', 'rechat-plugin'); ?></th>
                            <th><?php esc_html_e('Status', 'rechat-plugin'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="rch-import-table-body"></tbody>
                </table>
            </div>
            <ul id="rch-import-errors" class="rch-agent-import__errors"></ul>
        </section>
    </form>
</div>
