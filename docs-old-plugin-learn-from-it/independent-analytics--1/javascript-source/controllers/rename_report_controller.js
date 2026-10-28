import {Controller} from "@hotwired/stimulus"

export default class extends Controller {
    static targets = ['modal', 'modalButton', 'renameButton', 'input']

    static values = {
        id: String,
        name: String
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
        this.inputTarget.value = this.nameValue
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

    rename(e) {
        e.preventDefault()

        const name = this.inputTarget.value.trim()
        const data = {
            ...iawpActions.rename_report,
            id: this.idValue,
            name
        };

        if(name.length === 0) {
           this.inputTarget.value = '';
            return;
        }

        this.renameButtonTarget.setAttribute('disabled', 'disabled')
        this.renameButtonTarget.classList.add('sending')
        jQuery.post(ajaxurl, data, (response) => {
            this.renameButtonTarget.classList.remove('sending')
            this.renameButtonTarget.classList.add('sent')
            this.renameButtonTarget.classList.remove('sent')
            this.renameButtonTarget.removeAttribute('disabled');
            this.closeModal()
            this.nameValue = response.data.name
            Array.from(document.querySelectorAll('[data-name-for-report-id="' + this.idValue + '"]')).forEach((element) => {
                element.innerText = response.data.name
            })
        }).fail(() => {

        })
    }
}