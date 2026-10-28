import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["journey"];
    static values = {
        sessionId: Number,
    };

    hasTimelineHtml = false;
    isFetching = false;
    scrollOnLoad = false;

    connect() {
        this.initializeHighlighting();
    }

    toggleTimeline() {
        const isVisible = this.journeyTarget.classList.contains("visible");

        if (isVisible) {
            this.hideTimeline();
        } else {
            this.showTimeline();
        }
    }

    showTimeline() {
        this.fetchTimelineHtml();
        this.element.classList.add("timeline-visible");
        this.journeyTarget.classList.add("visible");
        // requestAnimationFrame(() => {
        //     this.element.scrollIntoView(false)
        // })
    }

    hideTimeline() {
        this.element.classList.remove("timeline-visible");
        this.journeyTarget.classList.remove("visible");
    }

    fetchTimelineHtml() {
        if (this.hasTimelineHtml || this.isFetching) {
            return;
        }

        const data = {
            ...iawpActions.get_journey_timeline,
            session_id: this.sessionIdValue,
        };

        this.isFetching = true;
        this.element.classList.add("loading-timeline");

        jQuery
            .post(ajaxurl, data, (response) => {
                this.isFetching = false;
                this.element.classList.remove("loading-timeline");
                this.handleFetchSuccess(response);
                if (this.scrollOnLoad) {
                    this.scrollOnLoad = false;
                    requestAnimationFrame(() => {
                        this.element.scrollIntoView(false);
                    });
                }
            })
            .fail(() => {
                this.isFetching = false;
                this.element.classList.remove("loading-timeline");
                this.handleFetchFailure();
            });
    }

    handleFetchSuccess(response) {
        const timelineDocument = new DOMParser().parseFromString(response.data.html, "text/html");
        const timelineElement = timelineDocument.body.firstElementChild;

        this.hasTimelineHtml = true;
        this.journeyTarget.replaceChildren(timelineElement);
    }

    handleFetchFailure() {
        // ...
    }

    initializeHighlighting() {
        const match = this.element.closest(`[data-session-to-highlight="${this.sessionIdValue}"]`);

        if (!match) {
            return;
        }

        this.scrollOnLoad = true;
        this.showTimeline();

        requestAnimationFrame(() => {
            this.element.scrollIntoView(false);
        });
    }
}
