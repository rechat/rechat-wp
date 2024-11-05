const { registerBlockType } = wp.blocks;
const { InspectorControls, ColorPalette } = wp.blockEditor || wp.editor;
const { PanelBody, RangeControl, SelectControl, TextControl, CheckboxControl } = wp.components;
import { useEffect, useState } from '@wordpress/element'; // useState and useEffect hooks
import ServerSideRender from '@wordpress/server-side-render';
//regions block
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
//offices block
registerBlockType('rch-rechat-plugin/offices-block', {
    title: 'Offices Block',
    description: 'Block for showing Offices',
    icon: 'building',
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
        filterByRegions: {
            type: 'string',
            default: '',
        },
    },
    edit({ attributes, setAttributes }) {
        const { postsPerPage, regionBgColor, textColor, filterByRegions } = attributes;
        const [regions, setRegions] = useState([]); // State to store fetched regions

        // Fetch the custom post type 'regions'
        useEffect(() => {
            // Dynamically get the base URL including the current subdirectory
            const baseUrl = `${window.location.origin}`;
            const apiUrl = `${baseUrl}/wp-json/wp/v2/regions?per_page=100`;

            fetch(apiUrl)
                .then((response) => response.json())
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

                    <PanelBody title={'Settings'}>
                        <RangeControl
                            label="Posts Per Page"
                            value={postsPerPage}
                            onChange={(value) => setAttributes({ postsPerPage: value })}
                            min={1}
                            max={20}
                        />
                        <p><strong>Select aregion for filter</strong></p>
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

// Agents block
registerBlockType('rch-rechat-plugin/agents-block', {
    title: 'Agents Block',
    description: 'Block for showing Agents',
    icon: 'businessperson',
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
        filterByRegions: {
            type: 'string',
            default: '',
        },
        filterByOffices: {
            type: 'string',
            default: '',
        },
        sortBy: {
            type: 'string',
            default: 'date', // Default sort by date
        },
        sortOrder: {
            type: 'string',
            default: 'desc', // Default sort order
        },
    },
    edit({ attributes, setAttributes }) {
        const { postsPerPage, regionBgColor, textColor, filterByRegions, filterByOffices, sortBy, sortOrder } = attributes;
        const [regions, setRegions] = useState([]); // State to store fetched regions
        const [offices, setOffices] = useState([]); // State to store fetched offices

        // Fetch the custom post type 'regions'
        useEffect(() => {
            const baseUrl = `${window.location.origin}`;
            const apiUrl = `${baseUrl}/wp-json/wp/v2/regions?per_page=100`;

            fetch(apiUrl)
                .then((response) => response.json())
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

        // Fetch the custom post type 'offices'
        useEffect(() => {
            const baseUrl = `${window.location.origin}`;
            const apiUrl = `${baseUrl}/wp-json/wp/v2/offices?per_page=100`;

            fetch(apiUrl)
                .then((response) => response.json())
                .then((data) => {
                    const options = data.map((office) => ({
                        label: office.title.rendered,
                        value: office.id,
                    }));
                    options.unshift({ label: 'None', value: '' });
                    setOffices(options);
                })
                .catch((error) => console.error('Error fetching offices:', error));
        }, []);

        return (
            <>
                <InspectorControls>
                    <PanelBody title={'Settings'}>
                        <RangeControl
                            label="Posts Per Page"
                            value={postsPerPage}
                            onChange={(value) => setAttributes({ postsPerPage: value })}
                            min={1}
                            max={20}
                        />
                        <p><strong>Select a Region for filter</strong></p>
                        <SelectControl
                            label="Select a Region"
                            value={filterByRegions}
                            options={regions.length ? regions : [{ label: 'Loading regions...', value: '' }]}
                            onChange={(selectedRegion) => setAttributes({ filterByRegions: selectedRegion })}
                        />
                        <p><strong>Select an Office for filter</strong></p>
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

registerBlockType('rch-rechat-plugin/listing-block', {
    title: 'Listing Block',
    description: 'Block for showing property listings',
    icon: 'building', // You can change the icon to something related to listings.
    category: 'widgets',
    attributes: {
        minimum_price: {
            type: 'number',
            default: null
        },
        maximum_price: {
            type: 'number',
            default: null
        },
        minimum_lot_square_meters: {
            type: 'number',
            default: null
        },
        maximum_lot_square_meters: {
            type: 'number',
            default: null
        },
        minimum_bathrooms: {
            type: 'number',
            default: null
        },
        maximum_bathrooms: { type: 'number', default: null },
        minimum_square_meters: { type: 'number', default: null },
        maximum_square_meters: { type: 'number', default: null },
        minimum_year_built: { type: 'number', default: null },
        maximum_year_built: { type: 'number', default: null },
        minimum_bedrooms: { type: 'number', default: null },
        maximum_bedrooms: { type: 'number', default: null },
        listing_per_page: { type: 'number', default: 5 },
        filterByRegions: { type: 'string', default: '' },
        filterByOffices: { type: 'string', default: '' },
        brand: { type: 'string', default: '' },
        selectedStatuses: { type: 'array', default: [] }, // New attribute for selected statuses
        listing_statuses: { type: 'array', default: [] }, // New attribute for listing statuses
    },
    edit({ attributes, setAttributes }) {
        const {
            minimum_price,
            maximum_price,
            minimum_lot_square_meters,
            maximum_lot_square_meters,
            minimum_bathrooms,
            maximum_bathrooms,
            minimum_square_meters,
            maximum_square_meters,
            minimum_year_built,
            maximum_year_built,
            minimum_bedrooms,
            maximum_bedrooms,
            listing_per_page,
            filterByRegions,
            filterByOffices,
            brand,
            selectedStatuses,
            listing_statuses,

        } = attributes;
        // React state for holding regions and offices data
        const [regions, setRegions] = useState([]);
        const [offices, setOffices] = useState([]);
        // Mapping of status options to their values
        const statusMapping = {
            Active: [
                'Active',
                'Incoming',
                'Coming Soon',
                'Pending',
                'Active Option Contract',
                'Active Contingent',
                'Active Kick Out',
                'Active Under Contract',
            ],
            Closed: ['Sold', 'Leased'],
            Archived: [
                'Withdrawn',
                'Expired',
                'Cancelled',
                'Withdrawn Sublisting',
                'Incomplete',
                'Unknown',
                'Out Of Sync',
                'Temp Off Market',
            ],
        };
        const statusOptions = [
            { label: 'Empty', value: 'Empty' },
            { label: 'Active', value: 'Active' },
            { label: 'Closed', value: 'Closed' },
            { label: 'Archived', value: 'Archived' },
        ];
        // Fetch Regions on component mount
        useEffect(() => {
            const baseUrl = `${window.location.origin}`;
            const apiUrl = `${baseUrl}/wp-json/wp/v2/regions?per_page=100`;
            fetch(apiUrl)
                .then((response) => response.json())
                .then((data) => {
                    const options = data.map((region) => ({
                        label: region.title.rendered,
                        value: region.meta.region_id
                    }));
                    options.unshift({ label: 'None', value: '' }); // Add "None" option
                    setRegions(options);
                })
                .catch((error) => console.error('Error fetching regions:', error));
        }, []);

        // Fetch Offices on component mount
        useEffect(() => {
            const baseUrl = `${window.location.origin}`;
            const apiUrl = `${baseUrl}/wp-json/wp/v2/offices?per_page=100`;

            fetch(apiUrl)
                .then((response) => response.json())
                .then((data) => {
                    const options = data.map((office) => ({
                        label: office.title.rendered,
                        value: office.meta.office_id
                    }));
                    options.unshift({ label: 'None', value: '' }); // Add "None" option
                    setOffices(options);
                })
                .catch((error) => console.error('Error fetching offices:', error));
        }, []);
        // Handle status selection
        const handleStatusChange = (status) => {
            const newSelectedStatuses = selectedStatuses.includes(status)
                ? selectedStatuses.filter((s) => s !== status)
                : [...selectedStatuses, status];

            // Collect all corresponding listing statuses based on the selection
            const newListingStatuses = newSelectedStatuses
                .flatMap((selected) => statusMapping[selected] || [])
                .filter((value, index, self) => self.indexOf(value) === index); // Remove duplicates

            setAttributes({
                selectedStatuses: newSelectedStatuses,
                listing_statuses: newListingStatuses,
            });
        };
        
        // Handle Region selection
        const handleRegionChange = (selectedRegion) => {
            setAttributes({
                ...attributes,
                filterByRegions: selectedRegion,
                filterByOffices: '', // Clear office selection
                brand: selectedRegion || '' // Set brand to region ID or default to empty
            });
        };
        // Handle Office selection
        const handleOfficeChange = (selectedOffice) => {
            setAttributes({
                ...attributes,
                filterByOffices: selectedOffice,
                filterByRegions: '', // Clear region selection
                brand: selectedOffice || '' // Set brand to office ID or default to empty
            });
        };
        return (
            <>
                <InspectorControls>
                    <PanelBody title={'Listing Settings'}>
                        <p><strong>Select a Regions for filter</strong></p>
                        <SelectControl
                            label="Select a Region"
                            value={filterByRegions}
                            options={regions}
                            onChange={handleRegionChange}
                        />
                        <p><strong>Select an Office for filter</strong></p>
                        {/* Select control for offices */}
                        <SelectControl
                            label="Select an Office"
                            value={filterByOffices}
                            options={offices}
                            onChange={handleOfficeChange}
                        />
                        <p><strong>Select Statuses</strong></p>
                        {statusOptions.map((option) => (
                            <CheckboxControl
                                key={option.value}
                                label={option.label}
                                checked={selectedStatuses.includes(option.value)}
                                onChange={() => handleStatusChange(option.value)}
                            />
                        ))}
                        <TextControl
                            label="Minimum Price"
                            value={minimum_price}
                            type="number"
                            onChange={(value) => setAttributes({ minimum_price: value === '' ? '' : parseInt(value) || 0 })}
                        />
                        <TextControl
                            label="Maximum Price"
                            value={maximum_price}
                            type="number"
                            onChange={(value) => setAttributes({ maximum_price: value === '' ? '' : parseInt(value) || 0 })}
                        />
                        <TextControl
                            label="Minimum Lot Size (m²)"
                            value={minimum_lot_square_meters}
                            type="number"
                            onChange={(value) => setAttributes({ minimum_lot_square_meters: value === '' ? '' : parseInt(value) || 0 })}
                        />
                        <TextControl
                            label="Maximum Lot Size (m²)"
                            value={maximum_lot_square_meters}
                            type="number"
                            onChange={(value) => setAttributes({ maximum_lot_square_meters: value === '' ? '' : parseInt(value) || 0 })}
                        />
                        <TextControl
                            label="Minimum Bathrooms"
                            value={minimum_bathrooms}
                            type="number"
                            onChange={(value) => setAttributes({ minimum_bathrooms: value === '' ? '' : parseInt(value) || 0 })}
                        />
                        <TextControl
                            label="Maximum Bathrooms"
                            value={maximum_bathrooms}
                            type="number"
                            onChange={(value) => setAttributes({ maximum_bathrooms: value === '' ? '' : parseInt(value) || 0 })}
                        />
                        <TextControl
                            label="Minimum Square Meters"
                            value={minimum_square_meters}
                            type="number"
                            onChange={(value) => setAttributes({ minimum_square_meters: value === '' ? '' : parseInt(value) || 0 })}
                        />
                        <TextControl
                            label="Maximum Square Meters"
                            value={maximum_square_meters}
                            type="number"
                            onChange={(value) => setAttributes({ maximum_square_meters: value === '' ? '' : parseInt(value) || 0 })}
                        />
                        <TextControl
                            label="Minimum Year Built"
                            value={minimum_year_built}
                            type="number"
                            onChange={(value) => setAttributes({ minimum_year_built: value === '' ? '' : parseInt(value) || 0 })}
                        />
                        <TextControl
                            label="Maximum Year Built"
                            value={maximum_year_built}
                            type="number"
                            onChange={(value) => setAttributes({ maximum_year_built: value === '' ? '' : parseInt(value) || 0 })}
                        />
                        <TextControl
                            label="Minimum Bedrooms"
                            value={minimum_bedrooms}
                            type="number"
                            onChange={(value) => setAttributes({ minimum_bedrooms: value === '' ? '' : parseInt(value) || 0 })}
                        />
                        <TextControl
                            label="Maximum Bedrooms"
                            value={maximum_bedrooms}
                            type="number"
                            onChange={(value) => setAttributes({ maximum_bedrooms: value === '' ? '' : parseInt(value) || 0 })}
                        />
                        <TextControl
                            label="listing Per Page"
                            value={listing_per_page}
                            type="number"
                            onChange={(value) => setAttributes({ listing_per_page: value === '' ? '' : parseInt(value) || 1 })}
                        />
                    </PanelBody>
                </InspectorControls>


                {/* <ServerSideRender
                    block="rch-rechat-plugin/listing-block"
                    attributes={attributes}
                /> */}
                <div className="listing-block-preview">
                    <p><strong>Listing Block:</strong></p>
                    <p>We display listing items based on your selected filters on the front end of the site.</p>
                </div>
            </>
        );
    },
    save() {
        return null; // Dynamic block, content will be generated by PHP
    },
});

