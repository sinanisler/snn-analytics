<select name="iawp_visitor_salt_refresh_interval" id="iawp_visitor_salt_refresh_interval" value="<?php echo esc_attr($interval); ?>">
    <?php foreach ($options as $value => $label): ?>
    <option value="<?php echo esc_attr($value); ?>" <?php selected($value, $interval, true); ?>>
        <?php echo esc_html($label); ?>
    </option>
    <?php endforeach ?>
</select>
<p class="description">
    <?php echo esc_html__("Enabling this option improves data privacy at the expense of unique visitor count accuracy.", 'independent-analytics') . ' <a href="https://independentwp.com/knowledgebase/data/refresh-visitor-salt/" target="_blank">'. esc_html__('Learn more.', 'independent-analytics') .'</a>'; ?>
</p>
