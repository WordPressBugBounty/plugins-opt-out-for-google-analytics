<?php

// If this file is called directly, abort.
defined('WPINC') || die;

class GAOO_Admin
{

    private $messages;
    private $plugin;
    /**
     * @var bool $tinymce_button True, if the TinyMCE button is shown on the current page.
     */
    private $tinymce_button = false;

    /**
     * GAOO_Admin constructor.
     */
    public function __construct()
    {
        add_action('admin_menu', array(
            $this,
            'menu'
        ));

        add_action('admin_notices', array(
            $this,
            'admin_notices'
        ));

        add_action('admin_enqueue_scripts', array(
            $this,
            'enqueue_scripts'
        ));

        add_action('save_post_page', array(
            $this,
            'save_page'
        ), 10, 1);

        add_action('gaoo_cronjob', array(
            $this,
            'send_status_mail'
        ));

        add_action('admin_post_gaoo_run_check', array(
            $this,
            'run_status_check'
        ));

        // Export and import of the settings, transfer to other sites of the network.
        foreach (array('gaoo_export' => 'export_settings', 'gaoo_import' => 'import_settings', 'gaoo_network_transfer' => 'network_transfer', 'gaoo_network_settings' => 'network_settings') as $action => $method) {
            add_action('admin_post_' . $action, array(
                $this,
                $method
            ));
        }

        // Create or disable the URL of the status for a monitoring (REST API), of the site and of the whole network.
        add_action('admin_post_gaoo_api_token', array(
            $this,
            'api_token'
        ));

        add_action('admin_post_gaoo_network_api_token', array(
            $this,
            'network_api_token'
        ));

        // Send a test to the webhook, and the webhook of the network.
        foreach (array('gaoo_webhook_test' => 'webhook_test', 'gaoo_network_webhook_test' => 'network_webhook_test') as $action => $method) {
            add_action('admin_post_' . $action, array(
                $this,
                $method
            ));
        }

        // Status check of a site, started in the network admin. It runs by the cron of the site, so its plugins are loaded.
        add_action('admin_post_gaoo_network_check', array(
            $this,
            'network_check'
        ));

        add_action('gaoo_run_check_now', array(
            $this,
            'run_check_now'
        ));

        add_filter('removable_query_args', array(
            $this,
            'removable_query_args'
        ));

        add_filter('site_status_tests', array(
            $this,
            'add_site_health_test'
        ));

        // Overview of all sites in the network admin
        if (is_multisite()) {
            add_action('network_admin_menu', array(
                $this,
                'network_menu'
            ));
        }

        add_action('wp_dashboard_setup', array(
            $this,
            'add_dashboard_widget'
        ));

        add_action('admin_notices', array(
            'GAOO_Promo',
            'render_admin_notice'
        ));


        add_filter('plugin_action_links', array(
            $this,
            'plugin_action_links'
        ), 10, 2);

        add_action('plugin_row_meta', array(
            $this,
            'plugin_row_meta'
        ), 10, 2);

        $this->plugin = GAOO_PLUGIN_NAME . DIRECTORY_SEPARATOR . 'ga-opt-out.php';
        $this->messages = GAOO_Messages::getInstance();
        // Migrate the settings and clear the promotion cache after an update, so the notice will be shown again. Works for every update method (Dashboard, FTP, WP-CLI).
        $old_version = get_option(GAOO_PREFIX . 'version');

        if ($old_version !== GAOO_VERSION) {
            GAOO_Utils::migrate($old_version);
            GAOO_Promo::clear_cache();
            update_option(GAOO_PREFIX . 'version', GAOO_VERSION);
        }

        $this->add_tinymce_button();
    }

    /**
     * Load TinyMCE filters and hooks
     */
    public function add_tinymce_button()
    {
        global $post;

        $post_id = !empty($post) ? $post->ID : filter_input(INPUT_GET, 'post', FILTER_SANITIZE_NUMBER_INT);

        // Check if it is the WP Privacy page and user has access to it
        if (!current_user_can('edit_pages') || empty($post_id) || $post_id != get_option('wp_page_for_privacy_policy', 0) || get_user_option('rich_editing') != 'true') {
            return;
        }

        // The icon of the button is defined in admin.css
        $this->tinymce_button = true;

        add_filter("mce_external_plugins", array(
            $this,
            'add_tinymce_plugin'
        ));
        add_filter('mce_buttons', array(
            $this,
            'register_tinymce_button'
        ));
    }

    /**
     * Render the dashboard widget content.
     */
    public function render_dashboard_widget()
    {
        $this->render_status('dashboard');
    }

    /**
     * Render the status check with summary, progress bar and all checks.
     *
     * @param string $context Where the status is shown: dashboard or settings. The dashboard shows additional links. (Default: dashboard)
     */
    public function render_status($context = 'dashboard')
    {
        $checklist = GAOO_Utils::check_todos(GAOO_Utils::get_options(), true);
        GAOO_Utils::store_result($checklist);

        // Information (e.g. Google Analytics is loaded after the consent) is shown, but not counted.
        $counted = array_filter($checklist, function ($check) {
            return empty($check['info']);
        });
        $passed = count(array_filter(array_column($counted, 'checked'), function ($checked) {
            return $checked === true;
        }));
        $failed = count(array_filter(array_column($counted, 'checked'), function ($checked) {
            return $checked === false;
        }));

        if ($failed > 0) {
            $state = 'todo';
            $icon = 'warning';
            $title = __('Action required', 'opt-out-for-google-analytics');
        } elseif ($passed < count($counted)) {
            $state = 'dontknow';
            $icon = 'info';
            $title = __('Not everything could be checked', 'opt-out-for-google-analytics');
        } else {
            $state = 'done';
            $icon = 'shield-alt';
            $title = __('Everything is fine', 'opt-out-for-google-analytics');
        }

        $icons = array(
            'check' => 'yes-alt',
            'todo' => 'dismiss',
            'dontknow' => 'editor-help',
            'info' => 'info-outline',
        );

        echo '<div class="gaoo-widget gaoo-widget-' . esc_attr($state) . '">';

        // Summary
        echo '<div class="gaoo-widget-summary">';
        echo '<span class="gaoo-widget-summary-icon dashicons dashicons-' . esc_attr($icon) . '" aria-hidden="true"></span>';
        echo '<div><strong>' . esc_html($title) . '</strong>';
        echo '<span>' . esc_html(sprintf(/* translators: 1: number of passed checks, 2: number of all checks */ __('%1$d of %2$d checks passed', 'opt-out-for-google-analytics'), $passed, count($counted))) . '</span></div>';
        echo '</div>';
        echo '<div class="gaoo-widget-progress" role="presentation"><span style="width: ' . esc_attr(round($passed / max(1, count($counted)) * 100)) . '%"></span></div>';

        // Checks
        echo '<ul class="gaoo-widget-checks">';

        foreach ($checklist as $check) {
            $status = !empty($check['info']) ? 'info' : ($check['checked'] === true ? 'check' : ($check['checked'] === false ? 'todo' : 'dontknow'));

            $row = '<span class="gaoo-widget-check-icon dashicons dashicons-' . esc_attr($icons[$status]) . '" aria-hidden="true"></span>';
            $row .= '<span class="gaoo-widget-check-label">' . esc_html(isset($check['text']) ? $check['text'] : $check['label']) . '</span>';

            if (!empty($check['value'])) {
                $row .= '<code>' . esc_html($check['value']) . '</code>';
            }

            echo '<li class="gaoo-widget-check-' . esc_attr($status) . '">';

            // If there is a page to fix the check, the whole row is the link. Not to the settings page, if it is hidden.
            if (!empty($check['url']) && ($this->can_manage() || false === strpos($check['url'], 'page=gaoo'))) {
                echo wp_kses_post('<a class="gaoo-widget-check-row" href="' . esc_url($check['url']) . '" target="_blank" title="' . esc_attr__('Go to page', 'opt-out-for-google-analytics') . '">' . $row . '<span class="gaoo-widget-check-arrow dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span><span class="screen-reader-text">(' . esc_html__('Go to page', 'opt-out-for-google-analytics') . ')</span></a>');
            } else {
                echo wp_kses_post('<div class="gaoo-widget-check-row">' . $row . '</div>');
            }

            echo '</li>';
        }

        echo '</ul>';

        // Footer with the dates of the checks
        $next_check = wp_next_scheduled(GAOO_CRONJOB);
        $last_check = (int) get_option(GAOO_PREFIX . 'last_check', 0);

        if (!empty($last_check) || !empty($next_check)) {
            echo '<div class="gaoo-widget-footer">';

            if (!empty($last_check)) {
                echo '<span class="gaoo-widget-last"><span class="dashicons dashicons-saved" aria-hidden="true"></span>' . esc_html(sprintf(/* translators: %s: date and time of the last check */ __('Last check: %s', 'opt-out-for-google-analytics'), date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $last_check))) . '</span>';
            }

            if (!empty($next_check)) {
                echo '<span class="gaoo-widget-next"><span class="dashicons dashicons-clock" aria-hidden="true"></span>' . esc_html(sprintf(/* translators: %s: date of the next check */ __('Next automatic check: %s', 'opt-out-for-google-analytics'), date_i18n(get_option('date_format'), $next_check))) . '</span>';
            }

            echo '</div>';
        }

        // Actions as a bar with cells of the same width, each cell is clickable. Long texts wrap inside of the cell.
        $actions = array();

        // Run the check now, the next automatic check is moved by the interval.
        if (current_user_can(GAOO_CAPABILITY)) {
            $actions[] = '<a class="gaoo-widget-action" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=gaoo_run_check'), 'gaoo-run-check')) . '" title="' . esc_attr__('Runs the status check now, including the email if a check fails. The next automatic check is moved accordingly.', 'opt-out-for-google-analytics') . '"><span class="dashicons dashicons-update" aria-hidden="true"></span><span>' . esc_html__('Check now', 'opt-out-for-google-analytics') . '</span></a>';
        }

        if ($context === 'dashboard' && $this->can_manage()) {
            $actions[] = '<a class="gaoo-widget-action" href="' . esc_url(admin_url('options-general.php?page=gaoo')) . '"><span class="dashicons dashicons-admin-generic" aria-hidden="true"></span><span>' . esc_html__('Settings') . '</span></a>'; // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- String of WordPress itself, so its translation is used.
        }

        if (!empty($actions)) {
            echo wp_kses_post('<div class="gaoo-widget-actions">' . implode('', $actions) . '</div>');
        }

        $promo = $context === 'dashboard' ? GAOO_Promo::get_widget_link() : null;

        if (!empty($promo)) {
            echo '<a class="gaoo-widget-promo" href="' . esc_url($promo['link']) . '" target="_blank"><span class="dashicons dashicons-privacy" aria-hidden="true"></span>' . esc_html($promo['link_text']) . '<span class="dashicons dashicons-external" aria-hidden="true"></span></a>';
        }

        echo '</div>';
    }

    /**
     * Add new dashboard widgets.
     */
    public function add_dashboard_widget()
    {
        // Only for admins. Disabled on the settings page, or hidden for the site admins by the network admin.
        if (current_user_can(GAOO_CAPABILITY) && GAOO_Utils::get_option('disable_dashboard') != 'on' && !GAOO_Utils::is_hidden_for_user('widget')) {
            wp_add_dashboard_widget('ga-opt-out', esc_html__('Opt-Out for Google Analytics - Status Check', 'opt-out-for-google-analytics'), array(
                $this,
                'render_dashboard_widget'
            ));
        }
    }

    /**
     * Clear the checklist cache after the page saved.
     *
     * @param int $post_id ID of the post
     */
    public function save_page($post_id)
    {
        // run only if this page is configured for the monitoring
        if ($post_id != GAOO_Utils::get_option('privacy_page_id')) {
            return;
        }

        GAOO_Utils::delete_todo_cache();
    }

    /**
     * Add this plugin to TinyMCE.
     *
     * @param array $plugins List with plugin URLs
     *
     * @return array List with plugin URLs
     */
    public function add_tinymce_plugin($plugins)
    {
        $plugins['gaoptout'] = add_query_arg('ver', GAOO_VERSION, GAOO_PLUGIN_URL . '/assets/tinymce' . GAOO_Utils::min_suffix() . '.js');

        return $plugins;
    }

    /**
     * Add buttons to TinyMCE.
     *
     * @param array $buttons List of buttons
     *
     * @return array List of buttons
     */
    public function register_tinymce_button($buttons)
    {
        array_push($buttons, "gaoptout");

        return $buttons;
    }

    /**
     * Add menu items to the admin menu
     */
    public function menu()
    {
        // The network admin can hide the settings page for the site admins, only super admins see it.
        if (GAOO_Utils::is_hidden_for_user('settings')) {
            return;
        }

        $hook_suffix = add_options_page('Opt-Out for Google Analytics', 'GA Opt-Out', GAOO_CAPABILITY, 'gaoo', array(
            $this,
            'menu_settings'
        ));

        if (!empty($hook_suffix)) {
            add_action('load-' . $hook_suffix, array(
                $this,
                'load_settings_page'
            ));
        }
    }

    /**
     * The current user may change the settings: the capability, and the settings page is not hidden by the network admin.
     *
     * @return bool True if allowed.
     */
    private function can_manage()
    {
        return current_user_can(GAOO_CAPABILITY) && !GAOO_Utils::is_hidden_for_user('settings');
    }

    /**
     * Before the settings page is loaded: add the help tabs ("Help" at the top of the page).
     */
    public function load_settings_page()
    {
        require_once GAOO_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'help.class.php';
        require_once GAOO_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'api.class.php';

        GAOO_Help::register();
    }

    /**
     * Customize the action links.
     *
     * @param array $links Actions links.
     * @param string $file Filename of the activated plugin.
     *
     * @return array Action links.
     */
    public function plugin_action_links($links, $file)
    {
        if ($file == $this->plugin && $this->can_manage()) {
            $links[] = '<a href="' . esc_url(admin_url('options-general.php?page=gaoo')) . '">' . esc_html__('Settings') . '</a>'; // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- String of WordPress itself, so its translation is used.
        }

        return $links;
    }

    /**
     * Plugin row meta links
     *
     * @param array $links already defined meta links.
     * @param string $file plugin file path and name being processed.
     *
     * @return array $input
     * @since 1.1
     *
     */
    function plugin_row_meta($links, $file)
    {
        if ($file == $this->plugin) {
            $links[] = "<a href='https://wordpress.org/support/plugin/opt-out-for-google-analytics/reviews/?rate=5#new-post' target='_blank'>" . esc_html__('Rate:', 'opt-out-for-google-analytics') . "<i class='gaoo-rate-stars'>" . "<svg xmlns='http://www.w3.org/2000/svg' width='15' height='15' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' class='feather feather-star'><polygon points='12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2'/></svg>" . "<svg xmlns='http://www.w3.org/2000/svg' width='15' height='15' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' class='feather feather-star'><polygon points='12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2'/></svg>" . "<svg xmlns='http://www.w3.org/2000/svg' width='15' height='15' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' class='feather feather-star'><polygon points='12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2'/></svg>" . "<svg xmlns='http://www.w3.org/2000/svg' width='15' height='15' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' class='feather feather-star'><polygon points='12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2'/></svg>" . "<svg xmlns='http://www.w3.org/2000/svg' width='15' height='15' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' class='feather feather-star'><polygon points='12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2'/></svg>" . "</i></a>";

            // Loaded only here, so the promotion data is not requested on every page load.
            $promotion = GAOO_Promo::get_links();

            if (!empty($promotion)) {
                foreach ($promotion as $item) {
                    $links[] = '<strong><a target="_blank" href="' . esc_url($item['link']) . '">' . esc_html($item['link_text']) . '</a></strong>';
                }
            }
        }

        return $links;
    }

    /**
     * Enqueue scripts and styles for the admin pages. Only on the pages, where they are needed.
     *
     * @param string $hook_suffix The current admin page.
     */
    public function enqueue_scripts($hook_suffix = '')
    {
        $min = GAOO_Utils::min_suffix();
        $is_settings_page = ('settings_page_gaoo' === $hook_suffix);
        $is_network_page = (false !== strpos($hook_suffix, 'gaoo-network'));

        // Dashboard widget, settings page, rating stars in the plugin list and the TinyMCE button.
        if (!$is_settings_page && !$is_network_page && !$this->tinymce_button && !in_array($hook_suffix, array('index.php', 'plugins.php'), true)) {
            return;
        }

        // The time of the file is part of the version, so the browser loads a changed file also without a new version of the plugin.
        $version = function ($file) {
            return GAOO_VERSION . '.' . filemtime(GAOO_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . $file);
        };

        // Small file for the widget, the rating stars and the TinyMCE button, the styles of the pages of the plugin only there.
        wp_enqueue_style('gaoo-widget-styles', GAOO_PLUGIN_URL . '/assets/widget' . $min . '.css', array(), $version('widget' . $min . '.css'));

        if ($is_settings_page || $is_network_page) {
            wp_enqueue_style('gaoo-admin-styles', GAOO_PLUGIN_URL . '/assets/admin' . $min . '.css', array('gaoo-widget-styles'), $version('admin' . $min . '.css'));
        }

        // Run only if functionallity on the right pages
        if ($is_settings_page || $is_network_page) {
            // The settings page and the overview in the network admin have the same look: a framed stage.
            add_filter('admin_body_class', function ($classes) {
                return $classes . ' gaoo-stage';
            });

            // The settings page is framed in the color of the admin menu, like the connectors page. The color depends on the admin color scheme of the user.
            global $_wp_admin_css_colors;

            $color_scheme = get_user_option('admin_color');
            $frame_color = isset($_wp_admin_css_colors[$color_scheme]->colors[0]) ? sanitize_hex_color($_wp_admin_css_colors[$color_scheme]->colors[0]) : '';

            if (!empty($frame_color)) {
                wp_add_inline_style('gaoo-admin-styles', 'body.gaoo-stage { --gaoo-frame: ' . $frame_color . '; }');
            }

            wp_enqueue_script('gaoo-admin-script', GAOO_PLUGIN_URL . '/assets/admin' . $min . '.js', array('jquery'), $version('admin' . $min . '.js'), true);

            require_once GAOO_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'css.class.php';

            wp_localize_script('gaoo-admin-script', 'gaoo', array(
                'edit_link' => esc_url(admin_url('post.php?action=edit&post=%d')),
                'text' => array(
                    // Used as text by the JavaScript, so they are not HTML encoded.
                    'copied' => __('Copied!', 'opt-out-for-google-analytics'),
                    'notcopied' => __("Couldn't copy!", 'opt-out-for-google-analytics'),
                    'transfer' => __('Transfer the settings of the selected site to all other sites? Their settings are overwritten.', 'opt-out-for-google-analytics'),
                    'leave' => __("Leave the page without saving the changes?\n\nOK: Leave the page.\nCancel: Stay on the page.", 'opt-out-for-google-analytics'),
                    // Text before the link in the preview, like in a privacy policy.
                    'preview' => __('You can disallow the collection of your data by Google Analytics:', 'opt-out-for-google-analytics'),
                    'import'  => __("The settings of the file replace the current settings of this page. This can not be undone.\n\nWe recommend to export the current settings first, as backup.\n\nImport the settings now?", 'opt-out-for-google-analytics'),
                    'unsaved' => __("You have unsaved changes. Do you want to save them now?\n\nOK: Save the changes.\nCancel: Do not save.", 'opt-out-for-google-analytics'),
                ),
                // Messages of the CSS check, the same check is done when saving.
                'css_errors' => GAOO_CSS::messages(),
            ));
        }
    }

    /**
     * Add admin notices, if function is enabled and no G-Code is configured.
     */
    public function admin_notices()
    {
        // Result of the import of the settings.
        if (isset($_GET['gaoo-imported']) && current_user_can(GAOO_CAPABILITY)) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only shows a message.
            $imported = (int) $_GET['gaoo-imported']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

            if ($imported < 0) {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('The file could not be imported. Please select a file, which was exported by this plugin.', 'opt-out-for-google-analytics') . '</p></div>';
            } else {
                /* translators: %d: number of settings */
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(sprintf(_n('%d setting was imported.', '%d settings were imported.', $imported, 'opt-out-for-google-analytics'), $imported)) . '</p></div>';
            }
        }

        // Result of the test of the webhook.
        if (isset($_GET['gaoo-webhook']) && current_user_can(GAOO_CAPABILITY)) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only shows a message.
            echo str_replace(' inline', ' is-dismissible', $this->webhook_notice(sanitize_key(wp_unslash($_GET['gaoo-webhook'])), (array) get_option(GAOO_PREFIX . 'webhook_result', array()))); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.EscapeOutput.OutputNotEscaped -- Only shows a message, escaped in the method.
        }

        // Result of the status check, which was started manually.
        if (isset($_GET['gaoo-checked']) && current_user_can(GAOO_CAPABILITY)) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only shows a message.
            $next_check = wp_next_scheduled(GAOO_CRONJOB);
            $message = esc_html__('The status check was run.', 'opt-out-for-google-analytics');

            if (!empty($next_check)) {
                $message .= ' ' . esc_html(sprintf(/* translators: %s: date of the next check */ __('The next automatic check is on %s.', 'opt-out-for-google-analytics'), date_i18n(get_option('date_format'), $next_check)));
            }

            echo '<div class="notice notice-success is-dismissible"><p>' . wp_kses_post($message) . '</p></div>';
        }

        // Current admin page, only to decide which messages are shown.
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only shows a message.

        if ('gaoo' === $page && $this->is_cronjob_disabled()) {
            echo '<div class="notice update-nag is-dismissible"><p>' . esc_html__('It seems that the WordPress cron is disabled in the wp-config.php. Please make sure that a server-side cronjob is set up, otherwise the status check does not run and you will not receive the status emails.', 'opt-out-for-google-analytics') . '</p></div>';
        }

        // Run only if open todos available.
        if (!$this->can_manage() || GAOO_Utils::get_option('disable_monitoring', false) || !GAOO_Utils::has_todos() || 'gaoo' === $page) {
            return;
        }

        echo '<div class="notice notice-error is-dismissible"><p>' . wp_kses_post(sprintf(/* translators: %s: URL of the settings page */ __('Google Analytics does not appear to function in compliance with data protection regulations. Please check the settings <a href="%s">here</a>.', 'opt-out-for-google-analytics'), esc_url(admin_url('options-general.php?page=gaoo')))) . '</p></div>';
    }

    /**
     * Check if cronjob is disabled, if status mails should be send.
     *
     * @return bool True if disabled, otherwise false.
     */
    private function is_cronjob_disabled()
    {
        return defined('DISABLE_WP_CRON') && DISABLE_WP_CRON && !empty(GAOO_Utils::get_option('status_mails', false));
    }

    /**
     * Show the settings page.
     */
    public function menu_settings()
    {
        // Handle form save
        // Submitted data contains slashes (like $_POST), stored options are already unslashed by GAOO_Utils::get_options().
        // Without valid data (e.g. a failed security check), the saved settings are shown.
        $form_data = isset($_POST['gaoo']) ? $this->save_menu_settings() : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- The nonce is checked by save_menu_settings().
        $form_data = empty($form_data) ? GAOO_Utils::get_options() : stripslashes_deep($form_data);

        $ga_plugins = array(
            'monsterinsights' => array(
                'label' => 'MonsterInsights – Google Analytics Dashboard for WordPress (Website Stats Made Easy)',
                'url_install' => $this->plugin_install_url('tab=search&s=Google+Analytics+MonsterInsights'),
                'url_activate' => admin_url('plugins.php?plugin_status=inactive&s=MonsterInsights'),
                'target' => '_blank',
                'is_active' => (is_plugin_active('google-analytics-premium' . DIRECTORY_SEPARATOR . 'googleanalytics-premium.php') || is_plugin_active('google-analytics-for-wordpress' . DIRECTORY_SEPARATOR . 'googleanalytics.php')),
                'is_installed' => (file_exists(WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'google-analytics-premium' . DIRECTORY_SEPARATOR . 'googleanalytics-premium.php') || file_exists(WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'google-analytics-for-wordpress' . DIRECTORY_SEPARATOR . 'googleanalytics.php')),
            ),
            'gadash' => array(
                'label' => 'ExactMetrics – Google Analytics Dashboard for WordPress (Website Stats Plugin)',
                'url_install' => $this->plugin_install_url('tab=search&s=exactmetrics+google+analytics'),
                'url_activate' => admin_url('plugins.php?plugin_status=inactive&s=ExactMetrics'),
                'target' => '_blank',
                'is_active' => is_plugin_active('google-analytics-dashboard-for-wp' . DIRECTORY_SEPARATOR . 'gadwp.php'),
                'is_installed' => file_exists(WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'google-analytics-dashboard-for-wp' . DIRECTORY_SEPARATOR . 'gadwp.php'),
            ),
            'analytify' => array(
                'label' => 'Analytify – Google Analytics Dashboard Plugin For WordPress',
                'url_install' => $this->plugin_install_url('tab=search&s=Analytify'),
                'url_activate' => admin_url('plugins.php?plugin_status=inactive&s=Analytify'),
                'target' => '_blank',
                'is_active' => is_plugin_active('wp-analytify' . DIRECTORY_SEPARATOR . 'wp-analytify.php'),
                'is_installed' => file_exists(WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'wp-analytify' . DIRECTORY_SEPARATOR . 'wp-analytify.php'),
            ),
            'gaga' => array(
                'label' => 'GA Google Analytics',
                'url_install' => $this->plugin_install_url('tab=search&s=GA+Google+Analytics'),
                'url_activate' => admin_url('plugins.php?plugin_status=inactive&s=GA+Google'),
                'target' => '_blank',
                'is_active' => is_plugin_active('ga-google-analytics' . DIRECTORY_SEPARATOR . 'ga-google-analytics.php'),
                'is_installed' => file_exists(WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . '/ga-google-analytics' . DIRECTORY_SEPARATOR . 'ga-google-analytics.php'),
            ),
            'sitekit' => array(
                'label' => 'Site Kit by Google – Analytics, Search Console, AdSense, Speed',
                'url_install' => $this->plugin_install_url('tab=search&s=Google+site+kit'),
                'url_activate' => admin_url('plugins.php?plugin_status=inactive&s=site+kit'),
                'target' => '_blank',
                'is_active' => is_plugin_active('google-site-kit' . DIRECTORY_SEPARATOR . 'google-site-kit.php'),
                'is_installed' => file_exists(WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'google-site-kit' . DIRECTORY_SEPARATOR . 'google-site-kit.php'),
            ),
        );

        extract($form_data);

        // if plugin set as current but not installed or activated, remove it from settings
        if (!empty($ga_plugin) && isset($ga_plugins[$ga_plugin]) && ((isset($ga_plugins[$ga_plugin]['is_active']) && !$ga_plugins[$ga_plugin]['is_active']) || isset($ga_plugins[$ga_plugin]['is_installed']) && !$ga_plugins[$ga_plugin]['is_installed'])) {
            delete_option(GAOO_PREFIX . 'ga_plugin');
            $ga_plugin = null;
        }

        $wp_privacy_page_id = get_option('wp_page_for_privacy_policy', 0);
        $wp_admin_mail = get_option('admin_email', null);

        include_once GAOO_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'settings.php';
    }

    /**
     * URL to search a plugin on the plugin install page. On multisite the page is only available in the network admin and the redirect would lose the search query.
     *
     * @param string $query Query string of the URL.
     *
     * @return string URL to the plugin install page.
     */
    private function plugin_install_url($query)
    {
        $path = 'plugin-install.php?' . $query;

        return is_multisite() ? network_admin_url($path) : admin_url($path);
    }

    /**
     * Handle the submited form data.
     *
     * @return array Stored options with the value.
     */
    private function save_menu_settings()
    {

        // Check if form submited by the right site
        if (!isset($_REQUEST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_REQUEST['_wpnonce'])), 'gaoo-settings')) {
            $this->messages->addError(esc_html__('Security check failed!', 'opt-out-for-google-analytics'));

            return array();
        }

        // Validate inputs
        $skip_save = array();
        // The values are stored slashed and unslashed by GAOO_Utils::get_options(), each value is checked and sanitized below.
        $data = filter_var_array(isset($_POST['gaoo']) && is_array($_POST['gaoo']) ? $_POST['gaoo'] : array(), array( // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
            'ga_plugin' => FILTER_UNSAFE_RAW,
            'link_deactivate' => FILTER_UNSAFE_RAW,
            'link_activate' => FILTER_UNSAFE_RAW,
            'ua_code' => FILTER_UNSAFE_RAW,
            'popup_deactivate' => FILTER_UNSAFE_RAW,
            'popup_activate' => FILTER_UNSAFE_RAW,
            'status' => FILTER_UNSAFE_RAW,
            'privacy_page_id' => FILTER_SANITIZE_NUMBER_INT,
            'disable_monitoring' => FILTER_SANITIZE_NUMBER_INT,
            'force_reload' => FILTER_SANITIZE_NUMBER_INT,
            'wp_privacy_page' => FILTER_SANITIZE_NUMBER_INT,
            'custom_css' => FILTER_UNSAFE_RAW,
            'custom_css_status' => FILTER_UNSAFE_RAW,
            'popup_notice' => FILTER_UNSAFE_RAW,
            'tracking_code' => FILTER_UNSAFE_RAW,
            'status_intervall' => FILTER_UNSAFE_RAW,
            'status_mails' => FILTER_UNSAFE_RAW,
            'uninstall_keep_data' => FILTER_UNSAFE_RAW,
            'disable_dashboard' => FILTER_UNSAFE_RAW,
            'status_mails_sync' => FILTER_SANITIZE_NUMBER_INT,
            'check_auth_user' => FILTER_UNSAFE_RAW,
            'check_auth_pass' => FILTER_UNSAFE_RAW,
            'consent_mode' => FILTER_SANITIZE_NUMBER_INT,
            'webhook_url' => FILTER_UNSAFE_RAW,
            'webhook_format' => FILTER_UNSAFE_RAW,
            'webhook_always' => FILTER_SANITIZE_NUMBER_INT,
            'webhook_status' => FILTER_SANITIZE_NUMBER_INT,
        ), false);

        // Sanitize string / text
        // Text fields, which are not sent (e.g. a manipulated request), are empty instead of null.
        foreach (array('ga_plugin', 'link_deactivate', 'link_activate', 'ua_code', 'popup_deactivate', 'popup_activate', 'custom_css', 'tracking_code', 'status_mails', 'webhook_url') as $text_field) {
            $data[$text_field] = isset($data[$text_field]) && is_scalar($data[$text_field]) ? (string) $data[$text_field] : '';
        }

        $text_fields = array('ga_plugin', 'popup_activate', 'popup_deactivate', 'ua_code', 'link_activate', 'link_deactivate');

        foreach ($text_fields as $text_field) {
            if (empty($data[$text_field])) {
                continue;
            }

            $data[$text_field] = htmlspecialchars($data[$text_field], ENT_QUOTES);
        }

        // Validate checkboxes
        $checkboxes = array('status', 'uninstall_keep_data', 'disable_dashboard', 'custom_css_status', 'popup_notice');

        foreach ($checkboxes as $checkbox) {
            if (!isset($data[$checkbox])) {
                continue;
            }

            if ($data[$checkbox] != 'on' && $data[$checkbox] != 'off') {
                $data[$checkbox] = 'off';
            }
        }

        // Validate G-Code if entered manually, otherwise empty field.
        if ($data['ga_plugin'] == 'manual' && !empty($data['ua_code'])) {
            $data['ua_code'] = GAOO_Utils::validate_ua_code(strtoupper($data['ua_code']));

            if (empty($data['ua_code'])) {
                $this->messages->addError(esc_html__("Please enter a valid G-Code (Google Analytics 4). Format: G-XXXXXXXXXX. Universal Analytics (UA-XXXXXX-Y) is no longer supported.", 'opt-out-for-google-analytics'));
            }
        } else {
            $data['ua_code'] = '';
            $data['tracking_code'] = '';
        }

        // Validate status intervall
        if (isset($data['status_intervall']) && !in_array($data['status_intervall'], array('daily', 'monthly', 'weekly'))) {
            $data['status_intervall'] = 'weekly';
        }

        // Removes space from start and end of the text.
        $data['link_activate'] = trim($data['link_activate']);
        $data['link_deactivate'] = trim($data['link_deactivate']);
        // The popup texts can have several lines, the line breaks are stored as \n.
        $data['popup_activate'] = trim(str_replace("\r\n", "\n", $data['popup_activate']));
        $data['popup_deactivate'] = trim(str_replace("\r\n", "\n", $data['popup_deactivate']));
        $data['tracking_code'] = trim($data['tracking_code']);

        // Only users which are allowed to post unfiltered HTML (e.g. not the site admins in a multisite) may change the tracking code. Removing it is always allowed.
        $old_tracking_code = get_option(GAOO_PREFIX . 'tracking_code', '');

        if (!current_user_can('unfiltered_html') && !empty($data['tracking_code']) && stripslashes($data['tracking_code']) !== stripslashes($old_tracking_code)) {
            $data['tracking_code'] = $old_tracking_code;
            $this->messages->addWarning(esc_html__('The tracking code was not changed, because you are not allowed to insert unfiltered HTML.', 'opt-out-for-google-analytics'));
        }

        // Only users which are allowed to edit CSS (e.g. not the site admins in a multisite) may change the custom CSS, like the "Additional CSS" of WordPress.
        if (!current_user_can('edit_css')) {
            $skip_save[] = 'custom_css';

            if (trim(stripslashes($data['custom_css'])) !== trim(stripslashes(get_option(GAOO_PREFIX . 'custom_css', '')))) {
                $this->messages->addWarning(esc_html__('The custom CSS was not changed, because you are not allowed to edit CSS.', 'opt-out-for-google-analytics'));
            }
        }

        // Check the custom CSS and format it. It is minified on the website.
        if (!empty($data['custom_css']) && !in_array('custom_css', $skip_save, true)) {
            require_once GAOO_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'css.class.php';

            $result = GAOO_CSS::check(stripslashes($data['custom_css']));

            if (!empty($result['errors'])) {
                $skip_save[] = 'custom_css';
                $css_not_saved = true;
                $errors = array_map(function ($error) {
                    return esc_html(GAOO_CSS::message($error));
                }, $result['errors']);

                $this->messages->addWarning(esc_html__('The custom CSS was not saved, because it contains errors. The previously saved CSS is still used. Please correct the errors and save again:', 'opt-out-for-google-analytics') . '<br>' . implode('<br>', $errors));
            } else {
                // Strip HTML tags, so the code can't break out of the style element.
                $data['custom_css'] = wp_slash(wp_strip_all_tags($result['css']));
            }
        }

        // Validate the mail addresses, separated by comma. Don't save, if one of them is invalid.
        if (!empty($data['status_mails'])) {
            $mails = array_filter(array_map('trim', explode(',', $data['status_mails'])), 'strlen');
            $invalid_mails = array_filter($mails, function ($mail) {
                return !is_email($mail);
            });

            if (!empty($invalid_mails)) {
                $this->messages->addError(sprintf(/* translators: %s: list of the invalid email addresses */ esc_html__('Please enter valid email addresses, separated by a comma. Invalid: %s', 'opt-out-for-google-analytics'), esc_html(implode(', ', $invalid_mails))));
            }

            $data['status_mails'] = implode(',', $mails);
        }

        // Webhook: only http(s) URLs, the format must be known.
        $data['webhook_url'] = isset($data['webhook_url']) ? trim((string) $data['webhook_url']) : '';

        if ('' !== $data['webhook_url'] && (!wp_http_validate_url($data['webhook_url']) || !in_array(wp_parse_url($data['webhook_url'], PHP_URL_SCHEME), array('http', 'https'), true))) {
            /* translators: %s: entered URL */
            $this->messages->addError(sprintf(esc_html__('Please enter a valid webhook URL, which starts with https://. Invalid: %s', 'opt-out-for-google-analytics'), esc_html($data['webhook_url'])));
        }

        $data['webhook_url'] = esc_url_raw($data['webhook_url'], array('http', 'https'));
        $data['webhook_format'] = isset($data['webhook_format']) && array_key_exists($data['webhook_format'], GAOO_Utils::webhook_formats()) ? $data['webhook_format'] : 'json';

        if (empty($data['link_deactivate'])) {
            $this->messages->addError(esc_html__("Please enter the text for the deactivate link, otherwise the link is not displayed.", 'opt-out-for-google-analytics'));
        }

        if (empty($data['link_activate'])) {
            $this->messages->addError(esc_html__("Please enter the text for the activate link, otherwise the link is not displayed.", 'opt-out-for-google-analytics'));
        }

        // The form is shown again with the entered values. Not sent checkboxes are off, like the defaults.
        if ($this->messages->hasError()) {
            return array_merge(GAOO_Utils::get_options_list(), $data);
        }

        // Access data for the check of the website. The saved password is never shown, so an empty field keeps it. Without user name, both are deleted.
        $data['check_auth_user'] = isset($data['check_auth_user']) ? sanitize_text_field(wp_unslash($data['check_auth_user'])) : '';
        $data['check_auth_pass'] = isset($data['check_auth_pass']) ? (string) $data['check_auth_pass'] : '';

        if ('' === $data['check_auth_user']) {
            $data['check_auth_pass'] = '';
        } elseif ('' === $data['check_auth_pass']) {
            $skip_save[] = 'check_auth_pass';
        }

        // If an option is not set, fill it with the default value.
        $data = array_merge(GAOO_Utils::get_options_list(), $data);
        $old_status_intervall = GAOO_Utils::get_option('status_intervall');

        // Sync. with WP Privacy page
        if (!empty($data['wp_privacy_page'])) {
            $data['privacy_page_id'] = get_option('wp_page_for_privacy_policy', 0);
        }

        foreach ($data as $k => $v) {
            if (!in_array($k, $skip_save, true)) {
                // The access data are only needed for the status check, so they are not loaded on every page.
                update_option(GAOO_PREFIX . $k, $v, in_array($k, array('check_auth_user', 'check_auth_pass', 'webhook_url', 'webhook_format', 'webhook_always', 'webhook_status'), true) ? false : null);
            }
        }

        // Show warniung if cronjob is disabled
        if ($this->is_cronjob_disabled()) {
            $this->messages->addWarning(esc_html__('It seems that the WordPress cron is disabled in the wp-config.php. Please make sure that a server-side cronjob is set up, otherwise the status check does not run and you will not receive the status emails.', 'opt-out-for-google-analytics'));
        }

        // Restart cronjob etc. if intervall is changed
        if ($old_status_intervall != $data['status_intervall']) {
            GAOO_Utils::delete_todo_cache();
            GAOO_Utils::start_cronjob(true);
        }

        // Only the custom CSS was not saved, if it contains errors.
        $this->messages->addSuccess(empty($css_not_saved) ? esc_html__('Settings saved.', 'opt-out-for-google-analytics') : esc_html__('Settings saved, except the custom CSS.', 'opt-out-for-google-analytics'));

        return $data;
    }

    /**
     * Run the status check now, started by the button in the dashboard widget or on the settings page.
     * It is the same check as the automatic one, including the email if a check fails. The next automatic check is moved by the interval.
     */
    public function run_status_check()
    {
        // Also allowed, if the settings page is hidden for the site admins: the check can be run in the dashboard widget.
        if (!current_user_can(GAOO_CAPABILITY)) {
            wp_die(esc_html__('Sorry, you are not allowed to do that.'), 403); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- String of WordPress itself, so its translation is used.
        }

        check_admin_referer('gaoo-run-check');

        $this->send_status_mail();
        GAOO_Utils::start_cronjob(true, true);

        $redirect = wp_get_referer();

        wp_safe_redirect(add_query_arg('gaoo-checked', 1, $redirect ? $redirect : admin_url('options-general.php?page=gaoo')));
        exit;
    }

    /**
     * Fields of the webhook, on the settings page (in the settings form) and in the network admin (own form).
     * The fields are hidden, while the webhook is disabled.
     *
     * @param bool $network True for the webhook of the network.
     */
    public function webhook_fields($network = false)
    {
        $settings = GAOO_Utils::webhook_settings($network);
        $result = (array) ($network ? get_site_option(GAOO_PREFIX . 'network_webhook_result', array()) : get_option(GAOO_PREFIX . 'webhook_result', array()));
        $name = $network ? 'gaoo_network_webhook' : 'gaoo';
        $id = $network ? 'gaoo-network-webhook' : 'gaoo-webhook';
        $test_url = $network ? wp_nonce_url(admin_url('admin-post.php?action=gaoo_network_webhook_test'), 'gaoo-network-webhook-test') : wp_nonce_url(admin_url('admin-post.php?action=gaoo_webhook_test'), 'gaoo-webhook-test');

        echo '<div class="gaoo-toggle-row gaoo-webhook-switch">';
        echo '<label class="gaoo-toggle" for="' . esc_attr($id) . '-status">';
        echo '<input type="hidden" name="' . esc_attr($name) . '[webhook_status]" value="0">';
        echo '<input type="checkbox" id="' . esc_attr($id) . '-status" class="gaoo-section-toggle" name="' . esc_attr($name) . '[webhook_status]" value="1" aria-controls="' . esc_attr($id) . '-fields" ' . checked($settings['enabled'], true, false) . '>';
        echo '<span class="gaoo-toggle-text"><strong>' . esc_html__('Use webhook', 'opt-out-for-google-analytics') . '</strong>';
        echo '<span class="description">' . ($network ? esc_html__('Sends the result of the status check of every site to one chat or service, if a check failed. The webhooks of the sites work independently.', 'opt-out-for-google-analytics') : esc_html__('Sends the result of the status check to a chat (Slack, Microsoft Teams, Discord, Google Chat) or a service like n8n, Make or Zapier, if a check failed.', 'opt-out-for-google-analytics'))
            // Only while the webhook is enabled: the instructions in the help.
            . ' <button type="button" class="button-link gaoo-open-help gaoo-if-on" data-tab="gaoo-webhook"' . ($settings['enabled'] ? '' : ' hidden') . '>' . esc_html__('Instructions for Slack, Teams, Discord and n8n', 'opt-out-for-google-analytics') . '</button></span></span>';
        echo '</label></div>';

        echo '<div class="gaoo-webhook-fields" id="' . esc_attr($id) . '-fields"' . ($settings['enabled'] ? '' : ' hidden') . '>';
        echo '<div class="gaoo-grid">';
        echo '<div class="gaoo-field"><label for="' . esc_attr($id) . '-url">' . esc_html__('Webhook URL', 'opt-out-for-google-analytics') . '</label>';
        echo '<input type="url" id="' . esc_attr($id) . '-url" name="' . esc_attr($name) . '[webhook_url]" class="large-text code" value="' . esc_attr($settings['url']) . '" placeholder="https://" autocomplete="off" spellcheck="false" aria-describedby="' . esc_attr($id) . '-url-description">';
        echo '<p class="description" id="' . esc_attr($id) . '-url-description">' . esc_html__('The URL is secret, it is not exported.', 'opt-out-for-google-analytics') . '</p></div>';
        echo '<div class="gaoo-field"><label for="' . esc_attr($id) . '-format">' . esc_html__('Format', 'opt-out-for-google-analytics') . '</label>';
        echo '<select id="' . esc_attr($id) . '-format" name="' . esc_attr($name) . '[webhook_format]">';

        foreach (GAOO_Utils::webhook_formats() as $key => $label) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($settings['format'], $key, false) . '>' . esc_html($label) . '</option>';
        }

        echo '</select></div></div>';

        echo '<div class="gaoo-toggle-row">';
        echo '<label class="gaoo-toggle" for="' . esc_attr($id) . '-always">';
        echo '<input type="hidden" name="' . esc_attr($name) . '[webhook_always]" value="0">';
        echo '<input type="checkbox" id="' . esc_attr($id) . '-always" name="' . esc_attr($name) . '[webhook_always]" value="1" ' . checked($settings['always'], true, false) . '>';
        echo '<span class="gaoo-toggle-text">' . esc_html__('Send the result after every status check, also if everything is fine.', 'opt-out-for-google-analytics') . '</span>';
        echo '</label></div>';

        // Test with the saved URL, and the result of the last request.
        if ('' !== $settings['url']) {
            echo '<p class="gaoo-api-actions gaoo-webhook-actions">';
            echo '<a class="button button-small" href="' . esc_url($test_url) . '">' . esc_html__('Send test', 'opt-out-for-google-analytics') . '</a>';

            if (!empty($result['time'])) {
                $ok = $result['code'] >= 200 && $result['code'] < 300;

                /* translators: 1: date and time, 2: HTTP status or error message */
                echo '<span class="description">' . esc_html(sprintf(__('Last sent: %1$s, %2$s', 'opt-out-for-google-analytics'), wp_date(get_option('date_format') . ' ' . get_option('time_format'), $result['time']), $result['code'] ? 'HTTP ' . $result['code'] : $result['error'])) . '</span>';
                echo '<span class="dashicons ' . ($ok ? 'dashicons-yes-alt gaoo-webhook-ok' : 'dashicons-warning gaoo-webhook-error') . '" aria-hidden="true"></span>';
            }

            echo '</p>';
        }

        echo '</div>';
    }

    /**
     * Footer of the settings page and of the overview in the network admin.
     */
    public function render_footer()
    {
        $stars = str_repeat("<svg xmlns='http://www.w3.org/2000/svg' width='15' height='15' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><polygon points='12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2' /></svg>", 5);

        echo '<div class="gaoo-footer"><span>';
        /* translators: %s: name of the company */
        printf(esc_html__('Made for you by %s', 'opt-out-for-google-analytics'), '<a href="' . esc_url('https://schweizer.solutions/madeby?utm_content=wp-ga-opt-out') . '" target="_blank" rel="noopener">Schweizer Solutions</a>');
        echo '<span class="gaoo-footer-separator" aria-hidden="true">&middot;</span>';
        echo '<a class="gaoo-rate" href="https://wordpress.org/support/plugin/opt-out-for-google-analytics/reviews/?rate=5#new-post" target="_blank">' . esc_html__('Rate the plugin', 'opt-out-for-google-analytics') . ' <span class="gaoo-rate-stars">' . $stars . '</span></a></span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG code.
        /* translators: %s: version number of the plugin */
        echo '<span>' . esc_html(sprintf(__('Version %s', 'opt-out-for-google-analytics'), GAOO_VERSION)) . '</span>';
        echo '</div>';
    }

    /**
     * Add the overview of all sites to the network admin (Settings > GA Opt-Out).
     */
    public function network_menu()
    {
        $hook_suffix = add_submenu_page('settings.php', 'Opt-Out for Google Analytics', 'GA Opt-Out', 'manage_network_options', 'gaoo-network', array(
            $this,
            'network_page'
        ));

        // Help of the network admin, like on the settings page.
        if (!empty($hook_suffix)) {
            add_action('load-' . $hook_suffix, function () {
                require_once GAOO_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'help.class.php';

                GAOO_Help::register(true);
            });
        }
    }

    /**
     * Overview of the status check of all sites in the network. The results are stored by the status check of each site.
     * The sites can be filtered by the status, searched, sorted and are shown in pages.
     */
    public function network_page()
    {
        require_once GAOO_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'api.class.php';

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Filter and sorting of the list, nothing is changed.
        $filter = isset($_GET['gaoo_status']) ? sanitize_key(wp_unslash($_GET['gaoo_status'])) : '';
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $orderby = isset($_GET['orderby']) && in_array($_GET['orderby'], array('name', 'status', 'last_check'), true) ? sanitize_key($_GET['orderby']) : 'status';
        $order = isset($_GET['order']) && 'desc' === $_GET['order'] ? 'desc' : 'asc';
        $paged = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
        // The page has two tabs: the status of all sites (default) and the settings of the network.
        $tab = isset($_GET['tab']) && 'settings' === $_GET['tab'] ? 'settings' : 'status';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        /**
         * Number of sites per page in the overview of the network.
         *
         * @param int $per_page Number of sites. (Default: 50)
         */
        $per_page = max(1, (int) apply_filters('gaoo_network_per_page', 50));
        $all_sites = GAOO_Utils::network_sites();
        $statuses = array(
            '' => __('All', 'opt-out-for-google-analytics'),
            'failed' => __('Action required', 'opt-out-for-google-analytics'),
            'ok' => __('Everything is fine', 'opt-out-for-google-analytics'),
            'not_checked' => __('Not checked yet', 'opt-out-for-google-analytics'),
            'inactive' => __('Plugin not active', 'opt-out-for-google-analytics'),
        );
        $counts = array_fill_keys(array_keys($statuses), 0);
        $base_url = network_admin_url('settings.php?page=gaoo-network');

        foreach ($all_sites as $site) {
            $counts['']++;
            $counts[$site['status']]++;
        }

        // Filter by status and search in the name and the URL.
        $sites = array_values(array_filter($all_sites, function ($site) use ($filter, $search) {
            return ('' === $filter || $filter === $site['status'])
                && ('' === $search || false !== stripos($site['name'] . ' ' . $site['url'], $search));
        }));

        // Sort: by status the sites with errors first, then not checked, fine and inactive.
        $rank = array('failed' => 0, 'not_checked' => 1, 'ok' => 2, 'inactive' => 3);

        usort($sites, function ($a, $b) use ($orderby, $order, $rank) {
            if ('status' === $orderby) {
                $result = $rank[$a['status']] - $rank[$b['status']];
            } elseif ('last_check' === $orderby) {
                $result = $a['last_check'] - $b['last_check'];
            } else {
                $result = 0;
            }

            $result = 0 === $result ? strcasecmp($a['name'], $b['name']) : $result;

            return 'desc' === $order ? -$result : $result;
        });

        $total = count($sites);
        $pages = max(1, (int) ceil($total / $per_page));
        $paged = min($paged, $pages);
        $sites = array_slice($sites, ($paged - 1) * $per_page, $per_page);
        $list_url = add_query_arg(array_filter(array('gaoo_status' => $filter, 's' => $search, 'orderby' => $orderby, 'order' => $order)), $base_url);

        // Same look as the settings page: framed stage with header, cards and footer.
        echo '<div class="wrap gaoo-settings gaoo-network" id="gaoo"><div class="gaoo-settings-scroll">';
        echo '<div class="gaoo-header"><div><h1>Opt-Out for Google Analytics</h1>';
        echo '<p>' . esc_html__('Multisite network: status check and settings of all sites.', 'opt-out-for-google-analytics') . '</p>';
        echo '<p class="gaoo-disclaimer">' . esc_html__('This plugin supports you with the opt-out, but cannot cover every legal requirement of your website. No liability is assumed.', 'opt-out-for-google-analytics') . '</p></div></div>';
        echo '<hr class="wp-header-end">';
        echo '<div class="gaoo-main">';

        // Tabs: the table can be long, so the settings have their own tab. The number of sites with a failed check is shown.
        $tabs = array(
            'status' => __('Status of all sites', 'opt-out-for-google-analytics'),
            'settings' => __('Settings', 'opt-out-for-google-analytics'),
        );

        echo '<nav class="gaoo-tabs" aria-label="' . esc_attr__('Network', 'opt-out-for-google-analytics') . '">';

        foreach ($tabs as $key => $label) {
            echo '<a href="' . esc_url('status' === $key ? $base_url : add_query_arg('tab', $key, $base_url)) . '"' . ($key === $tab ? ' class="is-current" aria-current="page"' : '') . '>' . esc_html($label);

            if ('status' === $key && $counts['failed'] > 0) {
                /* translators: %d: number of sites with a failed check */
                echo ' <span class="gaoo-tabs-count" title="' . esc_attr(sprintf(_n('%d site with a failed check', '%d sites with a failed check', $counts['failed'], 'opt-out-for-google-analytics'), $counts['failed'])) . '">' . (int) $counts['failed'] . '</span>';
            }

            echo '</a>';
        }

        echo '</nav>';

        if ('status' === $tab) {
            // The check was started: it runs by the cron of each site, so the page is reloaded after some seconds.
            if (isset($_GET['gaoo-started'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only shows a message.
                $started = absint($_GET['gaoo-started']); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $reload_url = remove_query_arg('gaoo-started');

                /* translators: %d: number of sites */
                echo '<div class="notice notice-info inline"><p>' . esc_html(sprintf(_n('The status check was started for %d site. It runs in the background, the page is reloaded in a few seconds.', 'The status check was started for %d sites. It runs in the background, the page is reloaded in a few seconds.', $started, 'opt-out-for-google-analytics'), $started)) . ' <a href="' . esc_url($reload_url) . '">' . esc_html__('Reload now', 'opt-out-for-google-analytics') . '</a></p></div>';
                echo '<script>setTimeout(function () { window.location.replace(' . wp_json_encode(esc_url_raw($reload_url)) . '); }, 8000);</script>';
            }

            echo '<div class="gaoo-card">';
            echo '<h2>' . esc_html__('Status of all sites', 'opt-out-for-google-analytics') . '</h2>';
            echo '<p class="description">' . esc_html__('The result of the last status check of each site. The check runs automatically on each site, or with "Check now" in the dashboard widget, on the settings page of the site or here.', 'opt-out-for-google-analytics') . '</p>';

            // Filter by status with the number of sites, like the lists of WordPress.
            echo '<ul class="subsubsub gaoo-network-filter">';
            $links = array();

            foreach ($statuses as $key => $label) {
                $url = add_query_arg(array_filter(array('gaoo_status' => $key, 's' => $search)), $base_url);
                $links[] = '<li><a href="' . esc_url($url) . '"' . ($key === $filter ? ' class="current" aria-current="page"' : '') . '>' . esc_html($label) . ' <span class="count">(' . (int) $counts[$key] . ')</span></a>';
            }

            echo implode(' |</li>', $links) . '</li></ul>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.

            echo '<div class="gaoo-network-toolbar">';
            echo '<a class="button" href="' . esc_url($this->network_check_url('all')) . '"><span class="dashicons dashicons-update" aria-hidden="true"></span> ' . esc_html__('Check all sites now', 'opt-out-for-google-analytics') . '</a>';
            echo '<form method="get" action="' . esc_url(network_admin_url('settings.php')) . '" class="gaoo-network-search" role="search">';
            echo '<input type="hidden" name="page" value="gaoo-network">';

            if ('' !== $filter) {
                echo '<input type="hidden" name="gaoo_status" value="' . esc_attr($filter) . '">';
            }

            echo '<label for="gaoo-network-search" class="screen-reader-text">' . esc_html__('Search sites', 'opt-out-for-google-analytics') . '</label>';
            echo '<input type="search" id="gaoo-network-search" name="s" value="' . esc_attr($search) . '" placeholder="' . esc_attr__('Name or URL', 'opt-out-for-google-analytics') . '">';
            echo '<button type="submit" class="button">' . esc_html__('Search sites', 'opt-out-for-google-analytics') . '</button>';
            echo '</form></div>';

            echo '<div class="gaoo-network-table"><table class="widefat striped"><thead><tr>';
            echo $this->network_column('name', __('Site', 'opt-out-for-google-analytics'), $list_url, $orderby, $order); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the method.
            echo $this->network_column('status', __('Status', 'opt-out-for-google-analytics'), $list_url, $orderby, $order); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the method.
            echo '<th scope="col">' . esc_html__('Failed checks', 'opt-out-for-google-analytics') . '</th>';
            echo $this->network_column('last_check', __('Last check', 'opt-out-for-google-analytics'), $list_url, $orderby, $order); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the method.
            echo '</tr></thead><tbody>';

            if (empty($sites)) {
                echo '<tr><td colspan="4">' . esc_html__('No sites found.', 'opt-out-for-google-analytics') . '</td></tr>';
            }

            foreach ($sites as $site) {
                echo '<tr><td><strong>' . esc_html($site['name']) . '</strong><br><a href="' . esc_url($site['url']) . '" target="_blank">' . esc_html(untrailingslashit(preg_replace('#^https?://#', '', $site['url']))) . '</a></td>';

                if ('inactive' === $site['status']) {
                    echo '<td colspan="3"><span class="gaoo-network-state">' . esc_html__('Plugin not active', 'opt-out-for-google-analytics') . '</span></td></tr>';
                    continue;
                }

                if ('not_checked' === $site['status']) {
                    $state = '<span class="gaoo-network-state">' . esc_html__('Not checked yet', 'opt-out-for-google-analytics') . '</span>';
                } elseif ('failed' === $site['status']) {
                    $state = '<span class="gaoo-network-state gaoo-network-todo"><span class="dashicons dashicons-warning" aria-hidden="true"></span>' . esc_html__('Action required', 'opt-out-for-google-analytics') . '</span>';
                } else {
                    $state = '<span class="gaoo-network-state gaoo-network-done"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>' . esc_html__('Everything is fine', 'opt-out-for-google-analytics') . '</span>';
                }

                if ($site['last_check']) {
                    /* translators: 1: number of passed checks, 2: number of all checks */
                    $state .= '<br><span class="description">' . esc_html(sprintf(__('%1$d of %2$d checks passed', 'opt-out-for-google-analytics'), $site['passed'], $site['total'])) . '</span>';
                }

                echo '<td>' . wp_kses_post($state) . '<br><a href="' . esc_url($site['settings_url']) . '">' . esc_html__('Settings') . '</a></td>'; // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- String of WordPress itself.
                echo '<td>' . (empty($site['failed_checks']) ? '&ndash;' : '<ul class="gaoo-network-failed"><li>' . implode('</li><li>', array_map('esc_html', $site['failed_checks'])) . '</li></ul>') . '</td>';
                echo '<td>' . ($site['last_check'] ? esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $site['last_check'])) : '&ndash;');

                if ($site['pending']) {
                    echo '<br><span class="description">' . esc_html__('Check is running …', 'opt-out-for-google-analytics') . '</span>';
                } else {
                    echo '<br><a class="button button-small" href="' . esc_url($this->network_check_url($site['id'])) . '">' . esc_html__('Check now', 'opt-out-for-google-analytics') . '</a>';
                }

                echo '</td></tr>';
            }

            echo '</tbody></table></div>';

            // Pages, if there are more sites than shown on one page.
            if ($pages > 1) {
                echo '<div class="gaoo-network-pages"><span class="description">';
                /* translators: 1: number of the page, 2: number of pages, 3: number of sites */
                echo esc_html(sprintf(_n('Page %1$d of %2$d, %3$d site', 'Page %1$d of %2$d, %3$d sites', $total, 'opt-out-for-google-analytics'), $paged, $pages, $total));
                echo '</span>';
                echo $paged > 1 ? '<a class="button button-small" href="' . esc_url(add_query_arg('paged', $paged - 1, $list_url)) . '" aria-label="' . esc_attr__('Previous page') . '">&lsaquo;</a>' : ''; // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- String of WordPress itself.
                echo $paged < $pages ? '<a class="button button-small" href="' . esc_url(add_query_arg('paged', $paged + 1, $list_url)) . '" aria-label="' . esc_attr__('Next page') . '">&rsaquo;</a>' : ''; // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- String of WordPress itself.
                echo '</div>';
            }

            echo '</div>';
        } else {
            // The same promotion as on the settings page.
            GAOO_Promo::render(false, true);

            // REST API with the status of all sites, for a monitoring of the whole network.
            $network_url = GAOO_API::get_network_url();
            $token_url = wp_nonce_url(admin_url('admin-post.php?action=gaoo_network_api_token'), 'gaoo-network-api-token');

            echo '<div class="gaoo-card gaoo-api" id="gaoo-network-api">';
            echo '<h2>' . esc_html__('Status URL of the network', 'opt-out-for-google-analytics') . '</h2>';
            echo '<p class="description">' . esc_html__('One URL for a monitoring of all sites (e.g. UptimeRobot): it returns the status of all sites as JSON, with the HTTP status 200 if no check failed and 503 if a check of a site failed. The URL contains a secret token, keep it private. Details in the help of this page, tab "REST API".', 'opt-out-for-google-analytics') . '</p>';

            if ('' !== $network_url) {
                echo '<div class="gaoo-api-url"><input type="text" class="large-text code" value="' . esc_attr($network_url) . '" readonly aria-label="' . esc_attr__('Status URL of the network', 'opt-out-for-google-analytics') . '">';
                echo '<button type="button" class="button button-small gaoo-copy" data-copy="' . esc_attr($network_url) . '" title="' . esc_attr__('Click to copy the URL!', 'opt-out-for-google-analytics') . '"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span><span class="gaoo-clipboard"></span></button></div>';
                echo '<p class="gaoo-api-actions"><a class="button button-small" href="' . esc_url($token_url) . '">' . esc_html__('Create new URL', 'opt-out-for-google-analytics') . '</a> ';
                echo '<a class="button button-small" href="' . esc_url(add_query_arg('disable', 1, $token_url)) . '">' . esc_html__('Disable', 'opt-out-for-google-analytics') . '</a></p>';
            } else {
                echo '<p class="gaoo-api-actions"><a class="button button-small" href="' . esc_url($token_url) . '">' . esc_html__('Create status URL', 'opt-out-for-google-analytics') . '</a></p>';
            }

            echo '</div>';

            // Settings of the network in one form with one save bar: the webhook, the template site and the access of the site admins.
            $template = (int) get_site_option(GAOO_PREFIX . 'network_template', 0);
            $hide_settings = !empty(get_site_option(GAOO_PREFIX . 'network_hide_settings', 0));
            $hide_widget = !empty(get_site_option(GAOO_PREFIX . 'network_hide_widget', 0));
            $site_options = '';

            foreach ($all_sites as $site) {
                $site_options .= '<option value="' . esc_attr($site['id']) . '"' . selected($template, $site['id'], false) . '>' . esc_html($site['name']) . '</option>';
            }

            // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Only shows the messages of the saving.
            if (isset($_GET['gaoo-saved'])) {
                echo '<div class="notice notice-success inline"><p>' . esc_html__('Settings saved.', 'opt-out-for-google-analytics') . '</p></div>';
            }

            if (isset($_GET['gaoo-transferred'])) {
                /* translators: %d: number of sites */
                echo '<div class="notice notice-success inline"><p>' . esc_html(sprintf(_n('The settings were transferred to %d site.', 'The settings were transferred to %d sites.', absint($_GET['gaoo-transferred']), 'opt-out-for-google-analytics'), absint($_GET['gaoo-transferred']))) . '</p></div>';
            }
            // phpcs:enable WordPress.Security.NonceVerification.Recommended

            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" id="gaoo-network-form">';
            echo '<input type="hidden" name="action" value="gaoo_network_settings">';
            wp_nonce_field('gaoo-network-settings');

            // Webhook of the network: the results of all sites to one chat or service.
            echo '<div class="gaoo-card gaoo-webhook" id="gaoo-network-webhook">';
            echo '<h2>' . esc_html__('Webhook of the network', 'opt-out-for-google-analytics') . '</h2>';

            if (isset($_GET['gaoo-webhook'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only shows a message.
                echo $this->webhook_notice(sanitize_key(wp_unslash($_GET['gaoo-webhook'])), (array) get_site_option(GAOO_PREFIX . 'network_webhook_result', array())); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.EscapeOutput.OutputNotEscaped -- Only shows a message, escaped in the method.
            }

            $this->webhook_fields(true);
            echo '</div>';

            // Settings of the sites: the template site for new sites and the transfer.
            echo '<div class="gaoo-card gaoo-network-sites" id="gaoo-network-sites">';
            echo '<h2>' . esc_html__('Settings of the sites', 'opt-out-for-google-analytics') . '</h2>';
            echo '<p class="description">' . esc_html__('Shared are the texts, the behavior, the custom CSS and the interval of the status check. Each site keeps its own settings: the source and code of Google Analytics, the privacy policy page, the access data for the check of the website, the email addresses and the webhook.', 'opt-out-for-google-analytics') . '</p>';

            // The template site: new sites get its shared settings.
            echo '<div class="gaoo-field gaoo-network-template">';
            echo '<label for="gaoo-template">' . esc_html__('Template site', 'opt-out-for-google-analytics') . '</label>';
            echo '<select id="gaoo-template" name="gaoo_network[template]" aria-describedby="gaoo-template-description"><option value="0">' . esc_html__('No template (new sites get the default settings)', 'opt-out-for-google-analytics') . '</option>' . $site_options . '</select>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
            echo '<p class="description" id="gaoo-template-description">' . esc_html__('New sites get the shared settings of this site automatically.', 'opt-out-for-google-analytics') . '</p>';
            echo '</div>';

            // Transfer once. It is an action, so it has its own button with a question, and is not saved.
            echo '<div class="gaoo-network-transfer">';
            echo '<h3>' . esc_html__('Transfer now', 'opt-out-for-google-analytics') . '</h3>';
            echo '<p class="description">' . esc_html__('Transfers the shared settings of a site once to all other sites. Their shared settings are overwritten.', 'opt-out-for-google-analytics') . '</p>';
            echo '<div class="gaoo-network-form">';
            echo '<label for="gaoo-source" class="screen-reader-text">' . esc_html__('Settings of the site', 'opt-out-for-google-analytics') . '</label>';
            echo '<select id="gaoo-source" name="gaoo_source" form="gaoo-transfer-form">' . str_replace(' selected=\'selected\'', '', $site_options) . '</select>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
            echo '<button type="submit" class="button" form="gaoo-transfer-form"><span class="dashicons dashicons-migrate" aria-hidden="true"></span> ' . esc_html__('Transfer to all other sites', 'opt-out-for-google-analytics') . '</button>';
            echo '</div></div>';
            echo '</div>';

            // Access of the site admins: settings page and dashboard widget can be hidden independently. Super admins always see them.
            echo '<div class="gaoo-card" id="gaoo-network-access">';
            echo '<h2>' . esc_html__('Access of the site admins', 'opt-out-for-google-analytics') . '</h2>';
            echo '<p class="description">' . esc_html__('For all sites of the network. Super admins always see the settings page and the dashboard widget.', 'opt-out-for-google-analytics') . '</p>';

            foreach (array(
                'settings' => array($hide_settings, __('Hide the settings page', 'opt-out-for-google-analytics'), __('Only super admins can open and change the settings. Site admins still see the result in the dashboard widget and can run the check, but without links to the settings.', 'opt-out-for-google-analytics')),
                'widget' => array($hide_widget, __('Hide the dashboard widget', 'opt-out-for-google-analytics'), __('Site admins do not see the status check in the dashboard.', 'opt-out-for-google-analytics')),
            ) as $key => $toggle) {
                echo '<div class="gaoo-toggle-row">';
                echo '<label class="gaoo-toggle" for="gaoo-network-hide-' . esc_attr($key) . '">';
                echo '<input type="hidden" name="gaoo_network[hide_' . esc_attr($key) . ']" value="0">';
                echo '<input type="checkbox" id="gaoo-network-hide-' . esc_attr($key) . '" name="gaoo_network[hide_' . esc_attr($key) . ']" value="1" ' . checked($toggle[0], true, false) . '>';
                echo '<span class="gaoo-toggle-text"><strong>' . esc_html($toggle[1]) . '</strong><span class="description">' . esc_html($toggle[2]) . '</span></span>';
                echo '</label></div>';
            }

            echo '</div>';

            echo '<div class="gaoo-savebar"><input type="submit" class="button button-primary" value="' . esc_attr__('Save Changes') . '"></div>'; // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- String of WordPress itself.
            echo '</form>';

            // The form of the transfer is outside of the settings form, forms can not be nested. Its fields are in the card above.
            // The question before the transfer is asked by the JavaScript.
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" id="gaoo-transfer-form">';
            echo '<input type="hidden" name="action" value="gaoo_network_transfer">';
            wp_nonce_field('gaoo-network-transfer');
            echo '</form>';
            echo '</div>';
        }

        $this->render_footer();

        echo '</div></div>';
    }

    /**
     * Sortable column of the overview of the network, like the lists of WordPress.
     *
     * @param string $key      Key of the sorting.
     * @param string $label    Title of the column.
     * @param string $list_url URL of the list with the filter.
     * @param string $orderby  Current sorting.
     * @param string $order    Current order: asc or desc.
     *
     * @return string HTML code.
     */
    private function network_column($key, $label, $list_url, $orderby, $order)
    {
        $is_current = $key === $orderby;
        $next = $is_current && 'asc' === $order ? 'desc' : 'asc';
        $url = add_query_arg(array('orderby' => $key, 'order' => $next, 'paged' => false), $list_url);

        return '<th scope="col" class="manage-column ' . ($is_current ? 'sorted ' . esc_attr($order) : 'sortable asc') . '"' . ($is_current ? ' aria-sort="' . ('asc' === $order ? 'ascending' : 'descending') . '"' : '') . '>'
            . '<a href="' . esc_url($url) . '"><span>' . esc_html($label) . '</span><span class="sorting-indicators"><span class="sorting-indicator asc" aria-hidden="true"></span><span class="sorting-indicator desc" aria-hidden="true"></span></span></a></th>';
    }

    /**
     * URL to start the status check of a site in the network admin.
     *
     * @param int|string $site ID of the site, or "all" for all sites.
     *
     * @return string URL with nonce.
     */
    private function network_check_url($site)
    {
        return wp_nonce_url(add_query_arg(array(
            'action' => 'gaoo_network_check',
            'site' => $site,
        ), admin_url('admin-post.php')), 'gaoo-network-check');
    }

    /**
     * Start the status check of one or all sites in the network admin. The check is scheduled as a single event and the cron
     * of the site is started, so the check runs in the context of the site, with its plugins (e.g. the source of the measurement ID).
     */
    public function network_check()
    {
        if (!current_user_can('manage_network_options')) {
            wp_die(esc_html__('Sorry, you are not allowed to do that.'), 403); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- String of WordPress itself.
        }

        check_admin_referer('gaoo-network-check');

        $site = isset($_GET['site']) ? sanitize_key(wp_unslash($_GET['site'])) : '';
        $started = GAOO_Utils::start_network_check('all' === $site ? array() : array(absint($site)));

        wp_safe_redirect(add_query_arg('gaoo-started', $started, network_admin_url('settings.php?page=gaoo-network')));
        exit;
    }

    /**
     * Status check started in the network admin, runs by the cron of the site. Same as "Check now".
     */
    public function run_check_now()
    {
        $this->send_status_mail();
        GAOO_Utils::start_cronjob(true, true);
    }

    /**
     * Add the status check to the Site Health of WordPress (Tools > Site Health).
     *
     * @param array $tests Tests of the Site Health.
     *
     * @return array Tests of the Site Health.
     */
    public function add_site_health_test($tests)
    {
        $tests['direct']['gaoo_status_check'] = array(
            'label' => __('Opt-Out for Google Analytics', 'opt-out-for-google-analytics'),
            'test' => array(
                $this,
                'site_health_test'
            ),
        );

        return $tests;
    }

    /**
     * Result of the status check for the Site Health. Failed checks are a recommendation, information and unknown checks are only listed.
     *
     * @return array Result of the test.
     */
    public function site_health_test()
    {
        $checklist = GAOO_Utils::check_todos(GAOO_Utils::get_options(), true);
        // Only failed checks, an information is not an error.
        $failed = in_array('failed', array_map(array('GAOO_Utils', 'check_result'), $checklist), true);
        $items = '';

        foreach ($checklist as $check) {
            $mark = !empty($check['info']) ? 'ℹ' : ($check['checked'] === true ? '✔' : ($check['checked'] === false ? '✕' : '?'));
            $items .= '<li>' . esc_html($mark . ' ' . $check['label']) . '</li>';
        }

        return array(
            'label' => $failed ? __('The opt-out for Google Analytics does not work correctly', 'opt-out-for-google-analytics') : __('The opt-out for Google Analytics works', 'opt-out-for-google-analytics'),
            'status' => $failed ? 'recommended' : 'good',
            'badge' => array(
                'label' => __('Privacy', 'opt-out-for-google-analytics'),
                'color' => 'blue',
            ),
            'description' => '<p>' . esc_html__('The GDPR requires that visitors can disallow the tracking by Google Analytics. The status check of the plugin "Opt-Out for Google Analytics" checks, whether this works:', 'opt-out-for-google-analytics') . '</p><ul>' . $items . '</ul>',
            'actions' => $this->can_manage() ? '<a href="' . esc_url(admin_url('options-general.php?page=gaoo')) . '">' . esc_html__('Check the settings', 'opt-out-for-google-analytics') . '</a>' : '',
            'test' => 'gaoo_status_check',
        );
    }

    /**
     * Download the settings as JSON file. The access data and the privacy policy page are not exported.
     */
    public function export_settings()
    {
        if (!$this->can_manage()) {
            wp_die(esc_html__('Sorry, you are not allowed to do that.'), 403); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- String of WordPress itself.
        }

        check_admin_referer('gaoo-export');

        $file = 'gaoo-settings-' . sanitize_file_name(wp_parse_url(home_url(), PHP_URL_HOST)) . '-' . gmdate('Y-m-d') . '.json';

        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $file . '"');

        echo wp_json_encode(GAOO_Utils::export_data(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Import the settings of an exported JSON file. Every value is checked like on saving, the tracking code and the custom CSS
     * only if the user may change them.
     */
    public function import_settings()
    {
        if (!$this->can_manage()) {
            wp_die(esc_html__('Sorry, you are not allowed to do that.'), 403); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- String of WordPress itself.
        }

        check_admin_referer('gaoo-import');

        $file = isset($_FILES['gaoo_import_file']['tmp_name']) ? $_FILES['gaoo_import_file']['tmp_name'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Path of the uploaded file.
        $data = ($file && is_uploaded_file($file) && filesize($file) < MB_IN_BYTES) ? json_decode((string) file_get_contents($file), true) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Uploaded file.
        $imported = GAOO_Utils::import_data($data);

        wp_safe_redirect(add_query_arg('gaoo-imported', $imported, admin_url('options-general.php?page=gaoo')));
        exit;
    }

    /**
     * Network admin: transfer the settings of a site to all other sites with the active plugin.
     */
    public function network_transfer()
    {
        if (!current_user_can('manage_network_options')) {
            wp_die(esc_html__('Sorry, you are not allowed to do that.'), 403); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- String of WordPress itself.
        }

        check_admin_referer('gaoo-network-transfer');

        $source = isset($_POST['gaoo_source']) ? absint($_POST['gaoo_source']) : 0;
        $transferred = GAOO_Utils::transfer_to_all($source);

        wp_safe_redirect(add_query_arg('gaoo-transferred', $transferred, network_admin_url('settings.php?page=gaoo-network&tab=settings')));
        exit;
    }

    /**
     * Create a new URL of the status for a monitoring, or disable it.
     */
    public function api_token()
    {
        if (!$this->can_manage()) {
            wp_die(esc_html__('Sorry, you are not allowed to do that.'), 403); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- String of WordPress itself.
        }

        check_admin_referer('gaoo-api-token');
        require_once GAOO_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'api.class.php';

        GAOO_API::set_token(!isset($_GET['disable']));

        wp_safe_redirect(admin_url('options-general.php?page=gaoo#gaoo-api'));
        exit;
    }

    /**
     * Create a new URL of the status of all sites in the network, or disable it.
     */
    public function network_api_token()
    {
        if (!current_user_can('manage_network_options')) {
            wp_die(esc_html__('Sorry, you are not allowed to do that.'), 403); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- String of WordPress itself.
        }

        check_admin_referer('gaoo-network-api-token');
        require_once GAOO_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'api.class.php';

        GAOO_API::set_network_token(!isset($_GET['disable']));

        wp_safe_redirect(network_admin_url('settings.php?page=gaoo-network&tab=settings#gaoo-network-api'));
        exit;
    }

    /**
     * Save the settings of the network: the webhook, the template site for new sites and the access of the site admins.
     */
    public function network_settings()
    {
        if (!current_user_can('manage_network_options')) {
            wp_die(esc_html__('Sorry, you are not allowed to do that.'), 403); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- String of WordPress itself.
        }

        check_admin_referer('gaoo-network-settings');

        $args = array('gaoo-saved' => 1);

        // Webhook of the network. The URL is only saved, if it is valid.
        $webhook = isset($_POST['gaoo_network_webhook']) && is_array($_POST['gaoo_network_webhook']) ? wp_unslash($_POST['gaoo_network_webhook']) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each value is sanitized below.
        $url = isset($webhook['webhook_url']) ? trim(sanitize_text_field($webhook['webhook_url'])) : '';
        $format = isset($webhook['webhook_format']) ? sanitize_key($webhook['webhook_format']) : 'json';

        if ('' !== $url && (!wp_http_validate_url($url) || !in_array(wp_parse_url($url, PHP_URL_SCHEME), array('http', 'https'), true))) {
            $args['gaoo-webhook'] = 'invalid';
        } else {
            update_site_option(GAOO_PREFIX . 'network_webhook_url', esc_url_raw($url, array('http', 'https')));
        }

        update_site_option(GAOO_PREFIX . 'network_webhook_status', empty($webhook['webhook_status']) ? 0 : 1);
        update_site_option(GAOO_PREFIX . 'network_webhook_format', array_key_exists($format, GAOO_Utils::webhook_formats()) ? $format : 'json');
        update_site_option(GAOO_PREFIX . 'network_webhook_always', empty($webhook['webhook_always']) ? 0 : 1);

        // Template site for new sites, and the access of the site admins.
        $sites = isset($_POST['gaoo_network']) && is_array($_POST['gaoo_network']) ? wp_unslash($_POST['gaoo_network']) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each value is sanitized below.
        $template = isset($sites['template']) ? absint($sites['template']) : 0;

        update_site_option(GAOO_PREFIX . 'network_template', $template && get_site($template) ? $template : 0);
        update_site_option(GAOO_PREFIX . 'network_hide_settings', empty($sites['hide_settings']) ? 0 : 1);
        update_site_option(GAOO_PREFIX . 'network_hide_widget', empty($sites['hide_widget']) ? 0 : 1);

        wp_safe_redirect(add_query_arg($args, network_admin_url('settings.php?page=gaoo-network&tab=settings')));
        exit;
    }

    /**
     * Send the status of all sites to the webhook of the network as a test.
     */
    public function network_webhook_test()
    {
        if (!current_user_can('manage_network_options')) {
            wp_die(esc_html__('Sorry, you are not allowed to do that.'), 403); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- String of WordPress itself.
        }

        check_admin_referer('gaoo-network-webhook-test');

        $result = GAOO_Utils::send_network_webhook_test();

        wp_safe_redirect(add_query_arg('gaoo-webhook', null === $result ? -1 : (int) $result['code'], network_admin_url('settings.php?page=gaoo-network&tab=settings')) . '#gaoo-network-webhook');
        exit;
    }

    /**
     * Message of the webhook: saved, invalid URL, or the result of the test (HTTP status, 0 for an error, -1 without URL).
     *
     * @param string $state Value of the parameter "gaoo-webhook".
     * @param array  $result Result of the last request.
     *
     * @return string HTML code of the notice.
     */
    private function webhook_notice($state, $result)
    {
        if ('saved' === $state) {
            return '<div class="notice notice-success inline"><p>' . esc_html__('Settings saved.', 'opt-out-for-google-analytics') . '</p></div>';
        }

        if ('invalid' === $state) {
            return '<div class="notice notice-error inline"><p>' . esc_html__('Please enter a valid webhook URL, which starts with https://. The URL was not saved.', 'opt-out-for-google-analytics') . '</p></div>';
        }

        $code = (int) $state;

        if ($code >= 200 && $code < 300) {
            /* translators: %d: HTTP status code */
            return '<div class="notice notice-success inline"><p>' . esc_html(sprintf(__('The test was sent to the webhook (HTTP status %d).', 'opt-out-for-google-analytics'), $code)) . '</p></div>';
        }

        if ($code < 0) {
            return '<div class="notice notice-error inline"><p>' . esc_html__('No webhook URL is saved.', 'opt-out-for-google-analytics') . '</p></div>';
        }

        /* translators: %s: HTTP status code or error message */
        return '<div class="notice notice-error inline"><p>' . esc_html(sprintf(__('The test could not be sent to the webhook: %s', 'opt-out-for-google-analytics'), $code ? 'HTTP ' . $code : (isset($result['error']) ? $result['error'] : ''))) . '</p></div>';
    }

    /**
     * Send the result of the last status check to the webhook as a test, with the saved settings.
     */
    public function webhook_test()
    {
        if (!$this->can_manage()) {
            wp_die(esc_html__('Sorry, you are not allowed to do that.'), 403); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- String of WordPress itself.
        }

        check_admin_referer('gaoo-webhook-test');

        $result = GAOO_Utils::send_webhook(GAOO_Utils::check_todos(GAOO_Utils::get_options(), true), true);

        wp_safe_redirect(add_query_arg('gaoo-webhook', null === $result ? -1 : (int) $result['code'], admin_url('options-general.php?page=gaoo')) . '#gaoo-webhook');
        exit;
    }

    /**
     * Remove the parameter of the manual status check from the URL, after the message is shown.
     *
     * @param array $args Parameters, which are removed.
     *
     * @return array Parameters, which are removed.
     */
    public function removable_query_args($args)
    {
        $args[] = 'gaoo-checked';
        $args[] = 'gaoo-started';
        $args[] = 'gaoo-imported';
        $args[] = 'gaoo-transferred';
        $args[] = 'gaoo-webhook';
        $args[] = 'gaoo-saved';

        return $args;
    }

    /**
     * Run the status check (automatically by the cronjob or manually) and send a mail with the report, if a check failed.
     */
    public function send_status_mail()
    {
        // Delete todo cache if cronjob is triggered
        GAOO_Utils::delete_todo_cache();
        update_option(GAOO_PREFIX . 'last_check', time(), false);

        $data = GAOO_Utils::get_options();
        $ua_code = GAOO_Utils::get_code($data['ga_plugin'], $data['ua_code']);

        // Check the website itself, the result is stored and used by the checklist.
        if (GAOO_Utils::is_opt_out_enabled($data) && !empty($ua_code)) {
            GAOO_Utils::run_frontend_check($ua_code);
        }

        $checklist = GAOO_Utils::check_todos($data, true);
        // Only failed checks, an information is not an error.
        $failed = in_array('failed', array_map(array('GAOO_Utils', 'check_result'), $checklist), true);
        GAOO_Utils::store_result($checklist);

        /**
         * After every status check, e.g. to send the result to another service.
         *
         * @param array $checklist List of the checks with the keys "label" and "checked" (true, false or null if unknown).
         * @param bool  $failed    True if at least one check failed.
         */
        do_action('gaoo_status_check_done', $checklist, $failed);

        // The webhooks of the site and of the network, if they are enabled.
        GAOO_Utils::send_webhooks($checklist, $failed);

        // The mail is only sent, if at least one check failed.
        if (!$failed) {
            return;
        }

        // Synchronized with the WordPress admin email: always the current address, also if it was changed after saving.
        if (!empty($data['status_mails_sync'])) {
            $data['status_mails'] = (string) get_option('admin_email', '');
        }

        $mail_to = empty($data['status_mails']) ? array() : array_filter(array_map('trim', explode(',', $data['status_mails'])), 'is_email');
        $mail_to = (array) apply_filters('gaoo_status_mail_recipients', array_values($mail_to), $checklist);

        if (empty($mail_to)) {
            return;
        }

        $mail_subject = '[ ' . wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES) . ' ] ' . __('Opt-Out for Google Analytics - Status check failed!', 'opt-out-for-google-analytics');
        $mail_body = __('Here is your status check report:', 'opt-out-for-google-analytics') . PHP_EOL . PHP_EOL;

        foreach ($checklist as $checkpoint) {
            $checked = $checkpoint['checked'];

            if (!empty($checkpoint['info'])) {
                $checked = 'i';
            } elseif ($checked === false) {
                $checked = '✕';
            } elseif ($checked === true) {
                $checked = '✔';
            } else {
                $checked = '?';
            }

            // The mail is plain text, so the labels are not HTML encoded.
            $mail_body .= "[ {$checked} ] " . wp_strip_all_tags($checkpoint['label']) . PHP_EOL;
        }

        $mail_body .= PHP_EOL . sprintf(/* translators: %s: URL of the settings page */ __('Check the settings: %s', 'opt-out-for-google-analytics'), admin_url('options-general.php?page=gaoo'));

        wp_mail($mail_to, apply_filters('gaoo_status_mail_subject', $mail_subject, $checklist), apply_filters('gaoo_status_mail_body', $mail_body, $checklist));
    }


}