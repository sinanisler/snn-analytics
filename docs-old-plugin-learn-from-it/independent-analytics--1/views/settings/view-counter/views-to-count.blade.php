<label class="column-label" for="iawp_view_counter_views_to_count">
    <select name="iawp_view_counter_views_to_count" id="iawp_view_counter_views_to_count">
        <option value="total" <?php selected($value, 'total', true); ?>><?php esc_html_e('All-time', 'independent-analytics'); ?></option>
        <option value="today" <?php selected($value, 'today', true); ?>><?php esc_html_e('Today', 'independent-analytics'); ?></option>
        <option value="yesterday" <?php selected($value, 'yesterday', true); ?>><?php esc_html_e('Yesterday', 'independent-analytics'); ?></option>
        <option value="this_week" <?php selected($value, 'this_week', true); ?>><?php esc_html_e('This Week', 'independent-analytics'); ?></option>
        <option value="last_week" <?php selected($value, 'last_week', true); ?>><?php esc_html_e('Last Week', 'independent-analytics'); ?></option>
        <option value="last_seven" <?php selected($value, 'last_seven', true); ?>><?php esc_html_e('Last 7 Days', 'independent-analytics'); ?></option>
        <option value="last_thirty" <?php selected($value, 'last_thirty', true); ?>><?php esc_html_e('Last 30 days', 'independent-analytics'); ?></option>
        <option value="last_sixty" <?php selected($value, 'last_sixty', true); ?>><?php esc_html_e('Last 60 days', 'independent-analytics'); ?></option>
        <option value="last_ninety" <?php selected($value, 'last_ninety', true); ?>><?php esc_html_e('Last 90 days', 'independent-analytics'); ?></option>
        <option value="this_month" <?php selected($value, 'this_month', true); ?>><?php esc_html_e('This month so far', 'independent-analytics'); ?></option>
        <option value="last_month" <?php selected($value, 'last_month', true); ?>><?php esc_html_e('Last month', 'independent-analytics'); ?></option>
        <option value="last_three_months" <?php selected($value, 'last_three_months', true); ?>><?php esc_html_e('Last 3 months', 'independent-analytics'); ?></option>
        <option value="last_six_months" <?php selected($value, 'last_six_months', true); ?>><?php esc_html_e('Last 6 months', 'independent-analytics'); ?></option>
        <option value="last_twelve_months" <?php selected($value, 'last_twelve_months', true); ?>><?php esc_html_e('Last 12 months', 'independent-analytics'); ?></option>
        <option value="this_year" <?php selected($value, 'this_year', true); ?>><?php esc_html_e('This year', 'independent-analytics'); ?></option>
        <option value="last_year" <?php selected($value, 'last_year', true); ?>><?php esc_html_e('Last year', 'independent-analytics'); ?></option>
    </select>
</label>