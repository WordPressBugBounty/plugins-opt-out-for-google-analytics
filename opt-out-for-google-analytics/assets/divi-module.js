// Visual Builder component of the Divi module, shows a preview of the opt-out link.
(function ($) {
    $(window).on('et_builder_api_ready', function (event, API) {
        var React = window.React,
            el = React.createElement,
            data = window.gaoo_divi || {};

        class GaooOptout extends React.Component {
            render() {
                if (!data.is_active) {
                    return el('div', {className: 'gaoo-divi-notice'}, data.notice);
                }

                return el('a', {
                    href: '#',
                    className: 'gaoo-link gaoo-link-deactivate',
                    onClick: function (e) {
                        e.preventDefault();
                    },
                }, this.props.link_deactivate || data.link_deactivate);
            }
        }

        GaooOptout.slug = 'gaoo_optout';

        API.registerModules([GaooOptout]);
    });
})(jQuery);