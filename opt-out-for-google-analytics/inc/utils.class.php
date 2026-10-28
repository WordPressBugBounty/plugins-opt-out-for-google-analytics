<?php
    // If this file is called directly, abort.
    defined( 'WPINC' ) || die;

    class GAOO_Utils {

        /**
         * Returns the values from a multiple columns of the array, identified by the $keys.
         *
         * @param array $array A multi-dimensional array or an array of objects from which to pull a column of values from.
         * @param array $keys  The keys of the columns
         *
         * @return array Returns an array of values representing a columns from the input array.
         */
        public static function get_array_columns( $array, $keys ) {
            $keys = array_flip( $keys );

            return array_map(
                function ( $a ) use ( $keys ) {
                    return array_intersect_key( $a, $keys );
                },
                $array
            );
        }

        /**
         * Return the JSON string with form data for the JavaScript code.
         *
         * @param array|null  $form_data Current settings array (Default: null)
         * @param string|null $ua_code   UA-Code (Default: null)
         *
         * @return array|null
         */
        public static function get_json( $form_data = null, $ua_code = null ) {
            if ( empty( $form_data ) ) {
                $form_data = self::get_options();
            }

            if ( empty( $ua_code ) && ! empty( $form_data ) ) {
                $ua_code = GAOO_Utils::get_code( $form_data[ 'ga_plugin' ], $form_data[ 'ua_code' ] );
            }

            if ( empty( $ua_code ) ) {
                return null;
            }

            $json_data = array(
                'link_deactivate'        => apply_filters( 'gaoo_link_deactivate_text', $form_data[ 'link_deactivate' ] ),
                'link_activate'          => apply_filters( 'gaoo_link_activate_text', $form_data[ 'link_activate' ] ),
                'force_reload'           => apply_filters( 'gaoo_force_reload', boolval( $form_data[ 'force_reload' ] ) ),
                // Show the popup texts as a notice below the link instead of a browser popup.
                'popup_notice'           => 'on' === $form_data[ 'popup_notice' ],
                // Also deny the consent "analytics_storage" of the Google Consent Mode on the opt-out.
                'consent_mode'           => ! empty( $form_data[ 'consent_mode' ] ),
                'disable_string'         => 'ga-disable-' . $ua_code,
                'generic_disable_string' => 'ga-opt-out',
            );

            if ( ! empty( $form_data[ 'popup_activate' ] ) ) {
                $json_data[ 'popup_activate' ] = apply_filters( 'gaoo_popup_activate_text', $form_data[ 'popup_activate' ] );
            }

            if ( ! empty( $form_data[ 'popup_deactivate' ] ) ) {
                $json_data[ 'popup_deactivate' ] = apply_filters( 'gaoo_popup_deactivate_text', $form_data[ 'popup_deactivate' ] );
            }

            return $json_data;
        }

        /**
         * Returns all options from this plugin
         *
         * @return array with fields (fieldname => fieldvalue)
         */
        public static function get_options() {
            // Set defaults
            $data = self::get_options_list();

            foreach ( $data as $k => &$v ) {
                $option = get_option( GAOO_PREFIX . $k, $v );

                if ( is_string( $option ) ) {
                    $option = stripslashes( $option );
                }

                $data[ $k ] = $option;
            }

            // Prefer WordPress Privacy page, is sync is enabled.
            if ( ! empty( $data[ 'wp_privacy_page' ] ) ) {
                $data[ 'privacy_page_id' ] = get_option( 'wp_page_for_privacy_policy', $data[ 'privacy_page_id' ] );
            }

            return $data;
        }

        /**
         * List of all options with the default value.
         *
         * @param bool $only_names Show only the names (keys) of the options, no defaults.
         *
         * @return array
         */
        public static function get_options_list( $only_names = false ) {
            $options = array(
                'ga_plugin'           => 'manual',
                'link_deactivate'     => esc_html__( 'Disallow Google Analytics to track me', 'opt-out-for-google-analytics' ),
                'link_activate'       => esc_html__( 'Allow Google Analytics to track me', 'opt-out-for-google-analytics' ),
                'ua_code'             => null,
                'popup_deactivate'    => esc_html__( 'Tracking is now disabled. Click the link again to enable it.', 'opt-out-for-google-analytics' ),
                'popup_activate'      => esc_html__( 'Tracking is now enabled. Click the link again to disable it.', 'opt-out-for-google-analytics' ),
                'popup_notice'        => 'off',
                'status'              => 'off',
                'privacy_page_id'     => 0,
                'disable_monitoring'  => 0,
                'force_reload'        => 0,
                'wp_privacy_page'     => 0,
                'custom_css'          => '',
                'custom_css_status'   => 'off',
                'tracking_code'       => '',
                'status_intervall'    => 'weekly',
                'status_mails'        => '',
                'status_mails_sync'   => 0,
                'uninstall_keep_data' => 0,
                'disable_dashboard'   => 0,
                'check_auth_user'     => '',
                'check_auth_pass'     => '',
                'consent_mode'        => 0,
                'webhook_url'         => '',
                'webhook_format'      => 'json',
                'webhook_always'      => 0,
                'webhook_status'      => 0,
            );

            if ( ! empty( $only_names ) ) {
                return array_keys( $options );
            }

            return $options;
        }

        /**
         * Returns the G-Code (Google Analytics 4)
         *
         * @param string $ga_plugin Key (monsterinsights, gadash, analytify, gaga, sitekit, manual) of choosen GA Plugin
         * @param string $ua_code   The G-Code if GA Plugin is set to manual (Default: null)
         *
         * @return string The G-Code (G-XXXXXXXXXX)
         */
        public static function get_code( $ga_plugin, $ua_code = null ) {

            switch ( $ga_plugin ) {
                case 'monsterinsights':
                    $ua_code = null;

                    if ( function_exists( 'monsterinsights_get_v4_id_to_output' ) ) {
                        $ua_code = monsterinsights_get_v4_id_to_output();
                    }

                    break;

                case 'gadash':
                    $ua_code = null;

                    if ( function_exists( 'exactmetrics_get_v4_id' ) ) {
                        $ua_code = exactmetrics_get_v4_id();
                    }

                    break;

                case 'analytify':

                    if ( class_exists( 'WP_ANALYTIFY_FUNCTIONS' ) ) {
                        $ua_code = WP_ANALYTIFY_FUNCTIONS::get_UA_code();
                    }

                    if ( empty( $ua_code ) && class_exists( 'WP_Analytify_Settings' ) ) {
                        $analytify_settings = new WP_Analytify_Settings();
                        $ua_code            = $analytify_settings->get_option( 'manual_ua_code', 'wp-analytify-authentication', false );
                    }

                    break;

                case 'gaga':

                    if ( ! empty( $GLOBALS[ 'GA_Google_Analytics' ] ) ) {
                        global $GA_Google_Analytics;

                        $options = get_option( 'gap_options', $GA_Google_Analytics->default_options() );
                        $ua_code = ( ! empty( $options[ 'gap_id' ] ) ) ? $options[ 'gap_id' ] : null;
                    }

                    break;

                case 'sitekit':

                    $analytics = get_option( 'googlesitekit_analytics-4_settings' );

                    if ( ! empty( $analytics ) && ! empty( $analytics[ 'measurementID' ] ) ) {
                        $ua_code = $analytics[ 'measurementID' ];
                    }

                    break;

                case 'manual':

                    $ua_code = self::get_option( 'ua_code' );

                    break;

                default:
                    $ua_code = null;
            }

            return self::validate_ua_code( apply_filters( 'gaoo_get_ua_code', $ua_code, $ga_plugin ) );
        }

        /**
         * Return value of a option.
         *
         * @param string $name    Name of the option.
         * @param mixed  $default Value to be returned, if option wasn't found. (Default: null)
         *
         * @return mixed Value of the option if found, otherwise the default value.
         */
        public static function get_option( $name, $default = null ) {
            $names = self::get_options_list( true );

            if ( empty( $name ) || ! in_array( $name, $names, true ) ) {
                return $default;
            }

            $option = get_option( GAOO_PREFIX . $name, $default );

            // Prefer WordPress Privacy page, is sync is enabled.
            if ( $name == 'privacy_page_id' && ! empty( self::get_option( 'wp_privacy_page' ) ) ) {
                $option = get_option( 'wp_page_for_privacy_policy', $option );
            }

            if ( is_string( $option ) ) {
                return stripslashes( $option );
            }

            return $option;
        }

        /**
         * Validate the G-Code. Universal Analytics (UA-) codes are no longer accepted, since Google shut down UA.
         *
         * @param string $ua_code G-Code to check validity.
         *
         * @return string Cleaned G-Code if valid, otherwise an empty string.
         */
        public static function validate_ua_code( $ua_code = '' ) {
            $ua_code = (string) $ua_code;
            $ua_code = preg_replace( '/\s+/', '', $ua_code );

            if ( empty( $ua_code ) ) {
                return '';
            }

            // Replace all type of dashes (n-dash, m-dash, minus) with normal dashes.
            $ua_code = str_replace( array( '–', '—', '−' ), '-', $ua_code );

            if ( self::is_ga4_code( $ua_code ) ) {
                return $ua_code;
            }

            return '';
        }

        /**
         * Check if the code is a Google Analytics 4 code.
         *
         * @param string $code Code to check.
         *
         * @return bool True if it is GA4, otherwise false.
         */
        public static function is_ga4_code( $code ) {
            return ( empty( $code ) ? false : boolval( preg_match( "/^G-[A-Za-z\d]+$/", $code ) ) );
        }

        /**
         * Stopping the cronjob.
         */
        public static function stop_cronjob() {
            wp_clear_scheduled_hook( GAOO_CRONJOB );
            wp_clear_scheduled_hook( 'gaoo_run_check_now' );
        }

        /**
         * Checking the status of the current website.
         *
         * @param array $data       Data with the plugin options.
         * @param bool  $get_labels Return array with labels. (Default: false)
         *
         * @return array Labeld array or an array with sum of the states.
         */
        public static function check_todos( $data, $get_labels = false ) {
            // Check if shortcode is set on page
            $privacy_page_accessible = null;
            $shortcode_url            = null;
            $shortcode_available      = null;

            if ( ! empty( $data[ 'privacy_page_id' ] ) && ( $page = get_post( $data[ 'privacy_page_id' ] ) ) ) {
                $page_content        = sanitize_post_field( 'post_content', $page->post_content, $page->ID, 'raw' );
                $shortcode_available = ( ! empty( $page_content ) && ( false !== strpos( $page_content, GAOO_SHORTCODE ) || has_block( 'gaoo/block', $page_content ) || has_block( 'gaoo/optout', $page_content ) ) );
                $shortcode_url       = admin_url( 'post.php?action=edit&post=' . $data[ 'privacy_page_id' ] );

                $privacy_page_accessible = ( sanitize_post_field( 'post_status', $page->post_status, $page->ID, 'raw' ) == 'publish' && empty( $page->post_password ) );

                // Divi: the module is stored as its own shortcode in the content.
                if ( ! $shortcode_available && ! empty( $page_content ) && preg_match( '/\[gaoo_optout[\s\]\/]/', $page_content ) ) {
                    $shortcode_available = true;
                }

                // Elementor: the content is stored in its own meta field, if the page is built with Elementor.
                if ( ! $shortcode_available && 'builder' === get_post_meta( $page->ID, '_elementor_edit_mode', true ) ) {
                    $elementor_data      = (string) get_post_meta( $page->ID, '_elementor_data', true );
                    $shortcode_available = ( false !== strpos( $elementor_data, GAOO_SHORTCODE ) || false !== strpos( $elementor_data, '"widgetType":"gaoo-optout"' ) );
                }

                // ACF and Secure Custom Fields (fork of ACF, same class and functions) support
                if ( class_exists( 'ACF' ) && ! $shortcode_available ) {
                    $fields = get_fields( $page->ID );
                    $fields = is_array( $fields ) ? $fields : array();

                    foreach ( $fields as $name => $value ) {
                        if ( ! empty( $value ) && is_string( $value ) && false !== strpos( $value, GAOO_SHORTCODE ) ) {
                            $shortcode_available = true;
                            break;
                        }
                    }
                }

                // The link was found on the page by the check of the website, e.g. added with another page builder or the theme.
                $frontend = get_option( GAOO_PREFIX . 'frontend_check', array() );

                if ( ! $shortcode_available && isset( $frontend[ 'page' ], $frontend[ 'page_id' ] ) && 'found' === $frontend[ 'page' ] && (int) $frontend[ 'page_id' ] === (int) $page->ID ) {
                    $shortcode_available = true;
                }

                // Other page builders (e.g. Bricks, Oxygen, Beaver Builder) store the content in their own format, so they can report the link.
                $shortcode_available = (bool) apply_filters( 'gaoo_shortcode_found', (bool) $shortcode_available, $page->ID, $page );
            }

            // Set the url to the settings page of the GA plugin. IP anonymization is no longer checked, because GA4 does not log or store IP addresses.
            switch ( $data[ 'ga_plugin' ] ) {
                case 'monsterinsights':
                    $uacode_url = admin_url( 'admin.php?page=monsterinsights_settings' );
                    break;

                case 'gadash':
                    $uacode_url = admin_url( 'admin.php?page=exactmetrics_settings#/' );
                    break;

                case 'analytify':
                    $uacode_url = admin_url( 'admin.php?page=analytify-settings#wp-analytify-authentication' );
                    break;

                case 'gaga':
                    $uacode_url = admin_url( 'options-general.php?page=ga-google-analytics#gap-panel-settings' );
                    break;

                case 'sitekit':
                    $uacode_url = admin_url( 'admin.php?page=googlesitekit-settings' );
                    break;

                default: // manual
                    $uacode_url = null;
            }

            $ua_code     = GAOO_Utils::get_code( $data[ 'ga_plugin' ] );
            $ua_code_txt = empty( $ua_code ) ? '' : " ($ua_code)";
            $checklist   = array(
                array(
                    'id'      => 'opt_out_enabled',
                    'label'   => __( 'Opt-Out Enabled', 'opt-out-for-google-analytics' ),
                    'checked' => self::is_opt_out_enabled( $data ),
                ),
                array(
                    'id'      => 'measurement_id',
                    'label'   => __( 'Found valid code', 'opt-out-for-google-analytics' ) . $ua_code_txt,
                    // Label without the code and the code itself, for the dashboard widget.
                    'text'    => __( 'Found valid code', 'opt-out-for-google-analytics' ),
                    'value'   => $ua_code,
                    'checked' => ( ! empty( $ua_code ) ),
                    'url'     => $uacode_url,
                ),
                array(
                    'id'      => 'shortcode',
                    'label'   => __( 'Found shortcode on page', 'opt-out-for-google-analytics' ),
                    'checked' => $shortcode_available,
                    'url'     => $shortcode_url,
                ),
                array(
                    'id'      => 'page_accessible',
                    'label'   => __( 'Page accessible', 'opt-out-for-google-analytics' ),
                    'checked' => $privacy_page_accessible,
                    'url'     => $shortcode_url,
                ),
                array(
                    'id'      => 'cronjob',
                    'label'   => __( 'Cronjob for this plugin activated', 'opt-out-for-google-analytics' ),
                    'checked' => ! empty( wp_next_scheduled( GAOO_CRONJOB ) ),
                ),
            );

            // Check of the website itself, the result is stored by the status check (cronjob or "Check now").
            if ( self::is_opt_out_enabled( $data ) && ! empty( $ua_code ) ) {
                $checklist = array_merge( $checklist, self::get_frontend_checks( $ua_code ) );

                // The link on the privacy policy page belongs to the check of the shortcode.
                $link_check = array_search( 'privacy_page_link', array_column( $checklist, 'id' ), true );

                if ( false !== $link_check ) {
                    $link = array_splice( $checklist, $link_check, 1 );
                    array_splice( $checklist, array_search( 'shortcode', array_column( $checklist, 'id' ), true ) + 1, 0, $link );
                }
            }

            // Additional checks, e.g. of other plugins. They are shown in the dashboard widget, on the settings page and in the status email.
            $checklist = apply_filters( 'gaoo_status_checks', $checklist, $data );
            $checklist = array_values( array_filter( is_array( $checklist ) ? $checklist : array(), function ( $check ) {
                return is_array( $check ) && isset( $check[ 'label' ] ) && is_string( $check[ 'label' ] ) && '' !== $check[ 'label' ];
            } ) );

            $custom = 0;

            foreach ( $checklist as $key => $check ) {
                // Only true (passed) and false (failed) are a result, everything else means "unknown".
                $checklist[ $key ][ 'checked' ] = isset( $check[ 'checked' ] ) && is_bool( $check[ 'checked' ] ) ? $check[ 'checked' ] : null;
                // Every check has an ID, which does not depend on the language, e.g. for the status URL. Own checks without ID get one.
                $checklist[ $key ][ 'id' ] = isset( $check[ 'id' ] ) && '' !== sanitize_key( $check[ 'id' ] ) ? sanitize_key( $check[ 'id' ] ) : 'custom_' . ( ++$custom );
            }

            // The ID is unique: with several checks of the same ID, the last one wins and is shown at the place of the first one. So a filter can also replace a check.
            $unique = array();

            foreach ( $checklist as $check ) {
                $unique[ $check[ 'id' ] ] = $check;
            }

            $checklist = array_values( $unique );

            // Remove cached value if status of todos has been updated.
            self::delete_todo_cache();

            // Return labeled array
            if ( ! empty( $get_labels ) ) {
                return $checklist;
            }

            $dontknow = $check = $todo = 0;

            foreach ( $checklist as $item ) {
                // An information is not an error, it is counted like an unknown result.
                if ( ! empty( $item[ 'info' ] ) ) {
                    $dontknow++;
                }
                elseif ( false === $item[ 'checked' ] ) {
                    $todo++;
                }
                elseif ( true === $item[ 'checked' ] ) {
                    $check++;
                }
                else {
                    $dontknow++;
                }
            }

            return array(
                'sum'      => count( $checklist ),
                'dontknow' => $dontknow,
                'check'    => $check,
                'todo'     => $todo,
            );
        }

        /**
         * Suffix of the minified assets. With SCRIPT_DEBUG the readable files are loaded, like WordPress does.
         *
         * @return string ".min" or empty string.
         */
        public static function min_suffix() {
            return ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
        }

        /**
         * Migrate the settings after an update of the plugin. Runs once per update and site.
         *
         * @param string|false $old_version Version before the update, false on a new installation or an update from a version before 2.5.
         */
        public static function migrate( $old_version ) {
            // 2.6: New switch for the custom CSS, disabled by default. Enable it, if custom CSS was already entered before the update.
            if ( false === get_option( GAOO_PREFIX . 'custom_css_status', false ) ) {
                $custom_css = (string) get_option( GAOO_PREFIX . 'custom_css', '' );

                update_option( GAOO_PREFIX . 'custom_css_status', preg_match( '/\S/u', $custom_css ) ? 'on' : 'off' );
            }
        }

        /**
         * Load the home page like a visitor without consent and check the HTML: is the opt-out script loaded and which
         * measurement IDs of Google Analytics are found. The result is stored, because the request takes some time.
         * JavaScript is not executed, so Google Analytics can't be found if it is loaded after the consent or by the Google Tag Manager.
         *
         * @param string $ua_code Measurement ID of the settings.
         *
         * @return array Result with the keys "time", "error", "script" (ok, missing, delayed), "ga" (found, other, gtm, none), "ids"
         *               and "page" (found, missing: the link on the privacy policy page).
         */
        public static function run_frontend_check( $ua_code ) {
            $result = array(
                'time'   => time(),
                'error'  => '',
                'script' => '',
                'ga'     => '',
                'ids'    => array(),
                'page'   => '',
            );

            // The parameter bypasses the page cache, the page is loaded like a visitor without cookies.
            $url      = apply_filters( 'gaoo_frontend_check_url', add_query_arg( 'gaoo-check', time(), home_url( '/' ) ) );
            $args = array(
                'timeout'     => 10,
                'redirection' => 3,
                'sslverify'   => apply_filters( 'https_local_ssl_verify', false ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook of WordPress itself.
            );

            // Access data, if the website is protected by the server with a password (HTTP basic authentication), e.g. a staging website.
            $auth_user = (string) self::get_option( 'check_auth_user', '' );

            if ( '' !== $auth_user ) {
                $args[ 'headers' ] = array(
                    'Authorization' => 'Basic ' . base64_encode( $auth_user . ':' . (string) self::get_option( 'check_auth_pass', '' ) ),
                );
            }

            $response = self::remote_get( $url, $args );

            if ( is_wp_error( $response ) ) {
                $result[ 'error' ] = $response->get_error_message();
            }
            elseif ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
                $result[ 'error' ] = sprintf( 'HTTP %d', (int) wp_remote_retrieve_response_code( $response ) );
            }
            else {
                $html = (string) wp_remote_retrieve_body( $response );

                // The opt-out script in the head. Optimization plugins delay scripts by changing their type, then it runs too late.
                if ( preg_match( '/<script\b([^>]*)>[^<]*ga-disable-' . preg_quote( $ua_code, '/' ) . '/i', $html, $script ) ) {
                    $type               = preg_match( '/\btype\s*=\s*["\']?([^"\'\s>]+)/i', $script[ 1 ], $type ) ? strtolower( $type[ 1 ] ) : '';
                    $result[ 'script' ] = in_array( $type, array( '', 'text/javascript', 'application/javascript', 'module' ), true ) ? 'ok' : 'delayed';
                }
                else {
                    $result[ 'script' ] = 'missing';
                }

                // Measurement IDs of Google Analytics: gtag.js and gtag('config', ...). Also found, if a consent tool blocks the script until the consent.
                preg_match_all( '/googletagmanager\.com\/gtag\/js\?id=(G-[A-Z0-9]+)|gtag\(\s*[\'"]config[\'"]\s*,\s*[\'"](G-[A-Z0-9]+)/i', $html, $matches );
                $result[ 'ids' ] = array_values( array_unique( array_map( 'strtoupper', array_filter( array_merge( $matches[ 1 ], $matches[ 2 ] ) ) ) ) );

                if ( ! empty( $result[ 'ids' ] ) ) {
                    $result[ 'ga' ] = in_array( strtoupper( $ua_code ), $result[ 'ids' ], true ) ? 'found' : 'other';
                }
                else {
                    $result[ 'ga' ] = preg_match( '/googletagmanager\.com\/gtm\.js|\bGTM-[A-Z0-9]{4,}\b/i', $html ) ? 'gtm' : 'none';
                }
            }

            // The privacy policy page itself: is the link displayed? This works with every page builder and theme.
            $page_id = (int) self::get_option( 'privacy_page_id', 0 );
            $page    = empty( $result[ 'error' ] ) && $page_id ? get_post( $page_id ) : null;

            if ( $page && 'publish' === $page->post_status && empty( $page->post_password ) ) {
                $response = self::remote_get( add_query_arg( 'gaoo-check', time(), get_permalink( $page ) ), $args );

                if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
                    $result[ 'page' ]    = preg_match( '/class\s*=\s*["\'][^"\']*\bgaoo-link\b/i', (string) wp_remote_retrieve_body( $response ) ) ? 'found' : 'missing';
                    $result[ 'page_id' ] = $page_id;
                }
            }

            $result[ 'code' ] = $ua_code;
            update_option( GAOO_PREFIX . 'frontend_check', $result, false );

            return $result;
        }

        /**
         * Load a page of the website for the check. With access data, the redirects are followed here: the access data are only sent
         * to the same host, so they do not reach another server (e.g. a redirect to an external page).
         *
         * @param string $url  URL.
         * @param array  $args Arguments of wp_remote_get().
         *
         * @return array|WP_Error Response.
         */
        private static function remote_get( $url, $args ) {
            if ( empty( $args[ 'headers' ][ 'Authorization' ] ) ) {
                return wp_remote_get( $url, $args );
            }

            $host                  = wp_parse_url( $url, PHP_URL_HOST );
            $args[ 'redirection' ] = 0;

            for ( $i = 0; $i <= 3; $i++ ) {
                $response = wp_remote_get( $url, $args );
                $code     = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
                $location = $code >= 300 && $code < 400 ? wp_remote_retrieve_header( $response, 'location' ) : '';

                if ( '' === $location || is_array( $location ) ) {
                    return $response;
                }

                $url = WP_Http::make_absolute_url( $location, $url );

                if ( wp_parse_url( $url, PHP_URL_HOST ) !== $host ) {
                    unset( $args[ 'headers' ][ 'Authorization' ] );
                }
            }

            return $response;
        }

        /**
         * Checks of the website itself for the status check, from the stored result of run_frontend_check().
         *
         * @param string $ua_code Measurement ID of the settings.
         *
         * @return array List of checks.
         */
        private static function get_frontend_checks( $ua_code ) {
            $result = get_option( GAOO_PREFIX . 'frontend_check', array() );

            // Not checked yet, or checked with another measurement ID.
            if ( empty( $result[ 'time' ] ) || ! isset( $result[ 'code' ] ) || strtoupper( $result[ 'code' ] ) !== strtoupper( $ua_code ) ) {
                return array(
                    array(
                        'id'      => 'website_check',
                        'label'   => __( 'Website not checked yet, click "Check now"', 'opt-out-for-google-analytics' ),
                        'checked' => null,
                        'info'    => true,
                    ),
                );
            }

            // The website is protected with a password (HTTP basic authentication).
            if ( 'HTTP 401' === $result[ 'error' ] ) {
                return array(
                    array(
                        'id'      => 'website_check',
                        'label'   => '' === (string) self::get_option( 'check_auth_user', '' ) ? __( 'The website is protected with a password (HTTP 401). Enter the access data in the settings of the status check.', 'opt-out-for-google-analytics' ) : __( 'The access data for the check of the website are not correct (HTTP 401).', 'opt-out-for-google-analytics' ),
                        'checked' => null,
                        'info'    => true,
                        'url'     => admin_url( 'options-general.php?page=gaoo#gaoo-check-auth-user' ),
                    ),
                );
            }

            if ( ! empty( $result[ 'error' ] ) ) {
                return array(
                    array(
                        'id'      => 'website_check',
                        /* translators: %s: error message */
                        'label'   => sprintf( __( 'The website could not be loaded for the check: %s', 'opt-out-for-google-analytics' ), $result[ 'error' ] ),
                        'checked' => null,
                        'info'    => true,
                    ),
                );
            }

            $script = array(
                'ok'      => array( __( 'Opt-out script is loaded on the website', 'opt-out-for-google-analytics' ), true ),
                'missing' => array( __( 'Opt-out script is missing on the website, e.g. removed by an optimization plugin', 'opt-out-for-google-analytics' ), false ),
                'delayed' => array( __( 'Opt-out script is delayed by an optimization plugin, so it runs too late', 'opt-out-for-google-analytics' ), false ),
            );
            $ga     = array(
                'found' => array( __( 'Google Analytics with this measurement ID found on the website', 'opt-out-for-google-analytics' ), true ),
                'other' => array( __( 'Google Analytics on the website uses another measurement ID', 'opt-out-for-google-analytics' ), false ),
                'gtm'   => array( __( 'Google Tag Manager found, the measurement ID in it can not be checked', 'opt-out-for-google-analytics' ), null ),
                'none'  => array( __( 'Google Analytics not found on the website, e.g. because it is loaded after the consent', 'opt-out-for-google-analytics' ), null ),
            );
            $checks = array();

            if ( isset( $script[ $result[ 'script' ] ] ) ) {
                $checks[] = array(
                    'id'      => 'opt_out_script',
                    'label'   => $script[ $result[ 'script' ] ][ 0 ],
                    'checked' => $script[ $result[ 'script' ] ][ 1 ],
                );
            }

            // The link on the privacy policy page, as the visitors see it.
            if ( ! empty( $result[ 'page' ] ) ) {
                $checks[] = array(
                    'id'      => 'privacy_page_link',
                    'label'   => 'found' === $result[ 'page' ] ? __( 'The opt-out link is displayed on the privacy policy page', 'opt-out-for-google-analytics' ) : __( 'The opt-out link is not displayed on the privacy policy page, e.g. because the theme does not render shortcodes', 'opt-out-for-google-analytics' ),
                    'checked' => 'found' === $result[ 'page' ],
                    'url'     => isset( $result[ 'page_id' ] ) ? get_permalink( $result[ 'page_id' ] ) : '',
                );
            }

            if ( isset( $ga[ $result[ 'ga' ] ] ) ) {
                $checks[] = array(
                    'id'      => 'google_analytics',
                    'label'   => $ga[ $result[ 'ga' ] ][ 0 ] . ( 'other' === $result[ 'ga' ] ? ' (' . implode( ', ', $result[ 'ids' ] ) . ')' : '' ),
                    'text'    => $ga[ $result[ 'ga' ] ][ 0 ],
                    'value'   => 'other' === $result[ 'ga' ] ? implode( ', ', $result[ 'ids' ] ) : '',
                    'checked' => $ga[ $result[ 'ga' ] ][ 1 ],
                    // Not checkable, because Google Analytics is loaded later (consent or Google Tag Manager): only an information.
                    'info'    => null === $ga[ $result[ 'ga' ] ][ 1 ],
                );
            }

            return $checks;
        }

        /**
         * Store the result of the status check of this site, for the overview in the network admin. The other plugins of a
         * site (e.g. the source of the measurement ID) are only loaded on the site itself, so the result is stored there.
         *
         * @param array $checklist Checks of check_todos().
         */
        public static function store_result( $checklist ) {
            $result = array(
                'time'   => time(),
                'passed' => 0,
                'total'  => 0,
                'failed' => array(),
                // IDs of the failed checks, which do not depend on the language, e.g. for the REST API of the network.
                'failed_ids' => array(),
            );

            foreach ( $checklist as $check ) {
                if ( ! empty( $check[ 'info' ] ) ) {
                    continue;
                }

                $result[ 'total' ]++;

                if ( true === $check[ 'checked' ] ) {
                    $result[ 'passed' ]++;
                }
                elseif ( false === $check[ 'checked' ] ) {
                    $result[ 'failed' ][]     = wp_strip_all_tags( $check[ 'label' ] );
                    $result[ 'failed_ids' ][] = $check[ 'id' ];
                }
            }

            update_option( GAOO_PREFIX . 'last_result', $result, false );
        }

        /**
         * Settings which can be exported, imported or transferred to other sites. The access data are never transferred,
         * and the privacy policy page is a page of each site.
         *
         * @param bool $site_specific Also exclude the source and code of Google Analytics, which can be different on each site of a network. (Default: false)
         *
         * @return array Names of the settings.
         */
        public static function transferable_options( $site_specific = false ) {
            $exclude = array( 'check_auth_user', 'check_auth_pass', 'privacy_page_id' );

            if ( $site_specific ) {
                // Also the recipients of the status: the email addresses and the webhook (its URL is secret).
                $exclude = array_merge( $exclude, array( 'ga_plugin', 'ua_code', 'tracking_code', 'wp_privacy_page', 'status_mails', 'status_mails_sync', 'webhook_url', 'webhook_status', 'webhook_format', 'webhook_always' ) );
            }

            return array_values( array_diff( self::get_options_list( true ), $exclude ) );
        }

        /**
         * The opt-out is enabled: only "off" disables it. An empty value of older versions means enabled, like on the settings page.
         *
         * @param array $data Settings.
         *
         * @return bool True if enabled.
         */
        public static function is_opt_out_enabled( $data ) {
            return 'off' !== ( isset( $data[ 'status' ] ) ? (string) $data[ 'status' ] : 'off' );
        }

        /**
         * Hidden by the network admin for the site admins: the settings page or the dashboard widget. Super admins always see them.
         *
         * @param string $what "settings" or "widget".
         *
         * @return bool True if hidden for the current user.
         */
        public static function is_hidden_for_user( $what ) {
            return is_multisite() && ! is_super_admin() && ! empty( get_site_option( GAOO_PREFIX . 'network_hide_' . $what, 0 ) );
        }

        /**
         * Transfer the settings of a site to all other sites of the network with the active plugin. The settings of each site are kept.
         *
         * @param int $source ID of the site.
         *
         * @return int Number of sites.
         */
        public static function transfer_to_all( $source ) {
            if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
                require_once ABSPATH . 'wp-admin' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'plugin.php';
            }

            $plugin         = GAOO_PLUGIN_NAME . '/ga-opt-out.php';
            $network_active = is_plugin_active_for_network( $plugin );
            $transferred    = 0;

            if ( ! $source || ! get_site( $source ) ) {
                return 0;
            }

            foreach ( get_sites( array( 'number' => 0, 'fields' => 'ids', 'deleted' => 0, 'archived' => 0, 'spam' => 0 ) ) as $blog_id ) {
                if ( (int) $blog_id === (int) $source || ( ! $network_active && ! in_array( $plugin, (array) get_blog_option( $blog_id, 'active_plugins', array() ), true ) ) ) {
                    continue;
                }

                self::copy_settings( (int) $source, (int) $blog_id, self::transferable_options( true ) );
                $transferred++;
            }

            return $transferred;
        }

        /**
         * Copy the settings of a site to another site of the network, as they are stored. The status check of the target site is updated.
         *
         * @param int   $from_id ID of the source site.
         * @param int   $to_id   ID of the target site.
         * @param array $names   Names of the settings, see transferable_options().
         */
        public static function copy_settings( $from_id, $to_id, $names ) {
            $values = array();

            switch_to_blog( $from_id );

            foreach ( $names as $name ) {
                $value = get_option( GAOO_PREFIX . $name, null );

                if ( null !== $value ) {
                    $values[ $name ] = $value;
                }
            }

            restore_current_blog();
            switch_to_blog( $to_id );

            foreach ( $values as $name => $value ) {
                update_option( GAOO_PREFIX . $name, $value );
            }

            self::delete_todo_cache();
            self::start_cronjob( true );

            restore_current_blog();
        }

        /**
         * New site in the network: copy the settings of the template site, if one is selected in the network admin.
         *
         * @param WP_Site $site New site.
         */
        public static function apply_network_template( $site ) {
            $template = (int) get_site_option( GAOO_PREFIX . 'network_template', 0 );

            if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
                require_once ABSPATH . 'wp-admin' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'plugin.php';
            }

            if ( empty( $template ) || $template === (int) $site->blog_id || ! get_site( $template ) || ! is_plugin_active_for_network( GAOO_PLUGIN_NAME . '/ga-opt-out.php' ) ) {
                return;
            }

            self::copy_settings( $template, (int) $site->blog_id, self::transferable_options( true ) );
        }

        /**
         * Delete the checklist cache
         *
         * @return bool True on success, otherwise false
         */
        public static function delete_todo_cache() {
            return delete_transient( GAOO_PREFIX . 'has_todos' );
        }

        /**
         * (Re-)Start the cronjob.
         *
         * @param bool $restart  Should restart running cronjob. (Default: false)
         * @param bool $checked  The check was just run (manually), so the next check is after the interval and not today. (Default: false)
         *
         * @return bool True on success, otherwise false.
         */
        public static function start_cronjob( $restart = false, $checked = false ) {
            if ( ! $restart && wp_next_scheduled( GAOO_CRONJOB ) ) {
                return true;
            }

            wp_unschedule_hook( GAOO_CRONJOB );

            $intervall = self::get_status_check_intervall( false );
            $start     = strtotime( gmdate( "Y-m-d" ) . ' 00:05:00' );

            if ( $checked ) {
                $schedules = wp_get_schedules();
                $start     += isset( $schedules[ $intervall ][ 'interval' ] ) ? $schedules[ $intervall ][ 'interval' ] : self::get_status_check_intervall( true ) * DAY_IN_SECONDS;
            }

            return false !== wp_schedule_event( $start, $intervall, GAOO_CRONJOB );
        }

        /**
         * Get the configurated status check intervall in days.
         *
         * @param bool $in_days Return in days or as string. (Default: false)
         *
         * @return int|string Intervall in days or as a string.
         */
        public static function get_status_check_intervall( $in_days = false ) {
            $intervall = GAOO_Utils::get_option( 'status_intervall', 'weekly' );

            switch ( $intervall ) {
                case 'daily':
                    return $in_days ? 1 : $intervall;

                case 'weekly':
                default:
                    return $in_days ? 7 : $intervall;

                case 'monthly':
                    return $in_days ? 30 : $intervall;
            }
        }

        /**
         * Check if there are open todos.
         *
         * @return bool True if open todos available, otherwise false.
         */
        public static function has_todos() {
            $transient_name = GAOO_PREFIX . 'has_todos';
            $has_todos      = get_transient( $transient_name );

            if ( false !== $has_todos ) {
                return boolval( $has_todos );
            }

            $data  = self::get_options();
            $todos = self::check_todos( $data );

            if ( empty( $todos ) ) {
                return;
            }

            $has_todos = ( (int) ( $todos[ 'sum' ] - $todos[ 'dontknow' ] ) != $todos[ 'check' ] );

            self::delete_todo_cache();
            // Store as integer, because a stored false can't be distinguished from a missing transient.
            set_transient( $transient_name, (int) $has_todos, self::get_status_check_intervall( true ) * DAY_IN_SECONDS );

            return boolval( $has_todos );
        }

        /**
         * Result of a check as text: passed, failed, unknown (not checked yet) or info (only an information, not an error).
         *
         * @param array $check Check of check_todos().
         *
         * @return string Result.
         */
        public static function check_result( $check ) {
            if ( ! empty( $check[ 'info' ] ) ) {
                return 'info';
            }

            if ( true === $check[ 'checked' ] ) {
                return 'passed';
            }

            return false === $check[ 'checked' ] ? 'failed' : 'unknown';
        }

        /**
         * Status of the checks for the REST API and the webhook: status, number of the checks and each check with ID, text and result.
         *
         * @param array $checklist Checks of check_todos() with labels.
         *
         * @return array Status.
         */
        public static function status_data( $checklist ) {
            $checks = array();
            $counts = array(
                'passed'  => 0,
                'failed'  => 0,
                'unknown' => 0,
                'info'    => 0,
            );

            foreach ( $checklist as $check ) {
                $result = self::check_result( $check );

                $counts[ $result ]++;
                $checks[] = array(
                    'id'     => $check[ 'id' ],
                    'label'  => wp_strip_all_tags( $check[ 'label' ] ),
                    'result' => $result,
                );
            }

            $last_check = (int) get_option( GAOO_PREFIX . 'last_check', 0 );
            $next_check = wp_next_scheduled( GAOO_CRONJOB );

            return array(
                'status'     => $counts[ 'failed' ] > 0 ? 'failed' : 'ok',
                'total'      => count( $checks ),
                'passed'     => $counts[ 'passed' ],
                'failed'     => $counts[ 'failed' ],
                'unknown'    => $counts[ 'unknown' ],
                'info'       => $counts[ 'info' ],
                'checks'     => $checks,
                'last_check' => $last_check ? gmdate( 'c', $last_check ) : null,
                'next_check' => $next_check ? gmdate( 'c', $next_check ) : null,
            );
        }

        /**
         * Formats of the webhook: own JSON or the message format of a chat service.
         *
         * @return array Format => name.
         */
        public static function webhook_formats() {
            return array(
                'json'    => __( 'JSON (e.g. n8n, Make, Zapier or an own script)', 'opt-out-for-google-analytics' ),
                'slack'   => __( 'Slack, Mattermost, Google Chat', 'opt-out-for-google-analytics' ),
                'teams'   => __( 'Microsoft Teams (Workflows)', 'opt-out-for-google-analytics' ),
                'discord' => __( 'Discord', 'opt-out-for-google-analytics' ),
            );
        }

        /**
         * Settings of the webhook of the site, or of the network (multisite).
         *
         * @param bool $network True for the webhook of the network.
         *
         * @return array Settings: enabled, url, format, always.
         */
        public static function webhook_settings( $network = false ) {
            if ( $network ) {
                return array(
                    'enabled' => ! empty( get_site_option( GAOO_PREFIX . 'network_webhook_status', 0 ) ),
                    'url'     => trim( (string) get_site_option( GAOO_PREFIX . 'network_webhook_url', '' ) ),
                    'format'  => (string) get_site_option( GAOO_PREFIX . 'network_webhook_format', 'json' ),
                    'always'  => ! empty( get_site_option( GAOO_PREFIX . 'network_webhook_always', 0 ) ),
                );
            }

            return array(
                'enabled' => ! empty( self::get_option( 'webhook_status', 0 ) ),
                'url'     => trim( (string) self::get_option( 'webhook_url', '' ) ),
                'format'  => (string) self::get_option( 'webhook_format', 'json' ),
                'always'  => ! empty( self::get_option( 'webhook_always', 0 ) ),
            );
        }

        /**
         * After a status check: send the result to the webhook of the site and of the network, if they are enabled.
         * They get the result if a check failed, or after every check if this is enabled.
         *
         * @param array $checklist Checks of check_todos() with labels.
         * @param bool  $failed    True if a check failed.
         */
        public static function send_webhooks( $checklist, $failed ) {
            foreach ( is_multisite() ? array( false, true ) : array( false ) as $network ) {
                $settings = self::webhook_settings( $network );

                if ( $settings[ 'enabled' ] && '' !== $settings[ 'url' ] && ( $failed || $settings[ 'always' ] ) ) {
                    self::send_webhook( $checklist, false, $network );
                }
            }
        }

        /**
         * Send the result of the status check of the site to the webhook. The result of the request is stored and shown on the settings page.
         *
         * @param array $checklist Checks of check_todos() with labels.
         * @param bool  $test      True for a test of the settings page. (Default: false)
         * @param bool  $network   True for the webhook of the network, which gets the results of all sites. (Default: false)
         *
         * @return array|null Result of the request (time, code, error), null if no webhook URL is set.
         */
        public static function send_webhook( $checklist, $test = false, $network = false ) {
            $status = self::status_data( $checklist );
            $site   = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

            // The message of a chat service: title, the checks which are not passed and the link to the settings.
            /* translators: 1: name of the website, 2: number of failed checks, 3: number of all checks */
            $title = 'ok' === $status[ 'status' ] ? sprintf( __( '%1$s: Opt-Out for Google Analytics - everything is fine (%3$d checks).', 'opt-out-for-google-analytics' ), $site, $status[ 'failed' ], $status[ 'total' ] ) : sprintf( __( '%1$s: Opt-Out for Google Analytics - %2$d of %3$d checks failed!', 'opt-out-for-google-analytics' ), $site, $status[ 'failed' ], $status[ 'total' ] );
            $lines = array();

            foreach ( $status[ 'checks' ] as $check ) {
                if ( 'passed' !== $check[ 'result' ] ) {
                    $lines[] = ( 'failed' === $check[ 'result' ] ? '✕' : ( 'info' === $check[ 'result' ] ? 'i' : '?' ) ) . ' ' . $check[ 'label' ];
                }
            }

            /* translators: %s: URL of the settings page */
            $lines[] = sprintf( __( 'Settings: %s', 'opt-out-for-google-analytics' ), admin_url( 'options-general.php?page=gaoo' ) );

            $json = array_merge( array(
                'event' => $test ? 'test' : 'status_check',
                'site'  => array(
                    'id'   => get_current_blog_id(),
                    'name' => $site,
                    'url'  => home_url( '/' ),
                ),
            ), $status, array( 'language' => determine_locale() ) );

            return self::post_webhook( $network, $test, $title, $lines, $json, $checklist );
        }

        /**
         * Test of the webhook of the network: the status of all sites.
         *
         * @return array|null Result of the request (time, code, error), null if no webhook URL is set.
         */
        public static function send_network_webhook_test() {
            $sites  = self::network_sites();
            $counts = array_count_values( array_column( $sites, 'status' ) );
            $failed = isset( $counts[ 'failed' ] ) ? $counts[ 'failed' ] : 0;
            $lines  = array();

            /* translators: 1: number of sites with a failed check, 2: number of all sites */
            $title = sprintf( __( 'Opt-Out for Google Analytics - network: %1$d of %2$d sites with a failed check.', 'opt-out-for-google-analytics' ), $failed, count( $sites ) );

            foreach ( $sites as $site ) {
                if ( 'failed' === $site[ 'status' ] ) {
                    /* translators: 1: name of the site, 2: number of failed checks, 3: URL of the settings */
                    $lines[] = '✕ ' . sprintf( __( '%1$s: %2$d checks failed (%3$s)', 'opt-out-for-google-analytics' ), $site[ 'name' ], $site[ 'failed' ], $site[ 'settings_url' ] );
                }
            }

            /* translators: %s: URL of the overview of the network */
            $lines[] = sprintf( __( 'Overview: %s', 'opt-out-for-google-analytics' ), network_admin_url( 'settings.php?page=gaoo-network' ) );

            $json = array(
                'event'       => 'test',
                'network'     => network_home_url( '/' ),
                'status'      => $failed > 0 ? 'failed' : 'ok',
                'total'       => count( $sites ),
                'ok'          => isset( $counts[ 'ok' ] ) ? $counts[ 'ok' ] : 0,
                'failed'      => $failed,
                'not_checked' => isset( $counts[ 'not_checked' ] ) ? $counts[ 'not_checked' ] : 0,
                'inactive'    => isset( $counts[ 'inactive' ] ) ? $counts[ 'inactive' ] : 0,
                'sites'       => array_map( function ( $site ) {
                    return array(
                        'id'            => $site[ 'id' ],
                        'name'          => $site[ 'name' ],
                        'url'           => $site[ 'url' ],
                        'status'        => $site[ 'status' ],
                        'failed_checks' => $site[ 'failed_ids' ],
                    );
                }, $sites ),
            );

            return self::post_webhook( true, true, $title, $lines, $json, array() );
        }

        /**
         * Send a message to the webhook of the site or of the network, in its format, and store the result.
         *
         * @param bool   $network   True for the webhook of the network.
         * @param bool   $test      True for a test.
         * @param string $title     Title of the message.
         * @param array  $lines     Lines of the message.
         * @param array  $json      Data of the format "json".
         * @param array  $checklist Checks, for the filter.
         *
         * @return array|null Result of the request (time, code, error), null if no webhook URL is set.
         */
        private static function post_webhook( $network, $test, $title, $lines, $json, $checklist ) {
            $settings = self::webhook_settings( $network );

            if ( '' === $settings[ 'url' ] ) {
                return null;
            }

            $title = ( $test ? __( 'Test:', 'opt-out-for-google-analytics' ) . ' ' : '' ) . $title;
            $text  = $title . "\n" . implode( "\n", $lines );

            switch ( $settings[ 'format' ] ) {
                case 'slack':
                    $payload = array( 'text' => $text );
                    break;

                case 'discord':
                    $payload = array( 'content' => mb_substr( $text, 0, 2000 ) );
                    break;

                case 'teams':
                    $payload = array(
                        'type'        => 'message',
                        'attachments' => array(
                            array(
                                'contentType' => 'application/vnd.microsoft.card.adaptive',
                                'content'     => array(
                                    '$schema' => 'http://adaptivecards.io/schemas/adaptive-card.json',
                                    'type'    => 'AdaptiveCard',
                                    'version' => '1.4',
                                    'body'    => array(
                                        array(
                                            'type'   => 'TextBlock',
                                            'text'   => $title,
                                            'weight' => 'Bolder',
                                            'wrap'   => true,
                                        ),
                                        array(
                                            'type' => 'TextBlock',
                                            'text' => implode( "\n\n", $lines ),
                                            'wrap' => true,
                                        ),
                                    ),
                                ),
                            ),
                        ),
                    );
                    break;

                default:
                    $payload = $json;
            }

            /**
             * Data of the webhook, sent as JSON.
             *
             * @param array  $payload   Data.
             * @param string $format    Format: json, slack, teams or discord.
             * @param array  $checklist Checks of the site, empty for the test of the network.
             * @param bool   $network   True for the webhook of the network.
             */
            $payload = apply_filters( 'gaoo_webhook_payload', $payload, $settings[ 'format' ], $checklist, $network );

            // Not a valid URL, the host is not found or is in the local network (see the filter "http_request_host_is_external").
            if ( ! wp_http_validate_url( $settings[ 'url' ] ) ) {
                $response = new WP_Error( 'gaoo_webhook_url', __( 'The URL is not valid, its host can not be found or it is in the local network.', 'opt-out-for-google-analytics' ) );
            }
            else {
                // wp_safe_remote_post() does not send requests to the local network, so a site admin of a multisite can not reach internal services.
                $response = wp_safe_remote_post( $settings[ 'url' ], array(
                    'timeout' => 10,
                    'headers' => array( 'Content-Type' => 'application/json; charset=utf-8' ),
                    'body'    => wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
                ) );
            }

            $result = array(
                'time'  => time(),
                'code'  => is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response ),
                'error' => is_wp_error( $response ) ? $response->get_error_message() : '',
                'test'  => $test,
            );

            if ( $network ) {
                update_site_option( GAOO_PREFIX . 'network_webhook_result', $result );
            }
            else {
                update_option( GAOO_PREFIX . 'webhook_result', $result, false );
            }

            return $result;
        }

        /**
         * Status of all sites in the network, of the last status check of each site.
         *
         * @return array Sites with id, name, url, settings_url, status (ok, failed, not_checked or inactive), total, passed, failed, failed_checks (labels), failed_ids, last_check and pending.
         */
        public static function network_sites() {
            if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
                require_once ABSPATH . 'wp-admin' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'plugin.php';
            }

            $plugin         = GAOO_PLUGIN_NAME . '/ga-opt-out.php';
            $network_active = is_plugin_active_for_network( $plugin );
            $sites          = array();

            // All sites, without limit.
            foreach ( get_sites( array( 'number' => 0, 'deleted' => 0, 'archived' => 0, 'spam' => 0 ) ) as $site ) {
                switch_to_blog( $site->blog_id );

                $is_active = $network_active || in_array( $plugin, (array) get_option( 'active_plugins', array() ), true );
                $result    = $is_active ? (array) get_option( GAOO_PREFIX . 'last_result', array() ) : array();
                $failed    = empty( $result[ 'failed' ] ) ? array() : (array) $result[ 'failed' ];

                if ( ! $is_active ) {
                    $status = 'inactive';
                }
                elseif ( empty( $result[ 'time' ] ) ) {
                    $status = 'not_checked';
                }
                else {
                    $status = empty( $failed ) ? 'ok' : 'failed';
                }

                $sites[] = array(
                    'id'            => (int) $site->blog_id,
                    'name'          => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
                    'url'           => home_url( '/' ),
                    'settings_url'  => admin_url( 'options-general.php?page=gaoo' ),
                    'status'        => $status,
                    'total'         => isset( $result[ 'total' ] ) ? (int) $result[ 'total' ] : 0,
                    'passed'        => isset( $result[ 'passed' ] ) ? (int) $result[ 'passed' ] : 0,
                    'failed'        => count( $failed ),
                    'failed_checks' => $failed,
                    'failed_ids'    => empty( $result[ 'failed_ids' ] ) ? array() : (array) $result[ 'failed_ids' ],
                    'last_check'    => empty( $result[ 'time' ] ) ? 0 : (int) $result[ 'time' ],
                    'pending'       => $is_active && (bool) wp_next_scheduled( 'gaoo_run_check_now' ),
                );

                restore_current_blog();
            }

            return $sites;
        }

        /**
         * Start the status check of sites in the network. The check is scheduled as a single event and the cron of the site is started,
         * so the check runs in the context of the site, with its plugins (e.g. the source of the measurement ID).
         *
         * @param array $site_ids IDs of the sites, empty for all sites.
         *
         * @return int Number of sites with the active plugin, where the check was started.
         */
        public static function start_network_check( $site_ids = array() ) {
            if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
                require_once ABSPATH . 'wp-admin' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'plugin.php';
            }

            $plugin         = GAOO_PLUGIN_NAME . '/ga-opt-out.php';
            $network_active = is_plugin_active_for_network( $plugin );
            $args           = array( 'number' => 0, 'fields' => 'ids', 'deleted' => 0, 'archived' => 0, 'spam' => 0 );
            $started        = 0;

            if ( ! empty( $site_ids ) ) {
                $args[ 'site__in' ] = array_map( 'absint', $site_ids );
            }

            foreach ( get_sites( $args ) as $blog_id ) {
                switch_to_blog( $blog_id );

                if ( $network_active || in_array( $plugin, (array) get_option( 'active_plugins', array() ), true ) ) {
                    if ( ! wp_next_scheduled( 'gaoo_run_check_now' ) ) {
                        wp_schedule_single_event( time(), 'gaoo_run_check_now' );
                    }

                    // Run the cron of this site now, otherwise the check runs with the next visit of the site.
                    spawn_cron();
                    $started++;
                }

                restore_current_blog();
            }

            return $started;
        }

        /**
         * Settings for the export as JSON file. The access data and the webhook URL are secret, so they are not exported.
         *
         * @return array Data of the file.
         */
        public static function export_data() {
            $options  = self::get_options();
            $settings = array();

            foreach ( array_diff( self::transferable_options(), array( 'webhook_url' ) ) as $name ) {
                $settings[ $name ] = $options[ $name ];
            }

            return array(
                'plugin'   => 'opt-out-for-google-analytics',
                'version'  => GAOO_VERSION,
                'exported' => gmdate( 'c' ),
                'settings' => $settings,
            );
        }

        /**
         * Import the settings of an exported file. Every value is checked like on saving.
         *
         * @param mixed $data    Data of the file.
         * @param bool  $trusted True if the user may change the tracking code and the custom CSS anyway, e.g. with WP-CLI. Otherwise the capabilities are checked. (Default: false)
         *
         * @return int Number of imported settings, -1 if the data are not an export of this plugin.
         */
        public static function import_data( $data, $trusted = false ) {
            if ( ! is_array( $data ) || ! isset( $data[ 'plugin' ], $data[ 'settings' ] ) || 'opt-out-for-google-analytics' !== $data[ 'plugin' ] || ! is_array( $data[ 'settings' ] ) ) {
                return -1;
            }

            $imported = 0;

            foreach ( array_diff( self::transferable_options(), array( 'webhook_url' ) ) as $name ) {
                if ( ! array_key_exists( $name, $data[ 'settings' ] ) ) {
                    continue;
                }

                $value = self::sanitize_imported( $name, $data[ 'settings' ][ $name ], $trusted );

                if ( null !== $value ) {
                    update_option( GAOO_PREFIX . $name, is_string( $value ) ? wp_slash( $value ) : $value );
                    $imported++;
                }
            }

            self::delete_todo_cache();
            self::start_cronjob( true );

            return $imported;
        }

        /**
         * Check an imported value, like the save of the settings.
         *
         * @param string $name    Name of the setting.
         * @param mixed  $value   Imported value.
         * @param bool   $trusted True if the capabilities are not checked.
         *
         * @return mixed Checked value, or null if it is not valid or the user may not change it.
         */
        private static function sanitize_imported( $name, $value, $trusted ) {
            if ( ! is_scalar( $value ) && null !== $value ) {
                return null;
            }

            $value = (string) $value;

            switch ( $name ) {
                case 'status':
                case 'custom_css_status':
                case 'popup_notice':
                case 'uninstall_keep_data':
                case 'disable_dashboard':
                    return 'on' === $value ? 'on' : 'off';

                case 'disable_monitoring':
                case 'force_reload':
                case 'wp_privacy_page':
                case 'status_mails_sync':
                case 'consent_mode':
                case 'webhook_always':
                case 'webhook_status':
                    return empty( $value ) ? 0 : 1;

                case 'status_intervall':
                    return in_array( $value, array( 'daily', 'weekly', 'monthly' ), true ) ? $value : null;

                case 'webhook_format':
                    return array_key_exists( $value, self::webhook_formats() ) ? $value : null;

                case 'ga_plugin':
                    return in_array( $value, array( 'manual', 'monsterinsights', 'gadash', 'analytify', 'gaga', 'sitekit' ), true ) ? $value : null;

                case 'ua_code':
                    return '' === $value ? '' : ( self::validate_ua_code( $value ) ?: null );

                case 'status_mails':
                    return implode( ',', array_filter( array_map( 'trim', explode( ',', $value ) ), 'is_email' ) );

                case 'link_deactivate':
                case 'link_activate':
                case 'popup_deactivate':
                case 'popup_activate':
                    // The texts are stored HTML encoded.
                    return htmlspecialchars( html_entity_decode( $value, ENT_QUOTES, 'UTF-8' ), ENT_QUOTES );

                case 'tracking_code':
                    return $trusted || current_user_can( 'unfiltered_html' ) ? $value : null;

                case 'custom_css':
                    if ( ! $trusted && ! current_user_can( 'edit_css' ) ) {
                        return null;
                    }

                    if ( '' === trim( $value ) ) {
                        return '';
                    }

                    require_once GAOO_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'css.class.php';
                    $result = GAOO_CSS::check( $value );

                    return empty( $result[ 'errors' ] ) ? wp_strip_all_tags( $result[ 'css' ] ) : null;
            }

            return null;
        }

        /**
         * Add custom schedules to WordPress defaults.
         *
         * @param array $schedules List of schedules
         *
         * @return array All schedules
         */
        public function add_cron_schedules( $schedules ) {
            if ( ! isset( $schedules[ 'weekly' ] ) ) {
                $schedules[ 'weekly' ] = array(
                    'interval' => 604800,
                    'display'  => esc_html__( 'weekly', 'opt-out-for-google-analytics' ),
                );
            }

            if ( ! isset( $schedules[ 'monthly' ] ) ) {
                $schedules[ 'monthly' ] = array(
                    'interval' => 2592000,
                    'display'  => esc_html__( 'monthly', 'opt-out-for-google-analytics' ),
                );
            }

            return $schedules;
        }
    }