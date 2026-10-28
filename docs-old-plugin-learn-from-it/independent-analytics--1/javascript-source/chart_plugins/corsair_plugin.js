module.exports = {
    id: 'corsair',
    beforeInit: (chart, _, opts) => {
        if (opts.disabled) {
            return;
        }
        chart.corsair = {
            x: 0,
            y: 0
        }
    },
    afterEvent: (chart, evt, opts) => {
        if (opts.disabled) {
            return;
        }
        const {
            chartArea: {
                top,
                bottom,
                left,
                right
            }
        } = chart;
        const {
            event: {
                x,
                y
            }
        } = evt;
        if (x < left || x > right || y < top || y > bottom) {
            chart.corsair = {
                x,
                y,
                draw: false
            }
            chart.draw();
            return;
        }

        chart.corsair = {
            x,
            y,
            draw: true
        }

        chart.draw();
    },
    afterDatasetsDraw: (chart, _, opts) => {
        if (opts.disabled) {
            return;
        }
        const {
            ctx,
            chartArea: {
                top,
                bottom,
                left,
                right
            }
        } = chart;
        let {
            x,
            y,
            draw
        } = chart.corsair;

        if (!draw) {
            return;
        }

        x = chart.tooltip.caretX;

        ctx.lineWidth = opts.width || 0;
        // // Todo - Why does dash fuck up dots?
        ctx.setLineDash(opts.dash || []);
        ctx.strokeStyle = opts.color || 'black'

        ctx.save();
        ctx.beginPath();
        ctx.moveTo(x, bottom);
        ctx.lineTo(x, top);
        // Uncomment these 2 lines to add horizontal line
        // ctx.moveTo(left, y);
        // ctx.lineTo(right, y);
        ctx.stroke();
        ctx.restore();
        ctx.setLineDash([]);
    }
}