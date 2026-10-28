<div class="<?php echo esc_attr($month->month_class()); ?>" data-month="<?php echo esc_attr($month->date_string()); ?>">
    <div class="iawp-calendar-heading">
        <button class="iawp-prev-month-nav iawp-month-nav"
            data-month="<?php echo esc_attr($month->date_string()); ?>"
            data-direction="prev"
            tabindex="-1">
            <span class="dashicons dashicons-arrow-left-alt2"></span>
        </button>
        <span class="iawp-month-name"><?php echo esc_html($month->name()); ?></span>
        <button class="iawp-next-month-nav iawp-month-nav"
            data-month="<?php echo esc_attr($month->date_string()); ?>"
            data-direction="next"
            tabindex="-1">
            <span class="dashicons dashicons-arrow-right-alt2"></span>
        </button>
    </div>
    <div class="iawp-day-names">
        <?php echo wp_kses_post($month->days_of_week()); ?>
    </div>
    <div class="iawp-days"><?php
        for ($i = 0; $i < $month->extra_cells(); $i++) {
            echo '<span class="iawp-cell empty"></span>';
        }
        foreach($month->days() as $day) :
            $day->setTime(0, 0, 0, 0);
            $class = $month->day_class($day, $first_data, $start_date, $end_date); ?>
            <span class="<?php echo esc_attr($class); ?>" 
                data-date="<?php echo esc_attr($day->format('Y-m-d')); ?>"
                data-display-date="<?php echo esc_attr(iawp()->date_i18n($user_format, $day)); ?>"
                aria-label="<?php echo esc_attr($day->format('F j')); ?>">
                    <?php echo esc_html($day->format('j')); ?>
                    <?php if ($day->format('Y-m-d') == $first_data) : ?>
                        <span class="first-data-note"><?php esc_html_e('First view recorded', 'independent-analytics'); ?></span>
                    <?php endif; ?>
            </span>
        <?php endforeach; ?>
    </div>
</div>