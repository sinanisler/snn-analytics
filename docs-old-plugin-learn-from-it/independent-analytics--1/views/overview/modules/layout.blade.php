@php /** @var \IAWP\Env $env */ @endphp
@php /** @var \IAWP\Overview\Modules\Module $module */ @endphp
@php /** @var bool $is_loaded */ @endphp
@php /** @var bool $is_empty */ @endphp
@php /** @var ?array $dataset */ @endphp

<div class="iawp-module <?php echo $module->is_full_width() ? 'full-width' : ''; ?>"
     data-controller="module"
     data-module-module-id-value="<?php echo esc_attr($module->id()); ?>"
     data-module-has-dataset-value="<?php echo $module->has_dataset() ? 'true' : 'false'; ?>"
>
    <header class="module-header">
        <div class="module-icon"><?php
            echo iawp_blade()->run('icons.overview.' . $module->module_type()); ?>
        </div>
        <div class="module-title-container">
            <h2><?php echo sanitize_text_field($module->name()); ?></h2>
            <p><?php echo sanitize_text_field($module->subtitle()); ?></p>
        </div>
        <div class="module-action-links">
            <button data-action="module#edit" class="edit-module-button"><span class="dashicons dashicons-admin-generic"></span></button>
            <button data-action="module#toggleWidth" class="toggle-width-button"><span class="dashicons dashicons-columns"></span></button>
            <button data-action="module#delete" class="delete-module-button"><span class="dashicons dashicons-trash"></span></button>
        </div>
    </header>
    <div class="module-contents">
        <div class="<?php echo esc_attr($module->module_type()); ?> <?php echo $is_loaded ? "is-loaded" : "is-loading"; ?> <?php echo $is_empty ? "is-empty" : ""; ?>"><?php
            echo iawp_blade()->run('overview.modules.' . $module->module_type(), [
                'module' => $module,
                'dataset' => $dataset,    
                'is_empty' => $is_empty,
                'is_loaded' => $is_loaded,
            ]); ?>
        </div>
    </div>
</div>
