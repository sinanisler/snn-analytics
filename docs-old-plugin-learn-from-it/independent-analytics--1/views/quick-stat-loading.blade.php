<div class="iawp-stat visible <?php echo esc_attr($id); ?>"
        data-id="<?php echo esc_attr($id); ?>" data-quick-stats-target="quickStat">
    <div class="metric">
        <span class="metric-name"><?php echo esc_html($name); ?></span>
        <?php if(!is_null($icon)) : ?>
            <span class="plugin-label"><?php echo iawp_icon($icon); ?></span>
        <?php endif; ?>
    </div>
    <div class="values">
        <span class="count">
            <span class="skeleton-loader"></span>
        </span>
    </div>
    <span class="growth">
        <span class="percentage">
            <span class="skeleton-loader"></span>
        </span> 
    </span>
</div>