const { registerBlockType } = wp.blocks;
const { InspectorControls, MediaUpload, MediaUploadCheck } = wp.blockEditor || wp.editor;
const { PanelBody, RangeControl, SelectControl, TextControl, CheckboxControl, RadioControl, Button, Spinner } = wp.components;
import { useEffect, useState, useRef } from '@wordpress/element';
import ServerSideRender from '@wordpress/server-side-render';
import apiFetch from '@wordpress/api-fetch';
import MapSelector from '../utils/map-selector';
import { fetchDataWithMeta } from '../utils/api-helpers';

/**
 * `/rch/v1/boundary-countries` and `/rch/v1/boundary-states` return `{ options: [{ value, label }] }`
 * already normalized in PHP (`rch_rechat_normalize_boundary_options`). Only validate for SelectControl.
 *
 * @param {unknown} res REST JSON body
 * @returns {{ label: string, value: string }[]}
 */
function parseBoundaryRestOptions(res) {
    if (!res || typeof res !== 'object') {
        return [];
    }
    const options = /** @type {Record<string, unknown>} */ (res).options;
    if (!Array.isArray(options)) {
        return [];
    }
    const out = [];
    for (const row of options) {
        if (!row || typeof row !== 'object') {
            continue;
        }
        const o = /** @type {Record<string, unknown>} */ (row);
        const label = o.label != null ? String(o.label).trim() : '';
        const value = o.value != null ? String(o.value).trim() : '';
        if (label !== '' && value !== '') {
            out.push({ label, value });
        }
    }
    return out;
}

registerBlockType('rch-rechat-plugin/listing-block', {
    title: 'Listing Block',
    description: 'Block for showing property listings',
    icon: 'building',
    category: 'widgets',
    attributes: {
        minimum_price: { type: 'string', default: '' },
        maximum_price: { type: 'string', default: '' },
        minimum_square_feet: { type: 'string', default: '' },
        maximum_square_feet: { type: 'string', default: '' },
        minimum_bathrooms: { type: 'string', default: '' },
        maximum_bathrooms: { type: 'string', default: '' },
        minimum_lot_square_feet: { type: 'string', default: '' },
        maximum_lot_square_feet: { type: 'string', default: '' },
        minimum_year_built: { type: 'string', default: '' },
        maximum_year_built: { type: 'string', default: '' },
        minimum_bedrooms: { type: 'string', default: '' },
        maximum_bedrooms: { type: 'string', default: '' },
        listing_per_page: { type: 'string', default: '' },
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
        own_listing: { type: 'boolean', default: false },
        property_types: { type: 'string', default: '' },
        filter_open_houses: { type: 'boolean', default: false },
        office_exclusive: { type: 'boolean', default: false },
        disable_sort: { type: 'boolean', default: false },
        map_latitude: { type: 'string', default: '' },
        map_longitude: { type: 'string', default: '' },
        map_zoom: { type: 'string', default: '12' },
        map_style: { type: 'string', default: '' },
        map_style_url: { type: 'string', default: '' },
        map_id: { type: 'string', default: '' },
        sort_by: { type: 'string', default: '-list_date' },
        filter_address: { type: 'string', default: '' },
        filter_search_limit: { type: 'string', default: '' },
        filter_suggestions_limit: { type: 'string', default: '' },
        filter_pagination_offset: { type: 'string', default: '' },
        property_subtypes: { type: 'string', default: '' },
        architectural_styles: { type: 'string', default: '' },
        filter_baths: { type: 'string', default: '' },
        minimum_parking_spaces: { type: 'string', default: '' },
        minimum_sold_date: { type: 'string', default: '' },
        filter_pool: { type: 'boolean', default: false },
        filter_agents: { type: 'string', default: '' },
        list_offices: { type: 'string', default: '' },
        filter_brand_id: { type: 'string', default: '' },
        disable_filter_loading_indicator: { type: 'boolean', default: false },
        filter_boundary_country: { type: 'string', default: '' },
        filter_boundary_state: { type: 'string', default: '' },
        // Legacy; no editor control — preserves old posts and satisfies REST attribute schema.
        layout_style: { type: 'string', default: '' },
    },
    edit({ attributes, setAttributes }) {
        const {
            minimum_price, maximum_price, minimum_square_feet, maximum_square_feet,
            minimum_bathrooms, maximum_bathrooms, minimum_lot_square_feet, maximum_lot_square_feet,
            minimum_year_built, maximum_year_built, minimum_bedrooms, maximum_bedrooms,
            listing_per_page, filterByRegions, filterByOffices, selectedStatuses, 
            disable_filter_address, disable_filter_price, disable_filter_beds, 
            disable_filter_baths, disable_filter_property_types, disable_filter_advanced,
            own_listing, property_types, filter_open_houses, office_exclusive, filter_pool, disable_sort, listing_statuses, map_latitude, map_longitude, map_zoom, map_style, map_style_url, map_id,
            sort_by, filter_address, filter_search_limit, filter_suggestions_limit, filter_pagination_offset, property_subtypes, architectural_styles, filter_baths, minimum_parking_spaces, minimum_sold_date, filter_agents, list_offices, filter_brand_id, disable_filter_loading_indicator,
            filter_boundary_country, filter_boundary_state,
        } = attributes;

        const [regions, setRegions] = useState([]);
        const [offices, setOffices] = useState([]);
        const [googleMapsApiKey, setGoogleMapsApiKey] = useState('');
        const [siteBoundaryDefaults, setSiteBoundaryDefaults] = useState(null);
        const [boundaryCountryOptions, setBoundaryCountryOptions] = useState([{ label: 'Any', value: '' }]);
        const [boundaryStateOptions, setBoundaryStateOptions] = useState([{ label: 'Any', value: '' }]);
        const [boundaryStatesLoading, setBoundaryStatesLoading] = useState(false);
        const defaultsSeededRef = useRef(false);

        const statusOptions = [
            { label: 'Active', value: 'Active' },
            { label: 'Pending', value: 'Pending' },
            { label: 'Closed', value: 'Closed' },
            { label: 'Archived', value: 'Archived' },
        ];

        useEffect(() => {
            fetchDataWithMeta('/wp/v2/regions?per_page=100', setRegions);
            fetchDataWithMeta('/wp/v2/offices?per_page=100', setOffices);

            apiFetch({ path: '/wp/v2/options' })
                .then((options) => {
                    if (options.rch_rechat_google_map_api_key) {
                        setGoogleMapsApiKey(options.rch_rechat_google_map_api_key);
                    }
                    setSiteBoundaryDefaults({
                        country: options.rch_selected_country ? String(options.rch_selected_country).toUpperCase() : '',
                        state: options.rch_selected_state ? String(options.rch_selected_state) : '',
                    });
                })
                .catch((error) => {
                    console.error('Error fetching editor options:', error);
                    setSiteBoundaryDefaults({ country: '', state: '' });
                });

            apiFetch({ path: '/rch/v1/boundary-countries' })
                .then((res) => {
                    const rows = parseBoundaryRestOptions(res);
                    setBoundaryCountryOptions([{ label: 'Any', value: '' }, ...rows]);
                })
                .catch((error) => {
                    console.error('Error loading boundary countries:', error);
                });
        }, []);

        useEffect(() => {
            if (defaultsSeededRef.current || siteBoundaryDefaults === null) {
                return;
            }
            defaultsSeededRef.current = true;
            const sc = siteBoundaryDefaults.country || '';
            const ss = siteBoundaryDefaults.state || '';
            const patch = {};
            if (!filter_boundary_country && !filter_boundary_state && sc && ss) {
                patch.filter_boundary_country = sc;
                patch.filter_boundary_state = ss;
            } else if (!filter_boundary_country && sc) {
                patch.filter_boundary_country = sc;
            } else if (
                !filter_boundary_state &&
                ss &&
                filter_boundary_country &&
                sc &&
                filter_boundary_country === sc
            ) {
                patch.filter_boundary_state = ss;
            }
            if (Object.keys(patch).length) {
                setAttributes(patch);
            }
        }, [siteBoundaryDefaults, filter_boundary_country, filter_boundary_state, setAttributes]);

        useEffect(() => {
            if (!filter_boundary_country) {
                setBoundaryStatesLoading(false);
                setBoundaryStateOptions([{ label: 'Any', value: '' }]);
                return;
            }
            let cancelled = false;
            setBoundaryStatesLoading(true);
            apiFetch({
                path: `/rch/v1/boundary-states?country=${encodeURIComponent(filter_boundary_country)}`,
            })
                .then((res) => {
                    if (cancelled) {
                        return;
                    }
                    const rows = parseBoundaryRestOptions(res);
                    setBoundaryStateOptions([{ label: 'Any', value: '' }, ...rows]);
                })
                .catch((error) => {
                    if (!cancelled) {
                        console.error('Error loading boundary states:', error);
                        setBoundaryStateOptions([{ label: 'Any', value: '' }]);
                    }
                })
                .finally(() => {
                    if (!cancelled) {
                        setBoundaryStatesLoading(false);
                    }
                });
            return () => {
                cancelled = true;
            };
        }, [filter_boundary_country]);

        const handleAttributeChange = (attr, value) => {
            setAttributes({ [attr]: value });
        };

        const handleStatusChange = (status) => {
            const updatedStatuses = selectedStatuses.includes(status)
                ? selectedStatuses.filter(s => s !== status)
                : [...selectedStatuses, status];
            const listingStatuses = updatedStatuses.flatMap((statusKey) =>
                ({
                    Active: ['Active', 'Incoming', 'Coming Soon'],
                    Pending: ['Pending'],
                    Closed: ['Sold', 'Leased'],
                    Archived: ['Withdrawn', 'Expired'],
                }[statusKey] || [])
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

        /**
         * Persist brand scope via filter_brand_id as well as own_listing.
         * Some installs strip unknown block attrs on REST save; filter_brand_id
         * still drives brand_id on <rechat-listings> in PHP.
         */
        const handleOwnListingChange = () => {
            const next = !own_listing;
            if (!next) {
                setAttributes({ own_listing: false, filter_brand_id: '' });
                return;
            }
            apiFetch({ path: '/wp/v2/options' })
                .then((options) => {
                    const brand =
                        options && options.rch_rechat_brand_id
                            ? String(options.rch_rechat_brand_id)
                            : '';
                    setAttributes({
                        own_listing: true,
                        ...(brand ? { filter_brand_id: brand } : {}),
                    });
                })
                .catch((error) => {
                    console.error('Error fetching brand for own_listing:', error);
                    setAttributes({ own_listing: true });
                });
        };

        return (
            <>
                <InspectorControls>
                    <PanelBody title="Listing Settings">
                        <CheckboxControl
                            label="Only our own listings"
                            checked={own_listing}
                            onChange={handleOwnListingChange}
                        />
                        <CheckboxControl
                            label="Open Houses Only"
                            checked={filter_open_houses}
                            onChange={() => setAttributes({ filter_open_houses: !filter_open_houses })}
                        />
                        <CheckboxControl
                            label="Hide Sort By"
                            checked={disable_sort}
                            onChange={() => setAttributes({ disable_sort: !disable_sort })}
                        />
                        <CheckboxControl
                            label="Office Exclusive"
                            checked={office_exclusive}
                            onChange={() => setAttributes({ office_exclusive: !office_exclusive })}
                        />
                        <SelectControl
                            label="Boundary country (filter_boundary_country)"
                            help="Defaults from General Settings; change here to scope this block only. ISO code from Rechat (e.g. US)."
                            value={filter_boundary_country}
                            options={boundaryCountryOptions}
                            onChange={(value) =>
                                setAttributes({
                                    filter_boundary_country: value ? String(value).toUpperCase() : '',
                                    filter_boundary_state: '',
                                })
                            }
                        />
                        {filter_boundary_country && boundaryStatesLoading ? (
                            <p
                                className="components-base-control__help"
                                style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 12 }}
                            >
                                <Spinner />
                                Loading states for this country…
                            </p>
                        ) : null}
                        <SelectControl
                            label="Boundary state / province (filter_boundary_state)"
                            help={
                                !filter_boundary_country
                                    ? 'Choose a country first, or leave both as Any.'
                                    : boundaryStatesLoading
                                      ? ''
                                      : 'Uses the state title expected by the Rechat SDK (same as General Settings).'
                            }
                            value={filter_boundary_state}
                            options={boundaryStateOptions}
                            disabled={!filter_boundary_country || boundaryStatesLoading}
                            onChange={(value) => setAttributes({ filter_boundary_state: value || '' })}
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
                                { label: 'All Listings', value: '' },
                                {label: 'Residential', value:'Residential'},
                                { label: 'Lease', value: 'Residential Lease' },
                                { label: 'Lots & Acreage', value: 'Lots & Acreage' },
                                { label: 'Commercial', value: 'Commercial' },
                                { label: 'Multi-Family', value: 'Multi-Family' },
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
                            label="Minimum Square Feet"
                            value={minimum_square_feet}
                            type="number"
                            onChange={(value) => setAttributes({ minimum_square_feet: value === '' ? '' : value.toString() })}
                        />
                        <TextControl
                            label="Maximum Square Feet"
                            value={maximum_square_feet}
                            type="number"
                            onChange={(value) => setAttributes({ maximum_square_feet: value === '' ? '' : value.toString() })}
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
                            label="Minimum Lot Square Feet"
                            value={minimum_lot_square_feet}
                            type="number"
                            onChange={(value) => setAttributes({ minimum_lot_square_feet: value === '' ? '' : value.toString() })}
                        />
                        <TextControl
                            label="Maximum Lot Square Feet"
                            value={maximum_lot_square_feet}
                            type="number"
                            onChange={(value) => setAttributes({ maximum_lot_square_feet: value === '' ? '' : value.toString() })}
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
                        <SelectControl
                            label="Sort By"
                            value={sort_by}
                            options={[
                                { label: 'Sort by Date', value: '-list_date' },
                                { label: 'Sort by Price', value: '-price' },
                            ]}
                            onChange={(value) => setAttributes({ sort_by: value })}
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
                    <PanelBody title="Additional Rechat filters (optional)" initialOpen={false}>
                        <TextControl
                            label="Initial address / map boundary (filter_address)"
                            help="Sets filter_address on the list view (e.g. city or place search)."
                            value={filter_address}
                            onChange={(v) => setAttributes({ filter_address: v || '' })}
                        />
                        <TextControl
                            label="Max result count (filter_search_limit)"
                            type="number"
                            value={filter_search_limit}
                            onChange={(v) => setAttributes({ filter_search_limit: v || '' })}
                        />
                        <TextControl
                            label="Search suggestions limit (filter_suggestions_limit)"
                            type="number"
                            value={filter_suggestions_limit}
                            onChange={(v) => setAttributes({ filter_suggestions_limit: v || '' })}
                        />
                        <TextControl
                            label="Initial pagination offset (filter_pagination_offset)"
                            type="number"
                            value={filter_pagination_offset}
                            onChange={(v) => setAttributes({ filter_pagination_offset: v || '' })}
                        />
                        <TextControl
                            label="Property subtypes (comma-separated)"
                            value={property_subtypes}
                            onChange={(v) => setAttributes({ property_subtypes: v || '' })}
                        />
                        <TextControl
                            label="Architectural styles (comma-separated)"
                            value={architectural_styles}
                            onChange={(v) => setAttributes({ architectural_styles: v || '' })}
                        />
                        <TextControl
                            label="Exact baths (filter_baths)"
                            type="number"
                            value={filter_baths}
                            onChange={(v) => setAttributes({ filter_baths: v || '' })}
                        />
                        <TextControl
                            label="Min parking spaces"
                            type="number"
                            value={minimum_parking_spaces}
                            onChange={(v) => setAttributes({ minimum_parking_spaces: v || '' })}
                        />
                        <TextControl
                            label="Minimum sold date (Unix ms, filter_minimum_sold_date)"
                            value={minimum_sold_date}
                            onChange={(v) => setAttributes({ minimum_sold_date: v || '' })}
                        />
                        <TextControl
                            label="Map ID (map_id, Cloud map styling)"
                            value={map_id}
                            onChange={(v) => setAttributes({ map_id: v || '' })}
                        />
                        <TextControl
                            label="Override brand ID (filter_brand_id)"
                            value={filter_brand_id}
                            onChange={(v) => setAttributes({ filter_brand_id: v || '' })}
                        />
                        <TextControl
                            label="Agent IDs (filter_agents, comma-separated)"
                            value={filter_agents}
                            onChange={(v) => setAttributes({ filter_agents: v || '' })}
                        />
                        <TextControl
                            label="Office IDs (list_offices / filter_list_offices, comma-separated)"
                            value={list_offices}
                            onChange={(v) => setAttributes({ list_offices: v || '' })}
                        />
                        <CheckboxControl
                            label="Pool only (filter_pool)"
                            checked={filter_pool}
                            onChange={() => setAttributes({ filter_pool: !filter_pool })}
                        />
                        <CheckboxControl
                            label="Disable filter loading indicator"
                            checked={disable_filter_loading_indicator}
                            onChange={() => setAttributes({ disable_filter_loading_indicator: !disable_filter_loading_indicator })}
                        />
                    </PanelBody>
                    <PanelBody title="Map Settings">
                        <SelectControl
                            label="Map style (preset)"
                            value={map_style || ''}
                            options={[
                                { label: 'Default (liberty)', value: '' },
                                { label: 'Liberty', value: 'liberty' },
                                { label: 'Bright', value: 'bright' },
                                { label: 'Positron', value: 'positron' },
                                { label: 'Dark', value: 'dark' },
                            ]}
                            onChange={(v) => setAttributes({ map_style: v || '' })}
                            help="MapLibre preset on rechat-map. Custom URL below overrides preset."
                        />
                        <TextControl
                            label="Map style URL (style_url)"
                            value={map_style_url}
                            onChange={(v) => setAttributes({ map_style_url: v || '' })}
                            help="Optional MapLibre style JSON URL. When set, overrides preset."
                        />
                        {googleMapsApiKey ? (
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
