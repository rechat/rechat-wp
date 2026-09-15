const { registerBlockType } = wp.blocks;
const { InspectorControls, useBlockProps } = wp.blockEditor || wp.editor;
const { PanelBody, TextControl, RangeControl, SelectControl, Placeholder } = wp.components;
import { useRef, useEffect } from '@wordpress/element';

/**
 * Ensure the Rechat SDK (CSS + JS that defines the <rechat-*> web components)
 * is present in the given document. In the WP 6.x editor the block canvas is an
 * iframe, so the SDK must be injected into the block's ownerDocument — not the
 * outer window — for the custom elements to upgrade. When Meta Boxes are present
 * the canvas is NOT iframed and ownerDocument is the main document; the same code
 * handles both.
 *
 * @param {Document} doc Target document (block element's ownerDocument).
 * @param {{sdkCss?: string, sdkJs?: string}} cfg SDK asset URLs.
 */
function ensureSdkLoaded(doc, cfg) {
    if (!doc || !doc.head) {
        return;
    }
    if (cfg.sdkCss && !doc.getElementById('rch-sdk-css-preview')) {
        const link = doc.createElement('link');
        link.id = 'rch-sdk-css-preview';
        link.rel = 'stylesheet';
        link.href = cfg.sdkCss;
        doc.head.appendChild(link);
    }
    if (cfg.sdkJs && !doc.getElementById('rch-sdk-js-preview')) {
        const script = doc.createElement('script');
        script.id = 'rch-sdk-js-preview';
        script.src = cfg.sdkJs;
        doc.head.appendChild(script);
    }
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

        const previewRef = useRef(null);

        // Inject the SDK into the block's own document (iframe-aware) once mounted.
        useEffect(() => {
            if (!hasPreview || !previewRef.current) {
                return;
            }
            ensureSdkLoaded(previewRef.current.ownerDocument, cfg);
        }, [hasPreview]);

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
                        // key forces the web component to remount when settings change
                        // so the SDK re-fetches with the new attributes.
                        <div ref={previewRef} className="rch-testimonials-editor-preview">
                            <rechat-root
                                key={`${cfg.brandId}-${limit}-${colorMode}`}
                                brand_id={cfg.brandId}
                                {...(colorMode ? { 'color-mode': colorMode } : {})}
                            >
                                {limit > 0 ? (
                                    <rechat-testimonials limit={limit}></rechat-testimonials>
                                ) : (
                                    <rechat-testimonials></rechat-testimonials>
                                )}
                            </rechat-root>
                        </div>
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
