const { registerBlockType } = wp.blocks;
const { InspectorControls, ColorPalette } = wp.blockEditor || wp.editor;
const { PanelBody, RangeControl, SelectControl } = wp.components;
import { useEffect, useState } from '@wordpress/element';
import ServerSideRender from '@wordpress/server-side-render';
import apiFetch from '@wordpress/api-fetch';

registerBlockType('rch-rechat-plugin/offices-block', {
    title: 'Offices Block',
    description: 'Block for showing Offices',
    icon: 'building',
    category: 'widgets',
    attributes: {
        postsPerPage: { type: 'number', default: 5 },
        regionBgColor: { type: 'string', default: '#edf1f5' },
        textColor: { type: 'string', default: '#000' },
        filterByRegions: { type: 'string', default: '' },
    },
    edit({ attributes, setAttributes }) {
        const { postsPerPage, regionBgColor, textColor, filterByRegions } = attributes;
        const [regions, setRegions] = useState([]);

        // Fetch the custom post type 'regions'
        useEffect(() => {
            apiFetch({ path: '/wp/v2/regions?per_page=100' })
                .then((data) => {
                    const options = data.map((region) => ({
                        label: region.title.rendered,
                        value: region.id,
                    }));
                    options.unshift({ label: 'None', value: '' });
                    setRegions(options);
                })
                .catch((error) => console.error('Error fetching regions:', error));
        }, []);

        return (
            <>
                <InspectorControls>
                    <PanelBody title="Settings">
                        <RangeControl
                            label="Posts Per Page"
                            value={postsPerPage}
                            onChange={(value) => setAttributes({ postsPerPage: value })}
                            min={1}
                            max={20}
                        />
                        <SelectControl
                            label="Select a Region"
                            value={filterByRegions}
                            options={regions.length ? regions : [{ label: 'Loading regions...', value: '' }]}
                            onChange={(selectedRegion) => setAttributes({ filterByRegions: selectedRegion })}
                        />
                        <p><strong>Select your background color</strong></p>
                        <ColorPalette
                            value={regionBgColor}
                            onChange={(color) => setAttributes({ regionBgColor: color })}
                        />
                        <p><strong>Select your text color</strong></p>
                        <ColorPalette
                            value={textColor}
                            onChange={(color) => setAttributes({ textColor: color })}
                        />
                    </PanelBody>
                </InspectorControls>
                <ServerSideRender
                    block="rch-rechat-plugin/offices-block"
                    attributes={attributes}
                />
            </>
        );
    },
    save() {
        return null;
    },
});
