import {Controller} from "@hotwired/stimulus"

export default class extends Controller {
    static values = {
        moduleToSwap: String
    }
    
    showIntro() {
        this.element.classList.add('show-intro')
        this.element.classList.remove('show-list')
        if (this.element.classList.contains('top'))
            this.element.classList.remove('visible')
    }

    cancel() {
        if(this.isSwapping()) {
            this.element.previousSibling.classList.remove('hidden')
            this.element.remove()
        } else {
            this.showIntro()
        }
    }

    showList() {
        this.element.classList.remove('show-intro')
        this.element.classList.add('show-list')
    }

    /**
     * Scroll to the module picker and call it out
     */
    scrollToPicker() {
        this.showList()

        // Let browser calculate new height for the module picker before scrolling to it
        requestAnimationFrame(() => {
            const reportHeader = document.getElementById('report-header-container')
            const modulePicker = this.element
            const adminHeaderHeight = 32
            const offset = 24
            const top = document.documentElement.scrollTop  + modulePicker.getBoundingClientRect().top - reportHeader.getBoundingClientRect().height - adminHeaderHeight - offset
            window.scrollTo({ top, behavior: "smooth" });
        })
    }

    showModule(event) {
        const moduleId = event.currentTarget.dataset.moduleId
        const templateString = document.getElementById(moduleId + '-module-template').innerHTML
        const templateDocument= new DOMParser().parseFromString(templateString, "text/html");
        const moduleEditor = templateDocument.body.firstElementChild

        moduleEditor.querySelector('button.change-module-type').remove()
        moduleEditor.setAttribute('data-module-editor-module-to-swap-value', this.moduleToSwapValue)

        this.element.replaceWith(moduleEditor)
    }

    isSwapping() {
        return !!this.moduleToSwapValue
    }
}