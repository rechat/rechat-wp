const { registerBlockType } = wp.blocks;
const { InspectorControls } = wp.blockEditor || wp.editor;
const { PanelBody, SelectControl, TextControl, ToggleControl } = wp.components;
import { useEffect, useState, useMemo } from '@wordpress/element';
import ServerSideRender from '@wordpress/server-side-render';
import apiFetch from '@wordpress/api-fetch';

/** Keep in sync with PHP `RECHAT_API_BASE_URL` in the main plugin file. */
const RECHAT_API_BASE_URL = 'https://api.rechat.com';

/** Always offered next to API tags; selectable like other tags. */
const STATIC_MORTGAGE_QUESTIONNAIRE_TAG = 'Mortgage Questionnaire';

registerBlockType('rch-rechat-plugin/leads-form-block', {
    title: 'Leads Form Block',
    description: 'Block for lead form submission',
    icon: 'admin-users',
    category: 'widgets',
    attributes: {
        formTitle: { type: 'string', default: 'Lead Form' },
        leadChannel: { type: 'string', default: '' },
        leadChannelName: { type: 'string', default: '' },
        assigneeAgentEmail: { type: 'string', default: '' },
        useMortgageQuestionLeadSource: { type: 'boolean', default: true },
        leadSource: { type: 'string', default: '' },
        showFirstName: { type: 'boolean', default: true },
        showLastName: { type: 'boolean', default: true },
        showPhoneNumber: { type: 'boolean', default: true },
        showEmail: { type: 'boolean', default: true },
        showNote: { type: 'boolean', default: true },
        selectedTagsFrom: { type: 'array', default: [] },
        submitButtonText: { type: 'string', default: 'Submit Request' },
    },
    edit({ attributes, setAttributes }) {
        const {
            formTitle,
            leadChannel,
            leadChannelName,
            assigneeAgentEmail,
            useMortgageQuestionLeadSource,
            leadSource,
            showFirstName,
            showLastName,
            showPhoneNumber,
            showEmail,
            showNote,
            selectedTagsFrom,
            submitButtonText,
        } = attributes;

        const [leadChannels, setLeadChannels] = useState();
        const [tags, setTags] = useState([]);
        const [agentOptions, setAgentOptions] = useState([{ label: 'Loading agents…', value: '' }]);
        const [loadingChannels, setLoadingChannels] = useState(true);
        const [loadingTags, setLoadingTags] = useState(true);
        const [loadingAgents, setLoadingAgents] = useState(true);
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
            if (isLoggedIn !== true) {
                return;
            }
            let cancelled = false;

            const loadAgents = async () => {
                setLoadingAgents(true);
                try {
                    const res = await apiFetch({ path: '/rch/v1/leads-form-agents' });
                    const agents = Array.isArray(res?.agents) ? res.agents : [];
                    const opts = [
                        { label: 'Select agent to receive this lead', value: '' },
                        ...agents.map((a) => ({
                            label: a.name && a.email ? `${a.name} (${a.email})` : a.email || a.name || 'Agent',
                            value: a.email || '',
                        })),
                    ];
                    if (!cancelled) {
                        setAgentOptions(opts);
                    }
                } catch (e) {
                    console.error('Error loading agents for lead form:', e);
                    if (!cancelled) {
                        setAgentOptions([
                            { label: 'Could not load agents (check agent posts have email meta)', value: '' },
                        ]);
                    }
                } finally {
                    if (!cancelled) {
                        setLoadingAgents(false);
                    }
                }
            };

            loadAgents();
            return () => {
                cancelled = true;
            };
        }, [isLoggedIn]);

        useEffect(() => {
            if (!isLoggedIn) {
                return;
            }
            let cancelled = false;
            apiFetch({ path: '/rch/v1/leads-form-linked-agent' })
                .then((res) => {
                    if (cancelled || !res?.linked || !res?.email) {
                        return;
                    }
                    if (assigneeAgentEmail !== res.email) {
                        setAttributes({ assigneeAgentEmail: res.email });
                    }
                })
                .catch(() => {});
            return () => {
                cancelled = true;
            };
        }, [isLoggedIn, assigneeAgentEmail, setAttributes]);

        useEffect(() => {
            if (isLoggedIn && brandId && accessToken) {
                const fetchLeadChannels = async () => {
                    try {
                        const channelResponse = await fetch(`${RECHAT_API_BASE_URL}/brands/${brandId}/leads/channels`, {
                            method: 'GET',
                            headers: {
                                Authorization: `Bearer ${accessToken}`,
                            },
                        });
                        const channelData = await channelResponse.json();
                        const options = channelData.data.map((channel) => ({
                            label: channel.name ? channel.name : 'Unnamed',
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
                        const tagsResponse = await fetch(`${RECHAT_API_BASE_URL}/contacts/tags`, {
                            method: 'GET',
                            headers: {
                                Authorization: `Bearer ${accessToken}`,
                                'X-RECHAT-BRAND': brandId,
                            },
                        });
                        const tagsData = await tagsResponse.json();
                        const tagOptions = tagsData.data.map((tag) => ({
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

        const tagsForCheckboxes = useMemo(() => {
            const list = Array.isArray(tags) ? [...tags] : [];
            const seen = new Set(list.map((t) => t.value));
            if (!seen.has(STATIC_MORTGAGE_QUESTIONNAIRE_TAG)) {
                list.push({
                    label: STATIC_MORTGAGE_QUESTIONNAIRE_TAG,
                    value: STATIC_MORTGAGE_QUESTIONNAIRE_TAG,
                });
            }
            return list;
        }, [tags]);

        const handleTagChange = (tagId) => {
            const newSelectedTagsFrom = selectedTagsFrom.includes(tagId)
                ? selectedTagsFrom.filter((id) => id !== tagId)
                : [...selectedTagsFrom, tagId];
            setAttributes({ selectedTagsFrom: newSelectedTagsFrom });
        };

        if (isLoggedIn === false) {
            return <p>Please log in to view and manage the lead channels and tags.</p>;
        }

        if (isLoggedIn === null) {
            return <p>Loading...</p>;
        }

        return (
            <>
                <InspectorControls>
                    <PanelBody title="Lead Form Settings">
                        <TextControl
                            label="Form Title"
                            value={formTitle}
                            onChange={(value) => setAttributes({ formTitle: value })}
                        />
                        <TextControl
                            label="Submit button text"
                            value={submitButtonText}
                            onChange={(value) => setAttributes({ submitButtonText: value })}
                        />
                        <SelectControl
                            label="Lead Channel"
                            value={leadChannel}
                            options={loadingChannels ? [{ label: 'Loading channels...', value: '' }] : leadChannels}
                            onChange={(selectedChannel) => {
                                const list = Array.isArray(leadChannels) ? leadChannels : [];
                                const match = list.find((o) => String(o.value) === String(selectedChannel));
                                const name = match && match.value ? String(match.label || '') : '';
                                setAttributes({ leadChannel: selectedChannel, leadChannelName: name });
                            }}
                        />
                        <SelectControl
                            label="Assignee agent (from Agents CPT)"
                            value={assigneeAgentEmail}
                            options={loadingAgents ? [{ label: 'Loading agents…', value: '' }] : agentOptions}
                            onChange={(value) => setAttributes({ assigneeAgentEmail: value })}
                        />
                        <p style={{ marginTop: '-8px', fontSize: '12px', color: '#757575' }}>
                            Uses each agent post’s email meta. On agent subsites, the linked hub agent email is set automatically.
                        </p>
                        <ToggleControl
                            label='Send lead_source as "Mortgage Question From"'
                            checked={useMortgageQuestionLeadSource}
                            onChange={(value) => setAttributes({ useMortgageQuestionLeadSource: value })}
                        />
                        {!useMortgageQuestionLeadSource && (
                            <TextControl
                                label="Custom lead source"
                                value={leadSource}
                                onChange={(value) => setAttributes({ leadSource: value })}
                                placeholder="e.g. Contact page"
                            />
                        )}
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
                                    tagsForCheckboxes.map((tag) => (
                                        <div key={tag.value} style={{ marginBottom: '8px' }}>
                                            <label>
                                                <input
                                                    type="checkbox"
                                                    value={tag.value}
                                                    checked={selectedTagsFrom.includes(tag.value)}
                                                    onChange={() => handleTagChange(tag.value)}
                                                />
                                                {tag.label}
                                                {tag.value === STATIC_MORTGAGE_QUESTIONNAIRE_TAG ? (
                                                    <span style={{ color: '#757575', fontSize: '11px', marginLeft: '6px' }}>
                                                        (fixed option)
                                                    </span>
                                                ) : null}
                                            </label>
                                        </div>
                                    ))
                                )}
                            </fieldset>
                        </div>
                    </PanelBody>
                </InspectorControls>
                <ServerSideRender block="rch-rechat-plugin/leads-form-block" attributes={attributes} />
            </>
        );
    },
    save() {
        return null;
    },
});
