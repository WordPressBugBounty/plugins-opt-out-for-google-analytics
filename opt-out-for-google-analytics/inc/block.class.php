<?php

    // If this file is called directly, abort.
    defined( 'WPINC' ) || die;

    class GAOO_Block {

        /**
         * @var string HANDLE Handle of the editor script.
         */
        const HANDLE = 'gaoo-block-editor';

        /**
         * @var GAOO_Public $public Used to render the block like the shortcode.
         */
        private $public;

        /**
         * GAOO_Block constructor.
         *
         * @param GAOO_Public $public Instance of the public class.
         */
        public function __construct( $public ) {
            $this->public = $public;

            $this->register();

            add_action( 'enqueue_block_editor_assets', array(
                $this,
                'add_editor_data'
            ) );
        }

        /**
         * Register the block, defined in block/block.json.
         */
        public function register() {
            wp_register_script( self::HANDLE, GAOO_PLUGIN_URL . '/assets/optout-block' . GAOO_Utils::min_suffix() . '.js', array(
                'wp-blocks',
                'wp-element',
                'wp-block-editor',
                'wp-components',
                'wp-i18n',
            ), GAOO_VERSION );

            wp_set_script_translations( self::HANDLE, 'opt-out-for-google-analytics' );

            register_block_type( GAOO_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'block', array(
                'render_callback' => array(
                    $this,
                    'render'
                ),
            ) );
        }

        /**
         * Pass the settings to the editor, to show a preview of the link and a warning if it is not displayed.
         */
        public function add_editor_data() {
            $options = GAOO_Utils::get_options();
            $code    = GAOO_Utils::get_code( $options[ 'ga_plugin' ], $options[ 'ua_code' ] );

            $data = array(
                'is_active'        => ( $options[ 'status' ] != 'off' && ! empty( $code ) ),
                // The link texts are stored HTML encoded.
                'link_deactivate'  => html_entity_decode( $options[ 'link_deactivate' ], ENT_QUOTES, 'UTF-8' ),
                'link_activate'    => html_entity_decode( $options[ 'link_activate' ], ENT_QUOTES, 'UTF-8' ),
                'popup_deactivate' => html_entity_decode( $options[ 'popup_deactivate' ], ENT_QUOTES, 'UTF-8' ),
                'popup_activate'   => html_entity_decode( $options[ 'popup_activate' ], ENT_QUOTES, 'UTF-8' ),
                'settings_url'     => current_user_can( GAOO_CAPABILITY ) ? admin_url( 'options-general.php?page=gaoo' ) : '',
            );

            wp_add_inline_script( self::HANDLE, 'var gaoo_block = ' . wp_json_encode( $data ) . ';', 'before' );
        }

        /**
         * Render the block on the website.
         *
         * @param array  $attributes Block attributes.
         * @param string $content    Saved content of the block. Blocks saved before version 2.5 contain the shortcode, it is not used.
         *
         * @return string HTML code or empty string, if the link should not be displayed.
         */
        public function render( $attributes, $content = '' ) {
            $html = $this->public->shortcode( array(
                'link_deactivate'  => isset( $attributes[ 'linkDeactivate' ] ) ? $attributes[ 'linkDeactivate' ] : '',
                'link_activate'    => isset( $attributes[ 'linkActivate' ] ) ? $attributes[ 'linkActivate' ] : '',
                'popup_deactivate' => isset( $attributes[ 'popupDeactivate' ] ) ? $attributes[ 'popupDeactivate' ] : '',
                'popup_activate'   => isset( $attributes[ 'popupActivate' ] ) ? $attributes[ 'popupActivate' ] : '',
            ) );

            if ( empty( $html ) ) {
                return '';
            }

            $class = empty( $attributes[ 'textAlign' ] ) ? '' : 'has-text-align-' . sanitize_html_class( $attributes[ 'textAlign' ] );

            return '<div ' . get_block_wrapper_attributes( array( 'class' => $class ) ) . '>' . $html . '</div>';
        }
    }