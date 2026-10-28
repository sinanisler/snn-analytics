import {Controller} from "@hotwired/stimulus"

export default class extends Controller {

    changeGroup(e) {
        const group = e.target.value;

        document.dispatchEvent(
            new CustomEvent('iawp:changeGroup', {
                detail: {
                    group
                }
            })
        )
    }
}
