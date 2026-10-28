import {Controller} from "@hotwired/stimulus"

export default class extends Controller {
    static values = {
        loadingText: String
    }

    refresh() {
        const data = {
            ...iawpActions.refresh_modules
        }

        const originalText = this.element.innerText
        this.element.innerText = this.loadingTextValue
        this.element.setAttribute('disabled', 'disabled')
        this.fadeOutModules()

        jQuery.post(ajaxurl, data, (response) => {
            this.element.innerText = originalText
            this.element.removeAttribute('disabled')
            response.data.modules.forEach(module => this.replaceModule(module.id, module.html))
            document.getElementById('iawp-modules-refreshed-at').innerText = response.data.modulesRefreshedAt
        }).fail((error) => {
            this.element.innerText = originalText
            this.element.removeAttribute('disabled')
        })
    }

    fadeOutModules() {
        const elements = Array.from(document.querySelectorAll('.module:not(.module-picker)'))

        elements.forEach((element) => {
            element.classList.add('will-be-refreshed')
            // element.querySelector('[popover]').hidePopover()
            // element.querySelector('[popovertarget]').setAttribute('disabled', 'disabled')
        })
    }

    replaceModule(moduleId, html) {
        const elementToReplace = document.querySelector(`[data-module-module-id-value="${moduleId}"]`)

        if(!elementToReplace) {
            return
        }

        const isDraggable = elementToReplace.classList.contains('draggable-module')
        elementToReplace.outerHTML = html

        // Update the new module with whatever draggable state the replaced module had
        setTimeout(() => {
            const module = document.querySelector(`[data-module-module-id-value="${moduleId}"]`)
            if(module) {
                module.classList.toggle('draggable-module', isDraggable)
            }
        }, 0)
    }
}