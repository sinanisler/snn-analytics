import {Controller} from "@hotwired/stimulus"
import corsairPlugin from '../chart_plugins/corsair_plugin'
import {Chart, registerables} from 'chart.js'
import color from 'color'
import {isDarkMode} from "../utils/appearance";

Chart.register(...registerables);

export default class extends Controller {
    static targets = ['canvas', 'primaryMetricSelect', 'secondaryMetricSelect', 'adaptiveWidthSelect'];

    static values = {
        labels: Array,
        data: Object,
        locale: String,
        currency: {
            type: String,
            default: 'USD'
        },
        isPreview: {
            type: Boolean,
            default: false,
        },
        showLegend: {
            type: Boolean,
            default: false,
        },
        disableDarkMode: {
            type: Boolean,
            default: false,
        },
        primaryChartMetricId: String,
        primaryChartMetricName: String,
        secondaryChartMetricId: String,
        secondaryChartMetricName: String,
        hasMultipleDatasets: Number
    }

    metricGroups = [{
        metrics: ['views', 'visitors', 'sessions'],
        format: 'int'
    }, {
        metrics: ['clicks'],
        format: 'int'
    }, {
        metrics: ['average_session_duration'],
        format: 'time'
    }, {
        metrics: ['bounce_rate'],
        format: 'percent'
    }, {
        metrics: ['views_per_session'],
        format: 'float'
    }, {
        metrics: ['wc_orders', 'wc_refunds'],
        format: 'int'
    }, {
        metrics: ['wc_gross_sales', 'wc_refunded_amount', 'wc_net_sales'],
        format: 'whole_currency'
    }, {
        metrics: ['wc_conversion_rate'],
        format: 'percent'
    }, {
        metrics: ['wc_earnings_per_visitor'],
        format: 'currency'
    }, {
        metrics: ['wc_average_order_volume'],
        format: 'whole_currency'
    }, {
        metrics: ['form_submissions'],
        prefix_to_include: 'form_submissions_for_',
        format: 'int'
    }, {
        metrics: ['form_conversion_rate'],
        prefix_to_include: 'form_conversion_rate_for_',
        format: 'percent'
    }]


    connect() {
        if (!this.isPreviewValue) {
            this.updateMetricSelectWidth(this.primaryMetricSelectTarget)
            // Only show the secondary metric select if there is a second metric to select
            if (this.hasMultipleDatasetsValue === 1) {
                this.updateMetricSelectWidth(this.secondaryMetricSelectTarget)
            }
        }
        this.createChart()
        this.updateChart()
    }

    disconnect() {
        if(this.chart) {
            this.chart.destroy()
            this.chart = null
        }
    }

    getLocale() {
        // Validate the locale
        try {
            new Intl.NumberFormat(this.localeValue)

            return this.localeValue
        } catch (e) {
            return 'en-US'
        }
    }

    hasSecondaryMetric() {
        return this.hasSecondaryChartMetricIdValue && this.secondaryChartMetricIdValue && this.secondaryChartMetricIdValue !== 'no_comparison'
    }

    tooltipTitle(tooltip) {
        const label = JSON.parse(tooltip[0].label)

        return label.tooltipLabel
    }

    getGroupByMetricId(metricId) {
        return this.metricGroups.find(group => {
            return group.metrics.includes(metricId) || (group.prefix_to_include && metricId.startsWith(group.prefix_to_include))
        })
    }

    formatValueForMetric(metricId, value) {
        const group = this.getGroupByMetricId(metricId)

        switch (group.format) {
            case 'whole_currency':
                return new Intl.NumberFormat(this.localeValue, {
                    style: 'currency',
                    currency: this.currencyValue,
                    currencyDisplay: 'narrowSymbol',
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 0,
                }).format(value / 100);
            case 'currency':
                return new Intl.NumberFormat(this.localeValue, {
                    style: 'currency',
                    currency: this.currencyValue,
                    currencyDisplay: 'narrowSymbol',
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                }).format(value / 100);
            case 'percent':
                return new Intl.NumberFormat(this.localeValue, {
                    style: 'percent',
                    maximumFractionDigits: 2,
                }).format(value / 100);
            case 'time':
                const minutes = Math.floor(value / 60);
                const seconds = value % 60

                return minutes.toString().padStart(2, '0') + ':' + seconds.toString().padStart(2, '0');
            case 'int':
                return new Intl.NumberFormat(this.localeValue, {
                    maximumFractionDigits: 0
                }).format(value);
            case 'float':
                return new Intl.NumberFormat(this.localeValue, {
                    maximumFractionDigits: 2
                }).format(value);
            default:
                return value
        }
    }

    tooltipLabel = (tooltip) => {
        if (typeof tooltip.dataset.tooltipLabel === 'function') {
            return tooltip.dataset.tooltipLabel(tooltip)
        }

        return tooltip.dataset.label + ': ' + this.formatValueForMetric(tooltip.dataset.id, tooltip.raw)
    }

    tickText(value) {
        const label = JSON.parse(this.getLabelForValue(value))

        return label.tick
    }

    /**
     * This works because we have a separate hidden select with a single option. When we set the newly
     * selected option as it's only option, we can see exactly how long the select needs to be
     */
    updateMetricSelectWidth(element) {
        const option = element.options[element.selectedIndex]

        // The intersection observer was added to improve support for the examiner which operates
        // in an iFrame. Without this, the adaptiveWidthSelectTarget has a width of zero when
        // this code runs.

        const observer = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    this.adaptiveWidthSelectTarget[0].innerHTML = option.innerText
                    element.style.width = this.adaptiveWidthSelectTarget.getBoundingClientRect().width + 'px'
                    observer.disconnect()
                }
            });
        });

        observer.observe(this.adaptiveWidthSelectTarget);

        element.parentElement.classList.add('visible');
    }

    hasSharedAxis(metricId, otherMetricId) {
        const group = this.getGroupByMetricId(metricId)
        const otherGroup = this.getGroupByMetricId(otherMetricId)

        return JSON.stringify(group) === JSON.stringify(otherGroup)
    }


    changePrimaryMetric(e) {
        const element = e.target
        this.primaryChartMetricIdValue = element.value
        this.primaryChartMetricNameValue = element.options[element.selectedIndex].innerText
        this.updateMetricSelectWidth(element)
        this.updateChart()

        Array.from(this.secondaryMetricSelectTarget.querySelectorAll('option')).forEach((option) => {
            option.toggleAttribute('disabled', option.value === element.value)
        })

        document.dispatchEvent(
            new CustomEvent('iawp:changePrimaryChartMetric', {
                detail: {
                    primaryChartMetricId: element.value
                }
            })
        )
    }

    changeSecondaryMetric(e) {
        const element = e.target
        const hasSelectedSecondaryMetric = element.value !== ''

        if (hasSelectedSecondaryMetric) {
            this.secondaryChartMetricIdValue = element.value
            this.secondaryChartMetricNameValue = element.options[element.selectedIndex].innerText
        } else {
            this.secondaryChartMetricIdValue = ''
            this.secondaryChartMetricNameValue = ''
        }

        this.updateMetricSelectWidth(element)
        this.updateChart()

        Array.from(this.primaryMetricSelectTarget.querySelectorAll('option')).forEach((option) => {
            option.toggleAttribute('disabled', option.value === element.value)
        })

        document.dispatchEvent(
            new CustomEvent('iawp:changeSecondaryChartMetric', {
                detail: {
                    secondaryChartMetricId: hasSelectedSecondaryMetric ? element.value : null
                }
            })
        )
    }

    updateChart() {
        const primaryDataset = this.chart.data.datasets[0]

        primaryDataset.id = this.primaryChartMetricIdValue
        primaryDataset.data = this.dataValue[this.primaryChartMetricIdValue]
        primaryDataset.label = this.primaryChartMetricNameValue

        const isEmptyPrimaryDataset = primaryDataset.data.every((value) => value === 0)
        this.chart.options.scales['y'].suggestedMax = isEmptyPrimaryDataset ? 10 : null
        this.chart.options.scales['y'].beginAtZero = primaryDataset.id !== 'bounce_rate'

        // Always start by removing the secondary dataset
        if (this.chart.data.datasets.length > 1) {
            this.chart.data.datasets.pop()
        }

        if (this.hasSecondaryMetric()) {
            const id = this.secondaryChartMetricIdValue
            const name = this.secondaryChartMetricNameValue
            const data = this.dataValue[id]
            const axisId = this.hasSharedAxis(this.primaryChartMetricIdValue, id) ? 'y' : 'defaultRight'

            this.chart.data.datasets.push(
                this.makeDataset(id, name, data, axisId, 'rgba(246,157,10)')
            )

            const isEmptySecondaryDataset = data.every((value) => value === 0)
            this.chart.options.scales['defaultRight'].suggestedMax = isEmptySecondaryDataset ? 10 : null
            this.chart.options.scales['defaultRight'].beginAtZero = id !== 'bounce_rate'
        }

        this.chart.update();
    }

    makeDataset(id, name, data, axisId, colorValue, isPrimary = false) {
        const accentColor = color(colorValue)

        return {
            id: id,
            label: name,
            data: data,
            borderColor: accentColor.string(),
            backgroundColor: accentColor.alpha(.1).string(),
            pointBackgroundColor: accentColor.string(),
            tension: 0.4,
            yAxisID: axisId,
            fill: true,
            order: isPrimary ? 1 : 0, // Stack orange on top of purple
        }
    }

    shouldUseDarkMode() {
        return isDarkMode() && !this.disableDarkModeValue
    }

    createChart() {
        Chart.defaults.font.family = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol"';
        const labels = this.labelsValue

        const primaryMetricDataset = this.makeDataset(
            this.primaryChartMetricIdValue,
            this.primaryChartMetricNameValue,
            this.dataValue[this.primaryChartMetricIdValue],
            'y',
            'rgba(108,70,174)',
            true
        )

        const data = {
            labels: labels,
            datasets: ([
                primaryMetricDataset
            ]).filter((dataset) => dataset !== null)
        };

        const options = {
            locale: this.getLocale(),
            maintainAspectRatio: this.isPreviewValue ? false : true,
            aspectRatio: 3,
            onResize(chart, { width }) {
                if(document.documentElement.clientWidth <= 782 && chart.options.aspectRatio === 3) {
                    chart.options.aspectRatio = 1.5
                    chart.update()
                } else if(document.documentElement.clientWidth > 782 && chart.options.aspectRatio === 1.5) {
                    chart.options.aspectRatio = 3
                    chart.update()
                }
            },
            interaction: {
                intersect: false,
                mode: 'index'
            },
            scales: {
                y: {
                    border: {
                        color: '#DEDAE6',
                        dash: [2, 4]
                    },
                    grid: {
                        color: this.shouldUseDarkMode() ? '#676173' : '#DEDAE6',
                        tickColor: '#DEDAE6',
                        display: true,
                        drawOnChartArea: true,
                    },
                    beginAtZero: true,
                    suggestedMax: null,
                    ticks: {
                        color: this.shouldUseDarkMode() ? '#ffffff' : '#6D6A73',
                        font: {
                            size: 14,
                            weight: 400,
                        },
                        precision: 0,
                        callback: (value, index, values) => {
                            return this.formatValueForMetric(this.primaryChartMetricIdValue, value)
                        },
                    },
                },
                defaultRight: {
                    position: 'right',
                    display: 'auto',
                    border: {
                        color: '#DEDAE6',
                        dash: [2, 4]
                    },
                    grid: {
                        color: this.shouldUseDarkMode() ? '#9a95a6' : '#DEDAE6',
                        tickColor: '#DEDAE6',
                        display: true,
                        drawOnChartArea: false,
                    },
                    beginAtZero: true,
                    suggestedMax: null,
                    ticks: {
                        color: this.shouldUseDarkMode() ? '#ffffff' : '#6D6A73',
                        font: {
                            size: 14,
                            weight: 400,
                        },
                        precision: 0,
                        callback: (value, index, values) => {
                            if (this.hasSecondaryMetric()) {
                                return this.formatValueForMetric(this.secondaryChartMetricIdValue, value)
                            } else {
                                return value
                            }
                        },
                    },
                },
                x: {
                    border: {
                        color: '#DEDAE6',
                    },
                    grid: {
                        tickColor: '#DEDAE6',
                        display: true,
                        drawOnChartArea: false,
                    },
                    ticks: {
                        color: this.shouldUseDarkMode() ? '#ffffff' : '#6D6A73',
                        autoSkip: true,
                        autoSkipPadding: 16,
                        maxRotation: 0,
                        // maxTicksLimit: 20,
                        font: {
                            size: 14,
                            weight: 400
                        },
                        callback: this.tickText
                    },
                },
            },
            plugins: {
                mode: String, // 'light' or 'dark'
                legend: {
                    display: this.showLegendValue,
                    align: 'start',
                    labels: {
                        boxHeight: 14,
                        boxWidth: 14,
                        useBorderRadius: true,
                        borderRadius: 7,
                        padding: 8,
                        color: this.shouldUseDarkMode() ? '#DEDAE6' : '#676173',
                        generateLabels: (chart) => {
                            const original = Chart.defaults.plugins.legend.labels.generateLabels(chart);
                            return original.map(label => ({
                                ...label,
                                fillStyle: label.strokeStyle,
                                lineWidth: 0,
                            }));
                        }
                    }
                },
                corsair: {
                    dash: [2, 4],
                    color: '#777',
                    width: 1
                },
                tooltip: {
                    itemSort: (a, b) => {
                        return a.datasetIndex < b.datasetIndex ? -1 : 1
                    },
                    callbacks: {
                        title: this.tooltipTitle,
                        label: this.tooltipLabel,
                    }
                }
            },
            elements: {
                point: {
                    radius: 4
                }
            }
        }

        const config = {
            type: 'line',
            data: data,
            options: options,
            plugins: [
                corsairPlugin,
                {
                  beforeInit(chart) {
                    const originalFit = chart.legend.fit;

                    chart.legend.fit = function () {
                      // Call the original function and bind scope in order to use `this` correctly inside it
                      originalFit.bind(chart.legend)();

                      this.height += 16;
                    }
                  }
                }
            ],
        };

        if(!this.chart) {
            this.chart = new Chart(this.canvasTarget, config);
        }
    }
}
