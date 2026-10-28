import {Controller} from "@hotwired/stimulus"
import Sortable from 'sortablejs'

export default class extends Controller {
    static values = {
        type: String
    }

    connect() {
        this.sortable = new Sortable(this.element, {
            animation: 150,
            ghostClass: 'iawp-sortable-ghost',
            delay: 2000,
            delayOnTouchOnly: true,
            onUpdate: (event) => this.updateOrder(event)
        });
    }

    updateOrder(event) {
        const elements = Array.from(this.element.querySelectorAll('li'));
        const ids = elements.map((element) => parseInt(element.dataset.reportId))
        const data = {
            ...iawpActions.sort_reports,
            type: this.typeValue,
            ids
        };

        jQuery.post(ajaxurl, data, (response) => {
            // Nothing to do
        }).fail(() => {
            this.sortable.sort(
                this.moveArrayItem(this.sortable.toArray(), event.newIndex, event.oldIndex)
            )
        })
    }

    moveArrayItem(array, fromIndex, toIndex) {
        const newArray = [...array]

        if (fromIndex === toIndex) {
            return newArray
        }

        const itemToMove = newArray.splice(fromIndex, 1)[0]
        newArray.splice(toIndex, 0, itemToMove)

        return newArray
    }
}