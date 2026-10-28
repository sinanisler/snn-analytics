import {Controller} from "@hotwired/stimulus"

export default class extends Controller {
    hasSetError = false

    connect() {
        this.interval = setInterval(() => {
            this.check();
        }, 5000)

        this.check()
    }

    /**
     * Check if a migration is running and reload the page if it's done
     */
    check() {
        const data = {
            ...iawpActions.migration_status
        };

        jQuery.post(ajaxurl, data, (response) => {
            if (response.data && response.data.isMigrating === false) {
                clearInterval(this.interval)
                document.location.reload();
            } else if (response.data && response.data.errorHtml && !this.hasSetError) {
                document.getElementById('iawp-migration-error').innerHTML = response.data.errorHtml
                document.getElementById('iawp-update-running').innerHTML = ''
                this.hasSetError = true
            }
        });
    }
}