<label class="column-label" for="iawp_view_counter_post_types[<?php echo esc_attr($counter); ?>]">
    <input type="checkbox" name="iawp_view_counter_post_types[<?php echo esc_attr($counter); ?>]" id="iawp_view_counter_post_types[<?php echo esc_attr($counter); ?>]" <?php checked(true, in_array($post_type->name, $saved), true); ?> value="<?php echo esc_attr($post_type->name); ?>">
    <span><?php echo esc_html($post_type->label); ?></span>
</label>