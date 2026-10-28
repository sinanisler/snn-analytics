@php
/** @var \IAWP\Tables\Table $table */
/** @var \IAWP\Models\Journey[] $rows */
@endphp

<div id="iawp-rows" class="journey-rows">
    <?php echo iawp_blade()->run('journeys.table-heading'); ?>
    <?php if(count($rows) === 0): ?>
        <p class="no-journeys"><?php esc_html_e('No journeys found.', 'independent-analytics'); ?></p>
    <?php endif; ?>
    <?php foreach($rows as $row): ?>
        <div class="journey" data-controller="journey" data-journey-session-id-value="<?php echo esc_attr($row->id()) ?>">
            <div class="journey-preview">
                <p class="journey-cell">
                    <button class="expand-journey">
                        <span class="dashicons dashicons-arrow-right-alt2"></span>
                        <span class="dashicons dashicons-update"></span>
                    </button>
                </p>
                <p class="journey-cell session-start-cell">
                    <span>
                        <span class="dashicons dashicons-clock"></span>
                        <?php echo esc_html($row->session_started_at()); ?>
                    </span>
                </p>
                <p class="journey-cell page-title-cell">
                    <?php echo esc_html($row->landing_page()); ?>
                </p>
                <p class="journey-cell referrer-cell">
                    <?php if($row->referrer_favicon_url()): ?>
                        <img class="iawp-favicon" src="<?php echo esc_url($row->referrer_favicon_url()); ?>" alt="<?php echo esc_attr($row->referrer()); ?>"/>
                    <?php elseif ($row->is_direct()): ?>
                        <img class="iawp-favicon" src="<?php echo esc_url(iawp_url_to('img/direct.png')); ?>" alt="<?php echo esc_attr($row->referrer()); ?>"/>
                    <?php else: ?>
                        <span class="iawp-favicon backup-icon" data-favicon-id="<?php echo esc_attr($row->fallback_favicon_color_id()); ?>"><?php echo esc_html(substr($row->referrer(), 0, 1)); ?></span>
                    <?php endif; ?>
                    <span class="truncated-text"><?php echo esc_html($row->referrer()); ?></span>
                </p>
                <p class="journey-cell utm-source-cell">
                    <?php if($row->utm_source()): ?>
                        <span><?php echo esc_html($row->utm_source()); ?></span>
                    <?php else: ?>
                        <span>&bull;</span>
                    <?php endif; ?>
                </p>
                <p class="journey-cell pages-viewed-cell" data-views-engagement-score="<?php echo esc_attr($row->views_engagement_score()) ?>" data-views="<?php echo esc_attr($row->views()); ?>">
                    <span>
                        <span class="dashicons dashicons-admin-page"></span><?php
                        echo esc_html(sprintf(_n('%s view', '%s views', $row->views(), 'independent-analytics'), number_format_i18n($row->views()))); ?>
                    </span>
                </p>
                <p class="journey-cell duration-cell" data-duration-engagement-score="<?php echo esc_attr($row->duration_engagement_score()) ?>" data-duration="<?php echo esc_attr(floor($row->duration_in_seconds()/30)); ?>">
                    <?php if($row->duration()): ?>
                        <span><span class="dashicons dashicons-clock"></span> <?php echo esc_html($row->duration()); ?></span>
                    <?php else: ?>
                        &bull;
                    <?php endif; ?>
                </p>
                <p class="journey-cell conversions-cell">
                    <?php if($row->has_conversion()): ?>
                        <?php if($row->has_order()): ?>
                            <span class="journey-conversion order"><?php esc_html_e('Order', 'independent-analytics'); ?></span>
                        <?php endif; ?>
                        <?php if($row->has_click()): ?>
                            <span class="journey-conversion click"><?php esc_html_e('Click', 'independent-analytics'); ?></span>
                        <?php endif; ?>
                        <?php if($row->has_form_submission()): ?>
                            <span class="journey-conversion submission"><?php esc_html_e('Form', 'independent-analytics'); ?></span>
                        <?php endif; ?>
                    <?php else: ?>
                        &bull;
                    <?php endif; ?>
                </p>
                <button class="expand-journey-overlay" data-action="click->journey#toggleTimeline"><?php esc_html_e('Expand Journey', 'independent-analytics'); ?></button>
            </div>
            <div class="journey-timeline" data-journey-target="journey"></div>
        </div>
    <?php endforeach; ?>
</div>
