import {Controller} from "@hotwired/stimulus"

export default class extends Controller {
    static targets = ['form', 'submitButton', 'newCampaign']

    submit(e) {
        e.preventDefault();

        const data = {
            ...iawpActions.create_campaign,
            'path': this.formTarget.elements['path'].value,
            'utm_source': this.formTarget.elements['utm_source'].value,
            'utm_medium': this.formTarget.elements['utm_medium'].value,
            'utm_campaign': this.formTarget.elements['utm_campaign'].value,
            'utm_term': this.formTarget.elements['utm_term'].value,
            'utm_content': this.formTarget.elements['utm_content'].value,
        };
        this.submitButtonTarget.setAttribute('disabled', 'disabled')
        this.submitButtonTarget.classList.add('sending');
        jQuery.post(ajaxurl, data, (response) => {
            this.submitButtonTarget.removeAttribute('disabled');
            this.submitButtonTarget.classList.remove('sending');
            this.element.outerHTML = response.data.html
        });
    }

    reuse(e) {
        try {
            this.newCampaignTarget.remove()
        } catch (e) {
            // Do nothing if the element isn't there
        }
        const data = JSON.parse(e.target.dataset.result)

        Object.keys(this.formTarget.elements).forEach((elementName) => {
            // Remove the error class from the input
            this.formTarget.elements[elementName].classList.remove('error')
            // Remove any sibling with error class
            this.formTarget.elements[elementName].parentElement.querySelectorAll('p.error').forEach((element) => {
                element.remove()
            })
        })

        // Iterate over all fields in object and populate matching input
        Object.keys(data).forEach((key) => {
            if (this.formTarget.elements[key]) {
                this.formTarget.elements[key].value = data[key]
            }
        })
        this.formTarget.parentElement.scrollIntoView({behavior: 'smooth'})
    }

    delete(e) {
        e.preventDefault();

        const data = {
            ...iawpActions.delete_campaign,
            'campaign_url_id': e.target.dataset.campaignUrlId
        };

        e.target.setAttribute('disabled', 'disabled')
        e.target.classList.add('sending')

        jQuery.post(ajaxurl, data, (response) => {
            e.target.removeAttribute('disabled');
            e.target.classList.add('sent')
            e.target.classList.remove('sending')

            setTimeout(() => {
                e.target.closest('.campaign').classList.add('removing')
            }, 500)

            setTimeout(() => {
                e.target.closest('.campaign').remove()
            }, 1000)
        });
    }
}