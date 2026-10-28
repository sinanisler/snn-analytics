import {Controller} from "@hotwired/stimulus"

export default class extends Controller {
    static values = {
        id: String,
        type: String
    }

    setFavoriteReport() {
        const data = {
            ...iawpActions.set_favorite_report,
            id: this.idValue,
            type: this.typeValue,
        }

        this.element.classList.add('active')
        jQuery.post(ajaxurl, data, (response) => {
            this.removeExistingStar()
            if(this.idValue) {
                this.markSavedReportAsFavorite(this.idValue)
            } else {
                this.markBaseReportAsFavorite(this.typeValue)
            }

        }).fail(() => {
            this.element.classList.remove('active')
        })
    }

    removeExistingStar() {
        const favoriteReports = Array.from(document.querySelectorAll('[data-report-id].favorite, [data-report-type].favorite'));

        favoriteReports.forEach((savedReport) => {
            savedReport.classList.remove('favorite')
        })
    }

    markSavedReportAsFavorite(id) {
        const element = document.querySelector(`[data-report-id="${id}"]`)

        if(element) {
            element.classList.add('favorite')
        }
    }

    markBaseReportAsFavorite(type) {
        const element = document.querySelector(`[data-report-type="${type}"]`)

        if(element) {
            element.classList.add('favorite')
        }
    }
}