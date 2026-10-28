import {Controller} from "@hotwired/stimulus"

export default class extends Controller {
    static targets = ['modal', 'modalButton', 'checkbox', 'checkboxContainer', 'tab', 'spinner']
    static values = {
        optionType: String
    }

    connect() {
        document.addEventListener('click', this.maybeClose)
        document.addEventListener('iawp:fetchingReport', this.onFetchingReport)
    }

    disconnect() {
        document.removeEventListener('click', this.maybeClose)
        document.removeEventListener('iawp:fetchingReport', this.onFetchingReport)

        if(this.modalTarget.classList.contains('show')) {
            this.closeModal()
        }
    }

    onFetchingReport = () => {
        const enabledElements = this.element.querySelectorAll('input:not([disabled])')

        enabledElements.forEach(element => element.disabled = true)
        this.spinnerTarget.classList.remove('hidden')

        document.addEventListener('iawp:fetchedReport', () => {
            enabledElements.forEach(element => element.disabled = false)
            this.spinnerTarget.classList.add('hidden')
        }, {once: true})
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

    requestGroupChange(e) {
        e.preventDefault();
        this.changeGroup(e.currentTarget.dataset.optionId)
    }

    changeGroup(newOptionId) {
        this.tabTargets.forEach((element, index) => {
            element.classList.toggle('current', element.dataset.optionId === newOptionId)
        })
        this.checkboxContainerTargets.forEach((element, index) => {
            element.classList.toggle('current', element.dataset.optionId === newOptionId)
        })
    }

    toggleOption() {
        const checkedCheckboxes = this.checkboxTargets.filter((checkbox) => checkbox.checked)

        this.checkboxTargets.forEach((checkbox) => {
            checkbox.setAttribute('data-test-visibility', checkbox.checked ? 'visible' : 'hidden')
            checkbox.removeAttribute('disabled')
        })

        // If only one selected, disable it
        if (checkedCheckboxes.length === 1) {
            checkedCheckboxes.at(0).setAttribute('disabled', 'disabled')
        }

        document.dispatchEvent(
            new CustomEvent(this.getEventName(), {
                detail: {
                    optionIds: checkedCheckboxes.map(checkbox => checkbox.name)
                }
            })
        )
    }

    getEventName() {
        switch(this.optionTypeValue) {
            case 'columns':
                return 'iawp:changeColumns'
            case 'quick_stats':
                return 'iawp:changeQuickStats'
        }
    }
}
