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
                'shortcode',
            ), GAOO_VERSION, false );

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
                // Behavior from the settings, used if the block uses the default.
                'popup_notice'     => 'on' === $options[ 'popup_notice' ],
                'force_reload'     => ! empty( $options[ 'force_reload' ] ),
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
                'popup_type'       => isset( $attributes[ 'popupType' ] ) ? $attributes[ 'popupType' ] : '',
                'force_reload'     => isset( $attributes[ 'forceReload' ] ) ? $attributes[ 'forceReload' ] : '',
            ) );

            if ( empty( $html ) ) {
                return '';
            }

            $class  = empty( $attributes[ 'textAlign' ] ) ? '' : 'has-text-align-' . sanitize_html_class( $attributes[ 'textAlign' ] );
            $id     = wp_unique_id( 'gaoo-block-' );
            $styles = self::link_styles( '.' . $id, $attributes );

            if ( '' !== $styles ) {
                $class .= ' ' . $id;
                $html  = '<style>' . $styles . '</style>' . $html;
            }

            return '<div ' . get_block_wrapper_attributes( array( 'class' => trim( $class ) ) ) . '>' . $html . '</div>';
        }

        /**
         * CSS of the link colors of the block, also on hover. The same as in assets/optout-block.js.
         *
         * @param string $selector   Selector of the block.
         * @param array  $attributes Block attributes.
         *
         * @return string CSS code, empty if no color is set.
         */
        private static function link_styles( $selector, $attributes ) {
            $colors = array();

            foreach ( array( 'linkColor', 'linkHoverColor', 'linkBackground', 'linkHoverBackground', 'noticeColor', 'noticeBackground' ) as $name ) {
                $colors[ $name ] = isset( $attributes[ $name ] ) ? self::sanitize_color( $attributes[ $name ] ) : '';
            }

            // Until version 2.7 the block used the colors of WordPress (text, background, link). Blocks which are not saved again use them for the link.
            // The sources in the order of priority: the color of the link was shown, even if the block had a text color. A name is a preset attribute, an array a path in "style".
            $legacy = array(
                'linkColor'      => array( array( 'elements', 'link', 'color', 'text' ), 'textColor', array( 'color', 'text' ) ),
                'linkHoverColor' => array( array( 'elements', 'link', ':hover', 'color', 'text' ) ),
                'linkBackground' => array( 'backgroundColor', array( 'color', 'background' ) ),
            );

            foreach ( $legacy as $name => $sources ) {
                foreach ( $sources as $source ) {
                    if ( '' !== $colors[ $name ] ) {
                        break;
                    }

                    if ( is_string( $source ) ) {
                        $colors[ $name ] = empty( $attributes[ $source ] ) ? '' : 'var(--wp--preset--color--' . _wp_to_kebab_case( $attributes[ $source ] ) . ')';
                        continue;
                    }

                    $value = isset( $attributes[ 'style' ] ) ? $attributes[ 'style' ] : array();

                    foreach ( $source as $key ) {
                        $value = is_array( $value ) && isset( $value[ $key ] ) ? $value[ $key ] : '';
                    }

                    // Preset colors are stored as "var:preset|color|slug".
                    if ( is_string( $value ) && 0 === strpos( $value, 'var:preset|color|' ) ) {
                        $value = 'var(--wp--preset--color--' . _wp_to_kebab_case( substr( $value, 17 ) ) . ')';
                    }

                    $colors[ $name ] = is_string( $value ) ? self::sanitize_color( $value ) : '';
                }
            }

            $normal = array();
            $hover  = array();
            $notice = array();

            if ( '' !== $colors[ 'linkColor' ] ) {
                $normal[] = 'color:' . $colors[ 'linkColor' ];
            }

            if ( '' !== $colors[ 'linkBackground' ] ) {
                $normal[] = 'background-color:' . $colors[ 'linkBackground' ];
            }

            // With a background, the link gets an inner spacing like a button.
            if ( '' !== $colors[ 'linkBackground' ] || '' !== $colors[ 'linkHoverBackground' ] ) {
                array_push( $normal, 'padding:.25em .6em', 'border-radius:4px', 'text-decoration:none' );
            }

            if ( '' !== $colors[ 'linkHoverColor' ] ) {
                $hover[] = 'color:' . $colors[ 'linkHoverColor' ];
            }

            if ( '' !== $colors[ 'linkHoverBackground' ] ) {
                $hover[] = 'background-color:' . $colors[ 'linkHoverBackground' ];
            }

            // Notice below the link, with a background it gets an inner spacing.
            if ( '' !== $colors[ 'noticeColor' ] ) {
                $notice[] = 'color:' . $colors[ 'noticeColor' ];
            }

            if ( '' !== $colors[ 'noticeBackground' ] ) {
                array_push( $notice, 'background-color:' . $colors[ 'noticeBackground' ], 'padding:.5em .75em', 'border-radius:4px' );
            }

            $css = '';

            if ( ! empty( $normal ) ) {
                $css .= $selector . ' .gaoo-link{' . implode( ';', $normal ) . '}';
            }

            if ( ! empty( $hover ) ) {
                $css .= $selector . ' .gaoo-link:hover,' . $selector . ' .gaoo-link:focus{' . implode( ';', $hover ) . '}';
            }

            if ( ! empty( $notice ) ) {
                $css .= $selector . ' .gaoo-notice{' . implode( ';', $notice ) . '}';
            }

            return $css;
        }

        /**
         * Allow only color values, so the CSS can't be manipulated: hex, rgb(a), hsl(a) and CSS variables of the theme.
         *
         * @param string $color Color value.
         *
         * @return string Color value or empty string.
         */
        public static function sanitize_color( $color ) {
            $color = trim( (string) $color );

            return preg_match( '/^(#[0-9a-f]{3,8}|(rgb|hsl)a?\([0-9.,%\s\/]+\)|var\(--[a-z0-9-]+\))$/i', $color ) ? $color : '';
        }
    }