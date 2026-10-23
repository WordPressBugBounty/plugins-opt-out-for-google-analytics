<?php
    // If this file is called directly, abort.
    defined( 'WPINC' ) || die;

    $language   = strstr( GAOO_LOCALE, '_', true );
    $language   = empty( $language ) ? 'en' : $language;
    $is_manual  = ( $ga_plugin == 'manual' || empty( $ga_plugin ) );
    $stars      = str_repeat( "<svg xmlns='http://www.w3.org/2000/svg' width='15' height='15' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><polygon points='12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2' /></svg>", 5 );
?>

<div class="wrap gaoo-settings" id="gaoo">

    <div class="gaoo-header">
        <div>
            <h1>Opt-Out for Google Analytics</h1>
            <p><?php esc_html_e( "Do you have a data processing agreement for Google Analytics?", 'opt-out-for-google-analytics' ); ?>
                <a href="<?php echo esc_url( 'https://support.google.com/analytics/answer/3379636?hl=' . $language ); ?>" target="_blank"><?php esc_html_e( 'More infos.', 'opt-out-for-google-analytics' ); ?></a>
            </p>
        </div>

        <a class="gaoo-rate" href="https://wordpress.org/support/plugin/opt-out-for-google-analytics/reviews/?rate=5#new-post" target="_blank">
            <span><?php esc_html_e( 'Please rate this plugin if it helped you:', 'opt-out-for-google-analytics' ); ?></span>
            <span class="gaoo-rate-stars"><?php echo $stars; ?></span>
        </a>
    </div>

    <hr class="wp-header-end">

    <?php $this->messages->render( true ); ?>

    <div class="gaoo-main">

        <div class="gaoo-card gaoo-card-flush">
            <?php $this->render_status( 'settings' ); ?>
        </div>

        <div class="gaoo-card gaoo-shortcode">
            <p><?php printf( esc_html__( "Use this shortcode on every page or post you want, to display the GA Opt Out: %s", 'opt-out-for-google-analytics' ), '<code title="' . esc_attr__( 'Click to copy the shortcode!', 'opt-out-for-google-analytics' ) . '">' . esc_html( GAOO_SHORTCODE ) . '</code>' ); ?>
                <button type="button" class="button button-small gaoo-copy" data-copy="<?php echo esc_attr( GAOO_SHORTCODE ); ?>" title="<?php esc_attr_e( 'Click to copy the shortcode!', 'opt-out-for-google-analytics' ); ?>"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span><span class="gaoo-clipboard"></span></button>
            </p>
        </div>

        <?php GAOO_Promo::render( false, true ); ?>

        <form method="post">

            <div class="gaoo-card">
                <h2><?php esc_html_e( "Status", 'opt-out-for-google-analytics' ); ?></h2>

                <label class="gaoo-toggle" for="gaoo-status">
                    <input type="checkbox" name="gaoo[status]" id="gaoo-status" value="on" <?php checked( true, ( $status == 'on' || empty( $status ) ) ); ?> />
                    <span class="gaoo-toggle-text"><?php esc_html_e( "Enable Opt-Out function", 'opt-out-for-google-analytics' ); ?></span>
                </label>

                <label class="gaoo-toggle" for="gaoo-force-reload">
                    <input type="checkbox" name="gaoo[force_reload]" value="1" id="gaoo-force-reload" <?php checked( $force_reload, 1 ); ?>>
                    <span class="gaoo-toggle-text"><strong><?php esc_html_e( "Force reload", 'opt-out-for-google-analytics' ); ?></strong>
                        <span class="description"><?php esc_html_e( "Force page reload after the click on the link.", 'opt-out-for-google-analytics' ); ?></span></span>
                </label>
            </div>

            <div class="gaoo-card">
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
                                <a href="<?php echo esc_url( 'https://support.google.com/analytics/answer/9539598?hl=' . $language ); ?>" target="_blank"><?php esc_html_e( 'More infos.', 'opt-out-for-google-analytics' ); ?></a></p>
                        </div>

                        <div class="gaoo-field">
                            <textarea id="ga-plugin-tracking-code" class="large-text code" rows="6" name="gaoo[tracking_code]" placeholder="<?php esc_attr_e( 'Enter here your tracking code for Google Analytics to insert it on your whole website. Leave empty if you do not want it.', 'opt-out-for-google-analytics' ); ?>"><?php echo esc_textarea( $tracking_code ); ?></textarea>
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

            <div class="gaoo-card">
                <h2><?php esc_html_e( "Privacy Policy Page", 'opt-out-for-google-analytics' ); ?></h2>
                <p class="description"><?php esc_html_e( "Select the page, where you will use this shortcode, for the monitoring.", 'opt-out-for-google-analytics' ); ?></p>

                <div class="gaoo-field gaoo-field-inline">
                    <?php wp_dropdown_pages( array( 'selected' => empty( $privacy_page_id ) ? 0 : $privacy_page_id, 'id' => 'gaoo-privacy-page', 'name' => 'gaoo[privacy_page_id]', 'show_option_none' => esc_html__( "— Select —", 'opt-out-for-google-analytics' ), 'option_none_value' => 0, 'post_status' => 'publish,draft', ) ); ?>

                    <a href="<?php echo esc_url( admin_url( 'post.php?action=edit&post=' . $privacy_page_id ) ); ?>" class="<?php echo empty( $privacy_page_id ) ? 'hide' : ''; ?> gaoo-edit-link button" target="_blank" title="<?php esc_attr_e( 'Edit selected page', 'opt-out-for-google-analytics' ); ?>"><span class="dashicons dashicons-edit" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'Edit selected page', 'opt-out-for-google-analytics' ); ?></span></a>
                </div>

                <label class="gaoo-toggle" for="gaoo-wp-privacy-page">
                    <input type="checkbox" name="gaoo[wp_privacy_page]" value="1" id="gaoo-wp-privacy-page" data-id="<?php echo esc_attr( $wp_privacy_page_id ); ?>" <?php checked( $wp_privacy_page, 1 ); ?>>
                    <span class="gaoo-toggle-text"><?php esc_html_e( "Synchronize with WordPress Privacy Policy page.", 'opt-out-for-google-analytics' ); ?>
                        <a href="<?php echo esc_url( admin_url( 'options-privacy.php' ) ); ?>" target="_blank" title="<?php esc_attr_e( "Go to settings", 'opt-out-for-google-analytics' ); ?>"><span class="dashicons dashicons-external" aria-hidden="true"></span></a></span>
                </label>
            </div>

            <div class="gaoo-card">
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
                    <input type="email" multiple class="regular-text" name="gaoo[status_mails]" id="gaoo-status-mails" value="<?php echo esc_attr( $status_mails ); ?>" placeholder="<?php esc_attr_e( "e.g. admin@example.org", 'opt-out-for-google-analytics' ); ?>" <?php echo empty( $status_mails_sync ) ? '' : 'readonly'; ?>>
                    <p class="description"><?php esc_html_e( "Leave empty to disable.", 'opt-out-for-google-analytics' ); ?></p>
                </div>

                <label class="gaoo-toggle" for="gaoo-status-mails-sync">
                    <input type="checkbox" name="gaoo[status_mails_sync]" value="1" id="gaoo-status-mails-sync" <?php checked( $status_mails_sync, 1 ); ?> data-mail="<?php echo esc_attr( $wp_admin_mail ); ?>">
                    <span class="gaoo-toggle-text"><?php esc_html_e( "synchronize with WordPress admin mail", 'opt-out-for-google-analytics' ); ?>
                        <a href="<?php echo esc_url( admin_url( 'options-general.php' ) ); ?>" target="_blank" title="<?php esc_attr_e( "Go to settings", 'opt-out-for-google-analytics' ); ?>"><span class="dashicons dashicons-external" aria-hidden="true"></span></a></span>
                </label>

                <label class="gaoo-toggle" for="gaoo-disable-monitoring">
                    <input type="checkbox" name="gaoo[disable_monitoring]" value="1" id="gaoo-disable-monitoring" <?php checked( $disable_monitoring, 1 ); ?>>
                    <span class="gaoo-toggle-text"><?php esc_html_e( "Suppress the message in the dashboard if the settings are not data protection compliant.", 'opt-out-for-google-analytics' ); ?></span>
                </label>

                <label class="gaoo-toggle" for="gaoo-disable-dashboard">
                    <input type="checkbox" id="gaoo-disable-dashboard" name="gaoo[disable_dashboard]" value="on" <?php checked( 'on', $disable_dashboard ); ?> />
                    <span class="gaoo-toggle-text"><?php esc_html_e( "Disable the dashboard widget for this plugin.", 'opt-out-for-google-analytics' ); ?></span>
                </label>
            </div>

            <div class="gaoo-card">
                <h2><?php esc_html_e( "Link texts", 'opt-out-for-google-analytics' ); ?></h2>

                <div class="gaoo-grid">
                    <div class="gaoo-field">
                        <label for="gaoo-link-deactivate"><?php esc_html_e( "Text of link for deactivate", 'opt-out-for-google-analytics' ); ?></label>
                        <input type="text" id="gaoo-link-deactivate" name="gaoo[link_deactivate]" class="large-text" value="<?php echo esc_attr( $link_deactivate ); ?>" />
                    </div>

                    <div class="gaoo-field">
                        <label for="gaoo-link-activate"><?php esc_html_e( "Text of link for activate", 'opt-out-for-google-analytics' ); ?></label>
                        <input type="text" id="gaoo-link-activate" name="gaoo[link_activate]" class="large-text" value="<?php echo esc_attr( $link_activate ); ?>" />
                    </div>

                    <div class="gaoo-field">
                        <label for="gaoo-popup-deactivate"><?php esc_html_e( "Popup Text for deactivate", 'opt-out-for-google-analytics' ); ?></label>
                        <span class="gaoo-input-clear"><input type="text" id="gaoo-popup-deactivate" name="gaoo[popup_deactivate]" class="large-text" value="<?php echo esc_attr( $popup_deactivate ); ?>" /><span class="gaoo-empty-popup dashicons dashicons-no-alt <?php echo empty( $popup_deactivate ) ? 'empty' : ''; ?>" title="<?php esc_attr_e( 'Click to empty field, to disable the popup.', 'opt-out-for-google-analytics' ); ?>"></span></span>
                        <p class="description"><?php esc_html_e( "Leave empty to disable popup", 'opt-out-for-google-analytics' ); ?></p>
                    </div>

                    <div class="gaoo-field">
                        <label for="gaoo-popup-activate"><?php esc_html_e( "Popup Text for activate", 'opt-out-for-google-analytics' ); ?></label>
                        <span class="gaoo-input-clear"><input type="text" id="gaoo-popup-activate" name="gaoo[popup_activate]" class="large-text" value="<?php echo esc_attr( $popup_activate ); ?>" /><span class="gaoo-empty-popup dashicons dashicons-no-alt <?php echo empty( $popup_activate ) ? 'empty' : ''; ?>" title="<?php esc_attr_e( 'Click to empty field, to disable the popup.', 'opt-out-for-google-analytics' ); ?>"></span></span>
                        <p class="description"><?php esc_html_e( "Leave empty to disable popup", 'opt-out-for-google-analytics' ); ?></p>
                    </div>
                </div>
            </div>

            <div class="gaoo-card">
                <h2><?php esc_html_e( "Add custom css", 'opt-out-for-google-analytics' ); ?></h2>
                <p class="description"><?php esc_html_e( "This CSS is only inserted where the shortcode is used.", 'opt-out-for-google-analytics' ); ?></p>

                <div class="gaoo-css">
                    <textarea id="gaoo-custom-css" name="gaoo[custom_css]" class="large-text code" rows="10"><?php echo esc_textarea( $custom_css ); ?></textarea>

                    <div class="gaoo-css-classes">
                        <strong><?php esc_html_e( 'Overview CSS classes', 'opt-out-for-google-analytics' ); ?></strong>
                        <dl>
                            <dt><code>.gaoo-link</code></dt>
                            <dd><?php esc_html_e( 'The link itselfs.', 'opt-out-for-google-analytics' ); ?></dd>
                            <dt><code>.gaoo-link-activate</code></dt>
                            <dd><?php esc_html_e( 'If the user has DISALLOWED tracking and wants to allow it again.', 'opt-out-for-google-analytics' ); ?></dd>
                            <dt><code>.gaoo-link-deactivate</code></dt>
                            <dd><?php esc_html_e( 'If the user has ALLOWED tracking and wants to diallow it again.', 'opt-out-for-google-analytics' ); ?></dd>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="gaoo-card">
                <h2><?php esc_html_e( "Keep data after uninstall", 'opt-out-for-google-analytics' ); ?></h2>

                <label class="gaoo-toggle" for="gaoo-uninstall-keep-data">
                    <input type="checkbox" id="gaoo-uninstall-keep-data" name="gaoo[uninstall_keep_data]" value="on" <?php checked( 'on', $uninstall_keep_data ); ?> />
                    <span class="gaoo-toggle-text"><?php esc_html_e( "If the settings and language files of this plugin should be kept after uninstallation, activate this checkbox. By default, all settings will be irrevocably deleted.", 'opt-out-for-google-analytics' ); ?></span>
                </label>
            </div>

            <?php wp_nonce_field( 'gaoo-settings' ); ?>

            <div class="gaoo-savebar">
                <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php esc_attr_e( "Save Changes" ); ?>">
            </div>
        </form>
    </div>
</div>