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
            $privacy_page_accessibile = null;
            $shortcode_url            = null;
            $shortcode_available      = null;

            if ( ! empty( $data[ 'privacy_page_id' ] ) && ( $page = get_post( $data[ 'privacy_page_id' ] ) ) ) {
                $page_content        = sanitize_post_field( 'post_content', $page->post_content, $page->ID, 'raw' );
                $shortcode_available = ( ! empty( $page_content ) && ( false !== strpos( $page_content, GAOO_SHORTCODE ) || has_block( 'gaoo/block', $page_content ) || has_block( 'gaoo/optout', $page_content ) ) );
                $shortcode_url       = admin_url( 'post.php?action=edit&post=' . $data[ 'privacy_page_id' ] );

                $privacy_page_accessibile = ( sanitize_post_field( 'post_status', $page->post_status, $page->ID, 'raw' ) == 'publish' && empty( $page->post_password ) );

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
                    'label'   => esc_html__( 'Opt-Out Enabled', 'opt-out-for-google-analytics' ),
                    'checked' => ( $data[ 'status' ] == 'on' || empty( $data[ 'status' ] ) ),
                ),
                array(
                    'label'   => esc_html__( 'Found valid code', 'opt-out-for-google-analytics' ) . $ua_code_txt,
                    // Label without the code and the code itself, for the dashboard widget.
                    'text'    => esc_html__( 'Found valid code', 'opt-out-for-google-analytics' ),
                    'value'   => $ua_code,
                    'checked' => ( ! empty( $ua_code ) ),
                    'url'     => $uacode_url,
                ),
                array(
                    'label'   => esc_html__( 'Found shortcode on page', 'opt-out-for-google-analytics' ),
                    'checked' => $shortcode_available,
                    'url'     => $shortcode_url,
                ),
                array(
                    'label'   => esc_html__( 'Page accessibile', 'opt-out-for-google-analytics' ),
                    'checked' => $privacy_page_accessibile,
                    'url'     => $shortcode_url,
                ),
                array(
                    'label'   => esc_html__( 'Cronjob for this plugin activated', 'opt-out-for-google-analytics' ),
                    'checked' => ! empty( wp_next_scheduled( GAOO_CRONJOB ) ),
                ),
            );

            // Remove cached value if status of todos has been updated.
            self::delete_todo_cache();

            // Return labeled array
            if ( ! empty( $get_labels ) ) {
                return $checklist;
            }

            $dontknow = $check = $todo = 0;

            foreach ( $checklist as $item ) {
                if ( false === $item[ 'checked' ] ) {
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
         * Returns a new CSSTidy instance. The library is only loaded when it is needed, e.g. not on pages without custom CSS.
         *
         * @param string|null $template Template of CSSTidy, e.g. "highest_compression". (Default: null)
         *
         * @return csstidy
         */
        public static function get_csstidy( $template = null ) {
            // Another plugin may have already loaded its own copy of CSSTidy.
            if ( ! class_exists( 'csstidy' ) ) {
                require_once GAOO_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'csstidy' . DIRECTORY_SEPARATOR . 'class.csstidy.php';
            }

            $csstidy = new csstidy();

            if ( ! empty( $template ) ) {
                $csstidy->load_template( $template );
            }

            return $csstidy;
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
         * @param bool $restart Should restart running cronjob. (Default: false)
         *
         * @return bool True on success, otherwise false.
         */
        public static function start_cronjob( $restart = false ) {
            if ( ! $restart && wp_next_scheduled( GAOO_CRONJOB ) ) {
                return true;
            }

            wp_unschedule_hook( GAOO_CRONJOB );

            return false !== wp_schedule_event( strtotime( date( "Y-m-d" ) . ' 00:05:00' ), self::get_status_check_intervall( false ), GAOO_CRONJOB );
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