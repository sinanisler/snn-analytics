import {Controller} from "@hotwired/stimulus"

export default class extends Controller {
    modules = new Set()

    connect() {
        document.addEventListener('iawp:moduleEditingStarted', this.moduleEditingStarted)
        document.addEventListener('iawp:moduleEditingFinished', this.moduleEditingFinished)
    }

    disconnect() {
        document.removeEventListener('iawp:moduleEditingStarted', this.moduleEditingStarted)
        document.removeEventListener('iawp:moduleEditingFinished', this.moduleEditingFinished)
    }

    toggleReordering() {
        if(!this.isReorderingEnabled()) {
            return
        }

        // Refactor at some point...
        const $ = jQuery
        $(this.element).toggleClass('active')
        const isActive = $(this.element).hasClass('active')
        $('#iawp-dashboard').toggleClass('reordering', isActive)
        $('.iawp-module').toggleClass('draggable-module', isActive)
        if (isActive) {
            document.querySelector('.add-module-toolbar-button').setAttribute('disabled', 'disabled')
        } else {
            document.querySelector('.add-module-toolbar-button').removeAttribute('disabled')
        }
        window.scrollTo({ top: 0, behavior: "smooth" })
    }

    moduleEditingStarted = ({ detail: { moduleId }}) => {
        this.modules.add(moduleId)
        this.updateButton()
    }

    moduleEditingFinished = ({ detail: { moduleId }}) => {
        this.modules.delete(moduleId)
        this.updateButton()
    }

    updateButton() {
        if(this.isReorderingEnabled()) {
            this.element.removeAttribute('disabled')
        } else {
            this.element.setAttribute('disabled', 'disabled')
        }
    }

    isReorderingEnabled() {
        return this.modules.size === 0
    }
}