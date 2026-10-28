const $ = jQuery;

const ScrollToTop = {
    setup: function() {
        $('#scroll-to-top').on('click', function() {
            window.scrollTo({
                top: 0,
                behavior: "smooth"
            });
            $(this).blur();
        });
    }
}

export { ScrollToTop };