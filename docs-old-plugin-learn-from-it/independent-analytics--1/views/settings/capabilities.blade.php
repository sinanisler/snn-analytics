<div class="user-capability-settings settings-container">
    <div class="heading">
        <h2><?php esc_html_e('User Permissions', 'independent-analytics'); ?></h2>
        <a class="tutorial-link" href="https://independentwp.com/knowledgebase/dashboard/give-users-permission-view-analytics/" target="_blank">
            <?php esc_html_e('Read Tutorial', 'independent-analytics'); ?>
        </a>
    </div>
    <p><?php esc_html_e('Decide which users can view the analytics and edit the settings.', 'independent-analytics'); ?></p>
    <form id="capabilities-form" method="post" action="options.php">
        <div class="inner">
            <p class="note"><?php esc_html_e('Admins can always view the analytics and edit the settings.', 'independent-analytics'); ?></p>
            <div class="user-roles">
                <?php foreach ($editable_roles as $role): ?>
                    <div class="user-role">
                        <p><?php echo esc_html($role['name']); ?></p>
                        <div class="role role-<?php echo esc_attr($role['key']); ?>">
                            <select name="<?php echo esc_attr($role['key']); ?>">
                                <option value=""><?php esc_html_e('No access', 'independent-analytics'); ?></option>
                                <?php foreach ($capabilities as $capability_key => $capability_label): ?>
                                    <option value="<?php echo esc_attr($capability_key) ?>"
                                        <?php selected($role['capability'] === $capability_key) ?>
                                    >
                                        <?php echo esc_html($capability_label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="white-label-setting">
            <label name="iawp_white_label" for="iawp_white_label">
                <input type="checkbox" name="iawp_white_label" id="iawp_white_label" <?php checked(get_option('iawp_white_label'), true, true); ?> />
                <span><?php esc_html_e('White-label for non-admins', 'independent-analytics'); ?></span>                
            </label>
        </div>
        <div class="save-button-container">
            <button id="save-permissions" class="iawp-button purple"><?php esc_html_e('Save Permissions', 'independent-analytics'); ?></button>
        </div>
    </form>
</div>