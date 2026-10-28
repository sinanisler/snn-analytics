<div class="blocked-by-role-settings settings-container">
    <div class="heading">
        <h2><?php esc_html_e('Ignore User Roles', 'independent-analytics'); ?></h2>
        <a class="tutorial-link" href="https://independentwp.com/knowledgebase/data/block-user-roles/" target="_blank">
            <?php esc_html_e('Read Tutorial', 'independent-analytics'); ?>
        </a>
    </div>
    <p><?php esc_html_e('Ignored user roles can still access the site, but their activity will not show up in the analytics.', 'independent-analytics'); ?></p>
    <form method='post' action='options.php' id="block-by-role-form" class="block-by-role-form">
        <input type='hidden' name='option_page' value='iawp_block_by_role_settings'/>
        <input type="hidden" name="action" value="update"/>
        <input type="hidden" name="_wp_http_referer"
               value="/wp-admin/admin.php?page=independent-analytics-settings">
        <?php wp_nonce_field('iawp_block_by_role_settings-options'); ?>
        <div class="inner">
            <div class="block-by-role duplicator">
                <div class="entry">
                    <select class="new-field select" value="">
                        <?php foreach ($roles as $role => $data) {
                            if (in_array($role, $blocked)) {
                                continue;
                            }
                            echo '<option value="' . esc_attr($role) . '">' . esc_html($data['name']) . '</option>';
                        } ?>
                    </select>
                    <button class="iawp-button purple duplicate-button"><?php esc_html_e('Add', 'independent-analytics'); ?></button>
                </div>
                <div class="blueprint">
                    <div class="entry">
                        <input type="text" readonly 
                            name="iawp_blocked_roles[]" 
                            id="iawp_blocked_roles[]"
                            data-option="iawp_blocked_roles"
                            value="">
                        <button class="remove iawp-button ghost-purple"><?php esc_html_e('Remove', 'independent-analytics'); ?></button>
                    </div>
                </div>
                <p class="error-message empty"><?php esc_html_e('Input is empty', 'independent-analytics'); ?></p>
                <p class="error-message exists"><?php esc_html_e('This user role is already blocked', 'independent-analytics'); ?></p>
            </div>
            <div class="saved">
                <h3><?php esc_html_e('Ignored User Roles', 'independent-analytics'); ?></h3>
                <?php for ($i = 0; $i < count($blocked); $i++): ?>
                    <div class="entry">
                        <input type="text" readonly
                               name="iawp_blocked_roles[<?php echo esc_attr($i); ?>]"
                               id="iawp_blocked_roles[<?php echo esc_attr($i); ?>]"
                               data-option="iawp_blocked_roles"
                               value="<?php echo esc_attr($blocked[$i]); ?>">
                        <button class="remove iawp-button ghost-purple"><?php esc_html_e('Remove', 'independent-analytics'); ?></button>
                    </div>
                <?php endfor; ?>
                <?php if (count($blocked) === 0): ?>
                    <p class="none"><?php esc_html_e('No ignored User Roles', 'independent-analytics'); ?></p>
                <?php endif; ?>
            </div>
            <label class="column-label" for="iawp_ignore_via_cookie">
                <input type="checkbox" name="iawp_ignore_via_cookie"
                    id="iawp_ignore_via_cookie" <?php checked(true, $ignore_cookie, true); ?>>
                <span><?php esc_html_e('Ignore via cookie', 'independent-analytics'); ?></span>
                <p class="description"><?php 
                    esc_html_e('Use a cookie so activity can be ignored when logged out too.', 'independent-analytics'); ?> 
                    <a href="https://independentwp.com/knowledgebase/tracking/block-user-roles/" target="_blank"><?php esc_html_e('Learn more.', 'independent-analytics'); ?></a>
                </p>
            </label>
            <div class="save-button-container">
                <?php submit_button(esc_html__('Save User Roles', 'independent-analytics'), 'iawp-button purple', 'save-block-by-role-settings', false); ?>
                <p class="warning-message"><span class="dashicons dashicons-warning"></span> <?php esc_html_e('Unsaved changes', 'independent-analytics'); ?></p>
            </div>
        </div>
    </form>
</div>