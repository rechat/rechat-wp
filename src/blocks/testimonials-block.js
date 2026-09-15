const { registerBlockType } = wp.blocks;
const { InspectorControls, useBlockProps } = wp.blockEditor || wp.editor;
const { PanelBody, TextControl, RangeControl, SelectControl, Placeholder } = wp.components;

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

        // The Rechat testimonials web component only renders on the front-end
        // (the SDK JS is not loaded in the editor iframe). Show a static
        // placeholder so the block is clearly visible in the editor — otherwise
        // it looks empty and gets inserted multiple times by mistake, which
        // stacks duplicate testimonial sections on the front-end.
        const instructions = [
            `Testimonials shown: ${limit > 0 ? limit : 'all'}`,
            colorMode ? `Color mode: ${colorMode}` : null,
            'Preview appears on the published page.',
        ].filter(Boolean).join(' · ');

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
                    <Placeholder
                        icon="format-quote"
                        label={title !== '' ? title : 'Rechat Testimonials'}
                        instructions={instructions}
                    />
                </div>
            </>
        );
    },
    save() {
        return null;
    },
});
