<div class="trackable-link" data-id="<?php echo esc_attr($link['id']); ?>">
    <div class="input-container">
        <div class="inner-container name">    
            <input class="link-name" type="text" value="<?php echo esc_attr($link['name']); ?>" />
        </div>
        <div class="inner-container type">
            <select class="link-type" value="<?php echo esc_attr($link['type']); ?>"><?php
                foreach ($types as $type => $title) : ?>
                    <option value="<?php echo esc_attr($type); ?>" <?php selected($type, $link['type'], true ); ?>><?php echo esc_html($title); ?></option>    
                <?php endforeach; ?>
            </select>
        </div>
        <div class="inner-container value"><?php
            foreach ($types as $type => $title) : ?>
                <span class="value-container <?php echo esc_attr($type); ?> <?php echo $type == $link['type'] ? 'visible' : ''; ?>"><?php
                    if ($type == 'extension') : ?>
                        <select class="link-value"><?php
                            foreach ($extensions as $extension) : ?>
                                <?php $selected = $link['type'] !== 'extension' ? 'pdf' : $link['value']; ?>
                                <option value="<?php echo esc_attr($extension); ?>" <?php selected($extension, $selected, true); ?>><?php echo esc_html($extension); ?></option>
                            <?php endforeach; ?>
                        </select><?php
                    elseif ($type == 'protocol') : ?>
                        <select class="link-value"><?php 
                            foreach ($protocols as $protocol) : ?>
                                <option value="<?php echo esc_attr($protocol); ?>" <?php selected($protocol, $link['value'], true); ?>><?php echo esc_html($protocol); ?></option>
                            <?php endforeach; ?>
                        </select><?php 
                    elseif ($type === 'external') : ?>
                        <input class="link-value external" type="text" disabled/><?php
                    else :
                        if ($type == 'class') { ?>
                            <span class="value-prefix">.</span><?php
                        }
                        if ($type == 'id') { ?>
                            <span class="value-prefix">#</span><?php
                        }
                        if ($type == 'subdirectory') { ?>
                            <span class="value-prefix">/</span><?php
                        } ?>
                        <input class="link-value <?php echo esc_attr($type); ?>" type="text" value="<?php echo esc_attr($type == $link['type'] ? $link['value'] : ''); ?>" /><?php
                        if ($type == 'subdirectory') : ?>
                            <span class="value-suffix">/</span><?php
                        endif;
                    endif; ?>
                </span>
            <?php endforeach; ?>
        </div>  
    </div>
    <div class="value-text-container">
        <span class="name"><?php 
            if ($link['is_active']) : ?>
                <span class="dashicons dashicons-yes-alt"></span><?php
            else : ?>
                <span class="dashicons dashicons-dismiss"></span><?php 
            endif;
            echo esc_html($link['name']); ?>
        </span>
        <span class="type"><?php echo esc_html($types[$link['type']]); ?></span>
        <span class="value"><?php
            if ($link['type'] == 'class') :
                echo '.' . esc_html($link['value']); ?>
                <button class="copy-class" data-controller="clipboard" data-action="clipboard#copy"
                    data-clipboard-text-value="<?php echo esc_attr($link['value']); ?>">
                    <span class="dashicons dashicons-clipboard"></span>
                </button><?php
            elseif ($link['type'] == 'id') : 
                echo '#' . esc_html($link['value']); ?>
                <button class="copy-class" data-controller="clipboard" data-action="clipboard#copy"
                        data-clipboard-text-value="<?php echo esc_attr($link['value']); ?>">
                <span class="dashicons dashicons-clipboard"></span>
                </button><?php
            elseif ($link['type'] == 'extension') : 
                echo '.' . esc_html($link['value']);
            elseif ($link['type'] == 'domain') : 
                echo esc_html($link['value']);
            elseif ($link['type'] == 'subdirectory') :
                echo '/' . esc_html($link['value']) . '/';
            elseif ($link['type'] == 'protocol') : 
                echo esc_html($link['value']) . ':';
            else :
                echo esc_html($link['value']);
            endif; ?>
        </span>
    </div><?php
    if ($link['is_active'] !== false) : ?>
        <div class="action-buttons">
            <div class="edit-container">
                <button class="edit-button"><?php echo esc_html__('Edit', 'independent-analytics'); ?></button>
            </div>
            <div class="save-cancel-container">
                <button class="save-button"><?php echo esc_html__('Save', 'independent-analytics'); ?></button>
                <button class="cancel-button"><?php echo esc_html__('Cancel', 'independent-analytics'); ?></button>
            </div>
        </div>
    <?php endif; ?>
    <button class="archive-button"><?php
        if ($link['is_active']) : 
            echo esc_html__('Archive', 'independent-analytics' );
        else : 
            echo esc_html__('Resume Tracking', 'independent-analytics' );
        endif; ?>
    </button><?php 
    if ($link['is_active'] === false) : ?>
        <button class="delete-link-button"><?php echo esc_html__('Delete', 'independent-analytics' ); ?></button>
    <?php endif; ?>
</div>