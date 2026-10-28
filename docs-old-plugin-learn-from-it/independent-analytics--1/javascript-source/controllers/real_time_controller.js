import {Controller} from "@hotwired/stimulus"
import {Chart, registerables} from 'chart.js'
import Isotope from "isotope-layout"
import htmlLegendPlugin from "../chart_plugins/html_legend_plugin";
import corsairPlugin from "../chart_plugins/corsair_plugin";
import {isDarkMode} from "../utils/appearance";

Chart.register(...registerables);
Chart.defaults.font.family = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol"';

export default class extends Controller {
    static targets = [
        'minuteChart',
        'secondChart',
        'visitorMessage',
        'pageMessage',
        'referrerMessage',
        'countryMessage',
        'pagesList',
        'referrersList',
        'countriesList',
        'campaignsList',
        'device_typesList'
    ]
    static values = {
        chartData: Object,
        nonce: String,
        visible: {
            type: Boolean,
            default: true
        }
    }

    connect() {
        document.addEventListener('visibilitychange', this.tabVisibilityChanged)
        this.initializeChart({
            element: this.minuteChartTarget,
            views: this.chartDataValue['minute_interval_views'],
            labelsShort: this.chartDataValue['minute_interval_labels_short'],
            labelsFull: this.chartDataValue['minute_interval_labels_full']
        })
        this.initializeChart({
            element: this.secondChartTarget,
            views: this.chartDataValue['second_interval_views'],
            labelsShort: this.chartDataValue['second_interval_labels_short'],
            labelsFull: this.chartDataValue['second_interval_labels_full']
        })
        this.startServerPolling()
    }

    disconnect() {
        document.removeEventListener('visibilitychange', this.tabVisibilityChanged)
    }

    tabVisibilityChanged = () => {
        this.visibleValue = !document.hidden

        if (this.visibleValue) {
            this.refresh()
            this.startServerPolling()
        }
    }

    startServerPolling() {
        clearInterval(this.interval)
        this.interval = setInterval(() => {
            if (this.visibleValue) {
                this.refresh()
            } else {
                clearInterval(this.interval)
            }
        }, 10000)
    }

    refresh() {
        const data = {
            ...iawpActions.real_time_data
        };

        jQuery.post(ajaxurl, data, (response, a, b) => {
            document.getElementById('real-time-dashboard').classList.remove('refreshed')
            void document.getElementById('real-time-dashboard').offsetWidth
            document.getElementById('real-time-dashboard').classList.add('refreshed')
            if (response.success) {
                this.rerender(response.data)
            }
        });
    }

    rerender(data) {
        this.visitorMessageTarget.textContent = data.visitor_message
        this.pageMessageTarget.textContent = data.page_message
        this.referrerMessageTarget.textContent = data.referrer_message
        this.countryMessageTarget.textContent = data.country_message
        this.updateChart({
            element: this.minuteChartTarget,
            visitors: data.chart_data.minute_interval_visitors,
            views: data.chart_data.minute_interval_views,
        })
        this.updateChart({
            element: this.secondChartTarget,
            visitors: data.chart_data.second_interval_visitors,
            views: data.chart_data.second_interval_views
        })
        this.updateList({
            element: this.pagesListTarget,
            entries: data.lists.pages.entries
        })
        this.updateList({
            element: this.referrersListTarget,
            entries: data.lists.referrers.entries
        })
        this.updateList({
            element: this.countriesListTarget,
            entries: data.lists.countries.entries
        })
        this.updateList({
            element: this.campaignsListTarget,
            entries: data.lists.campaigns.entries
        })
        this.updateList({
            element: this.device_typesListTarget,
            entries: data.lists.device_types.entries
        })
    }

    existingElements() {
        return Array.from(this.element.querySelectorAll('li:not(.exiting)'))
    }

    existingIds() {
        return this.existingElements().map((existingElement) => existingElement.dataset.id)
    }

    updateList({
                   element,
                   entries
               }) {
        let iso = element.iso

        if (!iso) {
            iso = element.iso = new Isotope(element, {
                itemSelector: "li",
                layoutMode: "vertical",
                sortColumn: "position",
                getSortData: {
                    position: (element) => {
                        return parseInt(element.dataset.position);
                    }
                }
            })
        }

        // Remove existing elements
        this.existingElements().forEach((existingElement) => {
            iso.remove(existingElement)
        })

        // Add new elements
        entries.forEach((entry, index) => {
            const entryElement = this.elementFromEntry(entry)
            iso.insert(entryElement)
        })

        iso.arrange()

        const totalElements = iso.items.length
        const emptyMessage = element.parentNode.querySelector('.most-popular-empty-message')

        if (totalElements > 0) {
            emptyMessage.classList.add('hide')
        } else {
            emptyMessage.classList.remove('hide')
        }
    }

    elementFromEntry(entry) {
        const id = entry['id']
        const position = entry['position']
        const title = entry['title']
        const subtitle = entry['subtitle']
        const views = entry['views']
        const flag = entry['flag'] ? entry['flag'] : ''
        const subtitleHTML = subtitle ? `<span class="real-time-subtitle">${subtitle}</span>` : '';

        const li = `
            <li data-id="${id}" data-position="${position}">
                <span class="real-time-position">${position}.</span>
                ${flag}
                <span class="real-time-resource">${title} ${subtitleHTML}</span>
                <span class="real-time-stat">${views}</span>
            </li>
        `
        const el = document.createElement('div');
        el.innerHTML = li;
        return el.firstElementChild;
    }

    initializeChart({
                        element,
                        views,
                        labelsShort,
                        labelsFull
                    }) {
        const data = {
            labels: labelsFull,
            datasets: [{
                id: 'views',
                label: iawpText.views,
                data: views,
                backgroundColor: 'rgba(108,70,174,0.2)',
                borderColor: 'rgba(108,70,174,1)',
                borderWidth: {
                    bottom: 0,
                    top: 3,
                    left: 0,
                    right: 0
                }
            },]
        }

        const config = {
            type: 'bar',
            data,
            options: {
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                responsive: true,
                scales: {
                    y: {
                        border: {
                            color: '#DEDAE6',
                            dash: [2, 4]
                        },
                        grid: {
                            tickColor: '#DEDAE6',
                            display: true,
                            drawOnChartArea: true,
                        },
                        beginAtZero: true,
                        suggestedMax: 5,
                        ticks: {
                            color: isDarkMode() ? '#ffffff' : '#6D6A73',
                            precision: 0
                        }
                    },
                    x: {
                        stacked: true,
                        border: {
                            color: '#DEDAE6',
                        },
                        grid: {
                            tickColor: '#DEDAE6',
                            display: true,
                            drawOnChartArea: false,
                        },
                        ticks: {
                            color: isDarkMode() ? '#ffffff' : '#6D6A73',
                            beginAtZero: true,
                            callback: function (value, index, values) {
                                return labelsShort[index];
                            }
                        }
                    }
                },
                plugins: {
                    mode: String, // 'light' or 'dark'
                    htmlLegend: {
                        container: element.parentNode.querySelector('.legend')
                    },
                    legend: {
                        display: false
                    },
                    corsair: {
                        dash: [2, 4],
                        color: '#777',
                        width: 1
                    },
                },
            },
            plugins: [
                corsairPlugin,
                htmlLegendPlugin
            ],
        };

        new Chart(element, config);
    }

    updateChart({
                    element,
                    views
                }) {
        const chart = Chart.getChart(element);
        chart.data.datasets.forEach((dataset, index) => {
            dataset.data.splice(0, dataset.data.length, ...views)
        });
        chart.update('none');
    }
}
