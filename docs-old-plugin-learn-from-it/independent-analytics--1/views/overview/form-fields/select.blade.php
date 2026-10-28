@php /** @var \IAWP\Overview\Form_Field $form_field */ @endphp
@php /** @var string $selected_value */ @endphp

<?php
$selected_value = $selected_value ?? null;
$template_values = $form_field->template_values();
$has_groups = !array_key_exists(0, $template_values);

if ($has_groups) : ?>
    <div>
        <label><?php echo sanitize_text_field($form_field->name()); ?></label>
        <select name="<?php echo esc_attr($form_field->id()); ?>" id="<?php echo esc_attr($form_field->id()); ?>"><?php
            $first_group = true;
            $first_loop = true;
            foreach ($template_values as $group_name => $values) : ?>
                <optgroup label="<?php echo esc_attr($group_name); ?>"><?php
                    foreach ($values as $value) : ?>
                        <option value="<?php echo esc_attr($value->id()); ?>"<?php
                            if ($selected_value !== null) {
                                echo $value->id() == $selected_value ? 'selected' : '';
                            } else {
                                echo $first_group && $first_loop ? 'selected' : '';
                            } ?>
                        ><?php echo sanitize_text_field($value->name()); ?></option><?php
                        $first_loop = false;
                    endforeach; ?>
                </optgroup><?php
                $first_group = false;
            endforeach; ?>
        </select>
    </div><?php
else : ?>
    <div>
        <label><?php echo sanitize_text_field($form_field->name()); ?></label>
        <select name="<?php echo esc_attr($form_field->id()); ?>" id="<?php echo esc_attr($form_field->id()); ?>"><?php
            $first_loop = true;
            foreach ($template_values as $value) : ?>
                <option value="<?php echo esc_attr($value->id()); ?>"<?php 
                if ($selected_value !== null) {
                    echo $value->id() == $selected_value ? 'selected' : '';
                } else {
                    echo $first_loop ? 'selected' : '';
                } ?>
                ><?php echo sanitize_text_field($value->name()); ?></option><?php
                $first_loop = false;
            endforeach; ?>
        </select>
    </div><?php
endif;