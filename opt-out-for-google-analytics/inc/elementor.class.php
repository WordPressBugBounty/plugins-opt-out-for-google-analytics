<?php

    // If this file is called directly, abort.
    defined( 'WPINC' ) || die;

    /**
     * Elementor widget to display the opt-out link. It uses the same output as the shortcode and the Gutenberg block.
     */
    class GAOO_Elementor_Widget extends \Elementor\Widget_Base {

        /**
         * Name of the widget.
         *
         * @return string
         */
        public function get_name() {
            return 'gaoo-optout';
        }

        /**
         * Title of the widget, shown in the Elementor panel.
         *
         * @return string
         */
        public function get_title() {
            return 'Opt-Out for Google Analytics';
        }

        /**
         * Icon of the widget.
         *
         * @return string
         */
        public function get_icon() {
            return 'eicon-lock-user';
        }

        /**
         * Categories of the widget.
         *
         * @return array
         */
        public function get_categories() {
            return array( 'general' );
        }

        /**
         * Keywords to find the widget.
         *
         * @return array
         */
        public function get_keywords() {
            return array( 'opt out', 'optout', 'opt-out', 'google', 'google analytics', 'gdpr', 'dsgvo', 'privacy' );
        }

        /**
         * Link to the help of the widget.
         *
         * @return string
         */
        public function get_custom_help_url() {
            return 'https://wordpress.org/plugins/opt-out-for-google-analytics/#faq';
        }

        /**
         * The output depends on the cookie of the visitor and enqueues the script, so it must not be cached by Elementor.
         *
         * @return bool
         */
        protected function is_dynamic_content(): bool {
            return true;
        }

        /**
         * Output the link without the additional inner wrapper (optimized markup).
         *
         * @return bool
         */
        public function has_widget_inner_wrapper(): bool {
            return false;
        }

        /**
         * Register the controls of the widget.
         */
        protected function register_controls() {
            $options = GAOO_Utils::get_options();

            // The texts are stored HTML encoded.
            $defaults = array();

            foreach ( array( 'link_deactivate', 'link_activate', 'popup_deactivate', 'popup_activate' ) as $key ) {
                $defaults[ $key ] = html_entity_decode( $options[ $key ], ENT_QUOTES, 'UTF-8' );
            }

            $this->start_controls_section( 'section_link_texts', array(
                'label' => esc_html__( 'Link texts', 'opt-out-for-google-analytics' ),
            ) );

            if ( ! self::is_link_displayed() ) {
                $this->add_control( 'notice_inactive', array(
                    'type'            => \Elementor\Controls_Manager::RAW_HTML,
                    'raw'             => esc_html__( 'The link is not displayed on the website, because the opt-out function is disabled or no valid Google Analytics 4 measurement ID was found.', 'opt-out-for-google-analytics' ) . ' <a href="' . esc_url( admin_url( 'options-general.php?page=gaoo' ) ) . '" target="_blank">' . esc_html__( 'Check the settings', 'opt-out-for-google-analytics' ) . '</a>',
                    'content_classes' => 'elementor-panel-alert elementor-panel-alert-warning',
                ) );
            }

            $this->add_text_control( 'link_deactivate', esc_html__( 'Text of link for deactivate', 'opt-out-for-google-analytics' ), $defaults[ 'link_deactivate' ] );
            $this->add_text_control( 'link_activate', esc_html__( 'Text of link for activate', 'opt-out-for-google-analytics' ), $defaults[ 'link_activate' ] );

            $this->end_controls_section();

            $this->start_controls_section( 'section_popup_texts', array(
                'label' => esc_html__( 'Popup texts', 'opt-out-for-google-analytics' ),
            ) );

            $this->add_text_control( 'popup_deactivate', esc_html__( 'Popup Text for deactivate', 'opt-out-for-google-analytics' ), $defaults[ 'popup_deactivate' ] );
            $this->add_text_control( 'popup_activate', esc_html__( 'Popup Text for activate', 'opt-out-for-google-analytics' ), $defaults[ 'popup_activate' ] );

            $this->end_controls_section();

            $this->start_controls_section( 'section_style', array(
                'label' => esc_html__( 'Link', 'opt-out-for-google-analytics' ),
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            ) );

            $this->add_responsive_control( 'align', array(
                'label'     => esc_html__( 'Alignment', 'opt-out-for-google-analytics' ),
                'type'      => \Elementor\Controls_Manager::CHOOSE,
                'options'   => array(
                    'left'   => array(
                        'title' => esc_html__( 'Left', 'opt-out-for-google-analytics' ),
                        'icon'  => 'eicon-text-align-left',
                    ),
                    'center' => array(
                        'title' => esc_html__( 'Center', 'opt-out-for-google-analytics' ),
                        'icon'  => 'eicon-text-align-center',
                    ),
                    'right'  => array(
                        'title' => esc_html__( 'Right', 'opt-out-for-google-analytics' ),
                        'icon'  => 'eicon-text-align-right',
                    ),
                ),
                'selectors' => array(
                    '{{WRAPPER}}' => 'text-align: {{VALUE}};',
                ),
            ) );

            $this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
                'name'     => 'typography',
                'selector' => '{{WRAPPER}} .gaoo-link',
            ) );

            $this->start_controls_tabs( 'tabs_link_colors' );

            $this->start_controls_tab( 'tab_link_normal', array(
                'label' => esc_html__( 'Normal', 'opt-out-for-google-analytics' ),
            ) );

            $this->add_control( 'link_color', array(
                'label'     => esc_html__( 'Text Color', 'opt-out-for-google-analytics' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .gaoo-link' => 'color: {{VALUE}};',
                ),
            ) );

            $this->end_controls_tab();

            $this->start_controls_tab( 'tab_link_hover', array(
                'label' => esc_html__( 'Hover', 'opt-out-for-google-analytics' ),
            ) );

            $this->add_control( 'link_color_hover', array(
                'label'     => esc_html__( 'Text Color', 'opt-out-for-google-analytics' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .gaoo-link:hover, {{WRAPPER}} .gaoo-link:focus' => 'color: {{VALUE}};',
                ),
            ) );

            $this->end_controls_tab();

            $this->end_controls_tabs();

            $this->end_controls_section();
        }

        /**
         * Add a text control for an individual text. If empty, the text from the settings (placeholder) is used.
         *
         * @param string $name        Name of the control.
         * @param string $label       Label of the control.
         * @param string $placeholder Text from the settings.
         */
        private function add_text_control( $name, $label, $placeholder ) {
            $this->add_control( $name, array(
                'label'       => $label,
                'type'        => \Elementor\Controls_Manager::TEXT,
                'placeholder' => $placeholder,
                'description' => esc_html__( 'Leave empty to use the text from the settings.', 'opt-out-for-google-analytics' ),
                'label_block' => true,
                'ai'          => array(
                    'active' => false,
                ),
            ) );
        }

        /**
         * Check if the link is displayed on the website.
         *
         * @return bool True if the opt-out function is enabled and a valid code was found, otherwise false.
         */
        private static function is_link_displayed() {
            $options = GAOO_Utils::get_options();

            return $options[ 'status' ] != 'off' && ! empty( GAOO_Utils::get_code( $options[ 'ga_plugin' ], $options[ 'ua_code' ] ) );
        }

        /**
         * Render the widget on the website and in the editor.
         */
        protected function render() {
            $settings = $this->get_settings_for_display();
            $public   = GAOO_Public::get_instance();
            $html     = empty( $public ) ? '' : $public->shortcode( array(
                'link_deactivate'  => isset( $settings[ 'link_deactivate' ] ) ? $settings[ 'link_deactivate' ] : '',
                'link_activate'    => isset( $settings[ 'link_activate' ] ) ? $settings[ 'link_activate' ] : '',
                'popup_deactivate' => isset( $settings[ 'popup_deactivate' ] ) ? $settings[ 'popup_deactivate' ] : '',
                'popup_activate'   => isset( $settings[ 'popup_activate' ] ) ? $settings[ 'popup_activate' ] : '',
            ) );

            $is_edit_mode = \Elementor\Plugin::$instance->editor->is_edit_mode();

            // Show a hint in the editor, otherwise the widget would be empty and invisible.
            if ( empty( $html ) && $is_edit_mode ) {
                echo '<div class="elementor-alert elementor-alert-warning">' . esc_html__( 'The link is not displayed on the website, because the opt-out function is disabled or no valid Google Analytics 4 measurement ID was found.', 'opt-out-for-google-analytics' ) . '</div>';

                return;
            }

            // The Elementor editor tries to scroll to links with a "#" href and fails with an error for "#" itself.
            if ( $is_edit_mode ) {
                $html = str_replace( 'href="#"', 'href="javascript:void(0)"', $html );
            }

            echo $html;
        }
    }