const $ = jQuery;

const Overview = {
    setup: function() {
        // Pagination
        $('body').on('click', '.pagination-button', function() {
            var current = $(this).parent().parent().find('.current');
            var pageCount = current.siblings('.module-pagination').find('.current-page');
            if ($(this).hasClass('right')) {
                if (current.next('.module-page').length == 0) {
                    return;
                }
                if (current.parents('.iawp-module').hasClass('full-width') 
                    && current.next().next('.module-page').length == 0
                    && window.innerWidth > 700) {
                    return;
                }
                current.removeClass('current');
                if (current.parents('.iawp-module').hasClass('full-width') && window.innerWidth > 700) {
                    current = current.next().next('.module-page').addClass('current');
                } else {
                    current = current.next('.module-page').addClass('current');
                }
                pageCount.text(parseInt(pageCount.text()) + 1);
                $(this).parent().find('.left').prop('disabled', false);
                if (current.next('.module-page').length == 0
                    || (current.parents('.iawp-module').hasClass('full-width') && current.next().next('.module-page').length == 0)
                ) {
                    $(this).prop('disabled', true);
                }
            } else {
                if (current.prev('.module-page').length !== 0) {
                    current.removeClass('current');
                    if (current.parents('.iawp-module').hasClass('full-width')
                        && window.innerWidth > 700) {
                        // Handle situation where they are on page 2 and the screen is < 700 then gets resized larger
                        if (current.hasClass('module-page-2') || current.hasClass('module-page-4')) {
                            current = current.prev('.module-page').addClass('current');
                        } else {
                            current = current.prev().prev('.module-page').addClass('current');
                        }
                    } else {
                        current = current.prev('.module-page').addClass('current');
                    }
                    pageCount.text(parseInt(pageCount.text()) - 1);
                    $(this).parent().find('.right').prop('disabled', false);
                    if (current.prev('.module-page').length == 0) {
                        $(this).prop('disabled', true);
                    }
                }
            }
        });
    }
}

export { Overview };