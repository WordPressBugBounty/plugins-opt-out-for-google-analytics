<?php
    /*
     * Plugin Name: Opt-Out for Google Analytics (DSGVO / GDPR)
     * Plugin URI: https://www.schweizersolutions.com/?utm_source=wordpress&utm_medium=plugin&utm_campaign=plugin_uri
     * Description: Adds the possibility for the user to opt out from Google Analytics. The user will not be tracked by Google Analytics on this site until he allows it again, clears his cookies or uses a different browser.
     * Version: 2.6
     * Requires at least: 5.8
     * Requires PHP: 7.0
     * Author: Schweizer Solutions GmbH
     * Author URI: https://www.schweizersolutions.com/?utm_source=wordpress&utm_medium=plugin&utm_campaign=author_uri
     * License: GPL-2.0+
     * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
     */

    // If this file is called directly, abort.
    defined( 'WPINC' ) || die;

    require_once __DIR__ . DIRECTORY_SEPARATOR . 'constants.php';

    require_once GAOO_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'utils.class.php';
    include_once GAOO_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'activator.class.php';
    include_once GAOO_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'deactivator.class.php';

    // Add custom schedules for the cronjob
    add_filter( 'cron_schedules', array( new GAOO_Utils(), 'add_cron_schedules' ), 9999 );

    register_activation_hook( __FILE__, array( 'GAOO_Activator', 'init' ) );

    // Divi 5 module. Registered here, because Divi builds its dependency tree before the hook "init".
    add_action( 'divi_module_library_modules_dependency_tree', function ( $dependency_tree ) {
        require_once GAOO_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'divi5.class.php';

        $dependency_tree->add_dependency( new GAOO_Divi5_Module() );
    } );

    add_action( 'divi_visual_builder_assets_before_enqueue_scripts', function () {
        if ( class_exists( 'GAOO_Divi5_Module' ) ) {
            GAOO_Divi5_Module::enqueue_builder_script();
        }
    } );
    register_deactivation_hook( __FILE__, array( 'GAOO_Deactivator', 'init' ) );

    Class GAOO {
        /**
         * Handling the start of the plugin
         */
        public function init() {
            $this->load_dependencies();
            $this->run();
        }

        /**
         * Load all classes.
         */
        public function load_dependencies() {
            require_once GAOO_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'singleton.class.php';
            require_once GAOO_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'messages.class.php';

            include_once GAOO_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'promo.class.php';
            include_once GAOO_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'admin.class.php';
            include_once GAOO_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'public.class.php';
            include_once GAOO_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'block.class.php';
        }

        /**
         * Runs initialisation of the plugin
         */
        public function run() {
            // Load translations
            defined( 'GAOO_LOCALE' ) || define( 'GAOO_LOCALE', determine_locale() );

            // Starts Classes
            new GAOO_Admin();
            new GAOO_Block( new GAOO_Public() );

            // Register the Elementor widget, if Elementor is active
            add_action( 'elementor/widgets/register', array( $this, 'register_elementor_widget' ) );

            // Register the Divi module, if the Divi theme or the Divi Builder plugin is active
            add_action( 'et_builder_ready', array( $this, 'register_divi_module' ) );

            // Load activator for MU support
            add_action( 'wp_initialize_site', array( new GAOO_Activator, 'new_blog' ), 100 );
        }

        /**
         * Register the Elementor widget. The file is only loaded here, because the widget extends a class of Elementor.
         *
         * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
         */
        public function register_elementor_widget( $widgets_manager ) {
            require_once GAOO_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'elementor.class.php';

            $widgets_manager->register( new GAOO_Elementor_Widget() );
        }

        /**
         * Register the Divi module. The file is only loaded here, because the module extends a class of Divi.
         */
        public function register_divi_module() {
            if ( ! class_exists( 'ET_Builder_Module' ) ) {
                return;
            }

            require_once GAOO_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'divi.class.php';

            new GAOO_Divi_Module();

            // Preview component for the Visual Builder
            add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_divi_builder_script' ) );
        }

        /**
         * Enqueue the preview component of the Divi module, only in the Visual Builder.
         */
        public function enqueue_divi_builder_script() {
            // The Visual Builder of Divi 5 uses its own module, see inc/divi5.class.php
            if ( ! function_exists( 'et_core_is_fb_enabled' ) || ! et_core_is_fb_enabled() || ( function_exists( 'et_builder_d5_enabled' ) && et_builder_d5_enabled() ) ) {
                return;
            }

            wp_enqueue_script( 'gaoo-divi-module', GAOO_PLUGIN_URL . '/assets/divi-module' . GAOO_Utils::min_suffix() . '.js', array( 'jquery', 'react' ), GAOO_VERSION, true );
            wp_localize_script( 'gaoo-divi-module', 'gaoo_divi', GAOO_Divi_Module::get_builder_data() );
        }

        /**
         * Redirect to setting page, if plugin got activated.
         *
         * @param string $plugin Activated plugin
         */
        public function activated_plugin( $plugin ) {
            if ( isset( $_REQUEST[ 'action' ] ) && $_REQUEST[ 'action' ] == 'activate' && $plugin == plugin_basename( __FILE__ ) ) {
                exit( wp_redirect( esc_url( admin_url( 'options-general.php?page=gaoo' ) ) ) );
            }
        }
    }

    // Start the plugin.
    $gaoo = new GAOO();

    add_action( 'init', array( $gaoo, 'init' ) );
    add_action( 'activated_plugin', array( $gaoo, 'activated_plugin' ) );