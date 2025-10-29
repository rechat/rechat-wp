const { registerBlockType } = wp.blocks;
const { InspectorControls, ColorPalette } = wp.blockEditor || wp.editor;
const { PanelBody, RangeControl, SelectControl } = wp.components;
import { useEffect, useState } from '@wordpress/element';
import ServerSideRender from '@wordpress/server-side-render';
import { fetchData } from '../utils/api-helpers';

registerBlockType('rch-rechat-plugin/agents-block', {
    title: 'Agents Block',
    description: 'Block for showing Agents',
    icon: 'businessperson',
    category: 'widgets',
    attributes: {
        postsPerPage: { type: 'number', default: 5 },
        regionBgColor: { type: 'string', default: '#edf1f5' },
        textColor: { type: 'string', default: '#000' },
        filterByRegions: { type: 'string', default: '' },
        filterByOffices: { type: 'string', default: '' },
        sortBy: { type: 'string', default: 'date' },
        sortOrder: { type: 'string', default: 'desc' },
    },
    edit({ attributes, setAttributes }) {
        const { postsPerPage, regionBgColor, textColor, filterByRegions, filterByOffices, sortBy, sortOrder } = attributes;
        const [regions, setRegions] = useState([]);
        const [offices, setOffices] = useState([]);

        useEffect(() => {
            fetchData('/wp/v2/regions?per_page=100', setRegions);
            fetchData('/wp/v2/offices?per_page=100', setOffices);
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
                        <SelectControl
                            label="Select an Office"
                            value={filterByOffices}
                            options={offices.length ? offices : [{ label: 'Loading offices...', value: '' }]}
                            onChange={(selectedOffice) => setAttributes({ filterByOffices: selectedOffice })}
                        />
                        <SelectControl
                            label="Sort By"
                            value={sortBy}
                            options={[
                                { label: 'Date', value: 'date' },
                                { label: 'Name', value: 'name' },
                            ]}
                            onChange={(selectedSort) => setAttributes({ sortBy: selectedSort })}
                        />
                        <SelectControl
                            label="Sort Order"
                            value={sortOrder}
                            options={[
                                { label: 'Ascending', value: 'asc' },
                                { label: 'Descending', value: 'desc' },
                            ]}
                            onChange={(selectedOrder) => setAttributes({ sortOrder: selectedOrder })}
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
                    block="rch-rechat-plugin/agents-block"
                    attributes={attributes}
                />
            </>
        );
    },
    save() {
        return null;
    },
});
