<label class="column-label" for="iawp_disable_views_column">
    <input type="checkbox" name="iawp_disable_views_column"
           id="iawp_disable_views_column" <?php checked(true, $value, true); ?>>
    <span><?php esc_html_e('Disable the "Views" column in the post menus', 'independent-analytics'); ?></span>
    <p class="description"><?php esc_html_e("This hides the \"Views\" column that appears in the Posts menu and other CPT menus.", 'independent-analytics'); ?></p>
</label>
