import {Controller} from "@hotwired/stimulus"

export default class extends Controller {
    static targets = ['modal', 'modalButton', 'deleteButton']

    static values = {
        id: String
    }

    connect() {
        document.addEventListener('click', this.maybeClose)
    }

    disconnect() {
        document.removeEventListener('click', this.maybeClose)
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
        this.modalTarget.classList.add('show')
        this.modalButtonTarget.classList.add('open')
        document.getElementById('iawp-layout').classList.add('modal-open');
    }

    closeModal() {
        this.modalTarget.classList.remove('show')
        this.modalButtonTarget.classList.remove('open')
        document.getElementById('iawp-layout').classList.remove('modal-open');
    }

    delete() {
        const data = {
            ...iawpActions.delete_report,
            id: this.idValue
        };

        this.deleteButtonTarget.setAttribute('disabled', 'disabled')
        this.deleteButtonTarget.classList.add('sending')
        jQuery.post(ajaxurl, data, (response) => {
            this.deleteButtonTarget.classList.remove('sending')
            this.deleteButtonTarget.classList.add('sent')
            setTimeout(() => {
                window.location = response.data.url
            }, 1000)
        }).fail(() => {

        })
    }
}