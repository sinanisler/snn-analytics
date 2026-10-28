import {Controller} from "@hotwired/stimulus"
import {downloadJSON} from "../download";

export default class extends Controller {
    static targets = ['submitButton', 'warningMessage', 'fileInput']

    static values = {
        databaseVersion: String
    }

    fileData = null

    import() {
        this.disableSubmissions()
        this.submitButtonTarget.classList.add('sending')

        const data = {
            ...iawpActions.import_reports,
            json: JSON.stringify(this.fileData)
        };

        jQuery.post(ajaxurl, data, (response) => {
            this.clearFileInput()
            this.submitButtonTarget.classList.remove('sending')
            this.submitButtonTarget.classList.add('sent')
            setTimeout(() => {
                this.submitButtonTarget.classList.remove('sent')
                window.location.reload()
            }, 1000)
        }).fail(() => {

        })
    }

    async handleFileSelected(e) {
        const json = await e.target.files[0].text()
        const data = JSON.parse(json)
        this.fileData = null
        this.hideWarning()
        this.disableSubmissions()

        if(!data['database_version'] || !Array.isArray(data['reports'])) {
            this.showWarning(iawpText.invalidReportArchive)
            return;
        }

        this.enableSubmissions()
        this.fileData = data
    }

    clearFileInput() {
        this.fileData = null;
        this.fileInputTarget.value = null
        this.hideWarning()
        this.disableSubmissions()
    }

    showWarning(message) {
        this.warningMessageTarget.innerText = message
        this.warningMessageTarget.style.display = 'block';
    }

    hideWarning() {
        this.warningMessageTarget.style.display = 'none';
    }

    enableSubmissions() {
        this.submitButtonTarget.removeAttribute('disabled')
    }

    disableSubmissions() {
        this.submitButtonTarget.setAttribute('disabled', 'disabled')
    }
}