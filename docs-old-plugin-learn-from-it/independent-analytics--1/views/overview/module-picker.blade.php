@php /** @var \IAWP\Env $env */ @endphp
@php /** @var \IAWP\Overview\Overview $overview */ @endphp
@php /** @var \IAWP\Overview\Modules\Module[] $saved_modules */ @endphp
@php /** @var \IAWP\Overview\Modules\Module[] $template_modules */ @endphp

<div data-controller="module-picker"
     data-action="add-module:addModule@window->module-picker#scrollToPicker"
     class="iawp-module module-picker show-intro"
>
    <div class="module-intro">
        <button class="add-module-button" data-action="module-picker#showList">
            <span class="button-inner iawp-button"><?php esc_html_e('Add Module', 'independent-analytics'); ?></span>
        </button>
    </div>

    <div class="module-picker-inner">
        <div class="module-picker-header">
            <span><?php esc_html_e('Choose a module', 'independent-analytics'); ?></span>
            <button class="iawp-button" data-action="module-picker#cancel"><?php esc_html_e('Cancel', 'independent-analytics'); ?></button>
        </div>

        <ul class="module-picker-list"><?php
            foreach($template_modules as $module) : ?>
                <li>
                    <button data-action="module-picker#showModule"
                            data-module-id="<?php echo esc_attr($module->module_type()); ?>">
                        <span class="module-icon"><?php echo iawp_blade()->run('icons.overview.' . $module->module_type()); ?></span>
                        <span class="module-name"><?php echo esc_html($module->module_name()); ?></span>
                        <span class="dashicons dashicons-arrow-right-alt2"></span>
                    </button>
                </li><?php
            endforeach; ?>
        </ul>
    </div>
</div>