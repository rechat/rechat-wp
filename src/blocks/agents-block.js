const { registerBlockType } = wp.blocks;
const { InspectorControls, ColorPalette } = wp.blockEditor || wp.editor;
const { PanelBody, RangeControl, SelectControl, FormTokenField } = wp.components;
import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import ServerSideRender from '@wordpress/server-side-render';
import { fetchData } from '../utils/api-helpers';

/** Unique, unambiguous token label for an agent (names can repeat). */
const agentTokenLabel = (agent) => `${agent.name} (#${agent.id})`;

/**
 * Fetch ALL agents across every REST page (WP caps per_page at 100), so brokerages
 * with more than 100 agents still list everyone in the picker.
 */
const fetchAllAgents = async () => {
    const perPage = 100;
    const base = `/wp/v2/agents?per_page=${perPage}&orderby=title&order=asc&_fields=id,title`;
    const mapAgents = (data) => data.map((a) => ({ id: a.id, name: a.title.rendered }));

    // First page (parse:false) exposes the X-WP-TotalPages header.
    const firstResponse = await apiFetch({ path: `${base}&page=1`, parse: false });
    const totalPages = parseInt(firstResponse.headers.get('X-WP-TotalPages') || '1', 10) || 1;
    let all = mapAgents(await firstResponse.json());

    if (totalPages > 1) {
        const pages = [];
        for (let page = 2; page <= totalPages; page++) {
            pages.push(apiFetch({ path: `${base}&page=${page}` }));
        }
        const rest = await Promise.all(pages);
        rest.forEach((data) => {
            all = all.concat(mapAgents(data));
        });
    }

    return all;
};

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
        // Manual agent selection: hide some agents / show only selected ones.
        agentSelectionMode: { type: 'string', default: 'all' }, // 'all' | 'include' | 'exclude'
        selectedAgents: { type: 'array', default: [] },          // agent post IDs (numbers)
    },
    edit({ attributes, setAttributes }) {
        const {
            postsPerPage, regionBgColor, textColor, filterByRegions, filterByOffices,
            sortBy, sortOrder, agentSelectionMode, selectedAgents,
        } = attributes;
        const [regions, setRegions] = useState([]);
        const [offices, setOffices] = useState([]);
        const [agents, setAgents] = useState([]);

        useEffect(() => {
            fetchData('/wp/v2/regions?per_page=100', setRegions);
            fetchData('/wp/v2/offices?per_page=100', setOffices);
            fetchAllAgents()
                .then((data) => setAgents(data))
                .catch((error) => console.error('Error fetching agents:', error));
        }, []);

        // Maps between agent id and its token label (both directions).
        const labelById = {};
        const idByLabel = {};
        agents.forEach((a) => {
            const label = agentTokenLabel(a);
            labelById[a.id] = label;
            idByLabel[label] = a.id;
        });

        const selectedTokens = selectedAgents
            .map((id) => labelById[id])
            .filter(Boolean);

        const onChangeTokens = (tokens) => {
            const ids = tokens
                .map((token) => idByLabel[token])
                .filter((id) => id !== undefined);
            setAttributes({ selectedAgents: ids });
        };

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
                                { label: 'Display order', value: 'display_order' },
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
                    <PanelBody title="Agent Selection" initialOpen={false}>
                        <SelectControl
                            label="Agents to display"
                            value={agentSelectionMode}
                            options={[
                                { label: 'All agents', value: 'all' },
                                { label: 'Only selected agents', value: 'include' },
                                { label: 'All except selected agents', value: 'exclude' },
                            ]}
                            onChange={(mode) => setAttributes({ agentSelectionMode: mode })}
                        />
                        {agentSelectionMode !== 'all' && (
                            <FormTokenField
                                label={agentSelectionMode === 'include' ? 'Agents to show' : 'Agents to hide'}
                                value={selectedTokens}
                                suggestions={agents.map(agentTokenLabel)}
                                onChange={onChangeTokens}
                                maxSuggestions={agents.length || 100}
                                __experimentalExpandOnFocus
                                __experimentalShowHowTo={false}
                            />
                        )}
                        {agentSelectionMode !== 'all' && !agents.length && (
                            <p><em>Loading agents…</em></p>
                        )}
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
