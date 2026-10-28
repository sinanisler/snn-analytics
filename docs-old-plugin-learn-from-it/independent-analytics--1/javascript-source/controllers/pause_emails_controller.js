import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    connect() {}

    pause() {
        this.setStatus(true);
    }

    resume() {
        this.setStatus(false);
    }

    setStatus(paused) {
        const data = {
            ...iawpActions.pause_email_reports,
            paused,
        };

        this.element.disabled = true;

        jQuery
            .post(ajaxurl, data, (response) => {
                window.location.reload();
            })
            .fail(() => {
                window.location.reload();
            });
    }
}
