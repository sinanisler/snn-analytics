module.exports = {
    id: 'htmlLegend',
    getLegendContainer(options) {
        if (options.container instanceof HTMLElement) {
            return options.container
        } else {
            return document.getElementById(options.containerID);
        }
    },
    afterUpdate(chart, args, options) {
        const legendContainer = this.getLegendContainer(options)
        let legendList = legendContainer.querySelector('ul');

        // Create a list as needed
        if (!legendList) {
            legendList = document.createElement('ul');
            legendList.classList.add('legend-list');
            legendContainer.appendChild(legendList);
        }

        // Remove old legend items
        while (legendList.firstChild) {
            legendList.firstChild.remove();
        }

        // Reuse the built-in legendItems generator
        const items = chart.options.plugins.legend.labels.generateLabels(chart);

        items.forEach((legendData) => {
            const id = chart.data.datasets.find((dataset) => dataset.label === legendData.text).id
            const li = document.createElement('li');

            li.onclick = () => {
                const {type} = chart.config;
                if (type === 'pie' || type === 'doughnut') {
                    // Pie and doughnut charts only have a single dataset and visibility is per item
                    chart.toggleDataVisibility(legendData.index);
                } else {
                    chart.setDatasetVisibility(legendData.datasetIndex, !chart.isDatasetVisible(legendData.datasetIndex));
                }
                chart.update();
                if (typeof options.callback === 'function') {
                    const visibleDatasets = chart.data.datasets.filter((dataset, index) => {
                        return chart.isDatasetVisible(index);
                    }).map((dataset) => {
                        return dataset.id
                    })
                    options.callback(visibleDatasets)
                }
            };

            li.classList.add('legend-item', `legend-item-for-${id}`)

            if (legendData.hidden) {
                li.classList.add('hidden')
            }

            // Color box
            const boxSpan = document.createElement('span');

            // Text
            const textContainer = document.createElement('p');
            textContainer.textContent = legendData.text

            li.appendChild(boxSpan);
            li.appendChild(textContainer);
            legendList.appendChild(li);
        });
    }
};