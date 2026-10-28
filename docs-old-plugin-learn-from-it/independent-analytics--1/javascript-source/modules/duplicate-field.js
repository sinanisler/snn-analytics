const $ = jQuery;

const FieldDuplicator = {
    setup: function() {
        var self = this;
        var duplicators = $('.duplicator');
        duplicators.each(function(index, duplicator) {
            $(this).find('.duplicate-button').on('click', function(e){
                e.preventDefault();
                self.createNewEntry($(duplicator));
            });
        });
        var entries = $('.entry');
        entries.each(function() {
            self.attachRemoveEvent($(this));
        });
        // TODO: find a better place for this
        $('#block-current-ip').on('click', function() {
            $('.block-new-ip .new-field').val($('#current-ip').data('current-ip'));
            $('.block-new-ip .duplicate-button').click();
            $(this).prop('disabled', true);
        });
    },
    createNewEntry: function(duplicator) {
        var entryField = duplicator.find('.new-field');
        if(this.errorChecks(entryField)) {
            return;
        }
        var clone = duplicator.find('.blueprint .entry').clone();
        clone.find('input').val(entryField.val());
        duplicator.next().append(clone);

        if (entryField.hasClass('select')) {
            entryField.find('option[value="'+ entryField.val() +'"').remove();
        } else {
            entryField.val('');
        }

        this.resetIndex(duplicator.next('.saved'));
        this.attachRemoveEvent(clone);
        duplicator.parents('form').removeClass('empty exists');
        this.hideNoneMessage(duplicator);
    },
    attachRemoveEvent: function(entry) {
        var self = this;
        entry.find('.remove').on('click', function(e){
            e.preventDefault();
            var saved = $(entry).parent('.saved');
            $(this).parents('form').addClass('unsaved');
            $(this).parent().remove();
            self.resetIndex(saved);
        });
    },
    resetIndex: function(saved) {
        var count = 0;
        saved.find('input').each(function() {
            $(this).attr('name', $(this).attr('data-option') + '['+count+']');
            $(this).attr('id', $(this).attr('data-option') + '['+count+']');
            count++;
        });
        saved.parents('form').addClass('unsaved');
    },
    errorChecks(entryField) {
        if (entryField.val() == '') {
            entryField.parents('form').addClass('empty');
            return true;
        } 
        var existingValues = [];
        entryField.parent().parent().next('.saved').find('.entry').each(function() {
            existingValues.push($(this).find('input').val());
        });
        if (existingValues.includes(entryField.val())) {
            entryField.parents('form').addClass('exists');
            return true;
        }

        return false;
    },
    hideNoneMessage: function(duplicator) {
        duplicator.parent().find('.none').hide();
    }
}

export { FieldDuplicator };