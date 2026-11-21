const { registerBlockType } = wp.blocks;
const { InspectorControls, useBlockProps } = wp.blockEditor || wp.editor;
const { PanelBody, TextControl, RangeControl, SelectControl, ToggleControl, Placeholder, ComboboxControl } = wp.components;
import { useEffect, useRef, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

/**
 * Fetch ALL agents across every REST page (WP caps per_page at 100).
 *
 * @returns {Promise<Array<{id: number, name: string}>>}
 */
const fetchAllAgents = async () => {
    const base = '/wp/v2/agents?per_page=100&orderby=title&order=asc&_fields=id,title';
    const mapAgents = (data) => data.map((a) => ({ id: a.id, name: a.title.rendered }));

    const first = await apiFetch({ path: `${base}&page=1`, parse: false });
    const totalPages = parseInt(first.headers.get('X-WP-TotalPages') || '1', 10) || 1;
    let all = mapAgents(await first.json());

    const pages = [];
    for (let page = 2; page <= totalPages; page++) {
        pages.push(apiFetch({ path: `${base}&page=${page}` }));
    }
    (await Promise.all(pages)).forEach((data) => {
        all = all.concat(mapAgents(data));
    });
    return all;
};

/**
 * ID of the agent being edited, or 0 when the editor is not on an agent post.
 *
 * @returns {number}
 */
const currentAgentPostId = () => {
    try {
        const editor = wp.data.select('core/editor');
        if (editor && editor.getCurrentPostType() === 'agents') {
            return parseInt(editor.getCurrentPostId(), 10) || 0;
        }
    } catch (e) {
        // no post editor store (widgets / site editor)
    }
    return 0;
};

/**
 * URL for the preview iframe. Loads from admin-ajax (SAME HOST) so the Rechat
 * SDK's hostname-based portal lookup gets a real, non-empty hostname — a srcDoc
 * / data: iframe has no hostname and the SDK request fails ("Too small:
 * expected string to have >=1 characters").
 *
 * @param {{ajaxUrl?: string, nonce?: string}} cfg
 * @param {number} limit
 * @param {string} colorMode
 * @param {boolean} loadMore
 * @param {number} agentId
 * @returns {string}
 */
function buildPreviewSrc(cfg, limit, colorMode, loadMore, agentId) {
    const params = [
        'action=rch_testimonials_preview',
        `nonce=${encodeURIComponent(cfg.nonce || '')}`,
        `limit=${encodeURIComponent(limit || 0)}`,
        `color_mode=${encodeURIComponent(colorMode || '')}`,
        // Only meaningful when disabled; harmless when true (SDK default).
        `load_more=${loadMore === false ? 'false' : 'true'}`,
        `agent_id=${agentId > 0 ? agentId : 0}`,
    ];
    const sep = (cfg.ajaxUrl || '').indexOf('?') === -1 ? '?' : '&';
    return `${cfg.ajaxUrl}${sep}${params.join('&')}`;
}

registerBlockType('rch-rechat-plugin/testimonials-block', {
    title: 'Testimonials Block',
    description: 'Rechat client testimonials (rendered via the Rechat SDK web component).',
    icon: 'format-quote',
    category: 'widgets',
    attributes: {
        limit: { type: 'number', default: 0 },
        title: { type: 'string', default: '' },
        colorMode: { type: 'string', default: '' },
        loadMore: { type: 'boolean', default: true },
        // -1 = never chosen, 0 = all testimonials, >0 = agent post ID.
        agentId: { type: 'number', default: -1 },
    },
    edit({ attributes, setAttributes }) {
        const { limit, title, colorMode, loadMore, agentId } = attributes;
        const [agents, setAgents] = useState([]);

        useEffect(() => {
            // Never chosen + editing an agent → start on that agent.
            if (agentId === -1) {
                const current = currentAgentPostId();
                if (current > 0) {
                    setAttributes({ agentId: current });
                }
            }
            fetchAllAgents()
                .then(setAgents)
                .catch((error) => console.error('Error fetching agents:', error));
        }, []);

        const agentOptions = [
            { label: 'All testimonials (no agent filter)', value: '0' },
            ...agents.map((a) => ({ label: `${a.name} (#${a.id})`, value: String(a.id) })),
        ];
        const blockProps = typeof useBlockProps === 'function' ? useBlockProps() : {};
        const cfg = (typeof window !== 'undefined' && window.rchTestimonialsPreview) || {};
        const hasPreview = Boolean(cfg.ajaxUrl && cfg.nonce && cfg.brandId);
        const iframeRef = useRef(null);

        // Auto-size the iframe to its content (same-origin srcDoc → readable).
        const handleIframeLoad = () => {
            const frame = iframeRef.current;
            if (!frame) {
                return;
            }
            try {
                const doc = frame.contentDocument;
                const win = frame.contentWindow;
                if (!doc || !doc.body || !win) {
                    return;
                }
                const resize = () => {
                    const h = Math.max(300, doc.body.scrollHeight);
                    frame.style.height = h + 'px';
                };
                resize();
                if (win.ResizeObserver) {
                    new win.ResizeObserver(resize).observe(doc.body);
                } else {
                    let ticks = 0;
                    const id = win.setInterval(() => {
                        resize();
                        if (++ticks > 20) {
                            win.clearInterval(id);
                        }
                    }, 500);
                }
            } catch (e) {
                // cross-origin or teardown — leave the default height.
            }
        };

        return (
            <>
                <InspectorControls>
                    <PanelBody title="Testimonials Settings">
                        <TextControl
                            label="Title (optional heading)"
                            value={title}
                            onChange={(value) => setAttributes({ title: value })}
                        />
                        <RangeControl
                            label="Number of testimonials (0 = show all)"
                            value={limit}
                            min={0}
                            max={50}
                            onChange={(value) => setAttributes({ limit: value || 0 })}
                        />
                        <SelectControl
                            label="Color mode"
                            value={colorMode}
                            options={[
                                { label: 'Site default', value: '' },
                                { label: 'Light', value: 'light' },
                                { label: 'Dark', value: 'dark' },
                            ]}
                            onChange={(value) => setAttributes({ colorMode: value })}
                        />
                        <ComboboxControl
                            label="Agent"
                            help="Show only this agent’s testimonials (filtered by Agent ID). An agent added by hand has no Agent ID, so all testimonials are shown."
                            value={String(agentId > 0 ? agentId : 0)}
                            options={agentOptions}
                            onChange={(value) => setAttributes({ agentId: parseInt(value, 10) || 0 })}
                        />
                        <ToggleControl
                            label="Show “load more” button"
                            help={loadMore ? 'SDK default (button shown).' : 'Sends load_more="false" — button hidden.'}
                            checked={loadMore}
                            onChange={(value) => setAttributes({ loadMore: value })}
                        />
                    </PanelBody>
                </InspectorControls>
                <div {...blockProps}>
                    {title !== '' ? <h2 className="rch-testimonials__title">{title}</h2> : null}
                    {hasPreview ? (
                        <iframe
                            ref={iframeRef}
                            // key forces a reload when settings change so the SDK
                            // re-fetches with the new attributes.
                            key={`${cfg.brandId}-${limit}-${colorMode}-${loadMore}-${agentId}`}
                            title="Testimonials preview"
                            onLoad={handleIframeLoad}
                            src={buildPreviewSrc(cfg, limit, colorMode, loadMore, agentId)}
                            // pointer-events:none so clicks pass THROUGH the iframe to the
                            // block wrapper — otherwise the iframe swallows every click and
                            // the block never gets selected, so its settings panel never opens.
                            style={{ width: '100%', minHeight: '300px', border: '0', pointerEvents: 'none' }}
                            scrolling="no"
                        />
                    ) : (
                        <Placeholder
                            icon="format-quote"
                            label={title !== '' ? title : 'Rechat Testimonials'}
                            instructions={
                                cfg.ajaxUrl
                                    ? 'Connect a Rechat account (no brand_id found) to preview testimonials. Front-end output is unaffected.'
                                    : 'Testimonials preview unavailable in the editor. It renders on the published page.'
                            }
                        />
                    )}
                </div>
            </>
        );
    },
    save() {
        return null;
    },
});
