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

            $this->add_text_control( 'popup_deactivate', esc_html__( 'Popup Text for deactivate', 'opt-out-for-google-analytics' ), $defaults[ 'popup_deactivate' ], \Elementor\Controls_Manager::TEXTAREA );
            $this->add_text_control( 'popup_activate', esc_html__( 'Popup Text for activate', 'opt-out-for-google-analytics' ), $defaults[ 'popup_activate' ], \Elementor\Controls_Manager::TEXTAREA );

            $this->end_controls_section();

            // Behavior with three states: the setting of the plugin (default), or a value only for this widget.
            $yes            = esc_html__( 'Yes', 'opt-out-for-google-analytics' );
            $no             = esc_html__( 'No', 'opt-out-for-google-analytics' );
            $browser_popup  = esc_html__( 'Browser popup', 'opt-out-for-google-analytics' );
            $text_below     = esc_html__( 'Text below the link', 'opt-out-for-google-analytics' );
            /* translators: %s: value of the settings */
            $like_settings  = esc_html__( 'Like the settings (%s)', 'opt-out-for-google-analytics' );

            $this->start_controls_section( 'section_behavior', array(
                'label' => esc_html__( 'Behavior', 'opt-out-for-google-analytics' ),
            ) );

            $this->add_control( 'popup_type', array(
                'label'   => esc_html__( 'Show the popup text as', 'opt-out-for-google-analytics' ),
                'type'    => \Elementor\Controls_Manager::SELECT,
                'default' => '',
                'options' => array(
                    ''       => sprintf( $like_settings, 'on' === $options[ 'popup_notice' ] ? $text_below : $browser_popup ),
                    'alert'  => $browser_popup,
                    'notice' => $text_below,
                ),
            ) );

            $this->add_control( 'force_reload', array(
                'label'   => esc_html__( 'Reload the page after the click', 'opt-out-for-google-analytics' ),
                'type'    => \Elementor\Controls_Manager::SELECT,
                'default' => '',
                'options' => array(
                    ''    => sprintf( $like_settings, empty( $options[ 'force_reload' ] ) ? $no : $yes ),
                    'yes' => $yes,
                    'no'  => $no,
                ),
            ) );

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

            $this->add_control( 'link_background', array(
                'label'     => esc_html__( 'Background Color', 'opt-out-for-google-analytics' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .gaoo-link' => 'background-color: {{VALUE}};',
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

            $this->add_control( 'link_background_hover', array(
                'label'     => esc_html__( 'Background Color', 'opt-out-for-google-analytics' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .gaoo-link:hover, {{WRAPPER}} .gaoo-link:focus' => 'background-color: {{VALUE}};',
                ),
            ) );

            $this->end_controls_tab();

            $this->end_controls_tabs();

            $this->add_responsive_control( 'link_padding', array(
                'label'      => esc_html__( 'Padding', 'opt-out-for-google-analytics' ),
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array( 'px', 'em', 'rem' ),
                'separator'  => 'before',
                'selectors'  => array(
                    '{{WRAPPER}} .gaoo-link' => 'display: inline-block; padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            ) );

            $this->add_control( 'link_border_radius', array(
                'label'      => esc_html__( 'Border Radius', 'opt-out-for-google-analytics' ),
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array( 'px', '%', 'em' ),
                'selectors'  => array(
                    '{{WRAPPER}} .gaoo-link' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            ) );

            $this->end_controls_section();

            // Notice below the link, if the popup text is not shown as a browser popup.
            $this->start_controls_section( 'section_notice_style', array(
                'label' => esc_html__( 'Notice', 'opt-out-for-google-analytics' ),
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            ) );

            $this->add_control( 'notice_hint', array(
                'type'            => \Elementor\Controls_Manager::RAW_HTML,
                'raw'             => esc_html__( 'The notice is shown below the link after the click, if the popup text is shown as "Text below the link".', 'opt-out-for-google-analytics' ),
                'content_classes' => 'elementor-descriptor',
            ) );

            $this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
                'name'     => 'notice_typography',
                'selector' => '{{WRAPPER}} .gaoo-notice',
            ) );

            $this->add_control( 'notice_color', array(
                'label'     => esc_html__( 'Text Color', 'opt-out-for-google-analytics' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .gaoo-notice' => 'color: {{VALUE}};',
                ),
            ) );

            $this->add_control( 'notice_background', array(
                'label'     => esc_html__( 'Background Color', 'opt-out-for-google-analytics' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .gaoo-notice' => 'background-color: {{VALUE}};',
                ),
            ) );

            $this->add_responsive_control( 'notice_padding', array(
                'label'      => esc_html__( 'Padding', 'opt-out-for-google-analytics' ),
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array( 'px', 'em', 'rem' ),
                'selectors'  => array(
                    '{{WRAPPER}} .gaoo-notice' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            ) );

            $this->add_control( 'notice_border_radius', array(
                'label'      => esc_html__( 'Border Radius', 'opt-out-for-google-analytics' ),
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array( 'px', '%', 'em' ),
                'selectors'  => array(
                    '{{WRAPPER}} .gaoo-notice' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            ) );

            $this->add_responsive_control( 'notice_spacing', array(
                'label'      => esc_html__( 'Spacing', 'opt-out-for-google-analytics' ),
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array( 'px', 'em' ),
                'range'      => array(
                    'px' => array(
                        'min' => 0,
                        'max' => 60,
                    ),
                ),
                'selectors'  => array(
                    '{{WRAPPER}} .gaoo-notice' => 'margin-top: {{SIZE}}{{UNIT}};',
                ),
            ) );

            $this->end_controls_section();
        }

        /**
         * Add a text control for an individual text. If empty, the text from the settings (placeholder) is used.
         *
         * @param string $name        Name of the control.
         * @param string $label       Label of the control.
         * @param string $placeholder Text from the settings.
         * @param string $type        Type of the control, e.g. a textarea for the popup texts. (Default: text)
         */
        private function add_text_control( $name, $label, $placeholder, $type = \Elementor\Controls_Manager::TEXT ) {
            $this->add_control( $name, array(
                'label'       => $label,
                'type'        => $type,
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
                'popup_type'       => isset( $settings[ 'popup_type' ] ) ? $settings[ 'popup_type' ] : '',
                'force_reload'     => isset( $settings[ 'force_reload' ] ) ? $settings[ 'force_reload' ] : '',
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

                // Preview of the notice in the editor, so it can be styled.
                $options   = GAOO_Utils::get_options();
                $is_notice = empty( $settings[ 'popup_type' ] ) ? 'on' === $options[ 'popup_notice' ] : 'notice' === $settings[ 'popup_type' ];
                $text      = empty( $settings[ 'popup_deactivate' ] ) ? html_entity_decode( $options[ 'popup_deactivate' ], ENT_QUOTES, 'UTF-8' ) : $settings[ 'popup_deactivate' ];

                if ( $is_notice && '' !== $text ) {
                    $html .= '<span class="gaoo-notice">' . esc_html( $text ) . '</span>';
                }
            }

            echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by the shortcode, contains the custom CSS.
        }
    }