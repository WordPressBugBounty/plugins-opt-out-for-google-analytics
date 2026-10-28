// Visual Builder component of the Divi module, shows a preview of the opt-out link.
(function ($) {
    $(window).on('et_builder_api_ready', function (event, API) {
        var React = window.React,
            el = React.createElement,
            data = window.gaoo_divi || {};

        class GaooOptout extends React.Component {
            // Background of the notice, like in GAOO_Divi_Module::render().
            static css(props) {
                if (!props.notice_background) {
                    return [];
                }

                return [[{
                    selector: '%%order_class%% .gaoo-notice',
                    declaration: 'background-color: ' + props.notice_background + '; padding: .5em .75em; border-radius: 4px;',
                }]];
            }

            render() {
                var props = this.props,
                    isNotice = props.popup_type ? props.popup_type === 'notice' : !!data.popup_notice,
                    noticeText = props.popup_deactivate || data.popup_deactivate;

                if (!data.is_active) {
                    return el('div', {className: 'gaoo-divi-notice'}, data.notice);
                }

                return el(React.Fragment, null,
                    el('a', {
                        href: '#',
                        className: 'gaoo-link gaoo-link-deactivate',
                        onClick: function (e) {
                            e.preventDefault();
                        },
                    }, props.link_deactivate || data.link_deactivate),
                    // Preview of the notice, so it can be styled.
                    isNotice && noticeText ? el('span', {
                        className: 'gaoo-notice',
                        style: {display: 'block', whiteSpace: 'pre-line', marginTop: '.5em'},
                    }, noticeText) : null
                );
            }
        }

        GaooOptout.slug = 'gaoo_optout';

        API.registerModules([GaooOptout]);
    });
})(jQuery);