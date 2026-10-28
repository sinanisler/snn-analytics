import {Controller} from "@hotwired/stimulus"

export default class extends Controller {
    static targets = ['quickStat']

    connect() {
        document.addEventListener('iawp:changeQuickStats', this.updateTableUI)
    }

    disconnect() {
        document.removeEventListener('iawp:changeQuickStats', this.updateTableUI)
    }

    updateTableUI = (e) => {
        const quickStatIds = e.detail.optionIds
        const quickStatCount = quickStatIds.length
        const stats = this.element.getElementsByClassName('iawp-stats')[0];

        stats.classList.forEach((className) => {
            if (className.startsWith("total-of-")) {
                stats.classList.remove(className)
            }
        })

        stats.classList.add('total-of-' + quickStatCount.toString())

        this.quickStatTargets.forEach((stat) => {
            const isPresent = quickStatIds.includes(stat.dataset.id)
            stat.classList.toggle('visible', isPresent)
        })
    }
}
