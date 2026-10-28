@php
/** @var IAWP\Journey\Timeline $timeline */
@endphp

<div class="journey-timeline-container">
    <div class="journey-timeline-events-container">
        <div class="journey-heading-container">
            <h3><?php esc_html_e('Session Timeline', 'independent-analytics'); ?></h3>
            <div class="journey-user-info">
                <div class="view-all-sessions <?php if($timeline->origin()->session_count() == 1) echo 'no-other-sessions'; ?>">
                    <span class="dashicons dashicons-admin-users"></span>
                    <?php if($timeline->origin()->session_count() > 1): ?>
                        <a href="<?php echo esc_url($timeline->visitor_url()); ?>" target="_blank"><?php echo esc_html($timeline->session_count_message()); ?> &rarr;</a>
                    <?php else: ?>
                        <?php echo esc_html($timeline->session_count_message()); ?>
                    <?php endif; ?>
                </div>
                <div class="icons">
                    <?php if($timeline->origin()->country_flag_url()): ?>
                        <button class="icon-button" data-controller="tooltip" data-tooltip-text-value="<?php echo esc_attr($timeline->origin()->country()); ?>">
                            <img class="icon" src="<?php echo esc_attr($timeline->origin()->country_flag_url()) ?>" alt="<?php echo esc_attr($timeline->origin()->country()); ?>">
                        </button>
                    <?php endif; ?>
                    <?php if($timeline->origin()->device_type()): ?>
                        <button class="icon-button" data-controller="tooltip" data-tooltip-text-value="<?php echo esc_attr($timeline->origin()->device_type()); ?>">
                            <img class="icon" src="<?php echo esc_attr($timeline->origin()->device_type_url()) ?>" alt="<?php echo esc_attr($timeline->origin()->device_type()); ?>">
                        </button>
                    <?php endif; ?>
                    <?php if($timeline->origin()->device_browser()): ?>
                        <button class="icon-button" data-controller="tooltip" data-tooltip-text-value="<?php echo esc_attr($timeline->origin()->device_browser()); ?>">
                            <img class="icon" src="<?php echo esc_attr($timeline->origin()->device_browser_url()) ?>" alt="<?php echo esc_attr($timeline->origin()->device_browser()); ?>">
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="journey-timeline-events">
            <?php foreach($timeline->events() as $event): ?>
                <span class="timeline-event-time"><?php
                    if ($event->type() == 'origin') {
                        echo esc_html($timeline->created_at_for_humans());
                    } else {
                        echo esc_html($event->created_at_for_humans());
                    } ?>
                </span>
                <div class="journey-timeline-event <?php echo esc_attr($event->type()); ?>">
                    <?php if (in_array($event->type(), ['click', 'submission', 'order'])) : ?>
                        <div class="border-box-1"></div>
                        <div class="border-box-2"></div>
                        <div class="border-box-3"></div>
                        <div class="border-box-4"></div>
                        <div class="dotted-border"></div>
                    <?php endif; ?>
                    <span class="timeline-event-label"><?php echo esc_html($event->label()); ?></span>
                    <div class="timeline-event-contents">
                        <?php echo wp_kses_post($event->html()); ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <span class="timeline-exit">
                <span><?php esc_html_e('Exit', 'independent-analytics'); ?></span>
            </span>
        </div>
    </div>
    <div class="journey-timeline-conversions-container">
        <h3><?php esc_html_e('Conversions', 'independent-analytics'); ?></h3>
        <div class="journey-timeline-conversions">
            <?php if (count($timeline->conversion_events()) == 0) : ?>
                <span><?php esc_html_e('This session did not include any conversions.', 'independent-analytics'); ?></span>
            <?php else: ?>
                <?php foreach($timeline->conversion_events() as $event): ?>
                    <div class="journey-timeline-event <?php echo esc_attr($event->type()); ?>">
                        <span class="timeline-event-label"><?php echo esc_html($event->label()); ?></span>
                        <div class="timeline-event-contents">
                            <?php echo wp_kses_post($event->html()); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
