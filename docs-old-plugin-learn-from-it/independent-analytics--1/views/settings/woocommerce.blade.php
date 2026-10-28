@php /** @var $statuses \IAWP\Ecommerce\WooCommerce_Status_Manager */ @endphp

<div data-controller="woocommerce-settings" class="settings-container woocommerce-order-statuses">
    <div class="heading">
        <h2><?php
            esc_html_e('WooCommerce Order Statuses', 'independent-analytics'); ?></h2>
        <a class="tutorial-link" href="https://independentwp.com/knowledgebase/woocommerce/woocommerce-order-statuses/" target="_blank">
            <?php
            esc_html_e('Read Tutorial', 'independent-analytics'); ?>
        </a>
    </div>
    <p><?php
        esc_html_e('Choose which order statuses should be counted in the analytics reports.', 'independent-analytics'); 
    ?></p>
    <div class="settings-checkbox-group">
        <ol><?php
            foreach ($statuses->get_statuses() as $status) : ?>
                <li>
                    <label>
                        <input type="checkbox"
                            name="<?php echo esc_attr($status['id']); ?>" <?php
                            echo $status['is_tracked'] === true ? 'checked' : ''; ?>
                            data-testid="wc-status-<?php echo esc_attr($status['id']); ?>"
                        >
                        <?php echo esc_html($status['name']); ?>
                    </label>
                </li><?php
            endforeach; ?>
        </ol>
    </div>
    <div class="button-group">
        <button class="button iawp-button purple"
                data-woocommerce-settings-target="saveButton"
                data-action="woocommerce-settings#saveClick"
                data-testid="save-woocommerce-settings"
        >
            <?php
            esc_html_e('Save', 'independent-analytics'); ?>
        </button>
        <button class="button iawp-button"
                data-woocommerce-settings-target="resetButton"
                data-action="woocommerce-settings#resetClick"
                data-testid="reset-woocommerce-settings"
        >
            <?php
            esc_html_e('Reset to default statuses', 'independent-analytics'); ?>
        </button>
        <div class="button-group-message">
            <p data-woocommerce-settings-target="message"></p>
        </div>
    </div>
</div>
