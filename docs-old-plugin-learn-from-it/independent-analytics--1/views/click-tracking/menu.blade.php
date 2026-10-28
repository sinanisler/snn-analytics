<div id="click-tracking-menu" class="click-tracking-menu">
    <div class="settings-container">
        <div class="settings-container-header">
            <h1><?php esc_html_e('Click Tracking', 'independent-analytics'); ?></h1>
            <a class="link-purple open-report" href="<?php echo esc_url(iawp_dashboard_url(['tab' => 'clicks'])); ?>"><span class="dashicons dashicons-analytics"></span> <?php esc_html_e('View Clicks Report', 'independent-analytics'); ?></a>
            <a class="link-purple" href="https://independentwp.com/knowledgebase/click-tracking/click-tracking/" target="_blank"><span class="dashicons dashicons-book"></span> <?php esc_html_e('Read Tutorial', 'independent-analytics'); ?></a>
        </div>
        <div id="click-tracking-cache-message-container" class="<?php echo $show_click_tracking_cache_message ? 'show' : ''; ?>">
            <div class="cache-note">
                <span class="dashicons dashicons-warning"></span>
                <p><?php esc_html_e('Please empty your cache to ensure your newest changes are tracked properly.', 'independent-analytics'); ?></p>
                <button id="click-tracking-cache-cleared" class="iawp-button"><?php esc_html_e('Ok', 'independent-analytics'); ?></button>
            </div>
        </div>
        <div id="validation-error-messages" class="validation-error-messages">
            <?php foreach ($error_messages as $class => $message) : ?>
                <p class="<?php echo esc_attr($class); ?>"><span class="dashicons dashicons-warning"></span> <?php echo esc_html($message); ?></p>
            <?php endforeach; ?>
        </div>
        <div class="tracked-links click-tracking-section">
            <div class="heading-container">
                <div>
                    <h2><?php esc_html_e('Link Patterns', 'independent-analytics'); ?></h2>
                    <p class="description">
                        <?php esc_html_e('Links matching the patterns below are being actively monitored for clicks.', 'independent-analytics'); ?>
                    </p>
                </div>
                <button id="create-new-link" class="create-new-link iawp-button purple"><?php esc_html_e('Add Link Pattern', 'independent-analytics'); ?></button>
            </div>
            <div class="table-labels">
                <span><?php esc_html_e('Name', 'independent-analytics'); ?></span>
                <span><?php esc_html_e('Type', 'independent-analytics'); ?></span>
                <span><?php esc_html_e('Value', 'independent-analytics'); ?></span>
                <button class="edit-button-for-spacing"><?php esc_html_e('Edit', 'independent-analytics'); ?></button>
                <button class="edit-button-for-spacing"><?php esc_html_e('Archive', 'independent-analytics'); ?></button>
            </div>
            <div id="tracked-links-list" class="tracked-links-list">
                <div id="sortable-tracked-links-list">
                    <?php
                    foreach($active_links as $link) {
                        echo iawp_blade()->run('click-tracking.link', [
                            'link' => $link,
                            'types' => $types,
                            'extensions' => $extensions,
                            'protocols' => $protocols
                        ]);
                    } ?>
                </div>
            </div>
            <p class="tracked-links-empty-message <?php echo count($active_links) === 0 ? "show" : ""; ?>"><?php esc_html_e('No link patterns found', 'independent-analytics'); ?></p>
            <div id="blueprint-link" class="blueprint-link"><?php 
                echo iawp_blade()->run('click-tracking.link', [
                    'link' => [
                        'id' => null,
                        'name' => '',
                        'type' => 'class',
                        'value' => '',
                        'is_active' => null
                    ],
                    'types' => $types,
                    'extensions' => $extensions,
                    'protocols' => $protocols
                ]); ?>
            </div>
        </div>
        <div id="archived-links" class="archived-links click-tracking-section">
            <h2>
                <?php esc_html_e('Archived Link Patterns', 'independent-analytics'); ?>
            </h2>
            <p class="description">
                <?php esc_html_e('Archived link patterns are no longer tracked, but their data remains in the Clicks report. Deleting an archived link pattern will remove it from this list and remove its data from the Clicks report permanently.', 'independent-analytics'); ?>
            </p>
            <button id="toggle-archived-links" class="iawp-button toggle-archived-links" data-alt-text="<?php esc_html_e('Hide Archived Links', 'independent-analytics'); ?>"><?php esc_html_e('Show Archived Links', 'independent-analytics'); ?></button>
            <div class="archived-links-table">
                <div class="table-labels">
                    <span><?php esc_html_e('Name', 'independent-analytics'); ?></span>
                    <span><?php esc_html_e('Type', 'independent-analytics'); ?></span>
                    <span><?php esc_html_e('Value', 'independent-analytics'); ?></span>
                    <button class="edit-button-for-spacing"><?php esc_html_e('Resume Tracking', 'independent-analytics'); ?></button>
                    <button class="edit-button-for-spacing"><?php esc_html_e('Delete', 'independent-analytics'); ?></button>
                </div>
                <div id="archived-links-list" class="archived-links-list"><?php
                    foreach($inactive_links as $link) {
                        echo iawp_blade()->run('click-tracking.link', [
                            'link' => $link,
                            'types' => $types,
                            'extensions' => $extensions,
                            'protocols' => $protocols
                        ]);
                    } ?>
                </div>
                <p class="archived-links-empty-message <?php echo count($inactive_links) === 0 ? "show" : ""; ?>"><?php esc_html_e('No archived link patterns found', 'independent-analytics'); ?></p>
            </div>
        </div>
    </div>
    <div id="delete-link-modal" aria-hidden="true" class="mm micromodal-slide delete-link-modal">
        <div tabindex="-1" class="mm__overlay" >
            <div role="dialog" aria-modal="true" class="mm__container">
                <div class="modal-title"><?php esc_html_e('Are you sure?', 'independent-analytics'); ?></div>
                <p><?php esc_html_e('Deleting this link pattern will remove its stats from the Clicks report permanently.', 'independent-analytics'); ?></p>
                <button data-link-id="" class="iawp-button purple yes"><?php esc_html_e('Yes', 'independent-analytics'); ?></button>
                <button class="iawp-button ghost-purple cancel" data-micromodal-close><?php esc_html_e('Cancel', 'independent-analytics'); ?></button>
            </div>
        </div>
    </div>
</div>