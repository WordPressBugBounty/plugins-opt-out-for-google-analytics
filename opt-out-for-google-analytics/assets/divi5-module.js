// Visual Builder of Divi 5: registers the module and shows a preview of the opt-out link.
// The metadata (divi5/module.json) and the conversion outline are passed by PHP, see GAOO_Divi5_Module::enqueue_builder_script().
(function (window) {
    var data = window.GaooDivi5ModuleData,
        divi = window.divi,
        hooks = window.vendor && window.vendor.wp && window.vendor.wp.hooks;

    if (!data || !divi || !divi.module || !divi.moduleLibrary || !hooks) {
        return;
    }

    var React = window.React,
        el = React.createElement,
        Module = divi.module,
        preview = data.preview || {};

    function ModuleStyles(props) {
        var attrs = props.attrs || {},
            text = attrs.module && attrs.module.advanced ? attrs.module.advanced.text : undefined;

        return el(Module.StyleContainer, {mode: props.mode, state: props.state, noStyleTag: props.noStyleTag},
            props.elements.style({
                attrName: 'module',
                styleProps: {
                    disabledOn: {
                        disabledModuleVisibility: props.settings ? props.settings.disabledModuleVisibility : undefined,
                    },
                    advancedStyles: [
                        {
                            componentName: 'divi/text',
                            props: {
                                selector: props.orderClass,
                                attr: text,
                            },
                        },
                    ],
                },
            }),
            props.elements.style({
                attrName: 'link',
            }),
            el(Module.CssStyle, {
                selector: props.orderClass,
                attr: attrs.css,
                cssFields: props.elements && props.elements.moduleMetadata ? props.elements.moduleMetadata.customCssFields : undefined,
            })
        );
    }

    function moduleClassnames(params) {
        var attrs = params.attrs || {};

        params.classnamesInstance.add(Module.textOptionsClassnames(attrs.module && attrs.module.advanced ? attrs.module.advanced.text : undefined));
    }

    function ModuleScriptData(props) {
        return props.elements.scriptData({
            attrName: 'module',
        });
    }

    function getText(attrs, name) {
        return attrs && attrs[name] && attrs[name].innerContent && attrs[name].innerContent.desktop ? attrs[name].innerContent.desktop.value : '';
    }

    function Edit(props) {
        var content = preview.isActive
            ? el('a', {
                href: '#',
                className: 'gaoo-link gaoo-link-deactivate',
                onClick: function (event) {
                    event.preventDefault();
                },
            }, getText(props.attrs, 'linkDeactivate') || preview.linkDeactivate)
            : el('div', {className: 'gaoo-divi-notice'}, preview.notice);

        return el(Module.ModuleContainer, {
                attrs: props.attrs,
                elements: props.elements,
                id: props.id,
                name: props.name,
                stylesComponent: ModuleStyles,
                classnamesFunction: moduleClassnames,
                scriptDataComponent: ModuleScriptData,
                isLooped: props.isLooped,
                loopIndex: props.loopIndex,
            },
            props.elements.styleComponents({
                attrName: 'module',
            }),
            content
        );
    }

    // Icon of the module in the module library (lock).
    hooks.addFilter('divi.iconLibrary.icon.map', 'gaoo.optout', function (icons) {
        icons = icons || {};
        icons['gaoo/lock'] = {
            name: 'gaoo/lock',
            viewBox: '0 0 24 24',
            component: function () {
                return el('path', {d: 'M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z'});
            },
        };

        return icons;
    });

    hooks.addAction('divi.moduleLibrary.registerModuleLibraryStore.after', 'gaoo.optout', function () {
        divi.moduleLibrary.registerModule(data.metadata, {
            metadata: data.metadata,
            conversionOutline: data.conversionOutline,
            placeholderContent: {},
            renderers: {
                edit: Edit,
            },
            callbacks: {
                content: {
                    elements: divi.moduleUtils ? divi.moduleUtils.elementsCallbacks : undefined,
                },
            },
        });
    });
})(window);