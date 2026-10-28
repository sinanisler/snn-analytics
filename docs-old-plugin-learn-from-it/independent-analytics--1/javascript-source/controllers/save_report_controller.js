import {Controller} from "@hotwired/stimulus"

export default class extends Controller {
    static targets = ['button', 'warning']

    static values = {
        'id': String
    }

    changes = {}
    saving = false

    connect() {
        document.addEventListener('iawp:changedOption', this.handleChangedOption)
    }

    handleChangedOption = ({ detail }) => {
        this.changes = {
            ...this.changes,
            ...detail
        }
        this.showWarning()
    }

    save() {
        if(this.saving) {
            return;
        }

        this.saving = true

        const data = {
            ...iawpActions.save_report,
            id: this.idValue,
            changes: JSON.stringify(this.changes)
        };

        this.buttonTarget.classList.add('sending')
        jQuery.post(ajaxurl, data, (response) => {
            this.saving = false
            this.hideWarning()
            this.changes = {}
            this.buttonTarget.classList.remove('sending')
            this.buttonTarget.classList.add('sent')

            setTimeout(() => {
                this.buttonTarget.classList.remove('sent')
            }, 1000)
        }).fail(() => {
            this.saving = false
        })
    }

    showWarning() {
        this.warningTarget.style.display = 'block'
    }

    hideWarning() {
        this.warningTarget.style.display = 'none'
    }
}
