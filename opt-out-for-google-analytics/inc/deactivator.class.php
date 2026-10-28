<?php

// If this file is called directly, abort.
defined( 'WPINC' ) || die;

if ( ! class_exists( 'GAOO_Deactivator' ) ):
	class GAOO_Deactivator {
		/**
		 * Init the deactivation of this plugin, with multisite support.
		 *
		 *
		 * @param bool $network_wide Activation is network wide or not
		 */
		public static function init( $network_wide ) {
			// Run the deactivation with multisite support.
			if ( is_multisite() && $network_wide ) {
				foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $blog_id ) {
					switch_to_blog( $blog_id );
					self::deactivate();
					restore_current_blog();
				}

				return;
			}

			// Run activation on single site install.
			self::deactivate();
		}

		/**
		 * Run the plugin deactivation.
		 */
		public static function deactivate() {
			GAOO_Utils::stop_cronjob();
		}
	}

endif;