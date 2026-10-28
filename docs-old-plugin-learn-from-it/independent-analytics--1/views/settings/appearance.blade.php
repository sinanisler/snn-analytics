<select name="iawp_appearance" id="iawp_appearance" value="<?php echo esc_attr($appearance); ?>">
    <?php foreach ($options as $value => $label): ?>
    <option value="<?php echo esc_attr($value); ?>" <?php selected($value, $appearance, true); ?>>
        <?php echo esc_html($label); ?>
    </option>
    <?php endforeach ?>
</select>
