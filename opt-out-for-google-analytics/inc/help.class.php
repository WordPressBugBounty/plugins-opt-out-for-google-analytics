<?php
    // If this file is called directly, abort.
    defined( 'WPINC' ) || die;

    /**
     * Help tabs of the settings page ("Help" at the top of the page), with the instructions, the FAQ and the hooks for developers.
     */
    class GAOO_Help {

        /**
         * Add the help tabs and the sidebar to the current screen.
         */
        public static function register( $network = false ) {
            $screen = get_current_screen();

            if ( empty( $screen ) ) {
                return;
            }

            $tabs = $network ? array(
                'network' => array( __( 'Overview' ), self::network_overview() ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- String of WordPress itself, so its translation is used.
                'webhook' => array( __( 'Webhook', 'opt-out-for-google-analytics' ), self::webhook( true ) ),
                'api'     => array( __( 'REST API', 'opt-out-for-google-analytics' ), self::api_network() ),
                'cli'     => array( 'WP-CLI', self::cli() ),
            ) : array(
                // Texts of WordPress itself (without text domain), so the existing translation is used.
                'overview'   => array( __( 'Overview' ), self::overview() ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- String of WordPress itself, so its translation is used.
                'shortcode'  => array( __( 'Shortcode', 'opt-out-for-google-analytics' ), self::shortcode() ),
                'status'     => array( __( 'Status check', 'opt-out-for-google-analytics' ), self::status_check() ),
                'webhook'    => array( __( 'Webhook', 'opt-out-for-google-analytics' ), self::webhook() ),
                'api'        => array( __( 'REST API', 'opt-out-for-google-analytics' ), self::api() ),
                'css'        => array( __( 'Custom CSS', 'opt-out-for-google-analytics' ), self::custom_css() ),
                'gtm'        => array( __( 'Google Tag Manager', 'opt-out-for-google-analytics' ), self::tag_manager() ),
                'developers' => array( __( 'Developers', 'opt-out-for-google-analytics' ), self::developers() ),
            );

            foreach ( $tabs as $id => $tab ) {
                $screen->add_help_tab( array(
                    'id'      => 'gaoo-' . $id,
                    'title'   => $tab[ 0 ],
                    'content' => '<div class="gaoo-help">' . $tab[ 1 ] . '</div>',
                ) );
            }

            $sidebar = '<p><strong>' . esc_html__( 'For more information:' ) . '</strong></p>' // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- String of WordPress itself, so its translation is used.
                . '<p><a href="https://wordpress.org/plugins/opt-out-for-google-analytics/#faq" target="_blank">' . esc_html__( 'FAQ', 'opt-out-for-google-analytics' ) . '</a></p>'
                . '<p><a href="https://wordpress.org/support/plugin/opt-out-for-google-analytics/" target="_blank">' . esc_html__( 'Support forum', 'opt-out-for-google-analytics' ) . '</a></p>'
                . '<p><a href="https://wordpress.org/support/plugin/opt-out-for-google-analytics/reviews/?rate=5#new-post" target="_blank">' . esc_html__( 'Rate the plugin', 'opt-out-for-google-analytics' ) . '</a></p>'
                . '<p><a href="' . esc_url( 'https://schweizer.solutions/madeby?utm_content=wp-ga-opt-out' ) . '" target="_blank" rel="noopener">Schweizer Solutions</a></p>';

            // The same promotion as in the dashboard widget, e.g. the privacy policy which is updated automatically.
            $promo = GAOO_Promo::get_widget_link();

            if ( ! empty( $promo ) ) {
                $sidebar .= '<p class="gaoo-help-promo"><a href="' . esc_url( $promo[ 'link' ] ) . '" target="_blank"><span class="dashicons dashicons-privacy" aria-hidden="true"></span>' . esc_html( $promo[ 'link_text' ] ) . '</a></p>';
            }

            $screen->set_help_sidebar( $sidebar );
        }

        /**
         * Paragraph with an escaped text.
         *
         * @param string $text Text, placeholders are replaced by the following arguments, which are not escaped (e.g. code).
         *
         * @return string HTML code.
         */
        private static function p( $text ) {
            $args = array_slice( func_get_args(), 1 );

            return '<p>' . ( empty( $args ) ? esc_html( $text ) : vsprintf( esc_html( $text ), $args ) ) . '</p>';
        }

        /**
         * List with escaped items.
         *
         * @param array  $items List of texts.
         * @param string $tag   Tag of the list. (Default: ul)
         *
         * @return string HTML code.
         */
        private static function items( $items, $tag = 'ul' ) {
            return '<' . $tag . '><li>' . implode( '</li><li>', $items ) . '</li></' . $tag . '>';
        }

        /**
         * Code element.
         *
         * @param string $code Code.
         *
         * @return string HTML code.
         */
        private static function code( $code ) {
            // Entities like &quot; are shown as text, esc_html() would keep them.
            return '<code>' . htmlspecialchars( $code, ENT_QUOTES, 'UTF-8' ) . '</code>';
        }

        /**
         * Code block with a button to copy it.
         *
         * @param string $code Code.
         *
         * @return string HTML code.
         */
        private static function code_block( $code ) {
            return '<div class="gaoo-help-code"><pre><code>' . htmlspecialchars( $code, ENT_QUOTES, 'UTF-8' ) . '</code></pre>'
                . '<button type="button" class="button button-small gaoo-copy" data-copy="' . esc_attr( $code ) . '" title="' . esc_attr__( 'Click to copy the code!', 'opt-out-for-google-analytics' ) . '"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span><span class="gaoo-clipboard"></span></button></div>';
        }

        /**
         * Overview: what the plugin does and how to set it up.
         *
         * @return string HTML code.
         */
        private static function overview() {
            return self::p( __( 'The General Data Protection Regulation (GDPR, German: DSGVO) requires that website visitors can object to the collection of their data by Google Analytics. This plugin adds a link, with which visitors disallow the tracking and allow it again later.', 'opt-out-for-google-analytics' ) )
                . '<h3>' . esc_html__( 'Setup in three steps', 'opt-out-for-google-analytics' ) . '</h3>'
                . self::items( array(
                    esc_html__( 'Select the source of the GA4 measurement ID, or enter it manually.', 'opt-out-for-google-analytics' ),
                    sprintf( /* translators: %s: shortcode */ esc_html__( 'Add the link to your privacy policy: with the shortcode %s, or with the block, the Elementor widget or the Divi module "Opt-Out for Google Analytics".', 'opt-out-for-google-analytics' ), self::code( GAOO_SHORTCODE ) ),
                    esc_html__( 'Select the privacy policy page, so the status check can monitor it, and save the settings.', 'opt-out-for-google-analytics' ),
                ), 'ol' )
                . '<h3>' . esc_html__( 'How does the opt-out work?', 'opt-out-for-google-analytics' ) . '</h3>'
                . self::p( __( 'When a visitor clicks the link, a cookie is set according to the specifications of Google, so Google Analytics does not track this visitor anymore. The cookie is only valid in the browser in which the link was clicked.', 'opt-out-for-google-analytics' ) )
                . self::p( __( 'If the visitor deletes the cookies, the plugin restores the cookie from the local storage of the browser, as long as the local storage was not cleared as well. If all browser data was deleted, the visitor has to click the link again.', 'opt-out-for-google-analytics' ) )
                . '<h3>' . esc_html__( 'Preview, import and export', 'opt-out-for-google-analytics' ) . '</h3>'
                . self::p( __( 'The preview in the box "Add custom CSS" shows the link with the current texts, behavior and custom CSS, also before saving. With "Import / export", the settings can be transferred to another website as JSON file. Before an import, which replaces the current settings, export them as backup. In a multisite network, the network admin can transfer the settings of one site to all other sites, and new sites can get the settings of a site automatically.', 'opt-out-for-google-analytics' ) )
                . '<h3>' . esc_html__( 'Liability', 'opt-out-for-google-analytics' ) . '</h3>'
                . self::p( /* translators: %s: name of the cookie */ __( 'You use this plugin at your own risk. As the website operator, you are responsible for making sure that it works on your website. Check e.g. whether a cookie in the format %s is set after clicking the link.', 'opt-out-for-google-analytics' ), self::code( 'ga-disable-G-XXXXXXXXXX' ) );
        }

        /**
         * Shortcode: attributes, line breaks, example and usage in themes.
         *
         * @return string HTML code.
         */
        private static function shortcode() {
            $example = sprintf( '[ga_optout link_deactivate="%1$s" link_activate="%2$s" popup_deactivate="%3$s\n%4$s" popup_activate="%5$s"]',
                __( 'Disable tracking', 'opt-out-for-google-analytics' ),
                __( 'Enable tracking', 'opt-out-for-google-analytics' ),
                __( 'Tracking is disabled.', 'opt-out-for-google-analytics' ),
                __( 'Thank you for your visit!', 'opt-out-for-google-analytics' ),
                __( 'Tracking is enabled again.', 'opt-out-for-google-analytics' )
            );

            return self::p( /* translators: %s: shortcode */ __( 'You can use the shortcode %s in posts, pages and widgets. In the block editor, in Elementor and in Divi you can also use the block, widget or module "Opt-Out for Google Analytics". There you enter the individual texts in the settings of the element.', 'opt-out-for-google-analytics' ), self::code( GAOO_SHORTCODE ) )
                . '<h3>' . esc_html__( 'Individual texts with attributes', 'opt-out-for-google-analytics' ) . '</h3>'
                . self::p( __( 'All attributes are optional. If an attribute is missing or empty, the text from the settings is used. This way you can use different texts on different pages, e.g. in several languages.', 'opt-out-for-google-analytics' ) )
                . self::p( __( 'In the block editor, a shortcode block with this shortcode can be converted into the block "Opt-Out for Google Analytics" (Transform). The texts are taken over.', 'opt-out-for-google-analytics' ) )
                . '<dl class="gaoo-help-atts">'
                . '<dt>' . self::code( 'link_deactivate' ) . '</dt><dd>' . esc_html__( 'Text of the link, as long as the visitor allows the tracking.', 'opt-out-for-google-analytics' ) . '</dd>'
                . '<dt>' . self::code( 'link_activate' ) . '</dt><dd>' . esc_html__( 'Text of the link, after the visitor has disallowed the tracking.', 'opt-out-for-google-analytics' ) . '</dd>'
                . '<dt>' . self::code( 'popup_deactivate' ) . '</dt><dd>' . esc_html__( 'Text of the popup, after the click to disallow the tracking.', 'opt-out-for-google-analytics' ) . '</dd>'
                . '<dt>' . self::code( 'popup_activate' ) . '</dt><dd>' . esc_html__( 'Text of the popup, after the click to allow the tracking again.', 'opt-out-for-google-analytics' ) . '</dd>'
                /* translators: 1: value, 2: value */
                . '<dt>' . self::code( 'popup_type' ) . '</dt><dd>' . sprintf( esc_html__( 'Show the popup text as browser popup (%1$s) or as text below the link (%2$s).', 'opt-out-for-google-analytics' ), self::code( 'alert' ), self::code( 'notice' ) ) . '</dd>'
                /* translators: 1: value, 2: value */
                . '<dt>' . self::code( 'force_reload' ) . '</dt><dd>' . sprintf( esc_html__( 'Reload the page after the click (%1$s) or not (%2$s).', 'opt-out-for-google-analytics' ), self::code( 'yes' ), self::code( 'no' ) ) . '</dd>'
                . '</dl>'
                . self::p( /* translators: 1: code for a line break, 2: code for a double quote */ __( 'For a line break in a popup text, write %1$s. Double quotes in a text are written as %2$s.', 'opt-out-for-google-analytics' ), self::code( '\n' ), self::code( '&quot;' ) )
                . '<h3>' . esc_html__( 'Example', 'opt-out-for-google-analytics' ) . '</h3>'
                . self::code_block( $example )
                . '<h3>' . esc_html__( 'My theme does not render shortcodes', 'opt-out-for-google-analytics' ) . '</h3>'
                . self::p( __( 'Add the following PHP code to your theme at the position, where the link should be displayed:', 'opt-out-for-google-analytics' ) )
                . self::code_block( "<?php echo do_shortcode( '[ga_optout]' ); ?>" );
        }

        /**
         * Status check: what is checked, the emails and the cronjob.
         *
         * @return string HTML code.
         */
        private static function status_check() {
            return self::p( __( 'The status check regularly checks whether the opt-out still works: the opt-out function is enabled, a valid measurement ID is found, the link is on the privacy policy page, the page is publicly available and the cronjob is active.', 'opt-out-for-google-analytics' ) )
                . self::p( __( 'It runs daily, weekly or monthly. With "Check now" you can run it at any time, the next automatic check is then moved by the interval. A changed setting, e.g. in a Google Analytics plugin, is detected with the next check.', 'opt-out-for-google-analytics' ) )
                . '<h3>' . esc_html__( 'Check of the website', 'opt-out-for-google-analytics' ) . '</h3>'
                . self::p( __( 'The status check also loads the home page like a visitor without consent and checks the HTML:', 'opt-out-for-google-analytics' ) )
                . self::items( array(
                    esc_html__( 'Is the opt-out script loaded, and not removed or delayed by an optimization or caching plugin? Otherwise the opt-out does not work.', 'opt-out-for-google-analytics' ),
                    esc_html__( 'Is Google Analytics loaded with the measurement ID of the settings, and not with another one?', 'opt-out-for-google-analytics' ),
                    esc_html__( 'Is the opt-out link displayed on the privacy policy page? This works with every page builder and theme, also if the link is added in a way the plugin does not know.', 'opt-out-for-google-analytics' ),
                ) )
                . self::p( __( 'If Google Analytics is loaded only after the consent or by the Google Tag Manager, it can not be found in the HTML. This is shown as an information and is not an error. If the website can not load itself, e.g. because the hoster blocks it, this is also shown as an information.', 'opt-out-for-google-analytics' ) )
                . self::p( __( 'If the website is protected with a password by the server (HTTP basic authentication), e.g. a staging website, enter the access data in the settings of the status check. The password is not shown again after saving.', 'opt-out-for-google-analytics' ) )
                . '<h3>' . esc_html__( 'Site Health, WP-CLI and multisite', 'opt-out-for-google-analytics' ) . '</h3>'
                . self::items( array(
                    esc_html__( 'The result is also shown in the Site Health of WordPress (Tools > Site Health).', 'opt-out-for-google-analytics' ),
                    /* translators: 1: command, 2: option */
                    sprintf( esc_html__( 'With WP-CLI: %1$s shows the result, with %2$s the check runs first. The command fails if a check failed, e.g. for a monitoring.', 'opt-out-for-google-analytics' ), self::code( 'wp gaoo status' ), self::code( '--run' ) ),
                    /* translators: 1: command, 2: command */
                    sprintf( esc_html__( 'Export and import of the settings with WP-CLI: %1$s and %2$s, e.g. to set up several websites with the same settings.', 'opt-out-for-google-analytics' ), self::code( 'wp gaoo settings export <file>' ), self::code( 'wp gaoo settings import <file> --yes' ) ),
                    /* translators: 1: command, 2: command */
                    sprintf( esc_html__( 'In a multisite network: %1$s shows the status of all sites (also filtered, e.g. with --status=failed), %2$s starts the check of all sites.', 'opt-out-for-google-analytics' ), self::code( 'wp gaoo network status' ), self::code( 'wp gaoo network check' ) ),
                    esc_html__( 'In a multisite network, the network admin shows the status of all sites, and the check can be started for one or all sites (Network Admin > Settings > GA Opt-Out). The sites can be filtered by the status, searched and sorted. Sites without the active plugin are shown as such.', 'opt-out-for-google-analytics' ),
                    esc_html__( 'With the status URL in the settings of the status check, a monitoring service (e.g. UptimeRobot) can check the result without access to the server: HTTP status 200 if everything is fine, 503 if a check failed. More in the tab "REST API".', 'opt-out-for-google-analytics' ),
                ) )
                . self::p( __( 'A webhook sends the result to a chat (Slack, Microsoft Teams, Discord, Google Chat) or a service like n8n, see the tab "Webhook".', 'opt-out-for-google-analytics' ) )
                . '<h3>' . esc_html__( 'Emails', 'opt-out-for-google-analytics' ) . '</h3>'
                . self::p( /* translators: %s: example with email addresses */ __( 'An email is only sent, if at least one check failed. Several email addresses are separated by a comma, e.g. %s.', 'opt-out-for-google-analytics' ), self::code( 'webmaster@example.org,dev@example.org' ) )
                . self::p( __( 'If you do not receive any emails, there may be the following reasons:', 'opt-out-for-google-analytics' ) )
                . self::items( array(
                    esc_html__( 'The email ended up in the spam folder. Add the sender address to your contacts.', 'opt-out-for-google-analytics' ),
                    esc_html__( 'The WordPress cron is disabled in the wp-config.php, and no server-side cronjob is set up.', 'opt-out-for-google-analytics' ),
                    esc_html__( 'A caching plugin delivers the pages from the cache, so the WordPress cron is not triggered.', 'opt-out-for-google-analytics' ),
                    esc_html__( 'The website has only a few visitors, so the WordPress cron is rarely triggered.', 'opt-out-for-google-analytics' ),
                ) )
                . self::p( __( 'We therefore recommend a server-side cronjob.', 'opt-out-for-google-analytics' ) );
        }

        /**
         * REST API: status of the status check for a monitoring service.
         *
         * @return string HTML code.
         */
        private static function api() {
            $ids = array(
                'opt_out_enabled'   => __( 'The opt-out function is enabled.', 'opt-out-for-google-analytics' ),
                'measurement_id'    => __( 'A valid measurement ID is found.', 'opt-out-for-google-analytics' ),
                'shortcode'         => __( 'The shortcode, block or module is on the privacy policy page.', 'opt-out-for-google-analytics' ),
                'privacy_page_link' => __( 'The opt-out link is displayed on the privacy policy page (check of the website).', 'opt-out-for-google-analytics' ),
                'page_accessible'   => __( 'The privacy policy page is publicly available.', 'opt-out-for-google-analytics' ),
                'cronjob'           => __( 'The cronjob of the status check is active.', 'opt-out-for-google-analytics' ),
                'website_check'     => __( 'The website could be loaded for the check (only if not).', 'opt-out-for-google-analytics' ),
                'opt_out_script'    => __( 'The opt-out script is loaded on the website.', 'opt-out-for-google-analytics' ),
                'google_analytics'  => __( 'Google Analytics is loaded with the measurement ID of the settings.', 'opt-out-for-google-analytics' ),
            );
            $list = array();

            foreach ( $ids as $id => $text ) {
                $list[] = self::code( $id ) . ' &ndash; ' . esc_html( $text );
            }

            /* translators: %s: filter name */
            $list[] = sprintf( esc_html__( 'Own checks of the filter %s: the key "id" is optional, without it they get "custom_1", "custom_2" etc. Set an ID, if you evaluate the check in an own code, because these numbers change when checks are added. The ID is unique: if several checks have the same ID, only the last one is shown, at the place of the first one. So an existing check can also be replaced.', 'opt-out-for-google-analytics' ), self::code( 'gaoo_status_checks' ) );

            return self::p( __( 'The result of the status check can be retrieved as JSON, e.g. by an uptime monitoring (UptimeRobot, Better Stack, Uptime Kuma etc.) or an own script. Enable the status URL in the settings of the status check. It contains a secret token; without it, the endpoint is disabled.', 'opt-out-for-google-analytics' ) )
                . '<h3>' . esc_html__( 'Request', 'opt-out-for-google-analytics' ) . '</h3>'
                . self::code_block( "GET /wp-json/gaoo/v1/status?token=TOKEN\nGET /wp-json/gaoo/v1/status?token=TOKEN&lang=de\n\ncurl -H \"X-GAOO-Token: TOKEN\" https://example.org/wp-json/gaoo/v1/status" )
                . self::items( array(
                    /* translators: 1: parameter, 2: header */
                    sprintf( esc_html__( '%1$s or header %2$s: the token of the status URL. A new token disables the old URL.', 'opt-out-for-google-analytics' ), self::code( 'token' ), self::code( 'X-GAOO-Token' ) ),
                    /* translators: 1: parameter, 2: example, 3: example */
                    sprintf( esc_html__( '%1$s (optional): language of the texts, e.g. %2$s or %3$s. Default is English, also if the language is not installed.', 'opt-out-for-google-analytics' ), self::code( 'lang' ), self::code( 'de' ), self::code( 'de_DE' ) ),
                ) )
                . '<h3>' . esc_html__( 'Response', 'opt-out-for-google-analytics' ) . '</h3>'
                . self::items( array(
                    /* translators: 1: HTTP status, 2: HTTP status, 3: HTTP status */
                    sprintf( esc_html__( 'HTTP status %1$s if no check failed, %2$s if at least one check failed, %3$s if the token is wrong.', 'opt-out-for-google-analytics' ), self::code( '200' ), self::code( '503' ), self::code( '403' ) ),
                    /* translators: 1: value, 2: value */
                    sprintf( esc_html__( '"status" is %1$s or %2$s, "total", "passed", "failed", "unknown" and "info" are the number of checks.', 'opt-out-for-google-analytics' ), self::code( 'ok' ), self::code( 'failed' ) ),
                    /* translators: 1: value, 2: value, 3: value, 4: value */
                    sprintf( esc_html__( '"result" of a check is %1$s, %2$s, %3$s (not checked yet) or %4$s (only an information, not an error).', 'opt-out-for-google-analytics' ), self::code( 'passed' ), self::code( 'failed' ), self::code( 'unknown' ), self::code( 'info' ) ),
                    esc_html__( '"id" of a check does not depend on the language, so it can be used in an own code. "label" is the translated text.', 'opt-out-for-google-analytics' ),
                    esc_html__( '"last_check" and "next_check" are in UTC (ISO 8601), "language" is the language of the texts.', 'opt-out-for-google-analytics' ),
                ) )
                . self::code_block( "{\n  \"status\": \"failed\",\n  \"total\": 8,\n  \"passed\": 6,\n  \"failed\": 1,\n  \"unknown\": 0,\n  \"info\": 1,\n  \"checks\": [\n    { \"id\": \"opt_out_enabled\", \"label\": \"Opt-Out Enabled\", \"result\": \"passed\" },\n    { \"id\": \"shortcode\", \"label\": \"Shortcode found on the page\", \"result\": \"failed\" },\n    { \"id\": \"google_analytics\", \"label\": \"...\", \"result\": \"info\" }\n  ],\n  \"last_check\": \"2026-10-28T06:00:00+00:00\",\n  \"next_check\": \"2026-10-29T06:00:00+00:00\",\n  \"language\": \"en_US\"\n}" )
                . '<h3>' . esc_html__( 'IDs of the checks', 'opt-out-for-google-analytics' ) . '</h3>'
                . self::items( $list )
                . '<h3>' . esc_html__( 'Uptime monitoring', 'opt-out-for-google-analytics' ) . '</h3>'
                . self::items( array(
                    esc_html__( 'Create an HTTP(S) monitor with the status URL. It reports an error, if the status is not 200.', 'opt-out-for-google-analytics' ),
                    /* translators: %s: keyword */
                    sprintf( esc_html__( 'Or a keyword monitor with the keyword %s, if your monitoring service can not check the HTTP status.', 'opt-out-for-google-analytics' ), self::code( '"status":"ok"' ) ),
                    esc_html__( 'The result is the one of the last check. An interval of one hour is enough, the check itself runs daily, weekly or monthly, or with "Check now".', 'opt-out-for-google-analytics' ),
                ) )
                . ( is_multisite() ? '<h3>' . esc_html__( 'Multisite network', 'opt-out-for-google-analytics' ) . '</h3>' . self::api_network() : '' );
        }

        /**
         * REST API of the network: status of all sites.
         *
         * @return string HTML code.
         */
        private static function api_network() {
            return self::p( __( 'In the network admin (Network Admin > Settings > GA Opt-Out), a status URL of the whole network can be created. One monitor checks all sites: HTTP status 200 if no check of a site failed, otherwise 503. The URL works only on the main site and has an own token.', 'opt-out-for-google-analytics' ) )
                . self::code_block( "GET /wp-json/gaoo/v1/network?token=TOKEN\nGET /wp-json/gaoo/v1/network?token=TOKEN&status=failed\n\n{\n  \"status\": \"failed\",\n  \"total\": 3,\n  \"ok\": 1,\n  \"failed\": 1,\n  \"not_checked\": 1,\n  \"inactive\": 0,\n  \"sites\": [\n    { \"id\": 2, \"name\": \"Shop\", \"url\": \"https://example.org/shop/\", \"status\": \"failed\", \"total\": 7, \"passed\": 6, \"failed\": 1, \"failed_checks\": [ \"privacy_page_link\" ], \"last_check\": \"2026-10-28T06:00:00+00:00\" }\n  ]\n}" )
                . self::items( array(
                    /* translators: 1: value, 2: value, 3: value, 4: value */
                    sprintf( esc_html__( '"status" of a site is %1$s, %2$s, %3$s or %4$s (plugin not active). The numbers are always of all sites.', 'opt-out-for-google-analytics' ), self::code( 'ok' ), self::code( 'failed' ), self::code( 'not_checked' ), self::code( 'inactive' ) ),
                    /* translators: %s: parameter */
                    sprintf( esc_html__( '%s (optional): show only the sites with this status, e.g. "failed".', 'opt-out-for-google-analytics' ), self::code( 'status' ) ),
                    esc_html__( '"failed_checks" contains the IDs of the failed checks. The texts of a site are returned by the status URL of the site.', 'opt-out-for-google-analytics' ),
                ) );
        }

        /**
         * Webhook: setup of the chat services and the data.
         *
         * @param bool $network True for the help of the network admin.
         *
         * @return string HTML code.
         */
        private static function webhook( $network = false ) {
            $intro = $network
                ? __( 'The webhook of the network sends the result of the status check of every site to one chat or service, if a check failed, or optionally after every check. Each message contains the name and URL of the site. "Send test" sends the status of all sites. The webhooks of the sites work independently of it.', 'opt-out-for-google-analytics' )
                : __( 'The webhook sends the result of the status check to a chat or another service, if a check failed, or optionally after every check. Enable it with the toggle "Use webhook" in the settings of the status check, the fields are only shown while it is enabled. "Send test" sends the result of the last check, to test the URL. In a multisite network, the network admin can also set a webhook for all sites.', 'opt-out-for-google-analytics' );

            return self::p( $intro )
                . '<h3>' . esc_html__( 'Set up', 'opt-out-for-google-analytics' ) . '</h3>'
                . self::items( array(
                    esc_html__( 'Slack, Mattermost, Google Chat: create an "Incoming Webhook" and select the format "Slack".', 'opt-out-for-google-analytics' ),
                    esc_html__( 'Microsoft Teams: create a workflow "Post to a channel when a webhook request is received" and select the format "Microsoft Teams".', 'opt-out-for-google-analytics' ),
                    esc_html__( 'Discord: create a webhook in the settings of the channel (Integrations) and select the format "Discord".', 'opt-out-for-google-analytics' ),
                    esc_html__( 'n8n, Make, Zapier or an own script: select the format "JSON".', 'opt-out-for-google-analytics' ),
                ) )
                . '<h3>' . esc_html__( 'Format "JSON"', 'opt-out-for-google-analytics' ) . '</h3>'
                . self::p( __( 'The same data as the REST API of the site, with "event" (status_check or test) and the site. The test of the network sends the status of all sites, like the REST API of the network.', 'opt-out-for-google-analytics' ) )
                . self::code_block( "{\n  \"event\": \"status_check\",\n  \"site\": { \"id\": 1, \"name\": \"Example\", \"url\": \"https://example.org/\" },\n  \"status\": \"failed\",\n  \"total\": 8,\n  \"passed\": 6,\n  \"failed\": 1,\n  \"unknown\": 0,\n  \"info\": 1,\n  \"checks\": [ { \"id\": \"privacy_page_link\", \"label\": \"...\", \"result\": \"failed\" } ],\n  \"last_check\": \"2026-10-28T06:00:00+00:00\",\n  \"next_check\": \"2026-10-29T06:00:00+00:00\",\n  \"language\": \"en_US\"\n}" )
                . '<h3>' . esc_html__( 'Security', 'opt-out-for-google-analytics' ) . '</h3>'
                . self::items( array(
                    esc_html__( 'The webhook URL is secret, it is not exported.', 'opt-out-for-google-analytics' ),
                    esc_html__( 'The webhook can not reach addresses of the local network, so a site admin can not reach internal services. A webhook in the own network can be allowed with the filter "http_request_host_is_external".', 'opt-out-for-google-analytics' ),
                    /* translators: %s: filter name */
                    sprintf( esc_html__( 'The data can be changed with the filter %s.', 'opt-out-for-google-analytics' ), self::code( 'gaoo_webhook_payload' ) ),
                ) );
        }

        /**
         * Overview of the network admin.
         *
         * @return string HTML code.
         */
        private static function network_overview() {
            return self::p( __( 'This page has two tabs: "Status of all sites" shows the result of the last status check of all sites, the number shows the sites with a failed check. "Settings" contains the status URL, the webhook and the settings of the sites.', 'opt-out-for-google-analytics' ) )
                . self::items( array(
                    esc_html__( 'Filter the sites by the status, search by name or URL, and sort by a click on the title of a column. Sites with a failed check are shown first.', 'opt-out-for-google-analytics' ),
                    esc_html__( '"Check all sites now" or "Check now" of a site starts the check in the background, by the cron of each site. The page is reloaded after a few seconds.', 'opt-out-for-google-analytics' ),
                    esc_html__( 'Sites where the plugin is not active are shown as such, they are not checked.', 'opt-out-for-google-analytics' ),
                ) )
                . '<h3>' . esc_html__( 'Settings of the sites', 'opt-out-for-google-analytics' ) . '</h3>'
                . self::p( __( 'Shared are the texts, the behavior, the custom CSS and the interval of the status check. Each site keeps its own settings: the source and code of Google Analytics, the privacy policy page, the access data for the check of the website, the email addresses and the webhook.', 'opt-out-for-google-analytics' ) )
                . self::items( array(
                    esc_html__( 'Template site: new sites get its shared settings automatically.', 'opt-out-for-google-analytics' ),
                    esc_html__( '"Transfer to all other sites" transfers the shared settings of a site once to all other sites.', 'opt-out-for-google-analytics' ),
                    esc_html__( 'The webhook, the template site and the access of the site admins are saved together with "Save Changes" at the bottom of the page.', 'opt-out-for-google-analytics' ),
                ) )
                . '<h3>' . esc_html__( 'Access of the site admins', 'opt-out-for-google-analytics' ) . '</h3>'
                . self::items( array(
                    esc_html__( 'Hide the settings page: only super admins can open and change the settings. Site admins still see the dashboard widget and can run the check, but there are no links to the settings.', 'opt-out-for-google-analytics' ),
                    esc_html__( 'Hide the dashboard widget: site admins do not see the status check in the dashboard.', 'opt-out-for-google-analytics' ),
                    esc_html__( 'Both options work independently, super admins always see everything.', 'opt-out-for-google-analytics' ),
                ) );
        }

        /**
         * WP-CLI commands.
         *
         * @return string HTML code.
         */
        private static function cli() {
            return self::p( __( 'All commands with WP-CLI, e.g. for scripts and a monitoring. "wp help gaoo" shows all options.', 'opt-out-for-google-analytics' ) )
                . self::code_block( "# Status of one site, --run runs the check first. Fails (exit code 1), if a check failed.\nwp gaoo status --url=https://example.org/shop/ --run\n\n# Status of all sites, also only the failed sites\nwp gaoo network status\nwp gaoo network status --status=failed --format=json\n\n# Start the check of all sites or of one site\nwp gaoo network check\nwp gaoo network check --site=2\n\n# Export and import of the settings\nwp gaoo settings export gaoo-settings.json\nwp site list --field=url | xargs -I % wp gaoo settings import gaoo-settings.json --yes --url=%" );
        }

        /**
         * Custom CSS: classes, check and formatting.
         *
         * @return string HTML code.
         */
        private static function custom_css() {
            return self::p( __( 'With the custom CSS you change the appearance of the link. It is only loaded on pages with the link. Click on a class in the overview next to the field, to add it to the CSS.', 'opt-out-for-google-analytics' ) )
                . '<dl class="gaoo-help-atts">'
                . '<dt>' . self::code( '.gaoo-link' ) . '</dt><dd>' . esc_html__( 'The link itself.', 'opt-out-for-google-analytics' ) . '</dd>'
                . '<dt>' . self::code( '.gaoo-link-deactivate' ) . '</dt><dd>' . esc_html__( 'The link, as long as the visitor allows the tracking.', 'opt-out-for-google-analytics' ) . '</dd>'
                . '<dt>' . self::code( '.gaoo-link-activate' ) . '</dt><dd>' . esc_html__( 'The link, after the visitor has disallowed the tracking.', 'opt-out-for-google-analytics' ) . '</dd>'
                . '<dt>' . self::code( '.gaoo-notice' ) . '</dt><dd>' . esc_html__( 'The popup text below the link, if it is not shown as a browser popup.', 'opt-out-for-google-analytics' ) . '</dd>'
                . '</dl>'
                . self::p( /* translators: %s: ID of the link */ __( 'The ID %s still works for compatibility, but please use the class. The ID is not unique, if the link is used several times on one page.', 'opt-out-for-google-analytics' ), self::code( '#gaoo-link' ) )
                . self::p( __( 'The CSS is checked while typing and when saving, e.g. for a missing ";" or "}". CSS with errors is not saved. When saving, the CSS is formatted, on the website it is minified automatically.', 'opt-out-for-google-analytics' ) )
                . self::p( __( 'With the switch "Use custom CSS" you can test the website without the CSS, the code is kept.', 'opt-out-for-google-analytics' ) );
        }

        /**
         * Google Tag Manager: cookies and local storage entries.
         *
         * @return string HTML code.
         */
        private static function tag_manager() {
            return self::p( __( 'Google Consent Mode: if the option is enabled (settings "Status"), the opt-out also denies the consent "analytics_storage". This also stops Google Analytics, which is loaded by the Google Tag Manager or with another measurement ID, if the website uses the Consent Mode (e.g. with a cookie banner). On the opt-in, the consent of the cookie banner applies again after the next page load.', 'opt-out-for-google-analytics' ) )
                . self::p( __( 'The opt-out cookie is set according to the specifications of Google, so the Google Tag Manager usually does not need to be adjusted.', 'opt-out-for-google-analytics' ) )
                . self::p( __( 'If you want to prevent the Google Analytics code from loading at all, check in the Google Tag Manager whether one of the following cookies or local storage entries has the value "true". If it is missing or "false", the visitor has not opted out.', 'opt-out-for-google-analytics' ) )
                . '<dl class="gaoo-help-atts">'
                . '<dt>' . self::code( 'ga-disable-G-XXXXXXXXXX' ) . '</dt><dd>' . esc_html__( 'Cookie and local storage entry for your measurement ID.', 'opt-out-for-google-analytics' ) . '</dd>'
                . '<dt>' . self::code( 'ga-opt-out' ) . '</dt><dd>' . esc_html__( 'Generic cookie and local storage entry, independent of the measurement ID.', 'opt-out-for-google-analytics' ) . '</dd>'
                . '</dl>';
        }

        /**
         * Developers: JavaScript event, actions and filters.
         *
         * @return string HTML code.
         */
        private static function developers() {
            return '<h3>' . esc_html__( 'JavaScript event', 'opt-out-for-google-analytics' ) . '</h3>'
                . self::p( /* translators: 1: name of the event, 2: name of the object, 3: property with the value, 4: property with jQuery */ __( 'After a click on the link, the event %1$s is fired on the %2$s object. %3$s is "true" if the visitor disallowed the tracking, and "false" if the visitor allowed it again. With jQuery, the value is in %4$s.', 'opt-out-for-google-analytics' ), self::code( 'gaoptout' ), self::code( 'window' ), self::code( 'event.detail' ), self::code( 'e.originalEvent.detail' ) )
                . self::code_block( "window.addEventListener( 'gaoptout', function ( event ) {\n\tconsole.log( event.detail );\n} );" )
                . '<h3>' . esc_html__( 'Link and texts', 'opt-out-for-google-analytics' ) . '</h3>'
                . self::code_block( "// Individual texts of the shortcode, block, Elementor widget and Divi module. An empty text uses the settings.\nadd_filter( 'gaoo_shortcode_atts', function ( \$atts ) {\n\t// Keys: link_deactivate, link_activate, popup_deactivate, popup_activate\n\treturn \$atts;\n} );\n\n// HTML code of the link, e.g. a wrapper. The class \"gaoo-link\" must be kept.\nadd_filter( 'gaoo_shortcode_html', function ( \$html, \$tracking_code, \$current_status, \$atts ) {\n\treturn '<p class=\"my-optout\">' . \$html . '</p>';\n}, 10, 4 );\n\n// Texts from the settings, also: gaoo_link_activate_text, gaoo_popup_deactivate_text, gaoo_popup_activate_text\nadd_filter( 'gaoo_link_deactivate_text', function ( \$text ) {\n\treturn \$text;\n} );\n\n// Reload the page after the click (true / false)\nadd_filter( 'gaoo_force_reload', '__return_true' );\n\n// Before and after the link, also: gaoo_after_shortcode\nadd_action( 'gaoo_before_shortcode', function ( \$tracking_code, \$current_status ) {\n\t// \$current_status: \"activate\" or \"deactivate\"\n}, 10, 2 );" )
                . '<h3>' . esc_html__( 'Measurement ID and script', 'opt-out-for-google-analytics' ) . '</h3>'
                . self::code_block( "// The GA4 measurement ID in use\nadd_filter( 'gaoo_get_ua_code', function ( \$tracking_code, \$ga_plugin ) {\n\treturn \$tracking_code;\n}, 10, 2 );\n\n// Before and after the script in the head, which disables Google Analytics, also: gaoo_after_head_script\nadd_action( 'gaoo_before_head_script', function ( \$tracking_code ) {\n} );" )
                . '<h3>' . esc_html__( 'Status check', 'opt-out-for-google-analytics' ) . '</h3>'
                . self::code_block( "// Page for the check of the website (default: home page)\nadd_filter( 'gaoo_frontend_check_url', function ( \$url ) {\n\treturn home_url( '/privacy-policy/' );\n} );\n\n// The link was found on the privacy policy page, e.g. for page builders with an own format\nadd_filter( 'gaoo_shortcode_found', function ( \$found, \$page_id, \$page ) {\n\treturn \$found || false !== strpos( (string) get_post_meta( \$page_id, 'my_builder_content', true ), '[ga_optout' );\n}, 10, 3 );\n\n// Own checks: id, label, checked (true = passed, false = failed, null = unknown), url (optional)\nadd_filter( 'gaoo_status_checks', function ( \$checks, \$settings ) {\n\t\$checks[] = array(\n\t\t'id'      => 'cookie_banner', // optional, unique ID, e.g. for the REST API\n\t\t'label'   => 'Cookie banner is active',\n\t\t'checked' => is_plugin_active( 'my-cookie-banner/my-cookie-banner.php' ),\n\t\t'url'     => admin_url( 'plugins.php' ),\n\t);\n\n\treturn \$checks;\n}, 10, 2 );\n\n// Data of the webhook (format: json, slack, teams or discord; \$network: webhook of the network)\nadd_filter( 'gaoo_webhook_payload', function ( \$payload, \$format, \$checks, \$network ) {\n\treturn \$payload;\n}, 10, 4 );\n\n// Number of sites per page in the overview of the network (default: 50)\nadd_filter( 'gaoo_network_per_page', function () {\n\treturn 100;\n} );\n\n// After every check, automatic or \"Check now\"\nadd_action( 'gaoo_status_check_done', function ( \$checks, \$failed ) {\n}, 10, 2 );\n\n// The email, if a check failed, also: gaoo_status_mail_subject, gaoo_status_mail_body\nadd_filter( 'gaoo_status_mail_recipients', function ( \$recipients, \$checks ) {\n\t\$recipients[] = 'monitoring@example.org';\n\n\treturn \$recipients;\n}, 10, 2 );" );
        }
    }
