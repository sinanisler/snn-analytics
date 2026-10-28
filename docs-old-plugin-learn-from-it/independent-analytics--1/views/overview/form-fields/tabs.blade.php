@php /** @var \IAWP\Overview\Form_Field $form_field */ @endphp
@php /** @var string[] $selected_value */ @endphp

<p><?php echo sanitize_text_field($form_field->name()); ?></p>

<div id="<?php echo esc_attr($form_field->id()); ?>"><?php
    $first_loop = true;
    foreach ($form_field->supported_values() as $value) : ?>
        <label>
            <input type="radio"
                   name="<?php echo esc_attr($form_field->id()); ?>"
                   value="<?php echo esc_attr($value->id()); ?>"<?php
                if ($selected_value !== null) {
                    echo $value->id() == $selected_value ? 'checked' : '';
                } else {
                    echo $first_loop ? 'checked' : '';
                } ?>
            />
            <span><?php echo sanitize_text_field($value->name()); ?></span>
        </label><?php
        $first_loop = false;
    endforeach; ?>
</div>
