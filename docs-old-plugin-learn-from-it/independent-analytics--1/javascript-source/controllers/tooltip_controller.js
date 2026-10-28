import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static values = { text: String };

    popover = null;
    hideTimeout = null;

    connect() {
        this.popover = this.getPopoverElement();
        this.element.after(this.popover);

        this.element.addEventListener("focus", this.showTooltip.bind(this));
        this.element.addEventListener("blur", this.hideTooltip.bind(this));

        this.element.addEventListener("mouseenter", this.showTooltip.bind(this));
        this.element.addEventListener("mouseleave", this.hideTooltip.bind(this));

        this.popover.addEventListener("mouseenter", this.enterTooltip.bind(this));
        this.popover.addEventListener("mouseleave", this.hideTooltip.bind(this));

        document.addEventListener("iawp:closeAllTooltips", this.hideTooltipNow.bind(this));
    }

    disconnect() {
        this.element.removeEventListener("focus", this.showTooltip.bind(this));
        this.element.removeEventListener("blur", this.hideTooltip.bind(this));

        this.element.removeEventListener("mouseenter", this.showTooltip.bind(this));
        this.element.removeEventListener("mouseleave", this.hideTooltip.bind(this));

        this.popover.removeEventListener("mouseenter", this.enterTooltip.bind(this));
        this.popover.removeEventListener("mouseleave", this.hideTooltip.bind(this));

        document.removeEventListener("iawp:closeAllTooltips", this.hideTooltipNow.bind(this));
    }

    showTooltip() {
        clearTimeout(this.hideTimeout);

        document.dispatchEvent(new CustomEvent("iawp:closeAllTooltips"));

        this.popover.showPopover();

        const rect = this.element.getBoundingClientRect();
        const top = rect.top + window.scrollY - this.popover.offsetHeight + 2;
        const left = rect.left + window.scrollX + rect.width / 2 - this.popover.offsetWidth / 2;

        this.popover.style.position = "absolute";
        this.popover.style.margin = "0";
        this.popover.style.inset = `${top}px auto auto ${left}px`;
    }

    hideTooltip() {
        this.hideTimeout = setTimeout(() => {
            this.popover.hidePopover();
        }, 50);
    }

    hideTooltipNow() {
        this.popover.hidePopover();
    }

    enterTooltip() {
        clearTimeout(this.hideTimeout);
    }

    getPopoverElement() {
        const element = document.createElement("div");
        element.popover = 'manual'
        element.classList.add("iawp-tooltip-popover");

        const tooltip = document.createElement("div");
        tooltip.classList.add("iawp-tooltip");

        const text = document.createElement("div");
        text.classList.add("iawp-tooltip-text");
        text.textContent = this.textValue;
        tooltip.appendChild(text);

        const arrow = document.createElement("div");
        arrow.classList.add("iawp-tooltip-arrow");
        tooltip.appendChild(arrow);

        element.appendChild(tooltip)

        return element
    }
}
