import {Controller} from "@hotwired/stimulus"
import * as easepick from "@easepick/bundle";

export default class extends Controller {
    static values = {
        unixTimestamp: {
            type: Number,
            default: Math.floor((new Date()).getTime() / 1000)
        }
    }
    connect() {
        this.element.easepick = new easepick.create({
            element: this.element,
            css: [
                this.element.dataset.css
            ],
            zIndex: 99,
            date: this.unixTimestampValue * 1000,
            format: this.element.dataset.format,
            autoApply: true,
            firstDay: parseInt(this.element.dataset.dow),
            setup: (picker) => {
                picker.on('select', (e) => {
                    this.unixTimestampValue = Math.floor(
                        e.detail.date.toJSDate().valueOf() / 1000
                    )
                })
            }
        });
    }
}
