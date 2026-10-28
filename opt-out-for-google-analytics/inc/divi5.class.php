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
                'popup_type'       => self::get_text( $attrs, 'popupType' ),
                'force_reload'     => self::get_text( $attrs, 'forceReload' ),
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
                    $elements->style( array(
                        'attrName' => 'notice',
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
         * Translate the texts of module.json (labels, groups and options), because JSON can't contain translatable strings.
         * The default option of the behavior shows the value of the settings, like the block and the Elementor widget.
         *
         * @param array $metadata Content of module.json.
         * @param array $options  Settings of the plugin.
         *
         * @return array Metadata with translated texts.
         */
        public static function translate_metadata( $metadata, $options ) {
            if ( ! is_array( $metadata ) ) {
                return $metadata;
            }

            $yes           = __( 'Yes', 'opt-out-for-google-analytics' );
            $no            = __( 'No', 'opt-out-for-google-analytics' );
            $browser_popup = __( 'Browser popup', 'opt-out-for-google-analytics' );
            $text_below    = __( 'Text below the link', 'opt-out-for-google-analytics' );
            /* translators: %s: value of the settings */
            $like_settings = __( 'Like the settings (%s)', 'opt-out-for-google-analytics' );

            $texts = array(
                'Displays the link, which allows the visitor to disallow or allow the tracking by Google Analytics.' => __( 'Displays the link, which allows the visitor to disallow or allow the tracking by Google Analytics.', 'opt-out-for-google-analytics' ),
                'Leave empty to use the text from the settings.' => __( 'Leave empty to use the text from the settings.', 'opt-out-for-google-analytics' ),
                'Link texts'                                     => __( 'Link texts', 'opt-out-for-google-analytics' ),
                'Popup texts'                                    => __( 'Popup texts', 'opt-out-for-google-analytics' ),
                'Behavior'                                       => __( 'Behavior', 'opt-out-for-google-analytics' ),
                'Text of link for deactivate'                    => __( 'Text of link for deactivate', 'opt-out-for-google-analytics' ),
                'Text of link for activate'                      => __( 'Text of link for activate', 'opt-out-for-google-analytics' ),
                'Popup Text for deactivate'                      => __( 'Popup Text for deactivate', 'opt-out-for-google-analytics' ),
                'Popup Text for activate'                        => __( 'Popup Text for activate', 'opt-out-for-google-analytics' ),
                'Show the popup text as'                         => __( 'Show the popup text as', 'opt-out-for-google-analytics' ),
                'Reload the page after the click'                => __( 'Reload the page after the click', 'opt-out-for-google-analytics' ),
                'Browser popup'                                  => $browser_popup,
                'Text below the link'                            => $text_below,
                'Yes'                                            => $yes,
                'No'                                             => $no,
                'Link'                                           => __( 'Link', 'opt-out-for-google-analytics' ),
                'Link Text'                                      => __( 'Link Text', 'opt-out-for-google-analytics' ),
                'Notice'                                         => __( 'Notice', 'opt-out-for-google-analytics' ),
                'Notice Text'                                    => __( 'Notice Text', 'opt-out-for-google-analytics' ),
                'Notice Background'                              => __( 'Notice Background', 'opt-out-for-google-analytics' ),
                'Notice Spacing'                                 => __( 'Notice Spacing', 'opt-out-for-google-analytics' ),
            );

            // Default option of the behavior, with the value of the settings.
            $defaults = array(
                'popupType'   => sprintf( $like_settings, 'on' === $options[ 'popup_notice' ] ? $text_below : $browser_popup ),
                'forceReload' => sprintf( $like_settings, empty( $options[ 'force_reload' ] ) ? $no : $yes ),
            );

            foreach ( $defaults as $attribute => $label ) {
                if ( isset( $metadata[ 'attributes' ][ $attribute ][ 'settings' ][ 'innerContent' ][ 'item' ][ 'component' ][ 'props' ][ 'options' ][ 'default' ] ) ) {
                    $metadata[ 'attributes' ][ $attribute ][ 'settings' ][ 'innerContent' ][ 'item' ][ 'component' ][ 'props' ][ 'options' ][ 'default' ][ 'label' ] = $label;
                }
            }

            array_walk_recursive( $metadata, function ( &$value, $key ) use ( $texts ) {
                if ( is_string( $value ) && in_array( $key, array( 'label', 'groupLabel', 'fieldLabel', 'description' ), true ) && isset( $texts[ $value ] ) ) {
                    $value = $texts[ $value ];
                }
            } );

            return $metadata;
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
                        'metadata'          => self::translate_metadata( wp_json_file_decode( self::get_folder() . 'module.json', array( 'associative' => true ) ), $options ),
                        'conversionOutline' => wp_json_file_decode( self::get_folder() . 'conversion-outline.json', array( 'associative' => true ) ),
                        'preview'           => array(
                            'isActive'       => ( $options[ 'status' ] != 'off' && ! empty( GAOO_Utils::get_code( $options[ 'ga_plugin' ], $options[ 'ua_code' ] ) ) ),
                            // The texts are stored HTML encoded.
                            'linkDeactivate'  => html_entity_decode( $options[ 'link_deactivate' ], ENT_QUOTES, 'UTF-8' ),
                            'popupDeactivate' => html_entity_decode( $options[ 'popup_deactivate' ], ENT_QUOTES, 'UTF-8' ),
                            'popupNotice'     => 'on' === $options[ 'popup_notice' ],
                            'notice'         => esc_html__( 'The link is not displayed on the website, because the opt-out function is disabled or no valid Google Analytics 4 measurement ID was found.', 'opt-out-for-google-analytics' ),
                        ),
                    ),
                ),
            ) );
        }
    }