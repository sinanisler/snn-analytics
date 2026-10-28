import {Controller} from "@hotwired/stimulus"
import {downloadJSON} from "../download";

export default class extends Controller {
    static targets = ['selectAllCheckbox', 'submitButton']

    export() {
        this.disableEverything()
        this.submitButtonTarget.classList.add('sending')

        const data = {
            ...iawpActions.export_reports,
            ids: this.getCheckedReportIds()
        };

        jQuery.post(ajaxurl, data, (response) => {
            downloadJSON('independent-analytics-reports.json', response.data.json)
            this.resetUI()
            this.submitButtonTarget.classList.remove('sending')
            this.submitButtonTarget.classList.add('sent')
            setTimeout(() => {
                this.submitButtonTarget.classList.remove('sent')
            }, 1000)
        }).fail(() => {
            this.resetUI()
        })
    }

    handleToggleSelectAll(e) {
        this.getAllCheckboxes().forEach(checkbox => checkbox.checked = e.target.checked)
        this.updateSubmitButton()
    }

    handleToggleReport(e) {
        if (this.getCheckedCheckboxes().length === this.getAllCheckboxes().length) {
            this.selectAllCheckboxTarget.checked = true
        } else {
            this.selectAllCheckboxTarget.checked = false
        }
        this.updateSubmitButton()
    }

    updateSubmitButton() {
        if (this.getCheckedCheckboxes().length === 0) {
            this.submitButtonTarget.setAttribute('disabled', 'disabled')
        } else {
            this.submitButtonTarget.removeAttribute('disabled')

        }
    }

    disableEverything() {
        this.submitButtonTarget.setAttribute('disabled', 'disabled')
        this.selectAllCheckboxTarget.setAttribute('disabled', 'disabled')
        this.getAllCheckboxes().forEach((checkbox) => {
            checkbox.setAttribute('disabled', 'disabled')
        })
    }

    resetUI() {
        this.submitButtonTarget.setAttribute('disabled', 'disabled')
        this.selectAllCheckboxTarget.removeAttribute('disabled')
        this.selectAllCheckboxTarget.checked = false
        this.getAllCheckboxes().forEach((checkbox) => {
            checkbox.removeAttribute('disabled')
            checkbox.checked = false
        })
    }

    getCheckedReportIds() {
        return this.getCheckedCheckboxes().map(checkbox => checkbox.value)
    }

    getAllCheckboxes() {
        return Array.from(
            this.element.querySelectorAll('input[name="report_id"]')
        )
    }

    getCheckedCheckboxes() {
        return Array.from(
            this.element.querySelectorAll('input[name="report_id"]:checked')
        )
    }
}