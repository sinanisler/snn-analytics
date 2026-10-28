import MicroModal from "micromodal"
import Sortable from 'sortablejs'

document.addEventListener("DOMContentLoaded", () => MicroModal.init())
const $ = jQuery;

const ClickTrackingMenu = {
    errorMessages: null,
    deleteButton: null,
    setup: function () {
        this.errorMessages = $('#validation-error-messages');
        this.deleteButton = $('#delete-link-modal .yes');
        var self = this;
        $('#click-tracking-menu').on('change', '.link-type', function () {
            self.changeVisibleInput($(this).parents('.trackable-link'), $(this).val());
        });
        $('#click-tracking-menu').on('click', '.edit-button', function () {
            self.enableEditing($(this).parents('.trackable-link'));
        });
        $('#click-tracking-menu').on('click', '.cancel-button', function () {
            self.cancelEditing($(this).parents('.trackable-link'));
        });
        $('#click-tracking-menu').on('click', '.save-button', function () {
            self.editLinkRequest($(this).parents('.trackable-link'));
        });
        $('#click-tracking-menu').on('click', '.archive-button', function () {
            self.archiveLinkRequest($(this).parents('.trackable-link'));
        });
        $('#toggle-archived-links').on('click', function () {
            self.toggleArchivedLinks($(this));
        });
        $('#click-tracking-menu').on('click', '.delete-link-button', function () {
            self.deleteButton.data('link-id', $(this).parents('.trackable-link').data('id'));
            MicroModal.show("delete-link-modal");
        });
        this.deleteButton.on('click', function () {
            self.deleteLinkRequest($(this).data('link-id'));
            $(this).data('link-id', '');
        });
        $('#create-new-link').on('click', function () {
            self.addNewLinkToTable();
        });
        $('#click-tracking-menu').on('keydown', '.link-name, .link-value', function (event) {
            self.triggerSaveButton(event, $(this).parents('.trackable-link'), $(this));
        });
        const trackedLinksSortable = new Sortable(document.getElementById('sortable-tracked-links-list'), {
            animation: 150,
            ghostClass: 'iawp-sortable-ghost',
            dragClass: 'is-dragging',
            delay: 2000,
            delayOnTouchOnly: true,
            onUpdate: (event) => this.sortLinks(trackedLinksSortable, event),
        });
        const archivedLinksSortable = new Sortable(document.getElementById('archived-links-list'), {
            animation: 150,
            ghostClass: 'iawp-sortable-ghost',
            dragClass: 'is-dragging',
            delay: 2000,
            delayOnTouchOnly: true,
            onUpdate: (event) => this.sortLinks(archivedLinksSortable, event),
        });
        document.getElementById('click-tracking-cache-cleared').addEventListener('click', function () {
            const data = {
                ...iawpActions.click_tracking_cache_cleared,
            };
            document.getElementById('click-tracking-cache-message-container').classList.remove('show')
            jQuery.post(ajaxurl, data, (response) => {
                
            }).fail(() => {
                document.getElementById('click-tracking-cache-message-container').classList.add('show')
            })
        })
    },
    editLinkRequest: function (link) {
        var self = this;
        const id = link.data('id');
        const name = link.find('.link-name').val();
        const type = link.find('.link-type').val();
        const value = link.find('.value-container.visible .link-value').val();

        const data = {
            ...iawpActions.edit_link,
            id,
            name,
            type,
            value
        };

        link.find('.save-button').addClass('saving');
        link.find('.save-button').prop('disabled', true);
        link.find('.cancel-button').prop('disabled', true);

        jQuery.post(ajaxurl, data, function (response) {
            link.find('.save-button').removeClass('saving');

            if (response.data.error && response.data.error.length > 0) {
                self.hideErrorMessages(link);
                link.after(self.errorMessages);
                self.errorMessages.find('.' + response.data.error).addClass('visible');
                link.find('.link-' + response.data.property).addClass('error');
                link.find('.save-button').prop('disabled', false);
                link.find('.cancel-button').prop('disabled', false);
            } else {
                if(response.data.shouldShowCacheMessage) {
                    document.getElementById('click-tracking-cache-message-container').classList.add('show')
                }
                if(link.parent().is('#sortable-tracked-links-list')) {
                    link.replaceWith(response.data.html)
                } else {
                    link.remove()
                    $('#sortable-tracked-links-list').prepend(response.data.html);
                }
                self.hideErrorMessages(link);
            }
        });
    },
    archiveLinkRequest: function (link) {
        const id = link.data('id');
        const data = {
            ...iawpActions.archive_link,
            id
        };
        const isActive = link.closest('.tracked-links-list').length > 0;

        link.addClass('archiving');

        jQuery.post(ajaxurl, data, (response) => {
            response = JSON.parse(response);
            if (response) {
                document.getElementById('click-tracking-cache-message-container').classList.add('show')
                link.remove();
                if (isActive) {
                    $('#archived-links-list').prepend(response);
                } else {
                    $('#sortable-tracked-links-list').prepend(response);
                }
            }

            this.toggleEmptyMessage()
        });
    },
    deleteLinkRequest: function (id) {
        var self = this;
        const data = {
            ...iawpActions.delete_link,
            id
        };

        this.deleteButton.addClass('sending');

        jQuery.post(ajaxurl, data, (response) => {
            self.deleteButton.removeClass('sending');
            $('.trackable-link[data-id="' + response.data.id + '"]').remove();
            MicroModal.close("delete-link-modal");
            this.toggleEmptyMessage()
        }).fail(() => {
            // Do nothing
        })
    },
    changeVisibleInput: function (link, type) {
        link.find('.value-container.visible').removeClass('visible');
        link.find('.value-container.' + type).addClass('visible');
    },
    enableEditing: function (link) {
        link.addClass('is-editing');
        this.focusOnInput(link.find('.link-name'));
    },
    cancelEditing: function (link) {
        if (link.hasClass('blueprint-clone')) {
            link.remove();
        } else {
            link.removeClass('is-editing');
        }
        this.hideErrorMessages(link);
        this.errorMessages.remove()
        this.toggleEmptyMessage()
    },
    toggleArchivedLinks: function (button) {
        $('#archived-links').toggleClass('open');
        const buttonText = button.text()
        button.text(button.data('alt-text'))
        button.data('alt-text', buttonText)
    },
    addNewLinkToTable: function () {
        if ($('#tracked-links-list').find('.blueprint-clone').length !== 0) {
            return;
        }
        var clone = $('#blueprint-link .trackable-link').clone();
        clone.addClass('is-editing blueprint-clone');
        $('#tracked-links-list').prepend(clone);
        clone.find('.link-name').focus();
        this.toggleEmptyMessage()
    },
    triggerSaveButton: function (event, link, input) {
        if (!link.hasClass('is-editing') || input.get(0).tagName == 'SELECT')
            return;
        if (event.key == 'Enter')
            link.find('.save-button').click();
    },
    focusOnInput: function (input) {
        var val = input.focus().val();
        input.val('').val(val);
    },
    hideErrorMessages: function (link) {
        link.find('input, select').removeClass('error');
        this.errorMessages.find('p').removeClass('visible');
    },
    toggleEmptyMessage: function () {
        if ($('#tracked-links-list .trackable-link').length === 0) {
            $('.tracked-links-empty-message').addClass('show');
        } else {
            $('.tracked-links-empty-message').removeClass('show');
        }

        if ($('#archived-links-list .trackable-link').length === 0) {
            $('.archived-links-empty-message').addClass('show');
        } else {
            $('.archived-links-empty-message').removeClass('show');
        }
    },
    sortLinks(sortable, event) {
        const elements = Array.from(event.target.querySelectorAll('.trackable-link'));
        const ids = elements.map((element) => parseInt(element.dataset.id))

        const data = {
            ...iawpActions.sort_links,
            ids
        };

        jQuery.post(ajaxurl, data, (response) => {
            // Do nothing
        }).fail(() => {
            sortable.sort(
                this.moveArrayItem(sortable.toArray(), event.newIndex, event.oldIndex)
            )
        })
    },
    moveArrayItem(array, fromIndex, toIndex) {
        const newArray = [...array]

        if (fromIndex === toIndex) {
            return newArray
        }

        const itemToMove = newArray.splice(fromIndex, 1)[0]
        newArray.splice(toIndex, 0, itemToMove)

        return newArray
    }
}

jQuery(function ($) {
    ClickTrackingMenu.setup();
});

