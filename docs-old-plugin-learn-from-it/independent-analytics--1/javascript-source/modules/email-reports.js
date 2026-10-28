const $ = jQuery;

const EmailReports = {
    setup: function() {
        var self = this;
        this.disableTestButtonIfEmpty();
        $('.email-reports .new-address input').on('change', function() {
            $('#test-email').attr('disabled', true);
        });
        $('.email-reports .saved .remove').on('click', function() {
            self.disableTestButtonIfEmpty();
        });

        // Show the correct interval note
        $('#' + $('#iawp_email_report_interval').val() + '-interval-note').show();
        // Change which note is visible based on selected interval
        $('#iawp_email_report_interval').on('change', function() {
            $('.interval-note').hide();
            $('#' + $(this).val() + '-interval-note').show();
        })

        var savedColors = $('#iawp_email_report_colors');
        const colorPickers = $('.iawp-color-picker');
        var options = {
            change: function(event, ui) {
                var colors = [];
                colorPickers.each(function() {
                    colors.push($(this).iris('color'));
                });
                savedColors.val(colors.join(','));
            }
        };
        colorPickers.each(function(){
            $(this).wpColorPicker(options);
        });
        $('#test-email').on('click', function(e){
            e.preventDefault();
            self.sendTestEmail();
        });
        $('#preview-email').on('click', function(e){
            e.preventDefault();
            self.previewEmail(savedColors.val());
        });
        $('#close-email-preview').on('click', function(e) {
            e.preventDefault();
            $('#email-preview-container').removeClass('visible');
            $('#email-preview').html('');
        })
    },
    disableTestButtonIfEmpty: function() {
        if ( $('.email-reports .saved input').length == 0) {
            $('#test-email').attr('disabled', true);
        }
    },
    sendTestEmail: function() {
        const data = {
            ...iawpActions.test_email
        };

        $('#test-email').addClass('sending');

        jQuery.post(ajaxurl, data, function (response) {
            $('#test-email').removeClass('sending');
            if (response) {
                $('#test-email').addClass('sent');
            } else {
                $('#test-email').addClass('failed');
            }
            
            setTimeout(function() {
                $('#test-email').removeClass('sent failed');
            }, 1000)

        });
    },
    previewEmail: function(colors) {
        const data = {
            ...iawpActions.preview_email,
            colors
        };

        $('#preview-email').addClass('sending');

        jQuery.post(ajaxurl, data, function (response) {
            $('#preview-email').removeClass('sending');
            if (response.success) {
                $('#preview-email').addClass('sent');
                $('#email-preview').html(response.data.html);
                $('#email-preview-container').addClass('visible');
            } else {
                $('#preview-email').addClass('failed');
            }
            
            setTimeout(function() {
                $('#preview-email').removeClass('sent failed');
            }, 1000)

        });
    }
}

export { EmailReports };