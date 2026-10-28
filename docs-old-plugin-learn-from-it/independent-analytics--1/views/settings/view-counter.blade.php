<div class="view-counter-settings settings-container">
    <a class="tutorial-link absolute" href="https://independentwp.com/knowledgebase/dashboard/display-view-counter/" target="_blank">
        <?php esc_html_e('Read Tutorial', 'independent-analytics'); ?>
    </a>
    <form method="post" action="options.php">
        <?php settings_fields('iawp_view_counter_settings'); ?>
        <?php do_settings_sections('independent-analytics-view-counter-settings'); ?>
        <div class="shortcode-note">
            <h3><?php esc_html_e('Using the shortcode','independent-analytics'); ?></h3>
            <p><?php esc_html_e('You can output the view counter in a custom location using the shortcode:', 'independent-analytics'); ?>
                <code>[iawp_view_counter]</code>
            </p>
            <p><?php esc_html_e('You can also customize the icon display, label, and date range used by the shortcode.', 'independent-analytics'); ?> 
                <a class="link-purple" style="text-decoration: underline;" href="https://independentwp.com/knowledgebase/dashboard/display-view-counter/" target="_blank"><?php esc_html_e('Learn more', 'independent-analytics'); ?></a>
            </p>
        </div>
        <?php submit_button(__('Save Settings', 'independent-analytics'), 'iawp-button purple', 'save-view-counter-settings'); ?>
    </form>
</div>