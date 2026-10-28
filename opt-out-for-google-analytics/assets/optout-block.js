(function (blocks, element, blockEditor, components, i18n) {
    var el = element.createElement,
        __ = i18n.__,
        data = window.gaoo_block || {},
        AlignmentControl = blockEditor.AlignmentControl;

    // Text field for an individual text. If empty, the text from the settings (placeholder) is used.
    // The popup texts can have several lines, so they use a textarea.
    function textControl(props, attribute, label, placeholder, multiline) {
        return el(multiline ? components.TextareaControl : components.TextControl, {
            rows: multiline ? 3 : undefined,
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

    // Text of a shortcode attribute: "\n" is a line break and &quot; a double quote, like on the website.
    function shortcodeText(value) {
        return typeof value === 'string' ? value.replace(/\\n/g, '\n').replace(/&quot;/g, '"') : '';
    }

    // Block attributes from the attributes of the shortcode.
    function shortcodeAttributes(named) {
        named = named || {};

        return {
            linkDeactivate: shortcodeText(named.link_deactivate),
            linkActivate: shortcodeText(named.link_activate),
            popupDeactivate: shortcodeText(named.popup_deactivate),
            popupActivate: shortcodeText(named.popup_activate),
            popupType: shortcodePopupType(named.popup_type),
            forceReload: shortcodeYesNo(named.force_reload),
        };
    }

    // Attribute popup_type of the shortcode: "alert", "notice" or empty (settings).
    function shortcodePopupType(value) {
        value = String(value || '').toLowerCase();

        return value === 'alert' || value === 'notice' ? value : '';
    }

    // Attribute force_reload of the shortcode: "yes", "no" or empty (settings), like GAOO_Public::yes_no().
    function shortcodeYesNo(value) {
        value = String(value || '').toLowerCase();

        if (['yes', '1', 'true', 'on'].indexOf(value) > -1) {
            return 'yes';
        }

        return ['no', '0', 'false', 'off'].indexOf(value) > -1 ? 'no' : '';
    }

    // Select with three states: the setting of the plugin (default), or a value only for this block.
    function selectControl(props, attribute, label, defaultLabel, options) {
        return el(components.SelectControl, {
            label: label,
            value: props.attributes[attribute],
            options: [{value: '', label: defaultLabel}].concat(options),
            onChange: function (value) {
                var attributes = {};

                attributes[attribute] = value;
                props.setAttributes(attributes);
            },
            __nextHasNoMarginBottom: true,
            __next40pxDefaultSize: true,
        });
    }

    // Preset color of WordPress as CSS variable, e.g. for the migration of the colors before version 2.7.
    function presetColor(value) {
        if (typeof value !== 'string' || !value) {
            return '';
        }

        if (value.indexOf('var:preset|color|') === 0) {
            value = value.substring(17);
        } else if (value.charAt(0) === '#' || value.indexOf('(') > -1) {
            return value;
        }

        return 'var(--wp--preset--color--' + value.replace(/([a-z])([A-Z0-9])/g, '$1-$2').toLowerCase() + ')';
    }

    // Color setting of the panel "Link colors".
    function colorSetting(props, attribute, label) {
        return {
            label: label,
            value: props.attributes[attribute],
            onChange: function (value) {
                var attributes = {};

                attributes[attribute] = value || '';
                props.setAttributes(attributes);
            },
        };
    }

    // CSS of the link colors, the same as in inc/block.class.php. With a background, the link gets an inner spacing like a button.
    function linkStyles(selector, attributes) {
        var normal = [],
            hover = [],
            notice = [],
            css = '';

        if (attributes.linkColor) {
            normal.push('color:' + attributes.linkColor);
        }

        if (attributes.linkBackground) {
            normal.push('background-color:' + attributes.linkBackground);
        }

        if (attributes.linkBackground || attributes.linkHoverBackground) {
            normal.push('padding:.25em .6em', 'border-radius:4px', 'text-decoration:none');
        }

        if (attributes.linkHoverColor) {
            hover.push('color:' + attributes.linkHoverColor);
        }

        if (attributes.linkHoverBackground) {
            hover.push('background-color:' + attributes.linkHoverBackground);
        }

        if (attributes.noticeColor) {
            notice.push('color:' + attributes.noticeColor);
        }

        if (attributes.noticeBackground) {
            notice.push('background-color:' + attributes.noticeBackground, 'padding:.5em .75em', 'border-radius:4px');
        }

        if (normal.length) {
            css += selector + ' .gaoo-link{' + normal.join(';') + '}';
        }

        if (hover.length) {
            css += selector + ' .gaoo-link:hover,' + selector + ' .gaoo-link:focus{' + hover.join(';') + '}';
        }

        if (notice.length) {
            css += selector + ' .gaoo-notice{' + notice.join(';') + '}';
        }

        return css;
    }

    blocks.registerBlockType('gaoo/block', {
        edit: function (props) {
            var attributes = props.attributes,
                previewClass = 'gaoo-block-preview-' + props.clientId,
                // The popup text is shown below the link: setting of the block, otherwise the setting of the plugin.
                isNotice = attributes.popupType ? attributes.popupType === 'notice' : !!data.popup_notice,
                yes = __('Yes', 'opt-out-for-google-analytics'),
                no = __('No', 'opt-out-for-google-analytics'),
                browserPopup = __('Browser popup', 'opt-out-for-google-analytics'),
                textBelow = __('Text below the link', 'opt-out-for-google-analytics'),
                styles = linkStyles('.' + previewClass, attributes),
                blockProps = blockEditor.useBlockProps({
                    className: [attributes.textAlign ? 'has-text-align-' + attributes.textAlign : '', previewClass].join(' ').trim(),
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
                // Colors of the link itself, also on hover. The colors of the block (typography and background) apply to the whole block.
                el(blockEditor.InspectorControls, {group: 'styles'},
                    el(blockEditor.PanelColorSettings, {
                        title: __('Link colors', 'opt-out-for-google-analytics'),
                        colorSettings: [
                            colorSetting(props, 'linkColor', __('Text', 'opt-out-for-google-analytics')),
                            colorSetting(props, 'linkHoverColor', __('Text on hover', 'opt-out-for-google-analytics')),
                            colorSetting(props, 'linkBackground', __('Background', 'opt-out-for-google-analytics')),
                            colorSetting(props, 'linkHoverBackground', __('Background on hover', 'opt-out-for-google-analytics')),
                        ],
                    }),
                    isNotice ? el(blockEditor.PanelColorSettings, {
                        title: __('Notice colors', 'opt-out-for-google-analytics'),
                        colorSettings: [
                            colorSetting(props, 'noticeColor', __('Text', 'opt-out-for-google-analytics')),
                            colorSetting(props, 'noticeBackground', __('Background', 'opt-out-for-google-analytics')),
                        ],
                    }) : null
                ),
                el(blockEditor.InspectorControls, null,
                    el(components.PanelBody, {title: __('Link texts', 'opt-out-for-google-analytics')},
                        textControl(props, 'linkDeactivate', __('Text of link for deactivate', 'opt-out-for-google-analytics'), data.link_deactivate),
                        textControl(props, 'linkActivate', __('Text of link for activate', 'opt-out-for-google-analytics'), data.link_activate)
                    ),
                    el(components.PanelBody, {title: __('Popup texts', 'opt-out-for-google-analytics'), initialOpen: false},
                        textControl(props, 'popupDeactivate', __('Popup Text for deactivate', 'opt-out-for-google-analytics'), data.popup_deactivate, true),
                        textControl(props, 'popupActivate', __('Popup Text for activate', 'opt-out-for-google-analytics'), data.popup_activate, true)
                    ),
                    el(components.PanelBody, {title: __('Behavior', 'opt-out-for-google-analytics'), initialOpen: false},
                        el(components.__experimentalVStack || 'div', {spacing: 4},
                            /* translators: %s: value of the settings */
                            selectControl(props, 'popupType', __('Show the popup text as', 'opt-out-for-google-analytics'), i18n.sprintf(__('Like the settings (%s)', 'opt-out-for-google-analytics'), data.popup_notice ? textBelow : browserPopup), [
                                {value: 'alert', label: browserPopup},
                                {value: 'notice', label: textBelow},
                            ]),
                            /* translators: %s: value of the settings */
                            selectControl(props, 'forceReload', __('Reload the page after the click', 'opt-out-for-google-analytics'), i18n.sprintf(__('Like the settings (%s)', 'opt-out-for-google-analytics'), data.force_reload ? yes : no), [
                                {value: 'yes', label: yes},
                                {value: 'no', label: no},
                            ])
                        )
                    )
                ),
                el('div', blockProps,
                    styles ? el('style', null, styles) : null,
                    warning,
                    // Preview of the link, as the visitor sees it, if the tracking is allowed.
                    el('a', {
                        className: 'gaoo-link gaoo-link-deactivate',
                        href: '#',
                        onClick: function (event) {
                            event.preventDefault();
                        },
                    }, attributes.linkDeactivate || data.link_deactivate || '[ga_optout]'),
                    // Preview of the notice, so its colors can be seen.
                    isNotice && (attributes.popupDeactivate || data.popup_deactivate) ? el('span', {
                        className: 'gaoo-notice',
                        style: {display: 'block', whiteSpace: 'pre-line', marginTop: '.5em'},
                    }, attributes.popupDeactivate || data.popup_deactivate) : null
                )
            );
        },
        // A shortcode block with the shortcode [ga_optout ...] or a pasted shortcode can be converted to this block, with the individual texts.
        transforms: {
            from: [
                {
                    type: 'block',
                    blocks: ['core/shortcode'],
                    isMatch: function (attributes) {
                        return /^\s*\[ga_optout(\s[^\]]*)?\]\s*$/.test(attributes.text || '');
                    },
                    transform: function (attributes) {
                        var match = window.wp.shortcode.next('ga_optout', attributes.text);

                        return blocks.createBlock('gaoo/block', shortcodeAttributes(match ? match.shortcode.attrs.named : {}));
                    },
                },
                {
                    type: 'shortcode',
                    tag: 'ga_optout',
                    attributes: {
                        linkDeactivate: {type: 'string', shortcode: function (attrs) { return shortcodeText(attrs.named.link_deactivate); }},
                        linkActivate: {type: 'string', shortcode: function (attrs) { return shortcodeText(attrs.named.link_activate); }},
                        popupDeactivate: {type: 'string', shortcode: function (attrs) { return shortcodeText(attrs.named.popup_deactivate); }},
                        popupActivate: {type: 'string', shortcode: function (attrs) { return shortcodeText(attrs.named.popup_activate); }},
                        popupType: {type: 'string', shortcode: function (attrs) { return shortcodePopupType(attrs.named.popup_type); }},
                        forceReload: {type: 'string', shortcode: function (attrs) { return shortcodeYesNo(attrs.named.force_reload); }},
                    },
                },
            ],
        },
        // The block is rendered by PHP (dynamic block).
        save: function () {
            return null;
        },
        deprecated: [
            {
                // Until version 2.7 the block used the colors of WordPress (text, background, link). They are moved to the link colors of the block.
                attributes: {
                    linkDeactivate: {type: 'string', default: ''},
                    linkActivate: {type: 'string', default: ''},
                    popupDeactivate: {type: 'string', default: ''},
                    popupActivate: {type: 'string', default: ''},
                    textAlign: {type: 'string'},
                    linkColor: {type: 'string', default: ''},
                    linkHoverColor: {type: 'string', default: ''},
                    linkBackground: {type: 'string', default: ''},
                    linkHoverBackground: {type: 'string', default: ''},
                    textColor: {type: 'string'},
                    backgroundColor: {type: 'string'},
                    style: {type: 'object'},
                },
                supports: {
                    html: false,
                    color: {text: true, background: true, link: true},
                    typography: {fontSize: true, lineHeight: true},
                    spacing: {margin: true, padding: true},
                },
                isEligible: function (attributes) {
                    return !!(attributes.textColor || attributes.backgroundColor || (attributes.style && (attributes.style.color || attributes.style.elements)));
                },
                migrate: function (attributes) {
                    var style = attributes.style || {},
                        color = style.color || {},
                        link = style.elements && style.elements.link ? style.elements.link : {},
                        migrated = Object.assign({}, attributes),
                        newStyle = Object.assign({}, style);

                    // The color of the link was shown, even if the block had a text color.
                    migrated.linkColor = attributes.linkColor || presetColor(link.color && link.color.text) || presetColor(attributes.textColor) || presetColor(color.text);
                    migrated.linkHoverColor = attributes.linkHoverColor || presetColor(link[':hover'] && link[':hover'].color && link[':hover'].color.text);
                    migrated.linkBackground = attributes.linkBackground || presetColor(attributes.backgroundColor) || presetColor(color.background);

                    delete migrated.textColor;
                    delete migrated.backgroundColor;
                    delete newStyle.color;
                    delete newStyle.elements;
                    migrated.style = Object.keys(newStyle).length ? newStyle : undefined;

                    return migrated;
                },
                save: function () {
                    return null;
                },
            },
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