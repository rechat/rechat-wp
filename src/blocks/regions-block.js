const { registerBlockType } = wp.blocks;
const { InspectorControls, ColorPalette } = wp.blockEditor || wp.editor;
const { PanelBody, RangeControl } = wp.components;
import ServerSideRender from '@wordpress/server-side-render';

registerBlockType('rch-rechat-plugin/regions-block', {
    title: 'Regions Block',
    description: 'Block for showing Regions',
    icon: 'admin-site',
    category: 'widgets',
    attributes: {
        postsPerPage: {
            type: 'number',
            default: 5,
        },
        regionBgColor: {
            type: 'string',
            default: '#edf1f5',
        },
        textColor: {
            type: 'string',
            default: '#000',
        },
    },
    edit({ attributes, setAttributes }) {
        const { postsPerPage, regionBgColor, textColor } = attributes;

        function updatePostPerPage(value) {
            setAttributes({ postsPerPage: value });
        }
        function regionBackgroundSelect(newColor) {
            setAttributes({ regionBgColor: newColor });
        }
        function textColorSelect(newTextColor) {
            setAttributes({ textColor: newTextColor });
        }

        return (
            <>
                <InspectorControls>
                    <PanelBody title={'Setting'}>
                        <RangeControl
                            label="Posts Per Page"
                            value={postsPerPage}
                            onChange={updatePostPerPage}
                            min={1}
                            max={20}
                        />
                        <p>
                            <strong>Select your background color</strong>
                        </p>
                        <ColorPalette value={regionBgColor} onChange={regionBackgroundSelect} />
                        <p>
                            <strong>Select your text color</strong>
                        </p>
                        <ColorPalette value={textColor} onChange={textColorSelect} />
                    </PanelBody>
                </InspectorControls>
                <ServerSideRender
                    block="rch-rechat-plugin/regions-block"
                    attributes={attributes}
                />
            </>
        );
    },
    save() {
        return null;
    },
});
