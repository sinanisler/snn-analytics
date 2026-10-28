<div id="iawp-date-picker" class="iawp-date-picker" data-relative-range="<?php echo esc_attr($relative_range); ?>">
    <div class="iawp-date-inputs">
        <div class="iawp-input-container prev-month">
            <button class="iawp-fast-travel prev-month" 
                data-month="<?php echo esc_attr($start_date->format('Y-m')); ?>"
                tabindex="-1">
                    <span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>
            </button>
            <input id="iawp-start-date-keyboard" class="iawp-start-date-keyboard keyboard-input" type="date" 
                value="<?php echo esc_attr(iawp()->date_i18n('Y-m-d', $start_date)); ?>" 
                aria-label="<?php esc_html_e('Start date', 'independent-analytics'); ?>" />
            <input id="iawp-start-date" class="iawp-start-date iawp-active" type="text" 
                readonly value="<?php echo esc_attr(iawp()->date_i18n($user_format, $start_date)); ?>"
                data-date="<?php echo esc_attr($start_date->format('Y-m-d')); ?>"
                data-month="<?php echo esc_attr($start_date->format('Y-m')); ?>" 
                tabindex="-1" />
        </div>
        <span class="iawp-date-input-separator">-</span>
        <div class="iawp-input-container current-month">
            <input id="iawp-end-date-keyboard" class="iawp-end-date-keyboard keyboard-input" type="date" 
                value="<?php echo esc_attr(iawp()->date_i18n('Y-m-d', $end_date)); ?>" 
                aria-label="<?php esc_html_e('End date', 'independent-analytics'); ?>" />
            <input id="iawp-end-date" class="iawp-end-date" type="text" 
                readonly value="<?php echo esc_attr(iawp()->date_i18n($user_format, $end_date)); ?>"
                data-date="<?php echo esc_attr($end_date->format('Y-m-d')); ?>"
                data-month="<?php echo esc_attr($end_date->format('Y-m')); ?>"
                tabindex="-1" />
            <button class="iawp-fast-travel current-month" 
                data-month="<?php echo esc_attr($end_date->format('Y-m')); ?>"
                tabindex="-1">
                    <span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>
            </button>
        </div>
        <div class="apply-buttons">
            <button id="apply-date"
                    class="iawp-button purple"
                    data-testid="apply-dates"
            >
                <?php esc_html_e('Apply', 'independent-analytics'); ?>
            </button>
            <button id="cancel-date"
                    class="iawp-button ghost-purple"
                    data-testid="close-calendar"
            >
                <?php esc_html_e('Cancel', 'independent-analytics'); ?>
            </button>
        </div>
    </div>
    <div id="iawp-calendars" class="iawp-calendars"><?php 
        foreach($months as $month) {
            echo iawp_blade()->run('date-picker.calendar-month', [
                'month' => $month,
                'start_date' => $start_date->format('Y-m-d'),
                'end_date' => $end_date->format('Y-m-d'),
                'user_format' => $user_format,
                'first_data' => $first_data
            ]); 
        } ?>
    </div>
    <div class="relative-dates iawp-date-range-buttons"><?php 
        foreach ($date_ranges as $date_range):
            $exact_start = $date_range->start()->setTimezone($timezone)->format('Y-m-d');
            $exact_end   = $date_range->end()->setTimezone($timezone)->format('Y-m-d'); 
            $classes     = $relative_range == $date_range->relative_range_id() ? 'iawp-button active' : 'iawp-button'; ?>
            <button class="<?php echo esc_attr($classes); ?>"
                    data-dates-target="relativeRange"
                    data-action="dates#relativeRangeSelected"
                    data-relative-range-id="<?php echo esc_attr($date_range->relative_range_id()) ?>"
                    data-relative-range-label="<?php echo esc_attr($date_range->label()) ?>"
                    data-relative-range-start="<?php echo esc_attr($exact_start) ?>"
                    data-relative-range-end="<?php echo esc_attr($exact_end) ?>"
                    data-display-date-start="<?php echo (new \DateTime($exact_start))->format($user_format); ?>"
                    data-display-date-end="<?php echo (new \DateTime($exact_end))->format($user_format); ?>"
                    data-timestamp-start="<?php echo strtotime($exact_start); ?>"
                    data-timestamp-end="<?php echo strtotime($exact_end); ?>"
            >
                <?php echo esc_html($date_range->label()) ?>
            </button>
        <?php endforeach; ?>
    </div>
</div>