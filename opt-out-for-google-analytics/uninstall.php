<?php

    /**
     * Fired when the plugin is uninstalled.
     *
     * When populating this file, consider the following flow
     * of control:
     *
     * - This method should be static
     * - Check if the $_REQUEST content actually is the plugin name
     * - Run an admin referrer check to make sure it goes through authentication
     * - Verify the output of $_GET makes sense
     * - Repeat with other user roles. Best directly by using the links/query string parameters.
     * - Repeat things for multisite. Once for a single site in the network, once sitewide.
     *
     */

    // If uninstall not called from WordPress, then exit.
    defined( 'WP_UNINSTALL_PLUGIN' ) || die;

    require_once __DIR__ . DIRECTORY_SEPARATOR . 'constants.php';
    require_once GAOO_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'utils.class.php';

    global $wpdb;

    // In a multisite network all sites, also if the plugin is uninstalled with WP-CLI. Each site can keep its data with its own setting.
    $gaoo_site_ids = is_multisite() ? get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) : array( 0 );
    $gaoo_keep_all = 'on' === GAOO_Utils::get_option( 'uninstall_keep_data' );

    foreach ( $gaoo_site_ids as $gaoo_site_id ) {
        if ( $gaoo_site_id ) {
            switch_to_blog( $gaoo_site_id );
        }

        // The cronjobs are removed by the deactivation, but not if the plugin was never active on the site.
        wp_clear_scheduled_hook( GAOO_CRONJOB );
        wp_clear_scheduled_hook( 'gaoo_run_check_now' );

        if ( 'on' !== get_option( GAOO_PREFIX . 'uninstall_keep_data' ) ) {
            // All options and transients of the plugin, also of older versions. There is no function of WordPress to delete options by a prefix.
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall, the cache of the deleted options is removed below.
            $gaoo_where = $wpdb->prepare( 'option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s', $wpdb->esc_like( GAOO_PREFIX ) . '%', $wpdb->esc_like( '_transient_' . GAOO_PREFIX ) . '%', $wpdb->esc_like( '_transient_timeout_' . GAOO_PREFIX ) . '%' );
            $gaoo_names = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE {$gaoo_where}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Prepared above.

            $wpdb->query( "DELETE FROM {$wpdb->options} WHERE {$gaoo_where}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Prepared above.
            // phpcs:enable

            // Only the cache of these options, not the whole object cache (e.g. Redis) of the website.
            foreach ( $gaoo_names as $gaoo_name ) {
                wp_cache_delete( $gaoo_name, 'options' );
            }

            wp_cache_delete( 'alloptions', 'options' );
            wp_cache_delete( 'notoptions', 'options' );
        }

        if ( $gaoo_site_id ) {
            restore_current_blog();
        }
    }

    if ( ! $gaoo_keep_all ) {
        // Data of the users (global table) and of the network: token of the status URL, the site for new sites, the access of the site admins and the webhook.
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s", $wpdb->esc_like( GAOO_PREFIX ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall.

        if ( is_multisite() ) {
            delete_site_option( GAOO_PREFIX . 'network_template' );
            delete_site_option( GAOO_PREFIX . 'network_api_token' );
            delete_site_option( GAOO_PREFIX . 'network_hide_settings' );
            delete_site_option( GAOO_PREFIX . 'network_hide_widget' );

            foreach ( array( 'status', 'url', 'format', 'always', 'result' ) as $gaoo_name ) {
                delete_site_option( GAOO_PREFIX . 'network_webhook_' . $gaoo_name );
            }
        }

        // remove the languages files
        $gaoo_language_files = glob( WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'languages' . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . GAOO_PLUGIN_NAME . '-*' );

        if ( ! empty( $gaoo_language_files ) ) {
            array_walk( $gaoo_language_files, function ( $file ) {
                wp_delete_file( $file );
            } );
        }
    }

    delete_option( GAOO_PREFIX . 'promotion_off' );

