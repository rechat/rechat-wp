const { registerBlockType } = wp.blocks;
const { InspectorControls, useBlockProps } = wp.blockEditor || wp.editor;
const { PanelBody, TextControl, RangeControl, SelectControl, Placeholder } = wp.components;
import { useRef } from '@wordpress/element';

/**
 * Minimal HTML attribute-value escape (double-quoted context).
 *
 * @param {string} v
 * @returns {string}
 */
function attr(v) {
    return String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
}

/**
 * Build a self-contained preview document for the Rechat testimonials web
 * component.
 *
 * The Rechat SDK mounts <rechat-root> by scanning the DOM when its script runs.
 * In the block editor the block is inserted AFTER the SDK has already
 * initialised, so a <rechat-root> rendered inline is never mounted. Rendering it
 * in an isolated iframe (with the SDK script + markup present at load) reproduces
 * the front-end load order exactly, so the component always mounts.
 *
 * @param {{sdkCss?: string, sdkJs?: string, brandId?: string}} cfg
 * @param {number} limit
 * @param {string} colorMode
 * @returns {string}
 */
function buildPreviewDoc(cfg, limit, colorMode) {
    const mode = colorMode === 'dark' ? 'dark' : 'light';
    const limitAttr = limit > 0 ? ` limit="${attr(limit)}"` : '';
    return `<!doctype html><html><head><meta charset="utf-8">`
        + (cfg.sdkCss ? `<link rel="stylesheet" href="${attr(cfg.sdkCss)}">` : '')
        + `<style>html,body{margin:0;padding:8px;background:transparent;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}</style>`
        + `</head><body>`
        + `<rechat-root brand_id="${attr(cfg.brandId)}" color-mode="${mode}">`
        + `<rechat-testimonials${limitAttr}></rechat-testimonials>`
        + `</rechat-root>`
        + (cfg.sdkJs ? `<script src="${attr(cfg.sdkJs)}"></script>` : '')
        + `</body></html>`;
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
    },
    edit({ attributes, setAttributes }) {
        const { limit, title, colorMode } = attributes;
        const blockProps = typeof useBlockProps === 'function' ? useBlockProps() : {};
        const cfg = (typeof window !== 'undefined' && window.rchTestimonialsPreview) || {};
        const hasPreview = Boolean(cfg.sdkJs && cfg.brandId);
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
                    </PanelBody>
                </InspectorControls>
                <div {...blockProps}>
                    {title !== '' ? <h2 className="rch-testimonials__title">{title}</h2> : null}
                    {hasPreview ? (
                        <iframe
                            ref={iframeRef}
                            // key forces a reload when settings change so the SDK
                            // re-fetches with the new attributes.
                            key={`${cfg.brandId}-${limit}-${colorMode}`}
                            title="Testimonials preview"
                            onLoad={handleIframeLoad}
                            srcDoc={buildPreviewDoc(cfg, limit, colorMode)}
                            style={{ width: '100%', minHeight: '300px', border: '0' }}
                            scrolling="no"
                        />
                    ) : (
                        <Placeholder
                            icon="format-quote"
                            label={title !== '' ? title : 'Rechat Testimonials'}
                            instructions={
                                cfg.sdkJs
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
