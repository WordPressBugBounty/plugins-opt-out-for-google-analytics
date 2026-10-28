<?php
    // If this file is called directly, abort.
    defined( 'WPINC' ) || die;

    /**
     * Status of the status check for a monitoring (e.g. UptimeRobot), as REST API endpoint: /wp-json/gaoo/v1/status
     * It is protected by a secret token, which is created on the settings page. Without token the endpoint is disabled.
     * In a multisite network, /wp-json/gaoo/v1/network of the main site shows the status of all sites, with an own token of the network admin.
     */
    class GAOO_API {

        /**
         * Name of the option with the token. It is not part of the settings, so saving the settings does not change it.
         */
        const TOKEN_OPTION = GAOO_PREFIX . 'api_token';

        /**
         * Name of the network option with the token of the status of all sites.
         */
        const NETWORK_TOKEN_OPTION = GAOO_PREFIX . 'network_api_token';

        /**
         * Register the route.
         */
        public static function register() {
            register_rest_route( 'gaoo/v1', '/status', array(
                'methods'             => 'GET',
                'callback'            => array( __CLASS__, 'status' ),
                'permission_callback' => array( __CLASS__, 'check_token' ),
            ) );

            if ( is_multisite() && is_main_site() ) {
                register_rest_route( 'gaoo/v1', '/network', array(
                    'methods'             => 'GET',
                    'callback'            => array( __CLASS__, 'network' ),
                    'permission_callback' => array( __CLASS__, 'check_network_token' ),
                ) );
            }
        }

        /**
         * Token of the API, empty if the API is disabled.
         *
         * @return string Token.
         */
        public static function get_token() {
            return (string) get_option( self::TOKEN_OPTION, '' );
        }

        /**
         * URL of the status for the monitoring, with the token.
         *
         * @return string URL, empty if the API is disabled.
         */
        public static function get_url() {
            $token = self::get_token();

            return '' === $token ? '' : add_query_arg( 'token', $token, rest_url( 'gaoo/v1/status' ) );
        }

        /**
         * Create a new token (the old URL stops working), or delete it to disable the API.
         *
         * @param bool $enable True creates a new token, false disables the API.
         */
        public static function set_token( $enable ) {
            if ( $enable ) {
                update_option( self::TOKEN_OPTION, wp_generate_password( 32, false ), false );
            }
            else {
                delete_option( self::TOKEN_OPTION );
            }
        }

        /**
         * Token of the status of all sites in the network, empty if it is disabled.
         *
         * @return string Token.
         */
        public static function get_network_token() {
            return (string) get_site_option( self::NETWORK_TOKEN_OPTION, '' );
        }

        /**
         * URL of the status of all sites in the network, with the token.
         *
         * @return string URL, empty if it is disabled.
         */
        public static function get_network_url() {
            $token = self::get_network_token();

            return '' === $token ? '' : add_query_arg( 'token', $token, get_rest_url( get_main_site_id(), 'gaoo/v1/network' ) );
        }

        /**
         * Create a new token of the network (the old URL stops working), or delete it.
         *
         * @param bool $enable True creates a new token, false disables the URL.
         */
        public static function set_network_token( $enable ) {
            if ( $enable ) {
                update_site_option( self::NETWORK_TOKEN_OPTION, wp_generate_password( 32, false ) );
            }
            else {
                delete_site_option( self::NETWORK_TOKEN_OPTION );
            }
        }

        /**
         * Check the token of the network.
         *
         * @param WP_REST_Request $request Request.
         *
         * @return bool|WP_Error True if the token is correct, otherwise an error.
         */
        public static function check_network_token( $request ) {
            return self::compare_token( self::get_network_token(), $request );
        }

        /**
         * Check the token of the request: parameter "token" or header "X-GAOO-Token".
         *
         * @param WP_REST_Request $request Request.
         *
         * @return bool|WP_Error True if the token is correct, otherwise an error.
         */
        public static function check_token( $request ) {
            return self::compare_token( self::get_token(), $request );
        }

        /**
         * Compare the token of the request with the saved token.
         *
         * @param string          $token   Saved token, empty if disabled.
         * @param WP_REST_Request $request Request.
         *
         * @return bool|WP_Error True if the token is correct, otherwise an error.
         */
        private static function compare_token( $token, $request ) {
            $given = (string) $request->get_header( 'x_gaoo_token' );

            // The parameter can also be an array (?token[]=), then it is not a valid token.
            if ( '' === $given ) {
                $param = $request->get_param( 'token' );
                $given = is_string( $param ) ? $param : '';
            }

            if ( '' === $token || '' === $given || ! hash_equals( $token, $given ) ) {
                return new WP_Error( 'gaoo_invalid_token', 'Invalid token.', array( 'status' => 403 ) );
            }

            return true;
        }

        /**
         * Locale of the response: English by default, or the language of the parameter "lang" (e.g. "de" or "de_DE"), if it is installed.
         *
         * @param string $lang Value of the parameter.
         *
         * @return string Locale, e.g. "en_US" or "de_DE".
         */
        private static function get_locale( $lang ) {
            $lang = str_replace( '-', '_', trim( (string) $lang ) );

            if ( '' === $lang || ! preg_match( '/^[a-z]{2,3}(_[a-z0-9]{2,})?$/i', $lang ) ) {
                return 'en_US';
            }

            foreach ( get_available_languages() as $locale ) {
                // Exact locale (de_DE) or the first locale of the language (de).
                if ( 0 === strcasecmp( $locale, $lang ) || ( false === strpos( $lang, '_' ) && 0 === stripos( $locale, $lang . '_' ) ) ) {
                    return $locale;
                }
            }

            return 'en_US';
        }

        /**
         * Result of the status check. The HTTP status is 200 if no check failed, otherwise 503, so a monitoring can check the status code.
         * Every check has an ID, which does not depend on the language, the texts are English or in the language of the parameter "lang".
         *
         * @param WP_REST_Request $request Request.
         *
         * @return WP_REST_Response Response.
         */
        public static function status( $request ) {
            $locale   = self::get_locale( $request->get_param( 'lang' ) );
            $switched = switch_to_locale( $locale );

            $status = GAOO_Utils::status_data( GAOO_Utils::check_todos( GAOO_Utils::get_options(), true ) );

            if ( $switched ) {
                restore_previous_locale();
            }

            $status[ 'language' ] = $locale;
            $response             = new WP_REST_Response( $status, 'failed' === $status[ 'status' ] ? 503 : 200 );

            // The status must not be cached, e.g. by a page cache.
            $response->header( 'Cache-Control', 'no-store' );

            return $response;
        }

        /**
         * Status of all sites in the network, of the last status check of each site. The HTTP status is 503 if a check of a site failed.
         * The parameter "status" (ok, failed, not_checked, inactive) shows only these sites, the numbers are always of all sites.
         *
         * @param WP_REST_Request $request Request.
         *
         * @return WP_REST_Response Response.
         */
        public static function network( $request ) {
            $filter = sanitize_key( (string) $request->get_param( 'status' ) );
            $counts = array(
                'ok'          => 0,
                'failed'      => 0,
                'not_checked' => 0,
                'inactive'    => 0,
            );
            $sites  = array();

            foreach ( GAOO_Utils::network_sites() as $site ) {
                $counts[ $site[ 'status' ] ]++;

                if ( '' !== $filter && $filter !== $site[ 'status' ] ) {
                    continue;
                }

                $sites[] = array(
                    'id'            => $site[ 'id' ],
                    'name'          => $site[ 'name' ],
                    'url'           => $site[ 'url' ],
                    'status'        => $site[ 'status' ],
                    'total'         => $site[ 'total' ],
                    'passed'        => $site[ 'passed' ],
                    'failed'        => $site[ 'failed' ],
                    'failed_checks' => $site[ 'failed_ids' ],
                    'last_check'    => $site[ 'last_check' ] ? gmdate( 'c', $site[ 'last_check' ] ) : null,
                );
            }

            $response = new WP_REST_Response( array(
                'status'      => $counts[ 'failed' ] > 0 ? 'failed' : 'ok',
                'total'       => array_sum( $counts ),
                'ok'          => $counts[ 'ok' ],
                'failed'      => $counts[ 'failed' ],
                'not_checked' => $counts[ 'not_checked' ],
                'inactive'    => $counts[ 'inactive' ],
                'sites'       => $sites,
            ), $counts[ 'failed' ] > 0 ? 503 : 200 );

            $response->header( 'Cache-Control', 'no-store' );

            return $response;
        }
    }
