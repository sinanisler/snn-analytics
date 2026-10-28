import {Controller} from "@hotwired/stimulus"

export default class extends Controller {
    static targets = ['sortButton']
    static values = {column: String}
    previousColumn = null
    sortDirection = null

    connect() {
        this.sortButtonTargets.forEach((sortButton) => {
            const sorted = !!sortButton.dataset.sortDirection

            if (sorted) {
                this.previousColumn = sortButton.dataset.sortColumn
                this.sortDirection = sortButton.dataset.defaultSortDirection
            }
        })
    }

    sortColumnColumn(e) {
        const column = e.currentTarget.dataset.sortColumn
        const defaultDirection = e.currentTarget.dataset.defaultSortDirection

        if (this.previousColumn === column) {
            this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc'
        } else {
            this.sortDirection = defaultDirection
        }
        this.previousColumn = column

        this.sortButtonTargets.forEach((sortButton) => {
            sortButton.dataset.sortDirection = sortButton.dataset.sortColumn === column ? this.sortDirection : ''
        })

        document.dispatchEvent(
            new CustomEvent('iawp:changeSort', {
                detail: {
                    sortColumn: column,
                    sortDirection: this.sortDirection
                }
            })
        )
    }
}