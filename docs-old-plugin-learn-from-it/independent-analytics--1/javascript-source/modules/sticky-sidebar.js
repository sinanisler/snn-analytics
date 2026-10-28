const $ = jQuery;

const StickySidebar = {
    setup() {
        if ($('#iawp-layout-sidebar').length == 0) {
            return;
        }
        
        let scrollPosition = window.scrollY;
        const sidebar = document.getElementById('iawp-layout-sidebar');
        const sidebarContainer = document.querySelector('.iawp-layout-sidebar');
        const layoutContainer = document.getElementById('iawp-layout');
        var self = this;

        if(!sidebar && !layoutContainer) {
            return; // These elements aren't visible on an interrupt page such as migration pending page
        }

        sidebar.scroll(0, window.scrollY);
        this.setMinMainHeight();

        document.addEventListener('scroll', () => {
            const change = scrollPosition - window.scrollY;

            if (window.scrollY < 1 || window.scrollY > ($(document).height() - $(window).height() - 1)) {
                scrollPosition = window.scrollY;
                return;
            }
            sidebar.scroll(0, sidebar.scrollTop - change);
            scrollPosition = window.scrollY;
        });

        window.addEventListener('resize', () => {
            this.setMinMainHeight();
        });

        document.getElementById('collapse-sidebar').addEventListener('click', () => {
            const isSidebarCollapsed = layoutContainer.classList.toggle('collapsed');
            this.saveSidebarState(isSidebarCollapsed)
            sidebar.scroll(0, window.scrollY);
            this.setMinMainHeight();
            this.setTableHorizontal();
        });

        $('#mobile-menu-toggle').on('click', function() {
            if ($('#menu-container').hasClass('open')) {
                $('#menu-container').removeClass('open');
                $(this).find('.text').text(iawpText.openMobileMenu);
            } else {
                $('#menu-container').addClass('open');
                $(this).find('.text').text(iawpText.closeMobileMenu);
            }
        });

        var dataTableContainer = $('#data-table-container');
        var dataTable = $('#data-table');
        
        // Data table resizing
        if (dataTable.width() > dataTableContainer.width()) {
            self.setTableHorizontal();
        }
        $(window).on('resize', function() {
            self.setTableHorizontal();
            self.setReportTitleMaxWidth();
        });

        this.setReportTitleMaxWidth();
    },
    saveSidebarState(isSidebarCollapsed) {
        const data = {
            ...iawpActions.update_user_settings,
            'is_sidebar_collapsed': isSidebarCollapsed
        };

        jQuery.post(ajaxurl, data, (response) => {

        }).fail(() => {

        });
    },
    setMinMainHeight() {
        $('.iawp-layout-main').css('min-height', $('.iawp-layout-sidebar .inner').outerHeight(true) + 32);
    },
    setTableHorizontal() {
        if ($('#data-table').width() > $('#data-table-container').width()) {
            $('#data-table-container').addClass('horizontal');
        } else {
            $('#data-table-container').removeClass('horizontal');
        }
    },
    setReportTitleMaxWidth() {
        if ($(window).width() < 600) {
            $('.rename-report').css('max-width', '');
        } else {
            $('.rename-report').css('max-width', 'calc(100% - ' + $('.report-title-bar .buttons').width() + 'px)');
        }
    }
}

export { StickySidebar };