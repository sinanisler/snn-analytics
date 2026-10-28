import {Controller} from "@hotwired/stimulus"

export default class extends Controller {
    static targets = ['modal', 'modalButton', 'copyButton', 'input']

    static values = {
        id: String,
        type: String
    }

    changes = {}

    connect() {
        document.addEventListener('iawp:changedOption', this.handleChangedOption)
        document.addEventListener('click', this.maybeClose)
    }

    disconnect() {
        document.removeEventListener('click', this.maybeClose)
    }

    handleChangedOption = ({ detail }) => {
        this.changes = {
            ...this.changes,
            ...detail
        }
    }

    maybeClose = (event) => {
        const isOpen = this.modalTarget.classList.contains('show')
        const isInComponent = this.element.contains(event.target)


        if (isOpen && !isInComponent) {
            this.closeModal()
        }
    }

    toggleModal(e) {
        e.preventDefault();
        const isOpen = this.modalTarget.classList.contains('show')
        isOpen ? this.closeModal() : this.openModal()
    }

    openModal() {
        this.inputTarget.value = ''
        this.modalTarget.classList.add('show')
        this.modalButtonTarget.classList.add('open')
        document.getElementById('iawp-layout').classList.add('modal-open');
        setTimeout(() => {
            this.inputTarget.focus()
            this.inputTarget.select()
        }, 200)
    }

    closeModal() {
        this.modalTarget.classList.remove('show')
        this.modalButtonTarget.classList.remove('open')
        document.getElementById('iawp-layout').classList.remove('modal-open');
    }

    copy(e) {
        e.preventDefault()

        const name = this.inputTarget.value.trim()
        const data = {
            ...iawpActions.copy_report,
            id: this.idValue,
            type: this.typeValue,
            name,
            changes: JSON.stringify(this.changes)
        };

        this.copyButtonTarget.setAttribute('disabled', 'disabled')
        this.copyButtonTarget.classList.add('sending')
        jQuery.post(ajaxurl, data, (response) => {
            this.copyButtonTarget.classList.remove('sending')
            this.copyButtonTarget.classList.add('sent')
            this.copyButtonTarget.classList.remove('sent')
            this.copyButtonTarget.removeAttribute('disabled');
            this.closeModal()
            window.location = response.data.url
        })
    }
}
