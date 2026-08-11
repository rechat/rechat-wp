const { registerBlockType } = wp.blocks;
const { InspectorControls } = wp.blockEditor || wp.editor;
const { PanelBody, TextControl, RangeControl, SelectControl } = wp.components;
import ServerSideRender from '@wordpress/server-side-render';

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
                <ServerSideRender block="rch-rechat-plugin/testimonials-block" attributes={attributes} />
            </>
        );
    },
    save() {
        return null;
    },
});
