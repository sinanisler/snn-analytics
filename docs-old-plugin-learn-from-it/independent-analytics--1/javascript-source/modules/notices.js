const $ = jQuery;

const Notices = {
    setup() {
        $('.dismiss-notice').on('click', function() {
            const id = $(this).data('notice-id');
            const data = {
                ...iawpActions.dismiss_notice,
                id
            };
            if (id === 'iawp_show_gsg') {
                $('.iawp-getting-started-notice').hide();
            } else {
                $(this).parents('.iawp-notice').hide();
            }
            jQuery.post(ajaxurl, data, (response) => {

            }).fail(() => {

            });
        });
    }
}

export { Notices };