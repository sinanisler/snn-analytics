import {Controller} from "@hotwired/stimulus"
import html2pdf from 'html2pdf.js'
import {Chart} from 'chart.js'
import {svgToPng} from "../utils/svg-to-png";

export default class extends Controller {
    static values = {
        loadingText: String
    }

    export() {
        this.element.classList.add('sending')
        this.element.setAttribute('disabled', 'disabled')

        setTimeout(async () => {
            const charts = Object.values(Chart.instances)
            const mapElements = window.iawpMaps || []

            // Assign a temporary unique id to every chart
            charts.forEach((chart) => {
                chart.canvas.dataset.chartExportId = Math.random()
            })

            // Assign a temporary unique id to every map
            mapElements.forEach((map) => {
                map.container.dataset.chartExportId = Math.random()
            })

            const clonedPage = document.getElementById('wpwrap').cloneNode(true)

            // Set the width to the PDFs page width
            clonedPage.style.width = 1056 + 'px'

            charts.forEach((chart) => {
                // Get an image of the chart
                const base64Image = chart.toBase64Image('image/png', 1);

                // Generate an image element to inline
                const imageElement = document.createElement('img')
                imageElement.src = base64Image
                imageElement.classList.add('chart-converted-to-image')

                // Swap the chart for the image
                const chartExportId = chart.canvas.dataset.chartExportId
                clonedPage.querySelector(`[data-chart-export-id='${chartExportId}']`).replaceWith(imageElement)

                // Remove the temporary export id
                delete chart.canvas.dataset.chartExportId
            })

            for (const map of mapElements) {
                // Generate an image element to inline
                const imageElement = await svgToPng(map.mapImage)
                imageElement.classList.add('chart-converted-to-image')

                // Swap the chart for the image
                const chartExportId = map.container.dataset.chartExportId
                clonedPage.querySelector(`[data-chart-export-id='${chartExportId}']`).replaceWith(imageElement)

                // Remove the temporary export id
                delete map.container.dataset.chartExportId
            }

            // Prevent stimulus controllers from firing
            clonedPage.querySelectorAll('[data-controller]').forEach((element) => {
                element.removeAttribute('data-controller')
            })

            // Remove module picker
            clonedPage.querySelectorAll('.module-picker').forEach((element) => {
                element.remove()
            })

            // Remove toolbar
            clonedPage.querySelectorAll('#report-header-container .toolbar').forEach((element) => {
                element.remove()
            })

            // Remove refresh content
            clonedPage.querySelectorAll('#report-header-container .last-updated-container').forEach((element) => {
                element.remove()
            })

            // Remove pagination
            clonedPage.querySelectorAll('.iawp-module .module-pagination').forEach((element) => {
                element.remove()
            })

            const options = {
                filename: 'overview.pdf',
                jsPDF: {
                    unit: 'in',
                    format: 'letter',
                    orientation: 'landscape',
                },
            }
            html2pdf().set(options).from(clonedPage).toContainer().save().then(() => {
                this.element.classList.add('sent')
                this.element.classList.remove('sending')
                this.element.removeAttribute('disabled')

                setTimeout(() => {
                    this.element.classList.remove('sent')
                }, 1000)
            })
        }, 250) // Allow animations to finish before exporting blocks things up
    }
}