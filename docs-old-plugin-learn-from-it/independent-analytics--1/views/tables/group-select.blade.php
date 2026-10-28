@php /** @var \IAWP\Tables\Groups\Groups $groups */ @endphp
@php /** @var \IAWP\Tables\Groups\Group $current_group */ @endphp

<?php
if ($groups->has_grouping_options()) : ?>
    <div class="group-select-container">
        <select id="group-select"
                class="group-select"
                data-controller="group"
                data-action="group#changeGroup"
        >
            <optgroup label="<?php echo __('Group rows by', 'independent-analytics'); ?>">
                <?php foreach ($groups->groups() as $group) : ?>
                    <option id="<?php echo esc_attr($group->id()); ?>"
                            value="<?php echo esc_attr($group->id()); ?>"
                            data-testid="group-by-<?php echo esc_attr($group->id()); ?>"
                            <?php selected($group->id(), $current_group->id(), true); ?>
                    >
                        <?php echo esc_html($group->singular()); ?>
                    </option>
                <?php endforeach; ?>
            </optgroup>
        </select>
        <label><span class="dashicons dashicons-open-folder"></span></label>
    </div>
<?php endif; ?>
