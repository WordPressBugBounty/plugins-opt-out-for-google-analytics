<?php
    // If this file is called directly, abort.
    defined( 'WPINC' ) || die;

    /**
     * Status check of the opt-out for Google Analytics, e.g. to monitor several websites with a script.
     */
    class GAOO_CLI {

        /**
         * Shows the status check. The command fails (exit code 1), if a check failed.
         *
         * ## OPTIONS
         *
         * [--run]
         * : Run the status check now, like "Check now": the website is checked, an email is sent if a check failed, and the next automatic check is moved.
         *
         * [--format=<format>]
         * : Format of the output.
         * ---
         * default: table
         * options:
         *   - table
         *   - json
         *   - csv
         *   - yaml
         * ---
         *
         * ## EXAMPLES
         *
         *     wp gaoo status
         *     wp gaoo status --run --format=json
         *     wp site list --field=url | xargs -I % wp gaoo status --url=%
         *
         * @param array $args       Positional arguments, not used.
         * @param array $assoc_args Options.
         */
        public function status( $args, $assoc_args ) {
            if ( ! empty( $assoc_args[ 'run' ] ) ) {
                // Same as the cronjob: check the website, send the email if a check failed.
                do_action( GAOO_CRONJOB );
                GAOO_Utils::start_cronjob( true, true );
            }

            $checklist = GAOO_Utils::check_todos( GAOO_Utils::get_options(), true );
            $items     = array();

            foreach ( $checklist as $check ) {
                $items[] = array(
                    'id'     => $check[ 'id' ],
                    'check'  => wp_strip_all_tags( $check[ 'label' ] ),
                    'result' => GAOO_Utils::check_result( $check ),
                );
            }

            $format = isset( $assoc_args[ 'format' ] ) ? $assoc_args[ 'format' ] : 'table';
            $failed = in_array( 'failed', array_column( $items, 'result' ), true );

            WP_CLI\Utils\format_items( $format, $items, array( 'id', 'check', 'result' ) );

            if ( 'table' === $format ) {
                $last_check = (int) get_option( GAOO_PREFIX . 'last_check', 0 );
                $next_check = wp_next_scheduled( GAOO_CRONJOB );

                WP_CLI::log( sprintf( 'Last check: %s | Next automatic check: %s', $last_check ? wp_date( 'Y-m-d H:i', $last_check ) : '-', $next_check ? wp_date( 'Y-m-d H:i', $next_check ) : '-' ) );
            }

            if ( $failed ) {
                WP_CLI::halt( 1 );
            }
        }
    }

    /**
     * Export and import of the settings, e.g. to set up several websites with the same settings.
     */
    class GAOO_CLI_Settings {

        /**
         * Exports the settings as JSON, like "Export settings" on the settings page. The access data and the webhook URL are not exported.
         *
         * ## OPTIONS
         *
         * [<file>]
         * : File to save the settings. Without file, the JSON is shown.
         *
         * ## EXAMPLES
         *
         *     wp gaoo settings export gaoo-settings.json
         *     wp gaoo settings export > gaoo-settings.json
         *
         * @param array $args       File.
         * @param array $assoc_args Not used.
         */
        public function export( $args, $assoc_args ) {
            $json = wp_json_encode( GAOO_Utils::export_data(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

            if ( empty( $args[ 0 ] ) ) {
                WP_CLI::line( $json );

                return;
            }

            if ( false === file_put_contents( $args[ 0 ], $json ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- File of the command line.
                WP_CLI::error( sprintf( 'The file %s could not be saved.', $args[ 0 ] ) );
            }

            WP_CLI::success( sprintf( 'The settings were exported to %s.', $args[ 0 ] ) );
        }

        /**
         * Imports the settings of an exported JSON file. The current settings are replaced.
         *
         * ## OPTIONS
         *
         * <file>
         * : Exported file, or "-" for the standard input.
         *
         * [--yes]
         * : Import without confirmation.
         *
         * ## EXAMPLES
         *
         *     wp gaoo settings import gaoo-settings.json --yes
         *     wp site list --field=url | xargs -I % wp gaoo settings import gaoo-settings.json --yes --url=%
         *
         * @param array $args       File.
         * @param array $assoc_args Options.
         */
        public function import( $args, $assoc_args ) {
            $file = $args[ 0 ];
            $json = '-' === $file ? file_get_contents( 'php://stdin' ) : ( is_readable( $file ) ? file_get_contents( $file ) : false ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- File of the command line.

            if ( false === $json ) {
                WP_CLI::error( sprintf( 'The file %s could not be read.', $file ) );
            }

            WP_CLI::confirm( 'The settings of the file replace the current settings. Import now?', $assoc_args );

            // The command line may change the tracking code and the custom CSS, like an admin with all capabilities.
            $imported = GAOO_Utils::import_data( json_decode( (string) $json, true ), true );

            if ( $imported < 0 ) {
                WP_CLI::error( 'The file is not an export of this plugin.' );
            }

            WP_CLI::success( sprintf( '%d settings were imported.', $imported ) );
        }
    }

    /**
     * Status check of all sites in a multisite network.
     */
    class GAOO_CLI_Network {

        /**
         * Shows the result of the last status check of all sites. The command fails (exit code 1), if a check of a site failed.
         *
         * ## OPTIONS
         *
         * [--status=<status>]
         * : Show only the sites with this status.
         * ---
         * options:
         *   - ok
         *   - failed
         *   - not_checked
         *   - inactive
         * ---
         *
         * [--format=<format>]
         * : Format of the output.
         * ---
         * default: table
         * options:
         *   - table
         *   - json
         *   - csv
         *   - yaml
         *   - count
         * ---
         *
         * ## EXAMPLES
         *
         *     wp gaoo network status
         *     wp gaoo network status --status=failed --format=json
         *
         * @param array $args       Not used.
         * @param array $assoc_args Options.
         */
        public function status( $args, $assoc_args ) {
            self::require_multisite();

            $filter = isset( $assoc_args[ 'status' ] ) ? $assoc_args[ 'status' ] : '';
            $items  = array();
            $failed = false;

            foreach ( GAOO_Utils::network_sites() as $site ) {
                $failed = $failed || 'failed' === $site[ 'status' ];

                if ( '' !== $filter && $filter !== $site[ 'status' ] ) {
                    continue;
                }

                $items[] = array(
                    'id'            => $site[ 'id' ],
                    'name'          => $site[ 'name' ],
                    'url'           => $site[ 'url' ],
                    'status'        => $site[ 'status' ],
                    'passed'        => $site[ 'total' ] ? $site[ 'passed' ] . '/' . $site[ 'total' ] : '',
                    'failed_checks' => implode( ', ', $site[ 'failed_ids' ] ),
                    'last_check'    => $site[ 'last_check' ] ? wp_date( 'Y-m-d H:i', $site[ 'last_check' ] ) : '',
                );
            }

            WP_CLI\Utils\format_items( isset( $assoc_args[ 'format' ] ) ? $assoc_args[ 'format' ] : 'table', $items, array( 'id', 'name', 'url', 'status', 'passed', 'failed_checks', 'last_check' ) );

            if ( $failed ) {
                WP_CLI::halt( 1 );
            }
        }

        /**
         * Starts the status check of all sites or of one site, like "Check all sites now" in the network admin.
         * The check runs in the background by the cron of each site, so its plugins are loaded.
         *
         * ## OPTIONS
         *
         * [--site=<id>]
         * : ID of the site. Without, the check of all sites is started.
         *
         * ## EXAMPLES
         *
         *     wp gaoo network check
         *     wp gaoo network check --site=2
         *
         * @param array $args       Not used.
         * @param array $assoc_args Options.
         */
        public function check( $args, $assoc_args ) {
            self::require_multisite();

            $started = GAOO_Utils::start_network_check( empty( $assoc_args[ 'site' ] ) ? array() : array( absint( $assoc_args[ 'site' ] ) ) );

            WP_CLI::success( sprintf( 'The status check was started for %d sites. Show the result with "wp gaoo network status" in a few seconds.', $started ) );
        }

        /**
         * The commands are only available in a multisite network.
         */
        private static function require_multisite() {
            if ( ! is_multisite() ) {
                WP_CLI::error( 'This is not a multisite network. Use "wp gaoo status".' );
            }
        }
    }
