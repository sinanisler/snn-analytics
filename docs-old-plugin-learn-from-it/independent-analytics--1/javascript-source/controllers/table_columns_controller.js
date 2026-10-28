import {Controller} from "@hotwired/stimulus"

export default class extends Controller {
    static targets = [''] // TODO - Use targets for cells, data table, and data table container

    connect() {
        document.addEventListener('iawp:changeColumns', this.updateTableUI)
    }

    disconnect() {
        document.removeEventListener('iawp:changeColumns', this.updateTableUI)
    }

    updateTableUI = (e) => {
        const columnIds = e.detail.optionIds
        const columnCount = columnIds.length
        const cells = document.querySelectorAll('.cell[data-column]')

        // Hide and show the correct cells
        cells.forEach((cell) => {
            const isPresent = columnIds.includes(cell.dataset.column)

            cell.classList.toggle('hide', !isPresent)
            cell.setAttribute('data-test-visibility', isPresent ? 'visible' : 'hidden')
        })

        // Update data-column-count so table knows how many columns are showing
        document.getElementById('data-table').setAttribute('data-column-count', columnCount.toString())
        document.getElementById('data-table').style.setProperty('--columns', columnCount.toString())
        document.getElementById('data-table').style.setProperty('--columns-mobile', (columnCount - 1).toString())

        // Adapt table size
        document.getElementById('data-table').style.setProperty('min-width', (columnCount * 170) + 'px');
        if (document.getElementById('data-table').offsetWidth > document.getElementById('data-table-container').offsetWidth) {
            document.getElementById('data-table-container').classList.add('horizontal');
        } else {
            document.getElementById('data-table-container').classList.remove('horizontal');
        }
    }
}
