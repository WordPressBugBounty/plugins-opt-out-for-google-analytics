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
        $passed = count(array_filter(array_column($checklist, 'checked'), function ($checked) {
            return $checked === true;
        }));
        $failed = count(array_filter(array_column($checklist, 'checked'), function ($checked) {
            return $checked === false;
        }));

        if ($failed > 0) {
            $state = 'todo';
            $icon = 'warning';
            $title = esc_html__('Action required', 'opt-out-for-google-analytics');
        } elseif ($passed < count($checklist)) {
            $state = 'dontknow';
            $icon = 'info';
            $title = esc_html__('Not everything could be checked', 'opt-out-for-google-analytics');
        } else {
            $state = 'done';
            $icon = 'shield-alt';
            $title = esc_html__('Everything is fine', 'opt-out-for-google-analytics');
        }

        $icons = array(
            'check' => 'yes-alt',
            'todo' => 'dismiss',
            'dontknow' => 'editor-help',
        );

        echo '<div class="gaoo-widget gaoo-widget-' . esc_attr($state) . '">';

        // Summary
        echo '<div class="gaoo-widget-summary">';
        echo '<span class="gaoo-widget-summary-icon dashicons dashicons-' . esc_attr($icon) . '" aria-hidden="true"></span>';
        echo '<div><strong>' . $title . '</strong>';
        echo '<span>' . esc_html(sprintf(__('%1$d of %2$d checks passed', 'opt-out-for-google-analytics'), $passed, count($checklist))) . '</span></div>';
        echo '</div>';
        echo '<div class="gaoo-widget-progress" role="presentation"><span style="width: ' . esc_attr(round($passed / max(1, count($checklist)) * 100)) . '%"></span></div>';

        // Checks
        echo '<ul class="gaoo-widget-checks">';

        foreach ($checklist as $check) {
            $status = $check['checked'] === true ? 'check' : ($check['checked'] === false ? 'todo' : 'dontknow');

            $row = '<span class="gaoo-widget-check-icon dashicons dashicons-' . esc_attr($icons[$status]) . '" aria-hidden="true"></span>';
            $row .= '<span class="gaoo-widget-check-label">' . (isset($check['text']) ? $check['text'] : $check['label']) . '</span>';

            if (!empty($check['value'])) {
                $row .= '<code>' . esc_html($check['value']) . '</code>';
            }

            echo '<li class="gaoo-widget-check-' . esc_attr($status) . '">';

            // If there is a page to fix the check, the whole row is the link.
            if (!empty($check['url'])) {
                echo '<a class="gaoo-widget-check-row" href="' . esc_url($check['url']) . '" target="_blank" title="' . esc_attr__('Got to page', 'opt-out-for-google-analytics') . '">' . $row . '<span class="gaoo-widget-check-arrow dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span><span class="screen-reader-text">(' . esc_html__('Got to page', 'opt-out-for-google-analytics') . ')</span></a>';
            } else {
                echo '<div class="gaoo-widget-check-row">' . $row . '</div>';
            }

            echo '</li>';
        }

        echo '</ul>';

        // Footer
        echo '<div class="gaoo-widget-footer">';

        $next_check = wp_next_scheduled(GAOO_CRONJOB);

        if (!empty($next_check)) {
            echo '<span class="gaoo-widget-next"><span class="dashicons dashicons-clock" aria-hidden="true"></span>' . esc_html(sprintf(__('Next automatic check: %s', 'opt-out-for-google-analytics'), date_i18n(get_option('date_format'), $next_check))) . '</span>';
        }

        if ($context === 'dashboard') {
            echo '<a class="button button-small" href="' . esc_url(admin_url('options-general.php?page=gaoo')) . '">' . esc_html__('Settings') . '</a>';
        }

        echo '</div>';

        $promo = $context === 'dashboard' ? GAOO_Promo::get_data() : null;

        if (!empty($promo) && !empty($promo['promo'])) {
            $promo = array_pop($promo['promo']);

            if (!empty($promo['link']) && !empty($promo['link_text']) && (empty($promo['hide_if_active']) || !is_array($promo['hide_if_active']) || !GAOO_Promo::should_hide($promo['hide_if_active']))) {
                echo '<a class="gaoo-widget-promo" href="' . esc_url($promo['link']) . '" target="_blank"><span class="dashicons dashicons-privacy" aria-hidden="true"></span>' . esc_html($promo['link_text']) . '<span class="dashicons dashicons-external" aria-hidden="true"></span></a>';
            }
        }

        echo '</div>';
    }

    /**
     * Add new dashboard widgets.
     */
    public function add_dashboard_widget()
    {
        if (GAOO_Utils::get_option('disable_dashboard') != 'on') {
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
        add_options_page('Opt-Out for Google Analytics', 'GA Opt-Out', GAOO_CAPABILITY, 'gaoo', array(
            $this,
            'menu_settings'
        ));
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
        if ($file == $this->plugin) {
            $links[] = '<a href="' . esc_url(admin_url('options-general.php?page=gaoo')) . '">' . esc_html__('Settings') . '</a>';
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

        // Dashboard widget, settings page, rating stars in the plugin list and the TinyMCE button.
        if (!$is_settings_page && !$this->tinymce_button && !in_array($hook_suffix, array('index.php', 'plugins.php'), true)) {
            return;
        }

        wp_enqueue_style('gaoo-admin-styles', GAOO_PLUGIN_URL . '/assets/admin' . $min . '.css', array(), GAOO_VERSION);

        // Run only if functionallity on the right pages
        if ($is_settings_page) {
            // The settings page is framed in the color of the admin menu, like the connectors page. The color depends on the admin color scheme of the user.
            global $_wp_admin_css_colors;

            $color_scheme = get_user_option('admin_color');
            $frame_color = isset($_wp_admin_css_colors[$color_scheme]->colors[0]) ? sanitize_hex_color($_wp_admin_css_colors[$color_scheme]->colors[0]) : '';

            if (!empty($frame_color)) {
                wp_add_inline_style('gaoo-admin-styles', 'body.settings_page_gaoo { --gaoo-frame: ' . $frame_color . '; }');
            }

            wp_enqueue_script('gaoo-admin-script', GAOO_PLUGIN_URL . '/assets/admin' . $min . '.js', array('jquery'), GAOO_VERSION, true);

            wp_localize_script('gaoo-admin-script', 'gaoo', array(
                'edit_link' => esc_url(admin_url('post.php?action=edit&post=%d')),
                'text' => array(
                    'copied' => esc_html__('Copied!', 'opt-out-for-google-analytics'),
                    'notcopied' => esc_html__("Couldn't copy!", 'opt-out-for-google-analytics'),
                ),
            ));
        }
    }

    /**
     * Add admin notices, if function is enabled and no G-Code is configured.
     */
    public function admin_notices()
    {
        if ((isset($_GET['page']) && $_GET['page'] == 'gaoo') && $this->is_cronjob_disabled()) {
            echo '<div class="notice update-nag is-dismissible"><p>' . esc_html__('It seems like you have disabled the cronjob through your wp-config.php ... Please make sure that you have installed a server-side cronjob, otherwise you wont receive the status reports via mail.', 'opt-out-for-google-analytics') . '</p></div>';
        }

        // Run only if open todos available.
        if (!current_user_can(GAOO_CAPABILITY) || GAOO_Utils::get_option('disable_monitoring', false) || !GAOO_Utils::has_todos() || (isset($_GET['page']) && $_GET['page'] == 'gaoo')) {
            return;
        }

        echo '<div class="notice notice-error is-dismissible"><p>' . sprintf(__('Google Analytics does not appear to function in compliance with data protection regulations. Please check the settings <a href="%s">here</a>.', 'opt-out-for-google-analytics'), esc_url(admin_url('options-general.php?page=gaoo'))) . '</p></div>';
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
        $form_data = isset($_POST['gaoo']) ? stripslashes_deep($this->save_menu_settings()) : GAOO_Utils::get_options();

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
        if (!isset($_REQUEST['_wpnonce']) || !wp_verify_nonce($_REQUEST['_wpnonce'], 'gaoo-settings')) {
            $this->messages->addError(esc_html__('Security check fail!', 'opt-out-for-google-analytics'));

            return array();
        }

        // Validate inputs
        $skip_save = array();
        $data = filter_var_array($_POST['gaoo'], array(
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
            'tracking_code' => FILTER_UNSAFE_RAW,
            'status_intervall' => FILTER_UNSAFE_RAW,
            'status_mails' => FILTER_UNSAFE_RAW,
            'uninstall_keep_data' => FILTER_UNSAFE_RAW,
            'disable_dashboard' => FILTER_UNSAFE_RAW,
            'status_mails_sync' => FILTER_SANITIZE_NUMBER_INT,
        ), false);

        // Sanitize string / text
        $text_fields = array('ga_plugin', 'popup_activate', 'popup_deactivate', 'ua_code', 'link_activate', 'link_deactivate');

        foreach ($text_fields as $text_field) {
            if (empty($data[$text_field])) {
                continue;
            }

            $data[$text_field] = htmlspecialchars($data[$text_field], ENT_QUOTES);
        }

        // Validate checkboxes
        $checkboxes = array('status', 'uninstall_keep_data', 'disable_dashboard', 'custom_css_status');

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
        $data['popup_activate'] = trim($data['popup_activate']);
        $data['popup_deactivate'] = trim($data['popup_deactivate']);
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

        // Check and validate the custom css code
        if (!empty($data['custom_css']) && !in_array('custom_css', $skip_save, true)) {
            $csstidy = GAOO_Utils::get_csstidy();

            if (!$csstidy->parse(stripslashes($data['custom_css']))) {
                $skip_save[] = 'custom_css';
                $this->messages->addWarning(esc_html__("Your custom css code was not stored, because it is not valid! We stoll hold the old version of it, please check below the code and save again.", 'opt-out-for-google-analytics'));
            } else {
                // Strip HTML tags, so the code can't break out of the style element.
                $data['custom_css'] = wp_slash(wp_strip_all_tags($csstidy->print->plain()));
            }
        }

        // Validate the mail addresses, separated by comma. Don't save, if one of them is invalid.
        if (!empty($data['status_mails'])) {
            $mails = array_filter(array_map('trim', explode(',', $data['status_mails'])), 'strlen');
            $invalid_mails = array_filter($mails, function ($mail) {
                return !is_email($mail);
            });

            if (!empty($invalid_mails)) {
                $this->messages->addError(sprintf(esc_html__('Please enter valid email addresses, separated by a comma. Invalid: %s', 'opt-out-for-google-analytics'), esc_html(implode(', ', $invalid_mails))));
            }

            $data['status_mails'] = implode(',', $mails);
        }

        if (empty($data['link_deactivate'])) {
            $this->messages->addError(esc_html__("Please enter the text for the deactivate link, otherwise link doesn't appears!", 'opt-out-for-google-analytics'));
        }

        if (empty($data['link_activate'])) {
            $this->messages->addError(esc_html__("Please enter the text for the activate link, otherwise link doesn't appears!", 'opt-out-for-google-analytics'));
        }

        if ($this->messages->hasError()) {
            return $data;
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
                update_option(GAOO_PREFIX . $k, $v);
            }
        }

        // Show warniung if cronjob is disabled
        if ($this->is_cronjob_disabled()) {
            $this->messages->addWarning(esc_html__('It seems like you have disabled the cronjob through your wp-config.php ... Please make sure that you have installed a server-side cronjob, otherwise you wont receive the status reports via mail.', 'opt-out-for-google-analytics'));
        }

        // Restart cronjob etc. if intervall is changed
        if ($old_status_intervall != $data['status_intervall']) {
            GAOO_Utils::delete_todo_cache();
            GAOO_Utils::start_cronjob(true);
        }

        $this->messages->addSuccess(esc_html__('Settings saved.', 'opt-out-for-google-analytics'));

        return $data;
    }

    /**
     * Send a mail with the status check report.
     */
    public function send_status_mail()
    {
        // Delete todo cache if cronjob is triggered
        GAOO_Utils::delete_todo_cache();

        $data = GAOO_Utils::get_options();

        if (empty($data['status_mails'])) {
            return;
        }

        $checklist = GAOO_Utils::check_todos($data, true);
        $checked = array_unique(array_filter(array_column($checklist, 'checked'), 'is_bool'));

        if (count($checked) == 1 && array_pop($checked) == true) {
            return;
        }

        $mail_to = explode(',', $data['status_mails']);
        $mail_to = array_filter(array_map('trim', $mail_to), 'is_email');

        if (empty($mail_to)) {
            return;
        }

        $mail_subject = '[ ' . esc_html(get_bloginfo('name')) . ' ] ' . esc_html__('Opt-Out for Google Analytics - Status check failed!', 'opt-out-for-google-analytics');
        $mail_body = esc_html__('Here is your status check report:', 'opt-out-for-google-analytics') . PHP_EOL . PHP_EOL;

        foreach ($checklist as $checkpoint) {
            $checked = $checkpoint['checked'];

            if ($checked === false) {
                $checked = '✕';
            } elseif ($checked === true) {
                $checked = '✔';
            } else {
                $checked = '?';
            }

            $mail_body .= "[ {$checked} ] " . esc_html($checkpoint['label']) . PHP_EOL;
        }

        $mail_body .= PHP_EOL . sprintf(esc_html__('Check the settings: %s', 'opt-out-for-google-analytics'), esc_url(admin_url('options-general.php?page=gaoo')));

        wp_mail($mail_to, $mail_subject, $mail_body);
    }


}