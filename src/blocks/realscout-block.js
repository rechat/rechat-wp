const { registerBlockType } = wp.blocks;
const { InspectorControls, PanelColorSettings, useBlockProps } = wp.blockEditor || wp.editor;
const { PanelBody, TextControl, Placeholder, Notice } = wp.components;

// Defaults mirror the RealScout embed snippet (see block-realscout.php).
const STYLE_DEFAULTS = {
    backgroundColor: '#ffffff',
    titleColor: '#000000',
    subtitleColor: 'rgba(28, 30, 38, 0.5)',
    inputTextColor: 'inherit',
    primaryButtonTextColor: '#ffffff',
    primaryButtonColor: 'rgb(35, 93, 137)',
    secondaryButtonTextColor: 'rgb(35, 93, 137)',
    secondaryButtonColor: '#ffffff',
    widgetWidth: 'auto',
};

const COLOR_LABELS = {
    backgroundColor: 'Background',
    titleColor: 'Title',
    subtitleColor: 'Subtitle',
    primaryButtonColor: 'Primary button',
    primaryButtonTextColor: 'Primary button text',
    secondaryButtonColor: 'Secondary button',
    secondaryButtonTextColor: 'Secondary button text',
};

// Attribute key → RealScout CSS custom property (same mapping as rch_realscout_block_style_map()).
const CSS_VARS = {
    backgroundColor: '--rs-hvw-background-color',
    titleColor: '--rs-hvw-title-color',
    subtitleColor: '--rs-hvw-subtitle-color',
    inputTextColor: '--rs-hvw-input-text-color',
    primaryButtonTextColor: '--rs-hvw-primary-button-text-color',
    primaryButtonColor: '--rs-hvw-primary-button-color',
    secondaryButtonTextColor: '--rs-hvw-secondary-button-text-color',
    secondaryButtonColor: '--rs-hvw-secondary-button-color',
    widgetWidth: '--rs-hvw-widget-width',
};

const attributes = Object.keys(STYLE_DEFAULTS).reduce((acc, key) => {
    acc[key] = { type: 'string', default: STYLE_DEFAULTS[key] };
    return acc;
}, {});

registerBlockType('rch-rechat-plugin/realscout-home-value-block', {
    title: 'RealScout Home Value',
    description: 'RealScout home value widget. The agent ID is filled in automatically on each agent site.',
    icon: 'admin-home',
    category: 'widgets',
    supports: { align: ['wide', 'full'] },
    attributes,
    edit({ attributes, setAttributes }) {
        const blockProps = useBlockProps();
        const config = window.rchRealScoutBlock || {};
        const encodedId = config.previewEncodedId || '';

        const widgetStyle = Object.keys(CSS_VARS).reduce((acc, key) => {
            acc[CSS_VARS[key]] = attributes[key] || STYLE_DEFAULTS[key];
            return acc;
        }, {});
        // Keep clicks on the block (select/move) instead of the widget's inputs.
        widgetStyle.pointerEvents = 'none';
        widgetStyle.display = 'block';

        return (
            <>
                <InspectorControls>
                    <PanelColorSettings
                        title="Widget Colors"
                        initialOpen={false}
                        colorSettings={Object.keys(COLOR_LABELS).map((key) => ({
                            label: COLOR_LABELS[key],
                            value: attributes[key],
                            onChange: (value) =>
                                setAttributes({ [key]: value || STYLE_DEFAULTS[key] }),
                        }))}
                    />
                    <PanelBody title="Layout" initialOpen={false}>
                        <TextControl
                            label="Widget width"
                            help="CSS width, e.g. auto, 100%, 600px."
                            value={attributes.widgetWidth}
                            onChange={(value) => setAttributes({ widgetWidth: value })}
                        />
                    </PanelBody>
                </InspectorControls>
                <div {...blockProps}>
                    {encodedId ? (
                        <realscout-home-value
                            key={encodedId}
                            agent-encoded-id={encodedId}
                            style={widgetStyle}
                        />
                    ) : (
                        <Placeholder
                            icon="admin-home"
                            label="RealScout Home Value"
                            instructions="No RealScout ID is configured for this site, so the widget will not be shown."
                        >
                            {!config.hasMainSiteId && (
                                <Notice status="warning" isDismissible={false}>
                                    Main website RealScout ID is not set.
                                    {config.canManage && config.settingsUrl && (
                                        <>
                                            {' '}
                                            <a href={config.settingsUrl} target="_blank" rel="noopener noreferrer">
                                                Open RealScout settings
                                            </a>
                                        </>
                                    )}
                                </Notice>
                            )}
                        </Placeholder>
                    )}
                </div>
            </>
        );
    },
    save() {
        return null;
    },
});
