<select class="filters-column" data-filters-target="column" data-action="filters#columnSelect">

    <option value="">
        <?php esc_html_e('Choose a column', 'independent-analytics'); ?>
    </option>
    <?php
    foreach ($column_sections as $section_name => $column_section) : ?>
        <optgroup label="<?php echo esc_attr($section_name . (($column_section['plugin_group'])->requires_pro() && iawp_is_free() ? ' (PRO)' : '')); ?>"><?php
            foreach ($column_section['columns'] as $column) :
                if (!$column->is_group_plugin_enabled()) : ?>
                    <option disabled><?php echo esc_html($column->name()); ?></option><?php
                    continue;
                endif; ?>

                <option value="<?php echo esc_attr($column->id()); ?>"
                        data-type="<?php echo esc_attr($column->type()); ?>">
                    <?php echo esc_html($column->name()); ?>
                </option><?php
            endforeach; ?>
        </optgroup><?php
    endforeach; ?>
</select>