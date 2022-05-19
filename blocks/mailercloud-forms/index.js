(function(wp, blocks, element, blockEditor) {
    /**
     * Registers a new block provided a unique name and an object defining its behavior.
     * @see https://wordpress.org/gutenberg/handbook/designers-developers/developers/block-api/#registering-a-block
     */
    var registerBlockType = wp.blocks.registerBlockType;
    /**
     * Returns a new element of given type. Element is an abstraction layer atop React.
     * @see https://wordpress.org/gutenberg/handbook/designers-developers/developers/packages/packages-element/
     */
    var el = wp.element.createElement;
    const { SelectControl } = wp.components;
    const { Component } = wp.element;
    const iconEl = el('svg', { width: 20, height: 20, viewBox: "0 0 20 20" },
        el('path', { d: "M 9.964844 0.351562 C 4.636719 0.351562 0.316406 4.671875 0.316406 10 C 0.316406 15.328125 4.636719 19.648438 9.964844 19.648438 C 15.296875 19.648438 19.617188 15.328125 19.617188 10 C 19.617188 4.671875 15.296875 0.351562 9.964844 0.351562 Z M 13.238281 11.800781 L 3.535156 11.800781 C 3.507812 10.679688 4.386719 9.75 5.507812 9.71875 C 5.976562 9.71875 6.417969 9.898438 6.765625 10.210938 C 7.296875 9 8.496094 8.210938 9.828125 8.199219 C 11.757812 8.25 13.285156 9.859375 13.238281 11.800781 Z M 16.398438 11.800781 L 14.285156 11.800781 C 14.285156 11.761719 14.285156 11.71875 14.285156 11.679688 C 14.316406 11.101562 14.816406 10.648438 15.398438 10.691406 C 15.988281 10.71875 16.425781 11.21875 16.398438 11.800781 Z M 16.398438 11.800781 " })
    );

    //var RichText = blockEditor.RichText;
    //var useBlockProps = blockEditor.useBlockProps;
    /**
     * Retrieves the translation of text.
     * @see https://wordpress.org/gutenberg/handbook/designers-developers/developers/packages/packages-i18n/
     */
    var __ = wp.i18n.__;

    /** custom component to show forms **/
    class SingleFormSelect extends Component {
        /**
         * Constructor
         * @param props
         */

        constructor(props) {
            super(props);

            // Set the initial state of the component.
            this.state = {
                forms: [{
                    label: __("Select a Form", "mailercloud"),
                    value: ''
                }],
                displayTitle: [
                    { label: __("Display Form Name", "mailercloud"), value: true },
                    { label: __("Hide Form Name", "mailercloud"), value: false },
                ],
            };
        }

        /**
         * After the component mounts, retrieve the forms and add them to the local component state.
         */
        async componentDidMount() {
            try {
                const forms = [];
                const results = await wp.apiFetch({
                    path: '/mailcloud/v1/get-signup-forms',
                    method: 'POST',
                    data: { username: 'admin', password: 'dkgklf2dasg$lkf8gldfs@HGvkdfsk#' }
                }).then((results) => {
                    results.webforms.forEach((post) => {
                        forms.push({
                            value: post.name,
                            label: post.name
                        });
                    });
                    this.setState({ forms: [...this.state.forms, ...forms] });
                    return results;
                });

            } catch (e) {
                console.error("ERROR: ", e.message);
            }
        }

        /**
         * Render the Gutenberg block in the admin area.
         */
        render() {
            // Destructure the selectedFrom from props.
            let { selectedForm, displayTitle } = this.props.attributes;
            return el(
                'div', {
                    className: 'mailercloud-block-container'
                },
                el('div', {
                        className: 'mailercloud-block-container-component'
                    },
                    el(SelectControl, {
                        label: __("Choose a Form from list", "mailercloud"),
                        options: this.state.forms,
                        onChange: (value) => this.props.setAttributes({ selectedForm: value }),
                        value: selectedForm,
                    }),
                )
            );
        }
    }

    /**
     * Every block starts by registering a new block type definition.
     * @see https://wordpress.org/gutenberg/handbook/designers-developers/developers/block-api/#registering-a-block
     */
    registerBlockType('mailercloud/mc-forms', {
        /**
         * This is the display title for your block, which can be translated with `i18n` functions.
         * The block inserter will show this name.
         */
        title: __('MC forms', 'mailercloud'),

        /**
         * Blocks are grouped into categories to help users browse and discover them.
         * The categories provided by core are `common`, `embed`, `formatting`, `layout` and `widgets`.
         */
        icon: iconEl,
        category: 'common',
        attributes: {
            selectedForm: {
                type: 'string'
            },
            displayTitle: {
                type: 'boolean',
            }
        },

        /**
         * Optional block extended support features.
         */
        /*
        supports: {
            // Removes support for an HTML mode.
            html: false,
        },
        */

        /**
         * The edit function describes the structure of your block in the context of the editor.
         * This represents what the editor will render when the block is used.
         * @see https://wordpress.org/gutenberg/handbook/designers-developers/developers/block-api/block-edit-save/#edit
         *
         * @param {Object} [props] Properties passed from the editor.
         * @return {Element}       Element to render.
         */
        edit: SingleFormSelect,

        /**
         * The save function defines the way in which the different attributes should be combined
         * into the final markup, which is then serialized by Gutenberg into `post_content`.
         * @see https://wordpress.org/gutenberg/handbook/designers-developers/developers/block-api/block-edit-save/#save
         *
         * @return {Element}       Element to render.
         */
        save: function() {
            return null
        }
    });
})(
    window.wp, window.wp.blocks, window.wp.element, window.wp.blockEditor
);