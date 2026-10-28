import {Controller} from "@hotwired/stimulus"
import MicroModal from "micromodal"

export default class extends Controller {
    static targets = ['saveButton', 'resetButton', 'message']

    getCheckboxes() {
        return Array.from(this.element.querySelectorAll('input[type="checkbox"]'))
    }

    getSelectedStatusIds() {
        return this.getCheckboxes()
            .filter((checkbox) => checkbox.checked)
            .map((checkbox) => checkbox.name)
    }

    toggleInputs(shouldDisable) {
        this.saveButtonTarget.toggleAttribute('disabled', shouldDisable)
        this.resetButtonTarget.toggleAttribute('disabled', shouldDisable)
        Array.from(this.element.querySelectorAll('input[type="checkbox"]')).forEach((element) => {
            element.toggleAttribute('disabled', shouldDisable)
        })
    }

    enableInputs() {
        this.toggleInputs(false)
    }

    disableInputs() {
        this.toggleInputs( true)
    }

    async saveClick() {
        this.disableInputs()
        const response = await this.sendRequest({
            statusesToTrack: this.getSelectedStatusIds()
        })
        this.enableInputs()

        if(response.wasSuccessful) {
            this.updateStatusCheckboxes(response.statusesToTrack)
            this.showMessage(true, 'Statuses were saved')
        } else {
            this.showMessage(false, 'Unable to save statuses')
        }
    }

    async resetClick() {
        this.disableInputs()
        const response = await this.sendRequest({
            resetToDefault: true,
        })
        this.enableInputs()

        if(response.wasSuccessful) {
            this.updateStatusCheckboxes(response.statusesToTrack)
            this.showMessage(true, 'Statuses were reset')
        } else {
            this.showMessage(false, 'Unable to reset statuses')
        }
    }

    async sendRequest({statusesToTrack = null, resetToDefault = false}) {
        const data = {
            ...iawpActions.set_woocommerce_statuses_to_track,
            statusesToTrack,
            resetToDefault
        }
        const response = await jQuery.post(ajaxurl, data)

        return {
            wasSuccessful: response.success,
            statusesToTrack: response.data.statusesToTrack
        }
    }

    updateStatusCheckboxes(statuses) {
        const trackedStatusIds = statuses
            .filter(status => status['is_tracked'])
            .map(status => status.id)

        this.getCheckboxes().forEach((element) => {
            element.checked = trackedStatusIds.includes(element.name)
        })
    }

    showMessage(isAGoodMessage, message) {
        this.messageTarget.style.color = isAGoodMessage ? 'green' : 'red'
        this.messageTarget.innerText = message

        setTimeout(() => {
            this.messageTarget.innerText = ''
        }, 2000)
    }
}
