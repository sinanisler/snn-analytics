@php /** @var \IAWP\Overview\Form_Field $form_field */ @endphp
@php /** @var string[] $selected_value */ @endphp

<?php
    $selected_value = $selected_value ?? null;
    $template_values = $form_field->template_values();
    $has_groups = !array_key_exists(0, $template_values);
?>

<span><?php echo sanitize_text_field($form_field->name()); ?></span>
<?php
if ($has_groups) : ?>
    <div id="<?php echo esc_attr($form_field->id()); ?>">
        <div class="checkbox-group-container" data-controller="checkbox-group">
            <div class="tab-container"><?php
                $first_loop = true;
                foreach ($template_values as $group_name => $values) : ?>
                    <button type="button"
                            class="checkbox-group-tab <?php echo $first_loop ? "selected" : ""; ?>"
                            data-group-name="<?php echo esc_attr($group_name); ?>"
                            data-checkbox-group-target="groupTab"
                            data-action="checkbox-group#changeTab"
                    ><?php echo sanitize_text_field($group_name); ?></button> <?php
                    $first_loop = false;
                endforeach; ?>
            </div><?php
            $first_group = true;
            $first_loop = true; 
            foreach ($template_values as $group_name => $values) : ?>
                <div data-checkbox-group-target="group"
                     data-group-name="<?php echo esc_attr($group_name); ?>"
                     class="checkbox-group <?php echo $first_group ? "selected" : ""; ?>"
                ><?php
                    foreach ($values as $value) : ?>
                        <label>
                            <input type="checkbox"
                                   name="<?php echo esc_attr($form_field->id()); ?>"
                                   value="<?php echo esc_attr($value->id()); ?>"<?php 
                                    if (is_array($selected_value)) {
                                        echo in_array($value->id(), $selected_value) ? 'checked' : '';
                                    } else {
                                        echo $first_group && $first_loop ? 'checked' : '';
                                    } ?>
                            />
                            <?php echo sanitize_text_field($value->name()); ?>
                        </label><?php
                        $first_loop = false;
                    endforeach; ?>
                </div><?php
                $first_group = false;
            endforeach; ?>
        </div>
    </div><?php
else : ?>
    <div id="<?php echo esc_attr($form_field->id()); ?>">
        <div class="checkbox-group selected" data-controller="checkbox-group"><?php
            $first_loop = true;
            foreach ($template_values as $value) : ?>
                <label>
                    <input type="checkbox"
                           name="<?php echo esc_attr($form_field->id()); ?>"
                           value="<?php echo esc_attr($value->id()); ?>"<?php
                        if (is_array($selected_value)) {
                            echo in_array($value->id(), $selected_value) ? 'checked' : '';
                        } else {
                            echo $first_loop ? 'checked' : '';
                        } ?>
                    />
                    <?php echo sanitize_text_field($value->name()); ?>
                </label><?php 
                $first_loop = false;
            endforeach; ?>
        </div>
    </div><?php
endif;
