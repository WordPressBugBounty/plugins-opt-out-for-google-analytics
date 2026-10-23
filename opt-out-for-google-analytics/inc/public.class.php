<?php

    // If this file is called directly, abort.
    defined( 'WPINC' ) || die;

    class GAOO_Public {

        private $csstidy;

        /**
         * GAOO_Public constructor.
         */
        public function __construct() {
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

            $this->csstidy = new csstidy();
            $this->csstidy->load_template( 'highest_compression' );
        }

        /**
         * Enqueue scripts and styles for the public pages.
         */
        public function enqueue_scripts() {
            $min = SCRIPT_DEBUG ? '' : '.min';

            wp_register_script( 'gaoo-public', GAOO_PLUGIN_URL . '/assets/public' . $min . '.js', array(), GAOO_VERSION, true );
        }

        /**
         * Handle the shortcode. Also used to render the Gutenberg block.
         *
         * @param array|string $atts Shortcode attributes: link_deactivate, link_activate, popup_deactivate and popup_activate overwrite the texts from the settings. (Default: empty array)
         *
         * @return string HTML code or empty string on error.
         */
        public function shortcode( $atts = array() ) {
            $atts = shortcode_atts( array(
                'link_deactivate'  => '',
                'link_activate'    => '',
                'popup_deactivate' => '',
                'popup_activate'   => '',
            ), $atts, 'ga_optout' );

            $atts      = array_map( 'trim', $atts );
            $form_data = GAOO_Utils::get_options();
            $ua_code   = GAOO_Utils::get_code( $form_data[ 'ga_plugin' ], $form_data[ 'ua_code' ] );

            // Disable shortcode if status is deactivated or set to manual but empty UA-Code
            if ( $form_data[ 'status' ] == 'off' || empty( $ua_code ) ) {
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

            if ( ! empty( $form_data[ 'custom_css' ] ) && $this->csstidy->parse( $form_data[ 'custom_css' ] ) ) {
                // Strip HTML tags, so the code can't break out of the style element.
                $html .= '<style type="text/css">' . wp_strip_all_tags( $this->csstidy->print->plain() ) . '</style>';
            }

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

            if ( $form_data[ 'status' ] == 'on' && ! empty( $ua_code ) ) {
                echo "<script>var disableStr = 'ga-disable-{$ua_code}'; if (document.cookie.indexOf(disableStr + '=true') > -1) { window[disableStr] = true; }</script>";
            }

            if ( ! empty( $form_data[ 'tracking_code' ] ) ) {
                echo $form_data[ 'tracking_code' ];
            }

            do_action( 'gaoo_after_head_script', $ua_code );
        }

    }