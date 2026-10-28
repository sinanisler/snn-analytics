import {Controller} from "@hotwired/stimulus"
import {downloadCSV} from '../download'
import html2pdf from 'html2pdf.js';
import {Chart} from 'chart.js'
import {svgToPng} from "../utils/svg-to-png";

class Report_Controller extends Controller {
    /**
     * Store the jQuery request so that it can be canceled if a new report is requested before the
     * current one is fetched.
     */
    static request;
    static targets = ['loadMore', 'exportReportTable', 'exportReportStatistics', 'exportPDF', 'spinner']
    static values = {
        isExaminer: Boolean,
        name: String,
        reportName: String,
        relativeRangeId: String,
        exactStart: String,
        exactEnd: String,
        group: String,
        chartInterval: String,
        sortColumn: String,
        sortDirection: String,
        columns: Array,
        quickStats: Array,
        primaryChartMetricId: String,
        secondaryChartMetricId: String,
        filters: Array,
        filterLogic: String
    }

    tableType = undefined
    exactStart = undefined
    exactEnd = undefined
    relativeRangeId = undefined
    columns = undefined
    quickStats = undefined
    filters = []
    filterLogic = undefined
    sortColumn = undefined
    sortDirection = undefined
    group = undefined
    chartInterval = undefined
    primaryChartMetricId = undefined
    secondaryChartMetricId = undefined
    page = 1

    connect() {
        // If any values are empty strings, set them equal to undefined instead
        this.exactStart = this.exactStartValue === "" ? undefined : this.exactStartValue;
        this.exactEnd = this.exactEndValue === "" ? undefined : this.exactEndValue;
        this.relativeRangeId = this.relativeRangeIdValue === "" ? undefined : this.relativeRangeIdValue
        this.columns = this.columnsValue
        this.quickStats = this.quickStatsValue
        this.group = this.groupValue
        this.chartInterval = this.chartIntervalValue
        this.sortColumn = this.sortColumnValue
        this.sortDirection = this.sortDirectionValue
        this.primaryChartMetricId = this.primaryChartMetricIdValue
        this.secondaryChartMetricId = this.secondaryChartMetricIdValue
        this.filters = this.filtersValue
        this.filterLogic = this.filterLogicValue
        this.tableType = jQuery('#data-table').data('table-name')
        document.addEventListener('iawp:changeDates', this.datesChanged)
        document.addEventListener('iawp:changeColumns', this.columnsChanged)
        document.addEventListener('iawp:changeQuickStats', this.quickStatsChanged)
        document.addEventListener('iawp:changeFilters', this.filtersChanged)
        document.addEventListener('iawp:changeSort', this.sortChanged)
        document.addEventListener('iawp:changeGroup', this.changeGroup)
        document.addEventListener('iawp:changeChartInterval', this.changeChartInterval)
        document.addEventListener('iawp:changePrimaryChartMetric', this.changePrimaryChartMetric)
        document.addEventListener('iawp:changeSecondaryChartMetric', this.changeSecondaryChartMetric)
        document.addEventListener('iawp:fetchingReport', this.onFetchingReport)
        setTimeout(() => {
            this.fetch({
                isInitialFetch: true,
                showLoadingOverlay: false
            })
        }, 0)
    }

    disconnect() {
        document.removeEventListener('iawp:changeDates', this.datesChanged)
        document.removeEventListener('iawp:changeColumns', this.columnsChanged)
        document.removeEventListener('iawp:changeQuickStats', this.quickStatsChanged)
        document.removeEventListener('iawp:changeFilters', this.filtersChanged)
        document.removeEventListener('iawp:changeSort', this.sortChanged)
        document.removeEventListener('iawp:changeGroup', this.changeGroup)
        document.removeEventListener('iawp:changeChartInterval', this.changeChartInterval)
        document.removeEventListener('iawp:changePrimaryChartMetric', this.changePrimaryChartMetric)
        document.removeEventListener('iawp:changeSecondaryChartMetric', this.changeSecondaryChartMetric)
        document.removeEventListener('iawp:fetchingReport', this.onFetchingReport)
    }

    emitChangedOption(detail) {
        document.dispatchEvent(
            new CustomEvent('iawp:changedOption', {
                detail
            })
        )
    }

    changePrimaryChartMetric = (e) => {
        this.primaryChartMetricId = e.detail.primaryChartMetricId
        this.emitChangedOption({
            'primary_chart_metric_id': this.primaryChartMetricId
        })
    }

    changeSecondaryChartMetric = (e) => {
        this.secondaryChartMetricId = e.detail.secondaryChartMetricId
        this.emitChangedOption({
            'secondary_chart_metric_id': this.secondaryChartMetricId
        })
    }

    datesChanged = (e) => {
        this.exactStart = e.detail.exactStart
        this.exactEnd = e.detail.exactEnd
        this.relativeRangeId = e.detail.relativeRangeId
        this.page = 1;

        this.emitChangedOption({
            'exact_start': this.exactStart || null,
            'exact_end': this.exactEnd || null,
            'relative_range_id': this.relativeRangeId || null
        })
        this.fetch({newDateRange: true})
    }

    columnsChanged = (e) => {
        this.columns = e.detail.optionIds
        this.emitChangedOption({
            'columns': this.columns
        })
    }

    quickStatsChanged = (e) => {
        this.quickStats = e.detail.optionIds
        this.emitChangedOption({
            'quick_stats': this.quickStats
        })
    }

    filtersChanged = (e) => {
        this.filters = e.detail.filters
        this.filterLogic = e.detail.filterLogic
        this.page = 1;

        this.emitChangedOption({
            'filters': this.filters,
            'filter_logic': this.filterLogic,
        })
        this.fetch({showLoadingOverlay: e.detail.showLoadingOverlay})
    }

    sortChanged = (e) => {
        this.sortColumn = e.detail.sortColumn
        this.sortDirection = e.detail.sortDirection
        this.page = 1;

        this.emitChangedOption({
            'sort_column': this.sortColumn,
            'sort_direction': this.sortDirection
        })
        this.fetch()
    }

    changeGroup = (e) => {
        if (this.group === e.detail.group) {
            return;
        }

        this.group = e.detail.group
        this.page = 1
        this.emitChangedOption({
            'group_name': this.group
        })
        this.fetch({newGroup: true});
    }

    changeChartInterval = (e) => {
        const chartInterval = e.detail.chartInterval

        if (this.chartInterval === chartInterval) {
            return;
        }

        this.chartInterval = chartInterval
        this.emitChangedOption({
            'chart_interval': this.chartInterval
        })
        this.fetch();
    }

    changeTable = (event) => {
        this.tableType = event.currentTarget.dataset.tableType
        this.page = 1;

        event.currentTarget.closest('.examiner-table-tabs').querySelectorAll('.active').forEach((element) => {
            element.classList.remove('active')
        })

        event.currentTarget.classList.add('active')

        this.fetch({ newTable: true })
    }

    onFetchingReport = () => {
        this.spinnerTarget.classList.remove('hidden')

        document.addEventListener('iawp:fetchedReport', () => {
            this.spinnerTarget.classList.add('hidden')
        }, {once: true})
    }

    loadMore = () => {
        this.page = this.page + 1

        this.fetch()
    }

    fetch({
              isInitialFetch = false,
              showLoadingOverlay = true,
              newGroup = false,
              newTable = false,
              newDateRange = false
          } = {}) {
        const isExaminer = this.isExaminerValue

        if (showLoadingOverlay) {
            jQuery('#iawp-parent').addClass('loading');
        }

        const data = {
            ...iawpActions.filter,
            'filters': this.filters,
            'filter_logic': this.filterLogic,
            'exact_start': this.exactStart,
            'exact_end': this.exactEnd,
            'is_new_date_range': newDateRange,
            'relative_range_id': this.relativeRangeId,
            'table_type': this.tableType,
            'columns': this.columns,
            'report_quick_stats': ['visitors'], // TODO
            'primary_chart_metric_id': this.primaryChartMetricId,
            'secondary_chart_metric_id': this.secondaryChartMetricId,
            'sort_column': this.sortColumn,
            'quick_stats': this.quickStats,
            'sort_direction': this.sortDirection,
            'group': this.group,
            'is_new_group': newGroup && !newTable,
            'chart_interval': this.chartInterval,
            'page': this.page,
        };

        if(newTable) {
            data.columns = null
        }

        if (Report_Controller.request) {
            Report_Controller.request.abort();
        }

        if(isExaminer) {
            const searchParams = new URLSearchParams(document.location.search);

            data['examiner_type'] = searchParams.get('tab')
            data['examiner_group'] = searchParams.get('group')
            data['examiner_id'] = searchParams.get('examiner')
        }

        document.dispatchEvent(
            new CustomEvent('iawp:fetchingReport')
        )

        Report_Controller.request = jQuery.post(ajaxurl, data, (response) => {
            response = response.data

            this.columns = response.columns
            this.filters = response.filters
            this.chartInterval = response.chartInterval


            document.dispatchEvent(
                new CustomEvent('iawp:fetchedReport')
            )

            if (newGroup || isExaminer) {
                jQuery('#iawp-table-wrapper').replaceWith(response.table)
                jQuery('[data-plugin-group-options-option-type-value=columns]').replaceWith(response.columnsHTML)

                document.dispatchEvent(
                    new CustomEvent('iawp:changeColumns', {
                        detail: {
                            optionIds: this.columns
                        }
                    })
                )
            } else {
                jQuery('#iawp-rows').replaceWith(response.rows);
            }

            jQuery('#dates-button span:last-child').text(response.label)

            const parser = new DOMParser();
            const statsDocument = parser.parseFromString(response.stats, 'text/html');

            jQuery('#quick-stats .iawp-stats').replaceWith(statsDocument.querySelector('.iawp-stats'))
            jQuery('#quick-stats').removeClass('skeleton-ui');

            if (isInitialFetch && this.filtersValue.length === 0) {
                // Do not update the chart if there are no filters and it's the initial load
            } else {
                jQuery('#independent-analytics-chart').closest('.chart-container').replaceWith(response.chart);
            }

            if(isExaminer) {
                jQuery('#table-toolbar').replaceWith(response.tableToolbar)
            }

            if(isExaminer && isInitialFetch) {
                this.enableExaminerTabs()
            }

            document.dispatchEvent(
                new CustomEvent('iawp:updateColumnsUserInterface')
            )

            document.dispatchEvent(
                new CustomEvent('iawp:groupChanged', {
                    detail: {
                        groupId: response.groupId
                    }
                })
            )

            document.dispatchEvent(
                new CustomEvent('iawp:filtersChanged', {
                    detail: {
                        filtersTemplateHTML: response.filtersTemplateHTML,
                        filtersButtonsHTML: response.filtersButtonsHTML,
                        filters: response.filters
                    }
                })
            )

            this.exportPDFTarget.removeAttribute('disabled')

            if (response.isLastPage) {
                this.loadMoreTarget.setAttribute('disabled', 'disabled')
            } else {
                this.loadMoreTarget.removeAttribute('disabled')
            }

            jQuery('#iawp-columns .row-number').text(response.totalNumberOfRows.toLocaleString());
            document.getElementById('data-table').setAttribute('data-total-number-of-rows', response.totalNumberOfRows)

            jQuery('#iawp-parent').removeClass('loading');

            if(!isInitialFetch && response.groupId === 'journey') {
                document.body.scrollIntoView({ behavior: 'instant', block: 'start' })
            }
        });
    }

    showExaminer(event) {
        const title = event.currentTarget.dataset.title
        const url = new URL(event.currentTarget.dataset.url)
        const updates = {
            'exact_start': this.exactStart,
            'exact_end': this.exactEnd,
            'relative_range_id': this.relativeRangeId,
            'quick_stats': this.quickStats,
            'primary_chart_metric_id': this.primaryChartMetricId,
            'secondary_chart_metric_id': this.secondaryChartMetricId,
            'chart_interval': this.chartInterval,
            'group': this.group,
        }
        const params = url.searchParams

        for (const [key, value] of Object.entries(updates)) {
            if (!value) {
                continue
            }

            if(Array.isArray(value)) {
                value.forEach((item) => params.append(key + '[]', item))
                continue
            }

            params.set(key, value)
        }

        url.search = params.toString()

        document.dispatchEvent(
            new CustomEvent('iawp:showExaminer', {
                detail: {
                    title,
                    reportName: this.reportNameValue,
                    dateLabel: this.element.querySelector('.date-picker-parent .iawp-label').innerText,
                    url: url.toString(),
                }
            })
        )
    }

    exportReportTable() {
        const data = {
            ...iawpActions.export_report_table,
            'table_type': jQuery('#data-table').data('table-name'),
            'columns': this.columns,
            'filters': this.filters,
            'exact_start': this.exactStart,
            'exact_end': this.exactEnd,
            'relative_range_id': this.relativeRangeId,
            'sort_column': this.sortColumn,
            'sort_direction': this.sortDirection,
            'group': this.group,
        };

        if(this.isExaminerValue) {
            const searchParams = new URLSearchParams(document.location.search);

            data['examiner_type'] = searchParams.get('tab')
            data['examiner_group'] = searchParams.get('group')
            data['examiner_id'] = searchParams.get('examiner')
        }

        this.exportReportTableTarget.classList.add('sending')
        this.exportReportTableTarget.setAttribute('disabled', 'disabled')

        if (Report_Controller.csvRequest) {
            Report_Controller.csvRequest.abort();
        }

        Report_Controller.csvRequest = jQuery.post(ajaxurl, data, (response) => {
            downloadCSV(this.getFileName('csv', 'table'), response.data.csv)
            this.exportReportTableTarget.classList.add('sent')
            this.exportReportTableTarget.classList.remove('sending')
            this.exportReportTableTarget.removeAttribute('disabled')

            setTimeout(() => {
                this.exportReportTableTarget.classList.remove('sent')
            }, 1000)
        })
    }

    exportReportStatistics() {
        const data = {
            ...iawpActions.export_report_statistics,
            'filters': this.filters,
            'exact_start': this.exactStart,
            'exact_end': this.exactEnd,
            'is_new_date_range': false,
            'relative_range_id': this.relativeRangeId,
            'table_type': jQuery('#data-table').data('table-name'),
            'columns': this.columns,
            'sort_column': this.sortColumn,
            'quick_stats': this.quickStats,
            'sort_direction': this.sortDirection,
            'group': this.group,
            'is_new_group': false,
            'chart_interval': this.chartInterval,
            'page': this.page,
        };

        if(this.isExaminerValue) {
            const searchParams = new URLSearchParams(document.location.search);

            data['examiner_type'] = searchParams.get('tab')
            data['examiner_group'] = searchParams.get('group')
            data['examiner_id'] = searchParams.get('examiner')
        }

        this.exportReportStatisticsTarget.classList.add('sending')
        this.exportReportStatisticsTarget.setAttribute('disabled', 'disabled')

        if (Report_Controller.csvRequest) {
            Report_Controller.csvRequest.abort();
        }

        Report_Controller.csvRequest = jQuery.post(ajaxurl, data, (response) => {
            downloadCSV(this.getFileName('csv', 'statistics'), response.data.csv)
            this.exportReportStatisticsTarget.classList.add('sent')
            this.exportReportStatisticsTarget.classList.remove('sending')
            this.exportReportStatisticsTarget.removeAttribute('disabled')

            setTimeout(() => {
                this.exportReportStatisticsTarget.classList.remove('sent')
            }, 1000)
        })
    }

    exportPDF() {
        this.exportPDFTarget.classList.add('sending')
        this.exportPDFTarget.setAttribute('disabled', 'disabled')

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
                const imageElement = await svgToPng(map.mapImage);
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

            // Preserve selected values in clone by manually adding "selected" to options
            clonedPage.querySelectorAll('select').forEach((element) => {
                if (!element.id) {
                    return
                }

                const originalValue = document.getElementById(element.id).value

                element.options.forEach((option) => {
                    option.toggleAttribute('selected', option.value === originalValue)
                })
            })

            const options = {
                filename: this.getFileName('pdf'),
                jsPDF: {
                    unit: 'in',
                    format: 'letter',
                    orientation: 'landscape',
                },
            }

            html2pdf().set(options).from(clonedPage).toContainer().save().then(() => {
                this.exportPDFTarget.classList.add('sent')
                this.exportPDFTarget.classList.remove('sending')
                this.exportPDFTarget.removeAttribute('disabled')

                setTimeout(() => {
                    this.exportPDFTarget.classList.remove('sent')
                }, 1000)
            })
        }, 250) // Allow animations to finish before exporting blocks things up
    }

   enableExaminerTabs() {
        document.querySelectorAll('.examiner-table-tabs button').forEach((button) => {
            button.removeAttribute('disabled')
        })
    }

    getFileName(fileExtension, type = null) {
        const reportTitleElement = document.querySelector('#report-title-bar .report-title')
        let reportTitle = reportTitleElement ? reportTitleElement.innerText : 'report'

        if (type) {
            reportTitle += '-' + type
        }

        return reportTitle.replace(/[^a-zA-Z0-9]+/g, '-').toLowerCase() + '.' + fileExtension
    }
}

export default Report_Controller;
