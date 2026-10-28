<?php
    // If this file is called directly, abort.
    defined( 'WPINC' ) || die;

    // The template is included in a method, so the variables are local and not global.
    // phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

    $language   = strstr( GAOO_LOCALE, '_', true );
    $language   = empty( $language ) ? 'en' : $language;
    $is_manual  = ( $ga_plugin == 'manual' || empty( $ga_plugin ) );
?>

<div class="wrap gaoo-settings" id="gaoo">
<div class="gaoo-settings-scroll">

    <div class="gaoo-header">
        <div>
            <h1>Opt-Out for Google Analytics</h1>
            <p><?php esc_html_e( "Do you have a data processing agreement for Google Analytics?", 'opt-out-for-google-analytics' ); ?>
                <a href="<?php echo esc_url( 'https://support.google.com/analytics/answer/3379636?hl=' . $language ); ?>" target="_blank"><?php esc_html_e( 'More information.', 'opt-out-for-google-analytics' ); ?></a>
            </p>
            <p class="gaoo-disclaimer"><?php esc_html_e( 'This plugin supports you with the opt-out, but cannot cover every legal requirement of your website. No liability is assumed.', 'opt-out-for-google-analytics' ); ?></p>
        </div>
    </div>

    <hr class="wp-header-end">

    <?php $this->messages->render( true ); ?>

    <div class="gaoo-main">

        <?php // Jump to the sections of the settings, the current section is highlighted. ?>
        <span class="gaoo-nav-sentinel" aria-hidden="true"></span>
        <nav class="gaoo-nav" aria-labelledby="gaoo-nav-title">
            <span class="gaoo-nav-title" id="gaoo-nav-title"><?php esc_html_e( 'Go to:', 'opt-out-for-google-analytics' ); ?></span>
            <a href="#gaoo-section-status"><?php esc_html_e( 'Status', 'opt-out-for-google-analytics' ); ?></a>
            <a href="#gaoo-section-tracking"><?php esc_html_e( 'Tracking ID', 'opt-out-for-google-analytics' ); ?></a>
            <a href="#gaoo-section-privacy"><?php esc_html_e( 'Privacy policy', 'opt-out-for-google-analytics' ); ?></a>
            <a href="#gaoo-section-check"><?php esc_html_e( 'Status check', 'opt-out-for-google-analytics' ); ?></a>
            <a href="#gaoo-section-appearance"><?php esc_html_e( 'Appearance', 'opt-out-for-google-analytics' ); ?></a>
            <a href="#gaoo-section-css"><?php esc_html_e( 'Custom CSS', 'opt-out-for-google-analytics' ); ?></a>
            <a href="#gaoo-section-transfer"><?php esc_html_e( 'Import / export', 'opt-out-for-google-analytics' ); ?></a>
        </nav>

        <div class="gaoo-card gaoo-card-flush">
            <?php $this->render_status( 'settings' ); ?>
        </div>

        <?php GAOO_Promo::render( false, true ); ?>

        <div class="gaoo-card gaoo-shortcode">
            <h2><?php esc_html_e( 'Shortcode', 'opt-out-for-google-analytics' ); ?></h2>

            <?php
                // The shortcode and the copy button stay together, if the line wraps.
                $shortcode_copy = '<span class="gaoo-shortcode-copy-code"><code>' . esc_html( GAOO_SHORTCODE ) . '</code>'
                    . '<button type="button" class="button button-small gaoo-copy" data-copy="' . esc_attr( GAOO_SHORTCODE ) . '" title="' . esc_attr__( 'Click to copy the shortcode!', 'opt-out-for-google-analytics' ) . '"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span><span class="gaoo-clipboard"></span></button></span>';
            ?>
            <p class="gaoo-shortcode-copy"><?php printf( /* translators: %s: shortcode with copy button */ esc_html__( "Use this shortcode on every page or post where the opt-out link should be displayed: %s", 'opt-out-for-google-analytics' ), $shortcode_copy ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?></p>
            <p class="description"><?php esc_html_e( 'In the block editor, in Elementor and in Divi you can also use the block, widget or module "Opt-Out for Google Analytics". There you enter the individual texts in the settings of the element.', 'opt-out-for-google-analytics' ); ?></p>

            <p class="gaoo-shortcode-help"><?php esc_html_e( 'Individual texts with attributes, line breaks in the popup text and an example:', 'opt-out-for-google-analytics' ); ?>
                <button type="button" class="button-link gaoo-open-help" data-tab="gaoo-shortcode"><?php esc_html_e( 'Open the help', 'opt-out-for-google-analytics' ); ?></button>
            </p>
        </div>

        <form method="post" id="gaoo-settings-form">

            <div class="gaoo-card" id="gaoo-section-status">
                <h2><?php esc_html_e( "Status", 'opt-out-for-google-analytics' ); ?></h2>

                <div class="gaoo-toggle-row">
                    <label class="gaoo-toggle" for="gaoo-status">
                        <input type="checkbox" name="gaoo[status]" id="gaoo-status" value="on" <?php checked( true, ( $status == 'on' || empty( $status ) ) ); ?> />
                        <span class="gaoo-toggle-text"><?php esc_html_e( "Enable Opt-Out function", 'opt-out-for-google-analytics' ); ?></span>
                    </label>
                </div>

                <div class="gaoo-toggle-row gaoo-consent-mode">
                    <label class="gaoo-toggle" for="gaoo-consent-mode">
                        <input type="checkbox" id="gaoo-consent-mode" name="gaoo[consent_mode]" value="1" <?php checked( ! empty( $consent_mode ) ); ?> />
                        <span class="gaoo-toggle-text"><strong><?php esc_html_e( 'Google Consent Mode', 'opt-out-for-google-analytics' ); ?></strong>
                            <span class="description"><?php esc_html_e( 'On the opt-out, also deny the consent "analytics_storage" of the Google Consent Mode. So the opt-out also works, if Google Analytics is loaded by the Google Tag Manager or another plugin with a different measurement ID. Only if the website uses the Consent Mode.', 'opt-out-for-google-analytics' ); ?></span></span>
                    </label>
                </div>

                <div class="gaoo-toggle-row">
                    <label class="gaoo-toggle" for="gaoo-uninstall-keep-data">
                        <input type="checkbox" id="gaoo-uninstall-keep-data" name="gaoo[uninstall_keep_data]" value="on" <?php checked( 'on', $uninstall_keep_data ); ?> />
                        <span class="gaoo-toggle-text"><strong><?php esc_html_e( "Keep data after uninstall", 'opt-out-for-google-analytics' ); ?></strong>
                            <span class="description"><?php esc_html_e( "If the settings and language files of this plugin should be kept after uninstallation, activate this checkbox. By default, all settings will be irrevocably deleted.", 'opt-out-for-google-analytics' ); ?></span></span>
                    </label>
                </div>
            </div>

            <div class="gaoo-card" id="gaoo-section-tracking">
                <h2><?php esc_html_e( "Tracking ID", 'opt-out-for-google-analytics' ); ?></h2>

                <div class="gaoo-sources">
                    <label class="gaoo-source" for="ga-plugin-manual">
                        <input type="radio" name="gaoo[ga_plugin]" id="ga-plugin-manual" value="manual" <?php checked( true, $is_manual ); ?> />
                        <span class="gaoo-source-text"><strong><?php esc_html_e( 'Enter manually', 'opt-out-for-google-analytics' ); ?></strong></span>
                    </label>

                    <div class="gaoo-source-details <?php echo $is_manual ? '' : 'hide'; ?>">
                        <div class="gaoo-field">
                            <label for="gaoo-ua-code"><?php esc_html_e( "Tracking ID", 'opt-out-for-google-analytics' ); ?></label>
                            <input type="text" id="gaoo-ua-code" name="gaoo[ua_code]" class="regular-text code" placeholder="<?php esc_attr_e( 'G-XXXXXXXXXX', 'opt-out-for-google-analytics' ); ?>" value="<?php echo esc_attr( $ua_code ); ?>" />
                            <p class="description"><?php esc_html_e( "Searching for your code?", 'opt-out-for-google-analytics' ); ?>
                                <a href="<?php echo esc_url( 'https://support.google.com/analytics/answer/9539598?hl=' . $language ); ?>" target="_blank"><?php esc_html_e( 'More information.', 'opt-out-for-google-analytics' ); ?></a></p>
                        </div>

                        <div class="gaoo-field">
                            <?php if ( ! current_user_can( 'unfiltered_html' ) ): ?>
                                <div class="notice notice-warning inline gaoo-readonly-notice"><p><?php esc_html_e( 'You are not allowed to change the tracking code, because your user role may not insert unfiltered HTML.', 'opt-out-for-google-analytics' ); ?></p></div>
                            <?php endif; ?>

                            <textarea id="ga-plugin-tracking-code" class="large-text code" rows="6" name="gaoo[tracking_code]" <?php echo current_user_can( 'unfiltered_html' ) ? '' : 'readonly'; ?> placeholder="<?php esc_attr_e( 'Enter your Google Analytics tracking code here to add it to your whole website. Leave empty if you do not need it.', 'opt-out-for-google-analytics' ); ?>"><?php echo esc_textarea( $tracking_code ); ?></textarea>
                        </div>
                    </div>

                    <?php foreach ( $ga_plugins as $key => $info ): ?>
                        <?php
                        $is_available = ( ! isset( $info[ 'is_active' ] ) || $info[ 'is_active' ] );
                        $label        = explode( ' – ', $info[ 'label' ], 2 );
                        $action       = null;

                        // Link only, if the user is allowed to activate / install plugins. Otherwise show only the status.
                        if ( isset( $info[ 'is_active' ] ) && ! $info[ 'is_active' ] ):
                            $action = array( 'text' => __( 'Not activated', 'opt-out-for-google-analytics' ), 'url' => current_user_can( 'activate_plugins' ) ? $info[ 'url_activate' ] : '' );
                        endif;

                        if ( isset( $info[ 'is_installed' ] ) && ! $info[ 'is_installed' ] ):
                            $action = array( 'text' => __( 'Not installed', 'opt-out-for-google-analytics' ), 'url' => current_user_can( 'install_plugins' ) ? $info[ 'url_install' ] : '' );
                        endif;
                        ?>
                        <label class="gaoo-source <?php echo $is_available ? '' : 'is-disabled'; ?>" for="ga-plugin-<?php echo esc_attr( $key ); ?>">
                            <input type="radio" name="gaoo[ga_plugin]" id="ga-plugin-<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $key ); ?>" <?php disabled( ! $is_available ); ?> <?php checked( $key, $ga_plugin ); ?> />
                            <span class="gaoo-source-text">
                                <strong><?php echo esc_html( $label[ 0 ] ); ?></strong>
                                <?php if ( ! empty( $label[ 1 ] ) ): ?>
                                    <span class="description"><?php echo esc_html( $label[ 1 ] ); ?></span>
                                <?php endif; ?>
                            </span>

                            <?php if ( ! empty( $action ) && empty( $action[ 'url' ] ) ): ?>
                                <span class="gaoo-source-status"><?php echo esc_html( $action[ 'text' ] ); ?></span>
                            <?php elseif ( ! empty( $action ) ): ?>
                                <a class="button button-small" href="<?php echo esc_url( $action[ 'url' ] ); ?>" target="<?php echo isset( $info[ 'target' ] ) ? esc_attr( $info[ 'target' ] ) : '_self'; ?>"><?php echo esc_html( $action[ 'text' ] ); ?></a>
                            <?php endif; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="gaoo-card" id="gaoo-section-privacy">
                <h2><?php esc_html_e( "Privacy Policy Page", 'opt-out-for-google-analytics' ); ?></h2>
                <p class="description"><?php esc_html_e( "Select the page where you use the shortcode, so the status check can monitor it.", 'opt-out-for-google-analytics' ); ?></p>

                <div class="gaoo-field gaoo-field-inline">
                    <?php wp_dropdown_pages( array( 'selected' => absint( $privacy_page_id ), 'id' => 'gaoo-privacy-page', 'name' => 'gaoo[privacy_page_id]', 'show_option_none' => esc_html__( "— Select —", 'opt-out-for-google-analytics' ), 'option_none_value' => 0, 'post_status' => 'publish,draft', ) ); ?>

                    <a href="<?php echo esc_url( admin_url( 'post.php?action=edit&post=' . $privacy_page_id ) ); ?>" class="<?php echo empty( $privacy_page_id ) ? 'hide' : ''; ?> gaoo-edit-link button" target="_blank" title="<?php esc_attr_e( 'Edit selected page', 'opt-out-for-google-analytics' ); ?>"><span class="dashicons dashicons-edit" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'Edit selected page', 'opt-out-for-google-analytics' ); ?></span></a>
                </div>

                <div class="gaoo-toggle-row">
                    <label class="gaoo-toggle" for="gaoo-wp-privacy-page">
                        <input type="checkbox" name="gaoo[wp_privacy_page]" value="1" id="gaoo-wp-privacy-page" data-id="<?php echo esc_attr( $wp_privacy_page_id ); ?>" <?php checked( $wp_privacy_page, 1 ); ?>>
                        <span class="gaoo-toggle-text"><?php esc_html_e( "Synchronize with WordPress Privacy Policy page.", 'opt-out-for-google-analytics' ); ?>
                            <a href="<?php echo esc_url( admin_url( 'options-privacy.php' ) ); ?>" target="_blank" title="<?php esc_attr_e( "Go to settings", 'opt-out-for-google-analytics' ); ?>"><span class="dashicons dashicons-external" aria-hidden="true"></span></a></span>
                    </label>
                </div>
            </div>

            <div class="gaoo-card" id="gaoo-section-check">
                <h2><?php esc_html_e( "Status check", 'opt-out-for-google-analytics' ); ?></h2>

                <div class="gaoo-field gaoo-field-inline">
                    <label for="gaoo-status-intervall"><?php esc_html_e( "Run the status check", 'opt-out-for-google-analytics' ); ?></label>
                    <select name="gaoo[status_intervall]" id="gaoo-status-intervall">
                        <option value="daily" <?php selected( $status_intervall, 'daily' ); ?>><?php esc_html_e( 'daily', 'opt-out-for-google-analytics' ); ?></option>
                        <option value="weekly" <?php selected( $status_intervall, 'weekly' ); ?>><?php esc_html_e( 'weekly', 'opt-out-for-google-analytics' ); ?></option>
                        <option value="monthly" <?php selected( $status_intervall, 'monthly' ); ?>><?php esc_html_e( 'monthly', 'opt-out-for-google-analytics' ); ?></option>
                    </select>
                </div>

                <div class="gaoo-field">
                    <label for="gaoo-status-mails"><?php esc_html_e( "Send mail if the status check failed, to", 'opt-out-for-google-analytics' ); ?></label>
                    <span class="gaoo-input-clear"><input type="email" multiple class="large-text" name="gaoo[status_mails]" id="gaoo-status-mails" value="<?php echo esc_attr( $status_mails ); ?>" placeholder="<?php esc_attr_e( "e.g. admin@example.org", 'opt-out-for-google-analytics' ); ?>" aria-describedby="gaoo-status-mails-description" <?php echo empty( $status_mails_sync ) ? '' : 'readonly'; ?>><button type="button" class="gaoo-empty-popup dashicons dashicons-no-alt <?php echo empty( $status_mails ) ? 'empty' : ''; ?>" title="<?php esc_attr_e( 'Click to empty the field.', 'opt-out-for-google-analytics' ); ?>" aria-label="<?php esc_attr_e( 'Click to empty the field.', 'opt-out-for-google-analytics' ); ?>"></button></span>
                    <p class="description" id="gaoo-status-mails-description"><?php esc_html_e( "Separate several email addresses with a comma. Leave empty to disable.", 'opt-out-for-google-analytics' ); ?></p>
                </div>

                <div class="gaoo-toggle-row">
                    <label class="gaoo-toggle" for="gaoo-status-mails-sync">
                        <input type="checkbox" name="gaoo[status_mails_sync]" value="1" id="gaoo-status-mails-sync" <?php checked( $status_mails_sync, 1 ); ?> data-mail="<?php echo esc_attr( $wp_admin_mail ); ?>">
                        <span class="gaoo-toggle-text"><?php esc_html_e( "Synchronize with the WordPress admin email.", 'opt-out-for-google-analytics' ); ?>
                            <a href="<?php echo esc_url( admin_url( 'options-general.php' ) ); ?>" target="_blank" title="<?php esc_attr_e( "Go to settings", 'opt-out-for-google-analytics' ); ?>"><span class="dashicons dashicons-external" aria-hidden="true"></span></a></span>
                    </label>
                </div>

                <div class="gaoo-toggle-row">
                    <label class="gaoo-toggle" for="gaoo-disable-monitoring">
                        <input type="checkbox" name="gaoo[disable_monitoring]" value="1" id="gaoo-disable-monitoring" <?php checked( $disable_monitoring, 1 ); ?>>
                        <span class="gaoo-toggle-text"><?php esc_html_e( "Suppress the message in the dashboard if the settings are not data protection compliant.", 'opt-out-for-google-analytics' ); ?></span>
                    </label>
                </div>

                <div class="gaoo-toggle-row">
                    <label class="gaoo-toggle" for="gaoo-disable-dashboard">
                        <input type="checkbox" id="gaoo-disable-dashboard" name="gaoo[disable_dashboard]" value="on" <?php checked( 'on', $disable_dashboard ); ?> />
                        <span class="gaoo-toggle-text"><?php esc_html_e( "Disable the dashboard widget for this plugin.", 'opt-out-for-google-analytics' ); ?></span>
                    </label>
                </div>


                <?php
                    // The saved password is never shown, only whether one is saved.
                    $has_auth_pass = '' !== (string) GAOO_Utils::get_option( 'check_auth_pass', '' );
                ?>
                <div class="gaoo-auth">
                    <strong><?php esc_html_e( 'Access data for the check of the website', 'opt-out-for-google-analytics' ); ?></strong>
                    <p class="description"><?php esc_html_e( 'Only needed, if the website is protected with a password by the server (HTTP basic authentication), e.g. a staging website. The saved password is not shown, leave the field empty to keep it. Empty the user name to delete the access data.', 'opt-out-for-google-analytics' ); ?></p>
                    <div class="gaoo-grid">
                        <div class="gaoo-field">
                            <label for="gaoo-check-auth-user"><?php esc_html_e( 'User name', 'opt-out-for-google-analytics' ); ?></label>
                            <input type="text" id="gaoo-check-auth-user" name="gaoo[check_auth_user]" class="large-text" value="<?php echo esc_attr( $check_auth_user ); ?>" autocomplete="off" spellcheck="false">
                        </div>
                        <div class="gaoo-field">
                            <label for="gaoo-check-auth-pass"><?php esc_html_e( 'Password', 'opt-out-for-google-analytics' ); ?></label>
                            <input type="password" id="gaoo-check-auth-pass" name="gaoo[check_auth_pass]" class="large-text" value="" autocomplete="new-password" placeholder="<?php echo $has_auth_pass ? esc_attr__( 'Saved, leave empty to keep it', 'opt-out-for-google-analytics' ) : ''; ?>">
                        </div>
                    </div>
                </div>

                <div class="gaoo-auth gaoo-webhook" id="gaoo-webhook">
                    <strong><?php esc_html_e( 'Webhook', 'opt-out-for-google-analytics' ); ?></strong>
                    <?php $this->webhook_fields(); ?>
                </div>

                <?php $api_url = GAOO_API::get_url(); ?>
                <div class="gaoo-auth gaoo-api" id="gaoo-api">
                    <strong><?php esc_html_e( 'Status URL for a monitoring', 'opt-out-for-google-analytics' ); ?></strong>
                    <p class="description"><?php esc_html_e( 'A monitoring service (e.g. UptimeRobot) can check this URL. It returns the result of the status check as JSON, with the HTTP status 200 if everything is fine and 503 if a check failed. The URL contains a secret token, keep it private.', 'opt-out-for-google-analytics' ); ?></p>
                    <?php if ( '' !== $api_url ): ?>
                        <div class="gaoo-api-url">
                            <input type="text" class="large-text code" value="<?php echo esc_attr( $api_url ); ?>" readonly aria-label="<?php esc_attr_e( 'Status URL for a monitoring', 'opt-out-for-google-analytics' ); ?>">
                            <button type="button" class="button button-small gaoo-copy" data-copy="<?php echo esc_attr( $api_url ); ?>" title="<?php esc_attr_e( 'Click to copy the URL!', 'opt-out-for-google-analytics' ); ?>"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span><span class="gaoo-clipboard"></span></button>
                        </div>
                        <p class="gaoo-api-actions">
                            <a class="button button-small" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=gaoo_api_token' ), 'gaoo-api-token' ) ); ?>"><?php esc_html_e( 'Create new URL', 'opt-out-for-google-analytics' ); ?></a>
                            <a class="button button-small" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=gaoo_api_token&disable=1' ), 'gaoo-api-token' ) ); ?>"><?php esc_html_e( 'Disable', 'opt-out-for-google-analytics' ); ?></a>
                        </p>
                    <?php else: ?>
                        <p class="gaoo-api-actions"><a class="button button-small" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=gaoo_api_token' ), 'gaoo-api-token' ) ); ?>"><?php esc_html_e( 'Create status URL', 'opt-out-for-google-analytics' ); ?></a></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="gaoo-card" id="gaoo-section-appearance">
                <h2><?php esc_html_e( "Appearance and behavior", 'opt-out-for-google-analytics' ); ?></h2>

                <div class="gaoo-grid">
                    <div class="gaoo-field">
                        <label for="gaoo-link-deactivate"><?php esc_html_e( "Text of link for deactivate", 'opt-out-for-google-analytics' ); ?></label>
                        <input type="text" id="gaoo-link-deactivate" name="gaoo[link_deactivate]" class="large-text" value="<?php echo esc_attr( $link_deactivate ); ?>" />
                    </div>

                    <div class="gaoo-field">
                        <label for="gaoo-link-activate"><?php esc_html_e( "Text of link for activate", 'opt-out-for-google-analytics' ); ?></label>
                        <input type="text" id="gaoo-link-activate" name="gaoo[link_activate]" class="large-text" value="<?php echo esc_attr( $link_activate ); ?>" />
                    </div>

                    <div class="gaoo-field gaoo-field-full">
                        <label for="gaoo-popup-deactivate"><?php esc_html_e( "Popup Text for deactivate", 'opt-out-for-google-analytics' ); ?></label>
                        <span class="gaoo-input-clear"><textarea id="gaoo-popup-deactivate" name="gaoo[popup_deactivate]" class="large-text" rows="3"><?php echo esc_textarea( html_entity_decode( $popup_deactivate, ENT_QUOTES, 'UTF-8' ) ); ?></textarea><button type="button" class="gaoo-empty-popup dashicons dashicons-no-alt <?php echo empty( $popup_deactivate ) ? 'empty' : ''; ?>" title="<?php esc_attr_e( 'Click to empty the field and disable the popup.', 'opt-out-for-google-analytics' ); ?>" aria-label="<?php esc_attr_e( 'Click to empty the field and disable the popup.', 'opt-out-for-google-analytics' ); ?>"></button></span>
                        <p class="description"><?php esc_html_e( "Leave empty to disable the popup.", 'opt-out-for-google-analytics' ); ?></p>
                    </div>

                    <div class="gaoo-field gaoo-field-full">
                        <label for="gaoo-popup-activate"><?php esc_html_e( "Popup Text for activate", 'opt-out-for-google-analytics' ); ?></label>
                        <span class="gaoo-input-clear"><textarea id="gaoo-popup-activate" name="gaoo[popup_activate]" class="large-text" rows="3"><?php echo esc_textarea( html_entity_decode( $popup_activate, ENT_QUOTES, 'UTF-8' ) ); ?></textarea><button type="button" class="gaoo-empty-popup dashicons dashicons-no-alt <?php echo empty( $popup_activate ) ? 'empty' : ''; ?>" title="<?php esc_attr_e( 'Click to empty the field and disable the popup.', 'opt-out-for-google-analytics' ); ?>" aria-label="<?php esc_attr_e( 'Click to empty the field and disable the popup.', 'opt-out-for-google-analytics' ); ?>"></button></span>
                        <p class="description"><?php esc_html_e( "Leave empty to disable the popup.", 'opt-out-for-google-analytics' ); ?></p>
                    </div>
                </div>

                <div class="gaoo-toggle-row">
                    <label class="gaoo-toggle" for="gaoo-popup-notice">
                        <input type="checkbox" name="gaoo[popup_notice]" value="on" id="gaoo-popup-notice" <?php checked( 'on', $popup_notice ); ?>>
                        <span class="gaoo-toggle-text"><strong><?php esc_html_e( 'Show popup text below the link', 'opt-out-for-google-analytics' ); ?></strong>
                            <span class="description"><?php esc_html_e( 'The text is shown as a notice below the link instead of a browser popup. It can be styled with the CSS class .gaoo-notice.', 'opt-out-for-google-analytics' ); ?></span></span>
                    </label>
                </div>

                <div class="gaoo-toggle-row">
                    <label class="gaoo-toggle" for="gaoo-force-reload">
                        <input type="checkbox" name="gaoo[force_reload]" value="1" id="gaoo-force-reload" <?php checked( $force_reload, 1 ); ?>>
                        <span class="gaoo-toggle-text"><strong><?php esc_html_e( "Force reload", 'opt-out-for-google-analytics' ); ?></strong>
                            <span class="description"><?php esc_html_e( "Force page reload after the click on the link.", 'opt-out-for-google-analytics' ); ?></span></span>
                    </label>
                </div>
            </div>

            <div class="gaoo-card" id="gaoo-section-css">
                <h2><?php esc_html_e( "Add custom CSS", 'opt-out-for-google-analytics' ); ?></h2>
                <p class="description"><?php esc_html_e( "This CSS is only inserted where the shortcode is used.", 'opt-out-for-google-analytics' ); ?></p>
                <p class="description"><?php esc_html_e( 'Note: The CSS applies to all links, also to the Gutenberg block, the Elementor widget and the Divi module. Colors, fonts and spacing set there may be overridden by this CSS.', 'opt-out-for-google-analytics' ); ?></p>

                <div class="gaoo-toggle-row">
                    <label class="gaoo-toggle" for="gaoo-custom-css-status">
                        <input type="hidden" name="gaoo[custom_css_status]" value="off" />
                        <input type="checkbox" id="gaoo-custom-css-status" class="gaoo-section-toggle" name="gaoo[custom_css_status]" value="on" aria-controls="gaoo-custom-css-fields" <?php checked( 'on', $custom_css_status ); ?> />
                        <span class="gaoo-toggle-text"><strong><?php esc_html_e( 'Use custom CSS', 'opt-out-for-google-analytics' ); ?></strong>
                            <span class="description"><?php esc_html_e( 'Switch off to test the website without the custom CSS. The code is kept.', 'opt-out-for-google-analytics' ); ?></span></span>
                    </label>
                </div>

                <?php // Everything below is only shown, while the custom CSS is used. ?>
                <div class="gaoo-section-fields" id="gaoo-custom-css-fields" <?php echo 'on' === $custom_css_status ? '' : 'hidden'; ?>>
                <?php if ( ! current_user_can( 'edit_css' ) ): ?>
                    <div class="notice notice-warning inline gaoo-readonly-notice"><p><?php esc_html_e( 'You are not allowed to change the custom CSS, because your user role may not edit CSS.', 'opt-out-for-google-analytics' ); ?></p></div>
                <?php endif; ?>

                <div class="gaoo-css">
                    <div class="gaoo-css-editor">
                        <div class="gaoo-code">
                            <div class="gaoo-code-lines" aria-hidden="true"></div>
                            <textarea id="gaoo-custom-css" name="gaoo[custom_css]" class="large-text code" rows="14" wrap="off" spellcheck="false" aria-describedby="gaoo-css-hint" <?php echo current_user_can( 'edit_css' ) ? '' : 'readonly'; ?>><?php echo esc_textarea( $custom_css ); ?></textarea>
                        </div>
                        <div class="gaoo-css-check" aria-live="polite"></div>
                        <p class="description" id="gaoo-css-hint"><?php esc_html_e( 'The CSS is checked and formatted when saving. On the website it is minified automatically.', 'opt-out-for-google-analytics' ); ?></p>
                    </div>

                    <div class="gaoo-css-classes">
                        <strong><?php esc_html_e( 'Overview CSS classes', 'opt-out-for-google-analytics' ); ?></strong>
                        <?php if ( current_user_can( 'edit_css' ) ): ?>
                            <p class="description"><?php esc_html_e( 'Click on a class to add it to the CSS. Classes which are already used are highlighted.', 'opt-out-for-google-analytics' ); ?></p>
                        <?php endif; ?>
                        <dl>
                            <?php
                                $css_classes = array(
                                    '.gaoo-link'            => __( 'The link itself.', 'opt-out-for-google-analytics' ),
                                    '.gaoo-link-activate'   => __( 'If the user has DISALLOWED tracking and wants to allow it again.', 'opt-out-for-google-analytics' ),
                                    '.gaoo-link-deactivate' => __( 'If the user has ALLOWED tracking and wants to disallow it again.', 'opt-out-for-google-analytics' ),
                                    '.gaoo-notice'          => __( 'The popup text below the link, if it is not shown as a browser popup.', 'opt-out-for-google-analytics' ),
                                );

                                foreach ( $css_classes as $css_class => $css_description ):
                            ?>
                                <dt><button type="button" class="gaoo-css-class" data-selector="<?php echo esc_attr( $css_class ); ?>" title="<?php esc_attr_e( 'Add to the CSS', 'opt-out-for-google-analytics' ); ?>" <?php disabled( ! current_user_can( 'edit_css' ) ); ?>><code><?php echo esc_html( $css_class ); ?></code><span class="gaoo-css-class-used dashicons dashicons-yes" aria-hidden="true"></span><span class="screen-reader-text gaoo-css-class-used-text"><?php esc_html_e( '(used)', 'opt-out-for-google-analytics' ); ?></span></button></dt>
                                <dd><?php echo esc_html( $css_description ); ?></dd>
                            <?php endforeach; ?>
                        </dl>
                    </div>
                </div>

                <div class="gaoo-preview-wrap">
                    <h3><?php esc_html_e( 'Preview', 'opt-out-for-google-analytics' ); ?></h3>
                    <p class="description"><?php esc_html_e( 'The link with the texts, the behavior and the custom CSS of this page, also before saving. Click the link to test it, the tracking on this website is not changed. The fonts and colors of the theme are not included, a reload of the page is not done.', 'opt-out-for-google-analytics' ); ?></p>
                    <iframe id="gaoo-preview" class="gaoo-preview" title="<?php esc_attr_e( 'Preview', 'opt-out-for-google-analytics' ); ?>" sandbox="allow-scripts allow-modals"></iframe>
                </div>
                </div>
            </div>

            <div class="gaoo-card gaoo-transfer" id="gaoo-section-transfer">
                <h2><?php esc_html_e( 'Import / export', 'opt-out-for-google-analytics' ); ?></h2>
                <p class="description"><?php esc_html_e( 'Transfer the settings to another website as JSON file. The privacy policy page, the access data for the check of the website and the webhook URL are not exported. The tracking code and the custom CSS are only imported, if you may change them.', 'opt-out-for-google-analytics' ); ?></p>

                <div class="gaoo-transfer-columns">
                    <div class="gaoo-transfer-column">
                        <h3><?php esc_html_e( 'Export', 'opt-out-for-google-analytics' ); ?></h3>
                        <p class="description"><?php esc_html_e( 'Download the saved settings as JSON file, e.g. as backup before an import.', 'opt-out-for-google-analytics' ); ?></p>
                        <div class="gaoo-transfer-row">
                            <a class="button gaoo-no-leave" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=gaoo_export' ), 'gaoo-export' ) ); ?>"><span class="dashicons dashicons-download" aria-hidden="true"></span> <?php esc_html_e( 'Export settings', 'opt-out-for-google-analytics' ); ?></a>
                        </div>
                    </div>

                    <div class="gaoo-transfer-column">
                        <h3><?php esc_html_e( 'Import', 'opt-out-for-google-analytics' ); ?></h3>
                        <p class="description"><?php esc_html_e( 'The settings of the file replace the current settings of this page.', 'opt-out-for-google-analytics' ); ?></p>
                        <?php // The fields belong to the import form below the settings form, forms can not be nested. ?>
                        <div class="gaoo-transfer-row">
                            <label for="gaoo-import-file" class="screen-reader-text"><?php esc_html_e( 'JSON file with the settings', 'opt-out-for-google-analytics' ); ?></label>
                            <input type="file" id="gaoo-import-file" name="gaoo_import_file" accept=".json,application/json" form="gaoo-import-form" required>
                            <button type="submit" class="button" form="gaoo-import-form"><span class="dashicons dashicons-upload" aria-hidden="true"></span> <?php esc_html_e( 'Import settings', 'opt-out-for-google-analytics' ); ?></button>
                        </div>
                    </div>
                </div>
            </div>

            <?php wp_nonce_field( 'gaoo-settings' ); ?>

            <div class="gaoo-savebar">
                <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php esc_attr_e( "Save Changes" ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- String of WordPress itself. ?>">
            </div>
        </form>

        <form id="gaoo-import-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
            <input type="hidden" name="action" value="gaoo_import">
            <?php wp_nonce_field( 'gaoo-import' ); ?>
        </form>
    </div>

    <?php $this->render_footer(); ?>
</div>
</div>