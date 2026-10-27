<?php

    // If this file is called directly, abort.
    defined( 'WPINC' ) || die;

    use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
    use ET\Builder\FrontEnd\Module\Style;
    use ET\Builder\Packages\Module\Module;
    use ET\Builder\Packages\Module\Options\Css\CssStyle;
    use ET\Builder\Packages\Module\Options\Element\ElementScriptData;
    use ET\Builder\Packages\Module\Options\Text\TextClassnames;
    use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;

    /**
     * Divi 5 module to display the opt-out link. It uses the same output as the shortcode and the Gutenberg block.
     * Existing Divi 4 modules (shortcode gaoo_optout) are converted by Divi with divi5/conversion-outline.json.
     */
    class GAOO_Divi5_Module implements DependencyInterface {

        /**
         * Name of the module, see divi5/module.json.
         */
        const NAME = 'gaoo/optout';

        /**
         * Load the module.
         */
        public function load() {
            add_action( 'init', array( self::class, 'register' ) );
        }

        /**
         * Register the module, defined in divi5/module.json.
         */
        public static function register() {
            ModuleRegistration::register_module( self::get_folder(), array(
                'render_callback' => array( self::class, 'render_callback' ),
            ) );
        }

        /**
         * Folder with module.json and conversion-outline.json.
         *
         * @return string
         */
        public static function get_folder() {
            return GAOO_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'divi5' . DIRECTORY_SEPARATOR;
        }

        /**
         * Returns the value of a text attribute.
         *
         * @param array  $attrs Module attributes.
         * @param string $name  Name of the attribute.
         *
         * @return string
         */
        private static function get_text( $attrs, $name ) {
            return isset( $attrs[ $name ][ 'innerContent' ][ 'desktop' ][ 'value' ] ) ? (string) $attrs[ $name ][ 'innerContent' ][ 'desktop' ][ 'value' ] : '';
        }

        /**
         * Render the module on the website.
         *
         * @param array          $attrs    Module attributes.
         * @param string         $content  Inner content, not used.
         * @param WP_Block       $block    Parsed block.
         * @param ModuleElements $elements Module elements.
         *
         * @return string HTML code.
         */
        public static function render_callback( $attrs, $content, $block, $elements ) {
            $public = GAOO_Public::get_instance();
            $html   = empty( $public ) ? '' : $public->shortcode( array(
                'link_deactivate'  => self::get_text( $attrs, 'linkDeactivate' ),
                'link_activate'    => self::get_text( $attrs, 'linkActivate' ),
                'popup_deactivate' => self::get_text( $attrs, 'popupDeactivate' ),
                'popup_activate'   => self::get_text( $attrs, 'popupActivate' ),
            ) );

            // Nothing to display, e.g. the opt-out function is disabled.
            if ( empty( $html ) ) {
                return '';
            }

            return Module::render( array(
                'orderIndex'          => $block->parsed_block[ 'orderIndex' ],
                'storeInstance'       => $block->parsed_block[ 'storeInstance' ],
                'id'                  => $block->parsed_block[ 'id' ],
                'name'                => $block->block_type->name,
                'moduleCategory'      => $block->block_type->category,
                'attrs'               => $attrs,
                'elements'            => $elements,
                'classnamesFunction'  => array( self::class, 'module_classnames' ),
                'stylesComponent'     => array( self::class, 'module_styles' ),
                'scriptDataComponent' => array( self::class, 'module_script_data' ),
                'children'            => array(
                    $elements->style_components( array( 'attrName' => 'module' ) ),
                    $html,
                ),
            ) );
        }

        /**
         * Styles of the module: design settings of the module and the font of the link.
         *
         * @param array $args Arguments from Divi.
         */
        public static function module_styles( $args ) {
            $attrs       = isset( $args[ 'attrs' ] ) ? $args[ 'attrs' ] : array();
            $elements    = $args[ 'elements' ];
            $settings    = isset( $args[ 'settings' ] ) ? $args[ 'settings' ] : array();
            $order_class = isset( $args[ 'orderClass' ] ) ? $args[ 'orderClass' ] : '';

            Style::add( array(
                'id'            => $args[ 'id' ],
                'name'          => $args[ 'name' ],
                'orderIndex'    => $args[ 'orderIndex' ],
                'storeInstance' => $args[ 'storeInstance' ],
                'styles'        => array(
                    $elements->style( array(
                        'attrName'   => 'module',
                        'styleProps' => array(
                            'disabledOn'     => array(
                                'disabledModuleVisibility' => isset( $settings[ 'disabledModuleVisibility' ] ) ? $settings[ 'disabledModuleVisibility' ] : null,
                            ),
                            'advancedStyles' => array(
                                array(
                                    'componentName' => 'divi/text',
                                    'props'         => array(
                                        'selector' => $order_class,
                                        'attr'     => isset( $attrs[ 'module' ][ 'advanced' ][ 'text' ] ) ? $attrs[ 'module' ][ 'advanced' ][ 'text' ] : array(),
                                    ),
                                ),
                            ),
                        ),
                    ) ),
                    $elements->style( array(
                        'attrName' => 'link',
                    ) ),
                    CssStyle::style( array(
                        'selector'  => $order_class,
                        'attr'      => isset( $attrs[ 'css' ] ) ? $attrs[ 'css' ] : array(),
                        'cssFields' => \WP_Block_Type_Registry::get_instance()->get_registered( self::NAME )->customCssFields,
                    ) ),
                ),
            ) );
        }

        /**
         * Class names of the module, e.g. for the text alignment.
         *
         * @param array $args Arguments from Divi.
         */
        public static function module_classnames( $args ) {
            $classnames = TextClassnames::text_options_classnames( isset( $args[ 'attrs' ][ 'module' ][ 'advanced' ][ 'text' ] ) ? $args[ 'attrs' ][ 'module' ][ 'advanced' ][ 'text' ] : array() );

            if ( $classnames ) {
                $args[ 'classnamesInstance' ]->add( $classnames, true );
            }
        }

        /**
         * Script data of the module, e.g. for animations.
         *
         * @param array $args Arguments from Divi.
         */
        public static function module_script_data( $args ) {
            ElementScriptData::set( array(
                'id'            => isset( $args[ 'id' ] ) ? $args[ 'id' ] : '',
                'selector'      => isset( $args[ 'selector' ] ) ? $args[ 'selector' ] : '',
                'attrs'         => isset( $args[ 'attrs' ][ 'module' ][ 'decoration' ] ) ? $args[ 'attrs' ][ 'module' ][ 'decoration' ] : array(),
                'storeInstance' => isset( $args[ 'storeInstance' ] ) ? $args[ 'storeInstance' ] : null,
            ) );
        }

        /**
         * Register the script for the Visual Builder of Divi 5, with the metadata and the data for the preview.
         */
        public static function enqueue_builder_script() {
            if ( ! function_exists( 'et_builder_d5_enabled' ) || ! et_builder_d5_enabled() || ! et_core_is_fb_enabled() ) {
                return;
            }

            $options = GAOO_Utils::get_options();

            \ET\Builder\VisualBuilder\Assets\PackageBuildManager::register_package_build( array(
                'name'    => 'gaoo-divi5-module',
                'version' => GAOO_VERSION,
                'script'  => array(
                    'src'                => GAOO_PLUGIN_URL . '/assets/divi5-module' . GAOO_Utils::min_suffix() . '.js',
                    'deps'               => array( 'react', 'divi-module-library', 'divi-vendor-wp-hooks' ),
                    'enqueue_top_window' => false,
                    'enqueue_app_window' => true,
                    'data_app_window'    => array(
                        'metadata'          => wp_json_file_decode( self::get_folder() . 'module.json', array( 'associative' => true ) ),
                        'conversionOutline' => wp_json_file_decode( self::get_folder() . 'conversion-outline.json', array( 'associative' => true ) ),
                        'preview'           => array(
                            'isActive'       => ( $options[ 'status' ] != 'off' && ! empty( GAOO_Utils::get_code( $options[ 'ga_plugin' ], $options[ 'ua_code' ] ) ) ),
                            // The texts are stored HTML encoded.
                            'linkDeactivate' => html_entity_decode( $options[ 'link_deactivate' ], ENT_QUOTES, 'UTF-8' ),
                            'notice'         => esc_html__( 'The link is not displayed on the website, because the opt-out function is disabled or no valid Google Analytics 4 measurement ID was found.', 'opt-out-for-google-analytics' ),
                        ),
                    ),
                ),
            ) );
        }
    }