import {Controller} from "@hotwired/stimulus"

export default class extends Controller {
    connect() {

    }

    setChartInterval(e) {
        document.dispatchEvent(
            new CustomEvent('iawp:changeChartInterval', {
                detail: {
                    chartInterval: e.target.value
                }
            })
        )
    }
}