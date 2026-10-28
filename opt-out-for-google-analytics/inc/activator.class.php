<?php

// If this file is called directly, abort.
defined( 'WPINC' ) || die;

if ( ! class_exists( 'GAOO_Activator' ) ):
	class GAOO_Activator {
		/**
		 * Init the activation of this plugin, with multisite support.
		 *
		 *
		 * @param bool $network_wide Activation is network wide or not
		 */
		public static function init( $network_wide ) {
			self::check_dependencies();

			// Run the activation with multisite support.
			if ( is_multisite() && $network_wide ) {
				foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $blog_id ) {
					switch_to_blog( $blog_id );
					self::activate();
					restore_current_blog();
				}

				return;
			}

			// Run activation on single site install.
			self::activate();
		}

		/**
		 * Checks if all dependencies for this plugin.
		 * If not matched, the plugin will not be activated.
		 *
		 * @since 1.0
		 *
		 * @global string $wp_version Current version of WordPress.
		 * @global wpdb $wpdb         WordPress database abstraction object.
		 *
		 * @param string $phpv        Mandatory PHP version. (Default: 7.4)
		 * @param string $wpv         Mandatory WordPress version. (Default: 6.3)
		 * @param string $dbv         Mandatory Database version. (Default: 5.6)
		 */
		public static function check_dependencies( $phpv = '7.4', $wpv = '6.3', $dbv = '5.6' ) {
			global $wp_version, $wpdb;

			// Check the installed PHP version.
			if ( version_compare( PHP_VERSION, $phpv, '<' ) ) {
				$text = sprintf( /* translators: %1$s: required PHP version */ __( 'This plugin cannot be activated because it requires a PHP version greater than %1$s. Your PHP version can be updated by your hosting company.', 'opt-out-for-google-analytics' ), $phpv );
				deactivate_plugins( plugin_basename( GAOO_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'ga-opt-out.php' ) );
				wp_die( '<p>' . esc_html( $text ) . '</p> <a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">' . esc_html__( 'Go back', 'opt-out-for-google-analytics' ) . '</a>' );
			}

			// Check the installed WordPress version.
			if ( version_compare( $wp_version, $wpv, '<' ) ) {
				$text = sprintf( /* translators: %1$s: required WordPress version */ __( 'This plugin cannot be activated because it requires a WordPress version greater than %1$s. Please go to Dashboard &#9656; Updates to get the latest version of WordPress.', 'opt-out-for-google-analytics' ), $wpv );
				deactivate_plugins( plugin_basename( GAOO_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'ga-opt-out.php' ) );
				wp_die( '<p>' . esc_html( $text ) . '</p> <a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">' . esc_html__( 'Go back', 'opt-out-for-google-analytics' ) . '</a>' );
			}

			// Check the installed Database version.
			// Version of the database server, without the prefix "5.5.5-" of older MariaDB versions.
			$mysql_version = $wpdb->db_version();

			if ( ! is_null( $mysql_version ) && version_compare( strtok( $mysql_version, '-' ), $dbv, '<' ) ) {
				$text = sprintf( /* translators: %1$s: required database version */ __( 'This plugin cannot be activated because it requires a Database version greater than %1$s. Your Database version can be updated by your hosting company.', 'opt-out-for-google-analytics' ), $dbv );
				deactivate_plugins( plugin_basename( GAOO_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'ga-opt-out.php' ) );
				wp_die( '<p>' . esc_html( $text ) . '</p> <a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">' . esc_html__( 'Go back', 'opt-out-for-google-analytics' ) . '</a>' );
			}
		}

		/**
		 * Run the plugin activation.
		 *
		 * @since 1.0
		 */
		public static function activate() {
			GAOO_Utils::start_cronjob( true );
		}

		/**
		 * Run the activation if a new blog created on multisite.
		 *
		 * @since 1.0
		 *
		 * @param WP_Site $site The new site.
		 */
		public function new_blog( $site ) {
			if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
				require_once ABSPATH . 'wp-admin' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'plugin.php';
			}

			// Run only if plugin is activated network-wide
			if ( ! is_plugin_active_for_network( plugin_basename( GAOO_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'ga-opt-out.php' ) ) ) {
				return;
			}

			switch_to_blog( $site->blog_id );
			self::activate();
			restore_current_blog();
		}
	}

endif;