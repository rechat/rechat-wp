const { registerBlockType } = wp.blocks;
const { InspectorControls } = wp.editor;
const { PanelBody, RangeControl } = wp.components;

registerBlockType('rch-plugin/regions-block', {
    title: 'Regions Block',
    category: 'widgets',
    icon: 'list-view',
    attributes: {
        postsPerPage: {
            type: 'number',
            default: 5,
        },
    },
    edit({ attributes, setAttributes }) {
        const { postsToShow, postsPerPage } = attributes;

        return (
            <div>
                <InspectorControls>
                    <PanelBody title="Settings">
                        <RangeControl
                            label="Number of Posts to Show"
                            value={postsToShow}
                            onChange={(value) => setAttributes({ postsToShow: value })}
                            min={1}
                            max={20}
                        />
                        <RangeControl
                            label="Posts Per Page"
                            value={postsPerPage}
                            onChange={(value) => setAttributes({ postsPerPage: value })}
                            min={1}
                            max={20}
                        />
                    </PanelBody>
                </InspectorControls>
                <div>
                    <p>Showing {postsToShow} posts per page: {postsPerPage}</p>
                </div>
            </div>
        );
    },
    save() {
        // Rendering is handled by PHP
        return null;
    },
});
// wp.blocks.registerBlockType('rch-plugin/regions-block', {
//     title: 'Regions Block',
//     category: 'widgets',
//     icon: 'list-view',
//     attributes: {
//         postsPerPage: {
//             type: 'number',
//             default: 5,
//         },
//     },
//     edit: {
//         function(props) {

//         }
//     },
//     save: {
//         function(props) {
//             return null
//         }

//     }
// })
