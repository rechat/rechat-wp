const { registerBlockType } = wp.blocks;
const { InspectorControls, ColorPalette } = wp.blockEditor || wp.editor;
const { PanelBody, RangeControl, SelectControl, TextControl, MultiSelectControl, CheckboxControl, ToggleControl } = wp.components;
import { useEffect, useState } from '@wordpress/element'; // useState and useEffect hooks
import ServerSideRender from '@wordpress/server-side-render';
import apiFetch from '@wordpress/api-fetch';
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
        postsPerPage: { type: 'number', default: 5 },
        regionBgColor: { type: 'string', default: '#edf1f5' },
        textColor: { type: 'string', default: '#000' },
        filterByRegions: { type: 'string', default: '' },
    },
    edit({ attributes, setAttributes }) {
        const { postsPerPage, regionBgColor, textColor, filterByRegions } = attributes;
        const [regions, setRegions] = useState([]); // State to store fetched regions

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
        return null; // Dynamic block, content will be rendered by the server
    },
});

// Agents block
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

        const fetchData = async (endpoint, setState) => {
            try {
                const data = await apiFetch({ path: endpoint });
                const options = data.map(item => ({
                    label: item.title.rendered,
                    value: item.id,
                }));
                options.unshift({ label: 'None', value: '' });
                setState(options);
            } catch (error) {
                console.error('Error fetching data:', error);
            }
        };

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
        return null; // Dynamic block, content will be generated by PHP
    },
});

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
        show_filter_bar: { type: 'boolean', default: true }, // New attribute for showing the filter bar
        own_listing: { type: 'boolean', default: true }, // New attribute for showing the filter bar

    },
    edit({ attributes, setAttributes }) {
        const {
            minimum_price, maximum_price, minimum_lot_square_meters, maximum_lot_square_meters,
            minimum_bathrooms, maximum_bathrooms, minimum_square_meters, maximum_square_meters,
            minimum_year_built, maximum_year_built, minimum_bedrooms, maximum_bedrooms,
            listing_per_page, filterByRegions, filterByOffices, selectedStatuses,show_filter_bar,own_listing
        } = attributes;

        const [regions, setRegions] = useState([]);
        const [offices, setOffices] = useState([]);

        const statusOptions = [
            { label: 'Active', value: 'Active' },
            { label: 'Closed', value: 'Closed' },
            { label: 'Archived', value: 'Archived' },
        ];

        const fetchData = async (path, setState) => {
            try {
                const data = await apiFetch({ path });
                setState([{ label: 'None', value: '' }, ...data.map(item => ({
                    label: item.title.rendered,
                    value: item.meta.region_id || item.meta.office_id
                }))]);
            } catch (error) {
                console.error('Error fetching data:', error);
            }
        };

        useEffect(() => {
            fetchData('/wp/v2/regions?per_page=100', setRegions);
            fetchData('/wp/v2/offices?per_page=100', setOffices);
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
        return (
            <>
                <InspectorControls>
                    <PanelBody title="Listing Settings">
                    <CheckboxControl
                            label="Show Filter Bar"
                            checked={show_filter_bar}
                            onChange={() => setAttributes({ show_filter_bar: !show_filter_bar })}
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
                </InspectorControls>
                <ServerSideRender
                    block="rch-rechat-plugin/listing-block"
                    attributes={attributes}
                />
            </>
        );
    },
    save() {
        return null; // Dynamic block, content will be generated by PHP
    },
});


//register contact lead channel block
registerBlockType('rch-rechat-plugin/leads-form-block', {
    title: 'Leads Form Block',
    description: 'Block for lead form submission',
    icon: 'admin-users',
    category: 'widgets',
    attributes: {
        formTitle: { type: 'string', default: 'Lead Form' }, // New attribute for form title
        leadChannel: { type: 'string', default: '' },
        showFirstName: { type: 'boolean', default: true },
        showLastName: { type: 'boolean', default: true },
        showPhoneNumber: { type: 'boolean', default: true },
        showEmail: { type: 'boolean', default: true },
        showNote: { type: 'boolean', default: true },
        selectedTagsFrom: { type: 'array', default: [] }, // Array to hold selected tags
        emailForGetLead: { type: 'string', default: '' },
    },
    edit({ attributes, setAttributes }) {
        const { formTitle, leadChannel, showFirstName, showLastName, showPhoneNumber, showEmail, showNote, selectedTagsFrom, emailForGetLead } = attributes;
        const [leadChannels, setLeadChannels] = useState();
        const [tags, setTags] = useState([]);
        const [loadingChannels, setLoadingChannels] = useState(true);
        const [loadingTags, setLoadingTags] = useState(true);
        const [isLoggedIn, setIsLoggedIn] = useState(null);
        const [brandId, setBrandId] = useState(null);
        const [accessToken, setAccessToken] = useState(null);

        useEffect(() => {
            const checkUserLogin = async () => {
                try {
                    const response = await apiFetch({ path: '/wp/v2/users/me' });
                    if (response && response.id) {
                        setIsLoggedIn(true);
                        fetchBrandId();
                        fetchAccessToken();
                    } else {
                        setIsLoggedIn(false);
                    }
                } catch (error) {
                    setIsLoggedIn(false);
                    console.error('Error checking user login:', error);
                }
            };
            checkUserLogin();
        }, []);

        const fetchBrandId = async () => {
            try {
                const brandResponse = await apiFetch({ path: '/wp/v2/options' });
                if (brandResponse.rch_rechat_brand_id) {
                    setBrandId(brandResponse.rch_rechat_brand_id);
                } else {
                    console.error('Brand ID not found in WordPress options.');
                }
            } catch (error) {
                console.error('Error fetching brand ID:', error);
            }
        };

        const fetchAccessToken = async () => {
            try {
                const tokenResponse = await apiFetch({ path: '/wp/v2/options' });
                if (tokenResponse.rch_rechat_access_token) {
                    setAccessToken(tokenResponse.rch_rechat_access_token);
                } else {
                    console.error('Access token not found in WordPress options.');
                }
            } catch (error) {
                console.error('Error fetching access token:', error);
            }
        };

        useEffect(() => {
            if (isLoggedIn && brandId && accessToken) {
                const fetchLeadChannels = async () => {
                    try {
                        const channelResponse = await fetch(`https://api.rechat.com/brands/${brandId}/leads/channels`, {
                            method: 'GET',
                            headers: {
                                'Authorization': `Bearer ${accessToken}`,
                            },
                        });
                        const channelData = await channelResponse.json();
                        const options = channelData.data.map(channel => ({
                            label: channel.title ? channel.title : 'Unnamed',
                            value: channel.id,
                        }));
                        // Add "Select your channel" option
                        options.unshift({
                            label: 'Select your channel',
                            value: '', // Empty value to represent "nothing selected"
                        });
                        setLeadChannels(options);
                    } catch (error) {
                        console.error('Error fetching lead channels:', error);
                    } finally {
                        setLoadingChannels(false);
                    }
                };
                fetchLeadChannels();

                // Fetch tags from the API
                const fetchTags = async () => {
                    try {
                        const tagsResponse = await fetch('https://api.rechat.com/contacts/tags', {
                            method: 'GET',
                            headers: {
                                'Authorization': `Bearer ${accessToken}`,
                                'X-RECHAT-BRAND': brandId,
                            },
                        });
                        const tagsData = await tagsResponse.json();
                        const tagOptions = tagsData.data.map(tag => ({
                            label: tag.tag,
                            value: tag.tag,
                        }));
                        setTags(tagOptions);
                    } catch (error) {
                        console.error('Error fetching tags:', error);
                    } finally {
                        setLoadingTags(false);
                    }
                };
                fetchTags();
            }
        }, [isLoggedIn, brandId, accessToken]);

        if (isLoggedIn === false) {
            return <p>Please log in to view and manage the lead channels and tags.</p>;
        }

        if (isLoggedIn === null) {
            return <p>Loading...</p>;
        }

        const handleTagChange = (tagId) => {
            const newSelectedTagsFrom = selectedTagsFrom.includes(tagId)
                ? selectedTagsFrom.filter(id => id !== tagId)
                : [...selectedTagsFrom, tagId];
            setAttributes({ selectedTagsFrom: newSelectedTagsFrom });
        };

        return (
            <>
                <InspectorControls>
                    <PanelBody title="Lead Form Settings">
                        <TextControl
                            label="Form Title"
                            value={formTitle}
                            onChange={(value) => setAttributes({ formTitle: value })}
                        />
                        <SelectControl
                            label="Lead Channel"
                            value={leadChannel}
                            options={loadingChannels ? [{ label: 'Loading channels...', value: '' }] : leadChannels}
                            onChange={(selectedChannel) => setAttributes({ leadChannel: selectedChannel })}
                        />
                        <TextControl
                            label="Email for Get This Lead In you Inbox"
                            value={emailForGetLead}
                            placeholder="Enter the email to receive leads"
                            onChange={(value) => setAttributes({ emailForGetLead: value })}
                        />
                        <ToggleControl
                            label="Show First Name Field"
                            checked={showFirstName}
                            onChange={(value) => setAttributes({ showFirstName: value })}
                        />
                        <ToggleControl
                            label="Show Last Name Field"
                            checked={showLastName}
                            onChange={(value) => setAttributes({ showLastName: value })}
                        />
                        <ToggleControl
                            label="Show Phone Number Field"
                            checked={showPhoneNumber}
                            onChange={(value) => setAttributes({ showPhoneNumber: value })}
                        />
                        <ToggleControl
                            label="Show Email Field"
                            checked={showEmail}
                            onChange={(value) => setAttributes({ showEmail: value })}
                        />
                        <ToggleControl
                            label="Show Note Field"
                            checked={showNote}
                            onChange={(value) => setAttributes({ showNote: value })}
                        />
                        <div style={{ maxHeight: '200px', overflowY: 'auto' }}>
                            <fieldset>
                                <legend>Tags</legend>
                                {loadingTags ? (
                                    <p>Loading tags...</p>
                                ) : (
                                    tags.map(tag => (
                                        <div key={tag.value} style={{ marginBottom: '8px' }}>
                                            <label>
                                                <input
                                                    type="checkbox"
                                                    value={tag.value}
                                                    checked={selectedTagsFrom.includes(tag.value)}
                                                    onChange={() => handleTagChange(tag.value)}
                                                />
                                                {tag.label}
                                            </label>
                                        </div>
                                    ))
                                )}
                            </fieldset>
                        </div>
                    </PanelBody>
                </InspectorControls>
                <ServerSideRender
                    block="rch-rechat-plugin/leads-form-block"
                    attributes={attributes}
                />
            </>
        );
    },
    save() {
        return null; // Server-rendered block
    },
});