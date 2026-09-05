const { registerBlockType } = wp.blocks;
const { InspectorControls } = wp.blockEditor || wp.editor;
const {
    PanelBody,
    TextControl,
    RangeControl,
    SelectControl,
    ToggleControl,
} = wp.components;
import ServerSideRender from '@wordpress/server-side-render';

registerBlockType('rch-rechat-plugin/off-market-block', {
    title: 'Off Market Block',
    description: 'Off Market listings grid or swiper (off_market CPT).',
    icon: 'building',
    category: 'widgets',
    attributes: {
        displayType: { type: 'string', default: 'normal' },
        status: { type: 'string', default: '' },
        limit: { type: 'number', default: 6 },
        columns: { type: 'number', default: 3 },
        orderby: { type: 'string', default: 'date' },
        order: { type: 'string', default: 'DESC' },
        title: { type: 'string', default: '' },
        spaceBetween: { type: 'number', default: 24 },
        loop: { type: 'boolean', default: true },
        autoplay: { type: 'boolean', default: false },
        autoplayDelay: { type: 'number', default: 3500 },
        pagination: { type: 'boolean', default: false },
    },
    edit({ attributes, setAttributes }) {
        const {
            displayType,
            status,
            limit,
            columns,
            orderby,
            order,
            title,
            spaceBetween,
            loop,
            autoplay,
            autoplayDelay,
            pagination,
        } = attributes;

        const isSwiper = displayType === 'swiper';

        return (
            <>
                <InspectorControls>
                    <PanelBody title="Off Market Settings">
                        <SelectControl
                            label="Display type"
                            value={displayType}
                            options={[
                                { label: 'Grid (normal)', value: 'normal' },
                                { label: 'Swiper (carousel)', value: 'swiper' },
                            ]}
                            onChange={(value) => setAttributes({ displayType: value })}
                        />
                        <TextControl
                            label="Title (optional heading)"
                            value={title}
                            onChange={(value) => setAttributes({ title: value })}
                        />
                        <TextControl
                            label="Status filter (comma list, e.g. sold, pending)"
                            help="Empty = all. Keywords (active, pending, sold, coming) or full text."
                            value={status}
                            onChange={(value) => setAttributes({ status: value })}
                        />
                        <RangeControl
                            label={
                                !isSwiper && pagination
                                    ? 'Per page'
                                    : 'Limit (-1 = all)'
                            }
                            value={limit}
                            min={!isSwiper && pagination ? 1 : -1}
                            max={48}
                            onChange={(value) =>
                                setAttributes({ limit: value === undefined ? 6 : value })
                            }
                        />
                        <RangeControl
                            label={isSwiper ? 'Slides per view (desktop)' : 'Columns'}
                            value={columns}
                            min={1}
                            max={6}
                            onChange={(value) => setAttributes({ columns: value || 1 })}
                        />
                        <SelectControl
                            label="Order by"
                            value={orderby}
                            options={[
                                { label: 'Date', value: 'date' },
                                { label: 'Price', value: 'price' },
                                { label: 'Title', value: 'title' },
                            ]}
                            onChange={(value) => setAttributes({ orderby: value })}
                        />
                        <SelectControl
                            label="Order"
                            value={order}
                            options={[
                                { label: 'Descending', value: 'DESC' },
                                { label: 'Ascending', value: 'ASC' },
                            ]}
                            onChange={(value) => setAttributes({ order: value })}
                        />
                        {!isSwiper && (
                            <ToggleControl
                                label="Pagination (show all across pages)"
                                help="Off = show only 'Per page' count. On = page links; 'Per page' controls each page."
                                checked={pagination}
                                onChange={(value) => setAttributes({ pagination: !!value })}
                            />
                        )}
                    </PanelBody>

                    {isSwiper && (
                        <PanelBody title="Swiper Settings" initialOpen={false}>
                            <RangeControl
                                label="Space between (px)"
                                value={spaceBetween}
                                min={0}
                                max={80}
                                onChange={(value) =>
                                    setAttributes({ spaceBetween: value === undefined ? 24 : value })
                                }
                            />
                            <ToggleControl
                                label="Loop"
                                checked={loop}
                                onChange={(value) => setAttributes({ loop: !!value })}
                            />
                            <ToggleControl
                                label="Autoplay"
                                checked={autoplay}
                                onChange={(value) => setAttributes({ autoplay: !!value })}
                            />
                            {autoplay && (
                                <RangeControl
                                    label="Autoplay delay (ms)"
                                    value={autoplayDelay}
                                    min={1000}
                                    max={10000}
                                    step={250}
                                    onChange={(value) =>
                                        setAttributes({
                                            autoplayDelay: value === undefined ? 3500 : value,
                                        })
                                    }
                                />
                            )}
                        </PanelBody>
                    )}
                </InspectorControls>
                <ServerSideRender
                    block="rch-rechat-plugin/off-market-block"
                    attributes={attributes}
                />
            </>
        );
    },
    save() {
        return null;
    },
});
