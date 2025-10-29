const { registerBlockType } = wp.blocks;
const { InspectorControls } = wp.blockEditor || wp.editor;
const { PanelBody, SelectControl, TextControl, ToggleControl } = wp.components;
import { useEffect, useState } from '@wordpress/element';
import ServerSideRender from '@wordpress/server-side-render';
import apiFetch from '@wordpress/api-fetch';

registerBlockType('rch-rechat-plugin/leads-form-block', {
    title: 'Leads Form Block',
    description: 'Block for lead form submission',
    icon: 'admin-users',
    category: 'widgets',
    attributes: {
        formTitle: { type: 'string', default: 'Lead Form' },
        leadChannel: { type: 'string', default: '' },
        showFirstName: { type: 'boolean', default: true },
        showLastName: { type: 'boolean', default: true },
        showPhoneNumber: { type: 'boolean', default: true },
        showEmail: { type: 'boolean', default: true },
        showNote: { type: 'boolean', default: true },
        selectedTagsFrom: { type: 'array', default: [] },
        emailForGetLead: { type: 'string', default: '' },
    },
    edit({ attributes, setAttributes }) {
        const { 
            formTitle, leadChannel, showFirstName, showLastName, showPhoneNumber, 
            showEmail, showNote, selectedTagsFrom, emailForGetLead 
        } = attributes;
        
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
                if (tokenResponse.rch_rechat_google_map_api_key) {
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
                        options.unshift({
                            label: 'Select your channel',
                            value: '',
                        });
                        setLeadChannels(options);
                    } catch (error) {
                        console.error('Error fetching lead channels:', error);
                    } finally {
                        setLoadingChannels(false);
                    }
                };
                fetchLeadChannels();

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
        return null;
    },
});
