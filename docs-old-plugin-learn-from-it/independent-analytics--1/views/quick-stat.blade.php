<div class="iawp-stat <?php echo esc_attr($id); ?> <?php echo $is_visible ? 'visible' : ''; ?>"
        data-id="<?php echo esc_attr($id); ?>" data-quick-stats-target="quickStat">
    <div class="metric">
        <span class="metric-name"><?php echo esc_html($name); ?></span>
        <?php if(!is_null($icon)) : ?>
            <span class="plugin-label"><?php echo iawp_icon($icon); ?></span>
        <?php endif; ?>
    </div>
    <div class="values">
        <span class="count"
                test-value="<?php echo esc_attr(strip_tags($formatted_value)); ?>">
            <?php echo wp_kses($formatted_value, ['span' => []]);
            if ($formatted_unfiltered_value) : ?>
                <span class="unfiltered"> / <?php echo wp_kses($formatted_unfiltered_value, ['span' => []]); ?></span>
            <?php endif; ?>
        </span>
    </div>
    <span class="growth">
        <span class="percentage <?php echo esc_attr($growth_html_class); ?>"
                test-value="<?php echo esc_attr($growth); ?>">
            <span class="dashicons dashicons-arrow-up-alt growth-arrow"></span>
                <?php echo esc_html($formatted_growth); ?>
            </span>
        <span class="period-label"><?php echo esc_html__('vs. previous period', 'independent-analytics'); ?></span>
    </span>
</div>