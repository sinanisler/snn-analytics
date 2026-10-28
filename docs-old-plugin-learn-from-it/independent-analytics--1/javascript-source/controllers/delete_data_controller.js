import {Controller} from "@hotwired/stimulus"
import MicroModal from "micromodal"

document.addEventListener("DOMContentLoaded", () => MicroModal.init())

export default class extends Controller {
    static values = {
        confirmation: String
    }
    static targets = ["submit", "input"]

    isValidConfirmation(confirmationValue) {
        return confirmationValue.toLowerCase() === "delete all data"
    }

    confirmationValueChanged(confirmationValue) {
        this.inputTarget.value = confirmationValue
        const disable = !this.isValidConfirmation(confirmationValue)
        this.submitTarget.toggleAttribute("disabled", disable)
    }

    updateConfirmation(e) {
        this.confirmationValue = e.target.value
    }

    open() {
        this.confirmationValue = ""
        MicroModal.show("delete-data-modal")
    }

    close(e) {
        if(e.target !== e.currentTarget) {
            return
        }

        MicroModal.close("delete-data-modal")
    }

    submit(e) {
        e.preventDefault()

        if (!this.isValidConfirmation(this.confirmationValue)) {
            return
        }

        const data = {
            ...iawpActions.delete_data,
            confirmation: this.confirmationValue
        }

        this.submitTarget.setAttribute('disabled', 'disabled')
        this.submitTarget.classList.add('sending')

        jQuery.post(ajaxurl, data, (response) => {
            this.submitTarget.classList.remove('sending')
            this.submitTarget.classList.add('sent')
            setTimeout(() => {
                document.location = response.data.redirectUrl
                this.submitTarget.classList.remove('sent')
            }, 1000)
        })

    }
}