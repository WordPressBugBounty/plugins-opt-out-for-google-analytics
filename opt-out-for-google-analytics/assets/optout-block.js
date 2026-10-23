(function (blocks, element, blockEditor, components, i18n) {
    var el = element.createElement,
        __ = i18n.__,
        data = window.gaoo_block || {},
        AlignmentControl = blockEditor.AlignmentControl || blockEditor.AlignmentToolbar;

    // Text field for an individual text. If empty, the text from the settings (placeholder) is used.
    function textControl(props, attribute, label, placeholder) {
        return el(components.TextControl, {
            label: label,
            help: __('Leave empty to use the text from the settings.', 'opt-out-for-google-analytics'),
            placeholder: placeholder,
            value: props.attributes[attribute],
            onChange: function (value) {
                var attributes = {};

                attributes[attribute] = value;
                props.setAttributes(attributes);
            },
            __nextHasNoMarginBottom: true,
            __next40pxDefaultSize: true,
        });
    }

    blocks.registerBlockType('gaoo/block', {
        edit: function (props) {
            var attributes = props.attributes,
                blockProps = blockEditor.useBlockProps({
                    className: attributes.textAlign ? 'has-text-align-' + attributes.textAlign : undefined,
                }),
                warning = null;

            if (!data.is_active) {
                warning = el(components.Notice, {status: 'warning', isDismissible: false},
                    __('The link is not displayed on the website, because the opt-out function is disabled or no valid Google Analytics 4 measurement ID was found.', 'opt-out-for-google-analytics'),
                    data.settings_url ? ' ' : null,
                    data.settings_url ? el('a', {href: data.settings_url, target: '_blank'}, __('Check the settings', 'opt-out-for-google-analytics')) : null
                );
            }

            return el(element.Fragment, null,
                el(blockEditor.BlockControls, {group: 'block'},
                    el(AlignmentControl, {
                        value: attributes.textAlign,
                        onChange: function (value) {
                            props.setAttributes({textAlign: value});
                        },
                    })
                ),
                el(blockEditor.InspectorControls, null,
                    el(components.PanelBody, {title: __('Link texts', 'opt-out-for-google-analytics')},
                        textControl(props, 'linkDeactivate', __('Text of link for deactivate', 'opt-out-for-google-analytics'), data.link_deactivate),
                        textControl(props, 'linkActivate', __('Text of link for activate', 'opt-out-for-google-analytics'), data.link_activate)
                    ),
                    el(components.PanelBody, {title: __('Popup texts', 'opt-out-for-google-analytics'), initialOpen: false},
                        textControl(props, 'popupDeactivate', __('Popup Text for deactivate', 'opt-out-for-google-analytics'), data.popup_deactivate),
                        textControl(props, 'popupActivate', __('Popup Text for activate', 'opt-out-for-google-analytics'), data.popup_activate)
                    )
                ),
                el('div', blockProps,
                    warning,
                    // Preview of the link, as the visitor sees it, if the tracking is allowed.
                    el('a', {
                        href: '#',
                        onClick: function (event) {
                            event.preventDefault();
                        },
                    }, attributes.linkDeactivate || data.link_deactivate || '[ga_optout]')
                )
            );
        },
        // The block is rendered by PHP (dynamic block).
        save: function () {
            return null;
        },
        deprecated: [
            {
                // Until version 2.4 the block saved the shortcode. These blocks will be migrated automatically.
                supports: {
                    html: false,
                },
                save: function () {
                    return ('[ga_optout]');
                },
            },
        ],
    });
})(window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.i18n);