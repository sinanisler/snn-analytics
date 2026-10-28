import {Controller} from "@hotwired/stimulus"
import MicroModal from "micromodal"

export default class extends Controller {
    static targets = ['cutoffs', 'saveButton', 'confirmButton', 'statusMessage', 'confirmationText']

    isModalActuallyOpen = false

    saveClick() {
        this.actuallySave(false)
    }

    async confirmClick() {
        this.confirmButtonTarget.setAttribute('disabled', 'disabled')
        this.confirmButtonTarget.innerText = this.confirmButtonTarget.dataset.loadingText

        await this.actuallySave(true)

        this.confirmButtonTarget.removeAttribute('disabled')
        this.confirmButtonTarget.innerText = this.confirmButtonTarget.dataset.originalText

        this.hideConfirmationModal()
    }

    selectChanged() {
        this.saveButtonTarget.removeAttribute('disabled')
    }

    cancelConfirmation(e) {
        if(e && e.target !== e.currentTarget) {
            return
        }

        this.hideConfirmationModal()
        this.saveButtonTarget.removeAttribute('disabled')
        this.saveButtonTarget.innerText = this.saveButtonTarget.dataset.originalText
    }

    async actuallySave(isConfirmed = false) {
        this.saveButtonTarget.setAttribute('disabled', 'disabled')
        this.saveButtonTarget.innerText = this.saveButtonTarget.dataset.loadingText

        const response = await this.sendRequest({
            pruningCutoff: this.cutoffsTarget.value,
            isConfirmed: isConfirmed
        })

        if (!response.wasSuccessful && response.confirmationText) {
            this.showConfirmationModal(response.confirmationText)
        } else {
            this.statusMessageTarget.classList.toggle('is-scheduled', response.isEnabled)
            this.statusMessageTarget.querySelector('p').innerHTML = response.statusMessage

            this.saveButtonTarget.removeAttribute('disabled')
            this.saveButtonTarget.innerText = this.saveButtonTarget.dataset.originalText
            this.saveButtonTarget.setAttribute('disabled', 'disabled');

            this.hideConfirmationModal()
        }
    }

    async sendRequest({
                          pruningCutoff,
                          isConfirmed
                      }) {
        const data = {
            ...iawpActions.configure_pruner,
            pruningCutoff: pruningCutoff,
            isConfirmed: isConfirmed,
        }
        const response = await jQuery.post(ajaxurl, data)

        return {
            wasSuccessful: response.success,
            confirmationText: response.data.confirmationText,
            isEnabled: response.data.isEnabled,
            statusMessage: response.data.statusMessage,
        }
    }

    showConfirmationModal = (confirmationText) => {
        this.confirmationTextTarget.innerText = confirmationText
        this.isModalActuallyOpen = true
        MicroModal.show("prune-modal", {
            onClose: () => {
                this.cancelConfirmation()
            }
        })
    }

    hideConfirmationModal = (e) => {
          if (this.isModalActuallyOpen) {
            this.isModalActuallyOpen = false
            MicroModal.close("prune-modal")
        }
    }
}
