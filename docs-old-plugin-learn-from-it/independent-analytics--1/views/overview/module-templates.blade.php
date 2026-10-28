@php /** @var \IAWP\Env $env */ @endphp
@php /** @var \IAWP\Overview\Overview $overview */ @endphp
@php /** @var \IAWP\Overview\Modules\Module[] $saved_modules */ @endphp
@php /** @var \IAWP\Overview\Modules\Module[] $template_modules */ @endphp

{{-- Template to reshow the module picker --}}
<template id="module-picker-template"><?php
    echo iawp_blade()->run('overview.module-picker', [ 
        'show_list' => true,
        'template_modules' => $template_modules, 
    ]); ?>
</template>

{{-- Template for every supported module type --}}
<?php
foreach($template_modules as $module) : ?>
    <template id="<?php echo esc_attr($module->module_type()) . '-module-template'; ?>"><?php 
        echo iawp_blade()->run('overview.module-editor', [
            'module' => $module
        ]); ?>
    </template><?php
endforeach;