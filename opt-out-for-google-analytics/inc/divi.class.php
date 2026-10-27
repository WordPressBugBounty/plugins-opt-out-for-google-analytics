<?php

    // If this file is called directly, abort.
    defined( 'WPINC' ) || die;

    /**
     * Divi module to display the opt-out link. It uses the same output as the shortcode and the Gutenberg block.
     */
    class GAOO_Divi_Module extends ET_Builder_Module {

        /**
         * @var string $slug Slug of the module, also used as shortcode name by Divi.
         */
        public $slug = 'gaoo_optout';

        /**
         * @var string $vb_support Full support of the Visual Builder, with the React component in assets/divi-module.js.
         */
        public $vb_support = 'on';

        /**
         * @var string $icon_path Path to the SVG icon of the module. Declared here, because Divi 4 does not declare it (PHP 8.2 deprecation).
         */
        public $icon_path = '';

        /**
         * @var array $module_credits Credits shown in the module settings.
         */
        protected $module_credits = array(
            'module_uri' => 'https://wordpress.org/plugins/opt-out-for-google-analytics/',
            'author'     => 'Schweizer Solutions GmbH',
            'author_uri' => 'https://www.schweizersolutions.com/',
        );

        /**
         * Initialize the module.
         */
        public function init() {
            $this->name             = 'Opt-Out for Google Analytics';
            $this->plural           = 'Opt-Out for Google Analytics';
            $this->icon_path        = GAOO_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'divi-icon.svg';
            $this->main_css_element = '%%order_class%%';

            $this->settings_modal_toggles = array(
                'general' => array(
                    'toggles' => array(
                        'link_texts'  => esc_html__( 'Link texts', 'opt-out-for-google-analytics' ),
                        'popup_texts' => esc_html__( 'Popup texts', 'opt-out-for-google-analytics' ),
                    ),
                ),
            );

            $this->advanced_fields = array(
                'fonts'        => array(
                    'link' => array(
                        'label' => esc_html__( 'Link', 'opt-out-for-google-analytics' ),
                        'css'   => array(
                            'main'  => '%%order_class%% .gaoo-link',
                            'hover' => '%%order_class%% .gaoo-link:hover',
                        ),
                    ),
                ),
                'text'         => array(
                    'use_background_layout' => false,
                    'css'                   => array(
                        'text_orientation' => '%%order_class%%',
                    ),
                ),
                'button'       => false,
                'link_options' => false,
            );
        }

        /**
         * Fields of the module.
         *
         * @return array
         */
        public function get_fields() {
            $options = GAOO_Utils::get_options();
            $fields  = array(
                'link_deactivate'  => array( esc_html__( 'Text of link for deactivate', 'opt-out-for-google-analytics' ), 'link_texts' ),
                'link_activate'    => array( esc_html__( 'Text of link for activate', 'opt-out-for-google-analytics' ), 'link_texts' ),
                'popup_deactivate' => array( esc_html__( 'Popup Text for deactivate', 'opt-out-for-google-analytics' ), 'popup_texts' ),
                'popup_activate'   => array( esc_html__( 'Popup Text for activate', 'opt-out-for-google-analytics' ), 'popup_texts' ),
            );

            $result = array();

            foreach ( $fields as $name => $field ) {
                $result[ $name ] = array(
                    'label'           => $field[ 0 ],
                    'type'            => 'text',
                    'option_category' => 'basic_option',
                    // The texts are stored HTML encoded.
                    'description'     => sprintf( esc_html__( 'Leave empty to use the text from the settings: %s', 'opt-out-for-google-analytics' ), esc_html( html_entity_decode( $options[ $name ], ENT_QUOTES, 'UTF-8' ) ) ),
                    'toggle_slug'     => $field[ 1 ],
                    'dynamic_content' => 'text',
                );
            }

            return $result;
        }

        /**
         * Render the module on the website.
         *
         * @param array  $attrs       List of unprocessed attributes.
         * @param string $content     Content being processed.
         * @param string $render_slug Slug of module that is used for rendering output.
         *
         * @return string HTML code.
         */
        public function render( $attrs, $content, $render_slug ) {
            $public = GAOO_Public::get_instance();
            $html   = empty( $public ) ? '' : $public->shortcode( array(
                'link_deactivate'  => isset( $this->props[ 'link_deactivate' ] ) ? $this->props[ 'link_deactivate' ] : '',
                'link_activate'    => isset( $this->props[ 'link_activate' ] ) ? $this->props[ 'link_activate' ] : '',
                'popup_deactivate' => isset( $this->props[ 'popup_deactivate' ] ) ? $this->props[ 'popup_deactivate' ] : '',
                'popup_activate'   => isset( $this->props[ 'popup_activate' ] ) ? $this->props[ 'popup_activate' ] : '',
            ) );

            $this->add_classname( $this->get_text_orientation_classname() );

            return $html;
        }

        /**
         * Data for the module in the Visual Builder: preview texts and whether the link is displayed.
         *
         * @return array
         */
        public static function get_builder_data() {
            $options = GAOO_Utils::get_options();

            return array(
                'is_active'       => ( $options[ 'status' ] != 'off' && ! empty( GAOO_Utils::get_code( $options[ 'ga_plugin' ], $options[ 'ua_code' ] ) ) ),
                'link_deactivate' => html_entity_decode( $options[ 'link_deactivate' ], ENT_QUOTES, 'UTF-8' ),
                'notice'          => esc_html__( 'The link is not displayed on the website, because the opt-out function is disabled or no valid Google Analytics 4 measurement ID was found.', 'opt-out-for-google-analytics' ),
            );
        }
    }