<?php

    // If this file is called directly, abort.
    defined( 'WPINC' ) || die;

    class GAOO_Public {

        /**
         * @var string|null $custom_css Minified custom CSS, created on the first use.
         */
        private $custom_css = null;

        /**
         * @var GAOO_Public|null $instance Instance of this class, used by the Elementor widget.
         */
        private static $instance = null;

        /**
         * GAOO_Public constructor.
         */
        public function __construct() {
            self::$instance = $this;

            add_action( 'wp_head', array(
                $this,
                'head_script'
            ), -1 );

            add_action( 'wp_enqueue_scripts', array(
                $this,
                'enqueue_scripts'
            ) );

            add_shortcode( 'ga_optout', array(
                $this,
                'shortcode'
            ) );

            if ( has_filter( 'widget_text', 'do_shortcode' ) !== false ) {
                add_filter( 'widget_text', 'do_shortcode' );
            }

        }

        /**
         * Returns the minified custom CSS. It is created once per request, because the link can be used several times on one page.
         *
         * @param string $css Custom CSS from the settings.
         *
         * @return string Minified CSS without HTML tags.
         */
        private function get_custom_css( $css ) {
            if ( null === $this->custom_css ) {
                require_once GAOO_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'css.class.php';

                // Strip HTML tags, so the code can't break out of the style element.
                $this->custom_css = wp_strip_all_tags( GAOO_CSS::minify( $css ) );
            }

            return $this->custom_css;
        }

        /**
         * Returns the instance of this class.
         *
         * @return GAOO_Public|null Instance, or null if not created yet.
         */
        public static function get_instance() {
            return self::$instance;
        }

        /**
         * Enqueue scripts and styles for the public pages.
         */
        public function enqueue_scripts() {
            wp_register_script( 'gaoo-public', GAOO_PLUGIN_URL . '/assets/public' . GAOO_Utils::min_suffix() . '.js', array(), GAOO_VERSION, true );
        }

        /**
         * Value "yes", "no" or empty (use the settings), also from 1/0, true/false and on/off.
         *
         * @param string $value Value of the attribute.
         *
         * @return string "yes", "no" or empty string.
         */
        public static function yes_no( $value ) {
            $value = strtolower( trim( (string) $value ) );

            if ( in_array( $value, array( 'yes', '1', 'true', 'on' ), true ) ) {
                return 'yes';
            }

            return in_array( $value, array( 'no', '0', 'false', 'off' ), true ) ? 'no' : '';
        }

        /**
         * Handle the shortcode. Also used to render the Gutenberg block.
         *
         * @param array|string $atts Shortcode attributes: link_deactivate, link_activate, popup_deactivate and popup_activate overwrite the texts from the settings,
         *                           popup_type (alert, notice) and force_reload (yes, no) the behavior. Empty values use the settings. (Default: empty array)
         *
         * @return string HTML code or empty string on error.
         */
        public function shortcode( $atts = array() ) {
            $atts = shortcode_atts( array(
                'link_deactivate'  => '',
                'link_activate'    => '',
                'popup_deactivate' => '',
                'popup_activate'   => '',
                'popup_type'       => '',
                'force_reload'     => '',
            ), $atts, 'ga_optout' );

            $atts      = array_map( 'trim', $atts );

            // The popup texts can have several lines. If the shortcode is in the content (e.g. Divi 4), WordPress adds a <br /> to the line breaks (wpautop), which would be shown in the popup.
            foreach ( array( 'popup_deactivate', 'popup_activate' ) as $key ) {
                $atts[ $key ] = preg_replace( '/<br\s*\/?>(\r?\n)?/i', "\n", $atts[ $key ] );
            }

            // Individual texts of the shortcode, the block, the Elementor widget and the Divi module, e.g. for an own multilingual setup. Empty texts use the settings.
            $atts = array_map( 'strval', shortcode_atts( array_fill_keys( array( 'link_deactivate', 'link_activate', 'popup_deactivate', 'popup_activate', 'popup_type', 'force_reload' ), '' ), (array) apply_filters( 'gaoo_shortcode_atts', $atts ) ) );

            // Display of the popup text (browser popup or notice below the link) and reload after the click. Empty values use the settings.
            $atts[ 'popup_type' ]   = in_array( strtolower( $atts[ 'popup_type' ] ), array( 'alert', 'notice' ), true ) ? strtolower( $atts[ 'popup_type' ] ) : '';
            $atts[ 'force_reload' ] = self::yes_no( $atts[ 'force_reload' ] );
            $form_data = GAOO_Utils::get_options();
            $ua_code   = GAOO_Utils::get_code( $form_data[ 'ga_plugin' ], $form_data[ 'ua_code' ] );

            // Disable shortcode if status is deactivated or set to manual but empty UA-Code
            if ( ! GAOO_Utils::is_opt_out_enabled( $form_data ) || empty( $ua_code ) ) {
                return '';
            }

            $current_status = isset( $_COOKIE[ 'ga-disable-' . $ua_code ] ) && $_COOKIE[ 'ga-disable-' . $ua_code ] == true ? 'activate' : 'deactivate';
            $json_data      = GAOO_Utils::get_json( $form_data, $ua_code );

            if ( empty( $json_data ) ) {
                return '';
            }

            // Block themes render the content before the hook 'wp_enqueue_scripts', so the script may not be registered yet.
            if ( ! wp_script_is( 'gaoo-public', 'registered' ) ) {
                $this->enqueue_scripts();
            }

            wp_localize_script( 'gaoo-public', 'gaoo_data', $json_data );
            wp_enqueue_script( 'gaoo-public' );

            do_action( 'gaoo_before_shortcode', $ua_code, $current_status );

            // Individual texts are passed to the JavaScript by data attributes, because there can be several links on one page.
            $data_attributes = '';

            if ( '' !== $atts[ 'popup_type' ] ) {
                $data_attributes .= ' data-gaoo-popup-type="' . esc_attr( $atts[ 'popup_type' ] ) . '"';
            }

            if ( '' !== $atts[ 'force_reload' ] ) {
                $data_attributes .= ' data-gaoo-reload="' . esc_attr( $atts[ 'force_reload' ] ) . '"';
            }

            foreach ( array( 'deactivate', 'activate' ) as $status ) {
                if ( ! empty( $atts[ 'link_' . $status ] ) ) {
                    $data_attributes .= ' data-gaoo-' . $status . '="' . esc_attr( $atts[ 'link_' . $status ] ) . '"';
                }

                if ( ! empty( $atts[ 'popup_' . $status ] ) ) {
                    $data_attributes .= ' data-gaoo-popup-' . $status . '="' . esc_attr( $atts[ 'popup_' . $status ] ) . '"';
                }
            }

            $link_text = empty( $atts[ 'link_' . $current_status ] ) ? $json_data[ 'link_' . $current_status ] : $atts[ 'link_' . $current_status ];
            $html      = '<a href="#" role="button" id="gaoo-link" class="gaoo-link gaoo-link-' . esc_attr( $current_status ) . '"' . $data_attributes . '>' . esc_html( $link_text ) . '</a>';

            // Basic style of the notice below the link, it can be changed with the custom CSS.
            $is_notice = '' === $atts[ 'popup_type' ] ? 'on' === $form_data[ 'popup_notice' ] : 'notice' === $atts[ 'popup_type' ];
            $styles    = $is_notice ? '.gaoo-notice{display:block;white-space:pre-line;margin-top:.5em}' : '';

            if ( 'on' === $form_data[ 'custom_css_status' ] && ! empty( $form_data[ 'custom_css' ] ) ) {
                $styles .= $this->get_custom_css( $form_data[ 'custom_css' ] );
            }

            if ( '' !== $styles ) {
                $html .= '<style type="text/css">' . $styles . '</style>';
            }

            // HTML code of the link (and the custom CSS), e.g. to add a wrapper. The class "gaoo-link" is needed by the JavaScript.
            $html = (string) apply_filters( 'gaoo_shortcode_html', $html, $ua_code, $current_status, $atts );

            do_action( 'gaoo_after_shortcode', $ua_code, $current_status );

            return $html;
        }

        /**
         * Adds the GA Opt-Out code to the header.
         */
        public function head_script() {
            $form_data = GAOO_Utils::get_options();
            $ua_code   = GAOO_Utils::get_code( $form_data[ 'ga_plugin' ], $form_data[ 'ua_code' ] );

            do_action( 'gaoo_before_head_script', $ua_code );

            if ( GAOO_Utils::is_opt_out_enabled( $form_data ) && ! empty( $ua_code ) ) {
                // Before Google Analytics: if the cookie was deleted (e.g. by the browser after 7 days), it is restored from the local storage.
                // Google Consent Mode (if enabled): deny "analytics_storage" for visitors with opt-out. It is sent again after loading, so it comes after the default of a cookie banner.
                // The global "disableStr" is kept for compatibility with own code, everything else is local.
                $consent = empty( $form_data[ 'consent_mode' ] ) ? '' : " window.dataLayer = window.dataLayer || []; var consent = function () { window.dataLayer.push(arguments); }; consent('consent', 'update', {analytics_storage: 'denied'}); window.addEventListener('load', function () { consent('consent', 'update', {analytics_storage: 'denied'}); });";

                echo "<script>var disableStr = 'ga-disable-" . esc_js( $ua_code ) . "'; (function () { var expires = '=true; expires=Thu, 31 Dec 2099 23:59:59 UTC; path=/'; try { if (document.cookie.indexOf(disableStr + '=true') === -1 && window.localStorage && localStorage.getItem(disableStr) === 'true') { document.cookie = disableStr + expires; document.cookie = 'ga-opt-out' + expires; } } catch (e) {} if (document.cookie.indexOf(disableStr + '=true') > -1) { window[disableStr] = true;" . $consent . " } })();</script>"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static code, the code is escaped.
            }

            if ( ! empty( $form_data[ 'tracking_code' ] ) ) {
                // Only users with the capability "unfiltered_html" can save the tracking code.
                echo $form_data[ 'tracking_code' ]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }

            do_action( 'gaoo_after_head_script', $ua_code );
        }

    }