import {Controller} from "@hotwired/stimulus"

export default class extends Controller {
    static values = {
        type: String,
    }

    isLoading = false

    create() {
        if(this.isLoading) {
            return;
        }

        this.element.querySelector('span').classList.remove('dashicons-plus-alt2')
        this.element.querySelector('span').classList.add('dashicons-update', 'iawp-spin')
        this.isLoading = true
        const data = {
            ...iawpActions.create_report,
            type: this.typeValue
        };

        jQuery.post(ajaxurl, data, (response) => {
            window.location = response.data.url
            this.isLoading = false
        }).fail(() => {
            this.isLoading = false
        })
    }
}