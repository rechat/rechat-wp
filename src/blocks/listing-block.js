const { registerBlockType } = wp.blocks;
const { InspectorControls, MediaUpload, MediaUploadCheck } = wp.blockEditor || wp.editor;
const { PanelBody, RangeControl, SelectControl, TextControl, CheckboxControl, RadioControl, Button } = wp.components;
import { useEffect, useState } from '@wordpress/element';
import ServerSideRender from '@wordpress/server-side-render';
import apiFetch from '@wordpress/api-fetch';
import MapSelector from '../utils/map-selector';
import { fetchDataWithMeta } from '../utils/api-helpers';

registerBlockType('rch-rechat-plugin/listing-block', {
    title: 'Listing Block',
    description: 'Block for showing property listings',
    icon: 'building',
    category: 'widgets',
    attributes: {
        minimum_price: { type: 'string', default: '' },
        maximum_price: { type: 'string', default: '' },
        minimum_lot_square_meters: { type: 'string', default: '' },
        maximum_lot_square_meters: { type: 'string', default: '' },
        minimum_bathrooms: { type: 'string', default: '' },
        maximum_bathrooms: { type: 'string', default: '' },
        minimum_square_meters: { type: 'string', default: '' },
        maximum_square_meters: { type: 'string', default: '' },
        minimum_year_built: { type: 'string', default: '' },
        maximum_year_built: { type: 'string', default: '' },
        minimum_bedrooms: { type: 'string', default: '' },
        maximum_bedrooms: { type: 'string', default: '' },
        listing_per_page: { type: 'string', default: '5' },
        filterByRegions: { type: 'string', default: '' },
        filterByOffices: { type: 'string', default: '' },
        selectedStatuses: { type: 'array', default: [] },
        listing_statuses: { type: 'array', default: [] },
        disable_filter_address: { type: 'boolean', default: false },
        disable_filter_price: { type: 'boolean', default: false },
        disable_filter_beds: { type: 'boolean', default: false },
        disable_filter_baths: { type: 'boolean', default: false },
        disable_filter_property_types: { type: 'boolean', default: false },
        disable_filter_advanced: { type: 'boolean', default: false },
        layout_style: { type: 'string', default: 'default' },
        show_agent_card: { type: 'boolean', default: false },
        agent_image: { type: 'string', default: '' },
        agent_name: { type: 'string', default: '' },
        agent_title: { type: 'string', default: '' },
        agent_phone: { type: 'string', default: '' },
        agent_email: { type: 'string', default: '' },
        agent_address: { type: 'string', default: '' },
        own_listing: { type: 'boolean', default: true },
        property_types: { type: 'string', default: '' },
        map_latitude: { type: 'string', default: '' },
        map_longitude: { type: 'string', default: '' },
        map_zoom: { type: 'string', default: '12' }
    },
    edit({ attributes, setAttributes }) {
        const {
            minimum_price, maximum_price, minimum_lot_square_meters, maximum_lot_square_meters,
            minimum_bathrooms, maximum_bathrooms, minimum_square_meters, maximum_square_meters,
            minimum_year_built, maximum_year_built, minimum_bedrooms, maximum_bedrooms,
            listing_per_page, filterByRegions, filterByOffices, selectedStatuses, 
            disable_filter_address, disable_filter_price, disable_filter_beds, 
            disable_filter_baths, disable_filter_property_types, disable_filter_advanced,
            layout_style, show_agent_card, agent_image, agent_name, agent_title,
            agent_phone, agent_email, agent_address,
            own_listing, property_types, listing_statuses, map_latitude, map_longitude, map_zoom
        } = attributes;

        const [regions, setRegions] = useState([]);
        const [offices, setOffices] = useState([]);
        const [googleMapsApiKey, setGoogleMapsApiKey] = useState('');

        const statusOptions = [
            { label: 'Active', value: 'Active' },
            { label: 'Closed', value: 'Closed' },
            { label: 'Archived', value: 'Archived' },
        ];

        useEffect(() => {
            fetchDataWithMeta('/wp/v2/regions?per_page=100', setRegions);
            fetchDataWithMeta('/wp/v2/offices?per_page=100', setOffices);
            
            // Fetch Google Maps API key
            apiFetch({ path: '/wp/v2/options' })
                .then(options => {
                    if (options.rch_rechat_google_map_api_key) {
                        setGoogleMapsApiKey(options.rch_rechat_google_map_api_key);
                    }
                })
                .catch(error => {
                    console.error('Error fetching Google Maps API key:', error);
                });
        }, []);

        const handleAttributeChange = (attr, value) => {
            setAttributes({ [attr]: value });
        };

        const handleStatusChange = (status) => {
            const updatedStatuses = selectedStatuses.includes(status)
                ? selectedStatuses.filter(s => s !== status)
                : [...selectedStatuses, status];
            const listingStatuses = updatedStatuses.flatMap(status =>
            ({
                Active: ['Active', 'Incoming', 'Coming Soon', 'Pending'],
                Closed: ['Sold', 'Leased'],
                Archived: ['Withdrawn', 'Expired']
            }[status] || [])
            );
            setAttributes({ selectedStatuses: updatedStatuses, listing_statuses: listingStatuses });
        };

        const handlePropertyTypeChange = (value) => {
            setAttributes({ property_types: value });
        };

        const handleMapLocationChange = (location) => {
            if (location && location.lat && location.lng) {
                setAttributes({
                    map_latitude: location.lat.toString(),
                    map_longitude: location.lng.toString(),
                });
            }
        };

        const handleZoomChange = (zoom) => {
            setAttributes({ map_zoom: zoom.toString() });
        };

        return (
            <>
                <InspectorControls>
                    <PanelBody title="Listing Settings">
                        <SelectControl
                            label="Layout Style"
                            value={layout_style}
                            options={[
                                { label: 'Default Layout', value: 'default' },
                                { label: 'Layout 2 (Listings Left, Map Right)', value: 'layout2' },
                                { label: 'Layout 3 (Map Wider)', value: 'layout3' },
                            ]}
                            onChange={(value) => setAttributes({ layout_style: value })}
                        />
                        <CheckboxControl
                            label="Only our own listings"
                            checked={own_listing}
                            onChange={() => setAttributes({ own_listing: !own_listing })}
                        />
                        <SelectControl
                            label="Select a Region"
                            value={filterByRegions}
                            options={regions}
                            onChange={(value) => handleAttributeChange('filterByRegions', value)}
                        />
                        <SelectControl
                            label="Select an Office"
                            value={filterByOffices}
                            options={offices}
                            onChange={(value) => handleAttributeChange('filterByOffices', value)}
                        />
                        <p><strong>Select Statuses</strong></p>
                        {statusOptions.map(option => (
                            <CheckboxControl
                                key={option.value}
                                label={option.label}
                                checked={selectedStatuses.includes(option.value)}
                                onChange={() => handleStatusChange(option.value)}
                            />
                        ))}
                        <p><strong>Property Type</strong></p>
                        <RadioControl
                            label="Select Property Type"
                            selected={property_types}
                            options={[
                                { label: 'All Listings', value: 'Residential, Residential Lease, Lots & Acreage, Commercial, Multi-Family' },
                                { label: 'Sale', value: 'Residential' },
                                { label: 'Lease', value: 'Residential Lease' },
                                { label: 'Lots & Acreage', value: 'Lots & Acreage' },
                                { label: 'Commercial', value: 'Commercial' },
                            ]}
                            onChange={handlePropertyTypeChange}
                        />
                        <TextControl
                            label="Minimum Price"
                            value={minimum_price}
                            type="number"
                            onChange={(value) => setAttributes({ minimum_price: value === '' ? '' : value.toString() })}
                        />
                        <TextControl
                            label="Maximum Price"
                            value={maximum_price}
                            type="number"
                            onChange={(value) => setAttributes({ maximum_price: value === '' ? '' : value.toString() })}
                        />
                        <TextControl
                            label="Minimum Lot Size (m²)"
                            value={minimum_lot_square_meters}
                            type="number"
                            onChange={(value) => setAttributes({ minimum_lot_square_meters: value === '' ? '' : value.toString() })}
                        />
                        <TextControl
                            label="Maximum Lot Size (m²)"
                            value={maximum_lot_square_meters}
                            type="number"
                            onChange={(value) => setAttributes({ maximum_lot_square_meters: value === '' ? '' : value.toString() })}
                        />
                        <TextControl
                            label="Minimum Bathrooms"
                            value={minimum_bathrooms}
                            type="number"
                            onChange={(value) => setAttributes({ minimum_bathrooms: value === '' ? '' : value.toString() })}
                        />
                        <TextControl
                            label="Maximum Bathrooms"
                            value={maximum_bathrooms}
                            type="number"
                            onChange={(value) => setAttributes({ maximum_bathrooms: value === '' ? '' : value.toString() })}
                        />
                        <TextControl
                            label="Minimum Square Meters"
                            value={minimum_square_meters}
                            type="number"
                            onChange={(value) => setAttributes({ minimum_square_meters: value === '' ? '' : value.toString() })}
                        />
                        <TextControl
                            label="Maximum Square Meters"
                            value={maximum_square_meters}
                            type="number"
                            onChange={(value) => setAttributes({ maximum_square_meters: value === '' ? '' : value.toString() })}
                        />
                        <TextControl
                            label="Minimum Year Built"
                            value={minimum_year_built}
                            type="number"
                            onChange={(value) => setAttributes({ minimum_year_built: value === '' ? '' : value.toString() })}
                        />
                        <TextControl
                            label="Maximum Year Built"
                            value={maximum_year_built}
                            type="number"
                            onChange={(value) => setAttributes({ maximum_year_built: value === '' ? '' : value.toString() })}
                        />
                        <TextControl
                            label="Minimum Bedrooms"
                            value={minimum_bedrooms}
                            type="number"
                            onChange={(value) => setAttributes({ minimum_bedrooms: value === '' ? '' : value.toString() })}
                        />
                        <TextControl
                            label="Maximum Bedrooms"
                            value={maximum_bedrooms}
                            type="number"
                            onChange={(value) => setAttributes({ maximum_bedrooms: value === '' ? '' : value.toString() })}
                        />
                        <TextControl
                            label="Listing Per Page"
                            value={listing_per_page}
                            type="number"
                            onChange={(value) => setAttributes({ listing_per_page: value === '' ? '' : value.toString() })}
                        />
                    </PanelBody>
                    <PanelBody title="Filter Visibility Settings" initialOpen={false}>
                        <p><strong>Disable Filters (check to hide)</strong></p>
                        <CheckboxControl
                            label="Disable Address Filter"
                            checked={disable_filter_address}
                            onChange={() => setAttributes({ disable_filter_address: !disable_filter_address })}
                        />
                        <CheckboxControl
                            label="Disable Price Filter"
                            checked={disable_filter_price}
                            onChange={() => setAttributes({ disable_filter_price: !disable_filter_price })}
                        />
                        <CheckboxControl
                            label="Disable Beds Filter"
                            checked={disable_filter_beds}
                            onChange={() => setAttributes({ disable_filter_beds: !disable_filter_beds })}
                        />
                        <CheckboxControl
                            label="Disable Baths Filter"
                            checked={disable_filter_baths}
                            onChange={() => setAttributes({ disable_filter_baths: !disable_filter_baths })}
                        />
                        <CheckboxControl
                            label="Disable Property Types Filter"
                            checked={disable_filter_property_types}
                            onChange={() => setAttributes({ disable_filter_property_types: !disable_filter_property_types })}
                        />
                        <CheckboxControl
                            label="Disable Advanced Filter"
                            checked={disable_filter_advanced}
                            onChange={() => setAttributes({ disable_filter_advanced: !disable_filter_advanced })}
                        />
                    </PanelBody>
                    <PanelBody title="Agent Card Settings" initialOpen={false}>
                        <CheckboxControl
                            label="Show Agent Card"
                            checked={show_agent_card}
                            onChange={() => setAttributes({ show_agent_card: !show_agent_card })}
                        />
                        {show_agent_card && (
                            <>
                                <MediaUploadCheck>
                                    <MediaUpload
                                        onSelect={(media) => setAttributes({ agent_image: media.url })}
                                        allowedTypes={['image']}
                                        value={agent_image}
                                        render={({ open }) => (
                                            <div style={{ marginBottom: '16px' }}>
                                                <label style={{ display: 'block', marginBottom: '8px', fontWeight: '600' }}>
                                                    Agent Image
                                                </label>
                                                {agent_image && (
                                                    <div style={{ marginBottom: '8px' }}>
                                                        <img 
                                                            src={agent_image} 
                                                            alt="Agent preview" 
                                                            style={{ 
                                                                maxWidth: '200px', 
                                                                height: 'auto',
                                                                borderRadius: '8px',
                                                                display: 'block'
                                                            }} 
                                                        />
                                                    </div>
                                                )}
                                                <div style={{ display: 'flex', gap: '8px' }}>
                                                    <Button onClick={open} variant="primary">
                                                        {agent_image ? 'Change Image' : 'Upload Image'}
                                                    </Button>
                                                    {agent_image && (
                                                        <Button 
                                                            onClick={() => setAttributes({ agent_image: '' })} 
                                                            variant="secondary"
                                                            isDestructive
                                                        >
                                                            Remove
                                                        </Button>
                                                    )}
                                                </div>
                                            </div>
                                        )}
                                    />
                                </MediaUploadCheck>
                                <TextControl
                                    label="Agent Name"
                                    value={agent_name}
                                    onChange={(value) => setAttributes({ agent_name: value })}
                                    placeholder="Brett Singleton"
                                />
                                <TextControl
                                    label="Agent Title"
                                    value={agent_title}
                                    onChange={(value) => setAttributes({ agent_title: value })}
                                    placeholder="Dallas Real Estate Agent"
                                />
                                <TextControl
                                    label="Agent Phone"
                                    value={agent_phone}
                                    onChange={(value) => setAttributes({ agent_phone: value })}
                                    placeholder="+1 424 152 1609"
                                />
                                <TextControl
                                    label="Agent Email"
                                    value={agent_email}
                                    onChange={(value) => setAttributes({ agent_email: value })}
                                    placeholder="brett@dallasrealestate.com"
                                />
                                <TextControl
                                    label="Agent Address"
                                    value={agent_address}
                                    onChange={(value) => setAttributes({ agent_address: value })}
                                    placeholder="12200 Ford Rd #434, Dallas, TX"
                                />
                            </>
                        )}
                    </PanelBody>
                    <PanelBody title="Map Settings">{googleMapsApiKey ? (
                            <>
                                <p><strong>Location Selector</strong></p>
                                <MapSelector
                                    apiKey={googleMapsApiKey}
                                    latitude={map_latitude}
                                    longitude={map_longitude}
                                    zoom={map_zoom}
                                    onLocationChange={handleMapLocationChange}
                                    onZoomChange={handleZoomChange}
                                />
                                <TextControl
                                    label="Latitude"
                                    value={map_latitude}
                                    onChange={(value) => setAttributes({ map_latitude: value })}
                                />
                                <TextControl
                                    label="Longitude"
                                    value={map_longitude}
                                    onChange={(value) => setAttributes({ map_longitude: value })}
                                />
                                <RangeControl
                                    label="Zoom Level"
                                    value={parseInt(map_zoom) || 12}
                                    onChange={handleZoomChange}
                                    min={1}
                                    max={20}
                                />
                            </>
                        ) : (
                            <p>Google Maps API key not found. Please make sure it is configured in the WordPress settings.</p>
                        )}
                    </PanelBody>
                </InspectorControls>
                <ServerSideRender
                    block="rch-rechat-plugin/listing-block"
                    attributes={attributes}
                />
            </>
        );
    },
    save() {
        return null;
    },
});
