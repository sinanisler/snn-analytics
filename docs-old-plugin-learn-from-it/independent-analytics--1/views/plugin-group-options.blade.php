@php /** @var \IAWP\Plugin_Group[] $plugin_groups */ @endphp
@php /** @var \IAWP\Plugin_Group_Option[] $options */ @endphp
@php /** @var string $option_type */ @endphp
@php /** @var string $option_name */ @endphp
@php /** @var string $option_icon */ @endphp

{{-- Array indicies are used to add section headers. Make sure there are no gaps. --}}
<?php $options = array_values($options); ?>

<?php
$plugin_groups = array_filter($plugin_groups, function ($plugin_group) use ($options) {
    foreach ($options as $option) {
        if($option->is_member_of_plugin_group($plugin_group->id())) {
            return true;
        }
    }

    return false;
});
?>

<?php
    // Only one group with one option? Don't render the button or modal.
    if(count($plugin_groups) === 1) {
        $plugin_group_options = array_filter($options, function ($option) use ($plugin_groups) {
           return $option->is_member_of_plugin_group($plugin_groups[0]->id());
        });

        if(count($plugin_group_options) === 1) {
            return;
        }
    }
?>

<div data-controller="plugin-group-options"
     data-plugin-group-options-option-type-value="<?php echo esc_attr($option_type); ?>"
     class="button-modal-container"
>
    {{-- Toggle button --}}
    <button class="stats-toggle-button iawp-button"
            data-plugin-group-options-target="modalButton"
            data-action="plugin-group-options#toggleModal"
    >
        <span class="dashicons dashicons-<?php echo esc_attr($option_icon); ?>"></span>
        <?php echo esc_html($option_name); ?>
    </button>

    {{-- Toggle modal --}}
    <div data-plugin-group-options-target="modal"
         class="stats-toggle"
    >
        <div class="top title-small">
            <?php esc_html_e('Choose Stats to Display', 'independent-analytics'); ?>
            <span data-plugin-group-options-target="spinner" class="dashicons dashicons-update iawp-spin hidden"></span>
        </div>
        <div class="inner">
            <div id="stats-toggle-sidebar" class="sidebar">
                <ul>
                    <?php foreach($plugin_groups as $plugin_group) : ?>
                        <li>
                            <a class="link-dark <?php echo $plugin_group->id() === 'general' ? 'current' : ''; ?>"
                               data-option-id="<?php echo esc_attr($plugin_group->id()); ?>"
                               data-action="plugin-group-options#requestGroupChange"
                               data-plugin-group-options-target="tab"
                               href="#"
                            >
                                <span><?php echo esc_html($plugin_group->name()); ?></span>
                                <?php if($plugin_group->requires_pro() && iawp_is_free()) : ?>
                                    <span class="pro-label"><?php esc_html_e('PRO', 'independent-analytics'); ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="main">
                <?php foreach($plugin_groups as $plugin_group) : ?>
                    <div class="checkbox-container <?php echo $plugin_group->id() === 'general' ? 'current' : ''; ?>"
                         data-option-id="<?php echo esc_attr($plugin_group->id()); ?>"
                         data-plugin-group-options-target="checkboxContainer"
                            {{--                             data-metric-category="{{ $plugin_group->id() }}"--}}
                    >
                        <span class="metrics-title"><?php echo esc_attr($plugin_group->name()); ?></span>

                        <?php foreach($options as $index => $option) :
                            if(!$option->is_subgroup_plugin_enabled() || !$option->is_member_of_plugin_group($plugin_group->id())) {
                                continue;
                            }

                            if(is_string($option->plugin_group_header()) && array_key_exists($index - 1, $options) && ($options[$index -1])->plugin_group_header() !== $option->plugin_group_header()) : ?>
                                <span class="metrics-subtitle"><?php echo esc_html($option->plugin_group_header()); ?></span>
                            <?php endif; ?>

                            <label class="<?php echo !$option->is_group_plugin_enabled() ? 'disabled' : ''; ?>">
                                <?php if (!$option->is_group_plugin_enabled()) : ?>
                                    <input id="iawp_<?php echo esc_attr($option_type) . '_' . esc_attr($option->id()); ?>"
                                           type="checkbox" 
                                           disabled="disabled">
                                <?php else : ?>
                                    <input id="iawp_<?php echo esc_attr($option_type) . '_' . esc_attr($option->id()); ?>"
                                           type="checkbox"
                                           data-action="plugin-group-options#toggleOption"
                                           data-plugin-group-options-target="checkbox"
                                           name="<?php echo esc_attr($option->id()); ?>"
                                           <?php checked(true, $option->is_visible(), true); ?>
                                    />
                                <?php endif; ?>
                                <span><?php echo esc_html($option->name()); ?></span>
                            </label>
                        <?php endforeach;

                        if ($plugin_group->requires_pro() && iawp_is_free()) : ?>
                            <div class="required-plugin-note">
                                <p><?php echo esc_html($plugin_group->upgrade_message()); ?></p>
                                <p><a href="<?php echo esc_attr($plugin_group->upgrade_link()); ?>"
                                      class="link-purple"
                                      target="_blank"><?php esc_html_e('Learn more', 'independent-analytics'); ?></a>
                                </p>
                            </div>
                        <?php elseif(!$plugin_group->has_active_group_plugins() && iawp_is_pro()) : ?>
                            <div class="required-plugin-note">
                                <p><?php echo esc_html($plugin_group->activate_message()); ?></p>
                                <p><a href="<?php echo esc_attr($plugin_group->activate_link()); ?>"
                                      class="link-purple"
                                      target="_blank"><?php esc_html_e('Learn more', 'independent-analytics'); ?></a>
                                </p>
                            </div>
                        <?php elseif(!$plugin_group->has_tracked_data()) : ?>
                            <div class="required-plugin-note">
                                <p><?php echo esc_html($plugin_group->no_tracked_data_message()); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>