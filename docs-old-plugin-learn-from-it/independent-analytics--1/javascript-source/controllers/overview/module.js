import {Controller} from "@hotwired/stimulus"
import {getModuleMarkup} from "../../modules/module-markup";

export default class extends Controller {
    static targets = []
    static values = {
        moduleId: String,
        hasDataset: Boolean
    }

    connect() {
        if(!this.hasDatasetValue) {
            getModuleMarkup(this.moduleIdValue)
            document.addEventListener('iawp:module-markup:' + this.moduleIdValue, this.handleMarkup)
        }
    }

    disconnect() {
        document.removeEventListener('iawp:module-markup:' + this.moduleIdValue, this.handleMarkup)
    }

    delete(event) {
        const button = event.currentTarget
        const module = button.closest('.iawp-module')
        const data = {
            ...iawpActions.delete_module,
            module_id: this.moduleIdValue
        }

        button.disabled = true
        button.classList.add('sending')
        module.classList.add('will-be-refreshed')

        jQuery.post(ajaxurl, data, (response) => {
            this.element.remove()
            button.disabled = false
        }).fail((error) => {
            button.classList.remove('sending')
            button.disabled = false
        })
    }

    edit(event) {
        const button = event.currentTarget
        const data = {
            ...iawpActions.get_markup_for_module,
            id: this.moduleIdValue
        }


        this.signalEditingStarted()
        button.classList.add('sending')
        button.disabled = true

        jQuery.post(ajaxurl, data, (response) => {
            this.element.outerHTML = response.data.editor_html
            button.classList.remove('sending')
            button.disabled = false
        }).fail((error) => {
            this.signalEditingFinished()
            button.classList.remove('sending')
            button.disabled = false
        })
    }

    toggleWidth(event) {
        const button = event.currentTarget
        const module = button.closest('.iawp-module')
        const shouldBeFullWidth = !module.classList.contains('full-width')

        const data = {
            ...iawpActions.edit_module,
            module_id: this.moduleIdValue,
            fields: {
                is_full_width: shouldBeFullWidth
            }
        }

        module.classList.toggle('full-width', shouldBeFullWidth)

        if ((module.querySelector('.recent-views') || module.querySelector('.recent-conversions')) && module.querySelector('.module-pagination')) {
            module.querySelector(".current-page").textContent = "1"
            module.querySelector(".current").classList.remove('current')
            module.querySelector(".module-page").classList.add('current')
            module.querySelector(".pagination-button.left").disabled = true
            module.querySelector(".pagination-button.right").disabled = false
        }
        jQuery.post(ajaxurl, data, (response) => {
            //
        }).fail((error) => {
            // module.classList.toggle('full-width', !shouldBeFullWidth)
        })
    }

    handleMarkup = (event) => {
        const html = event.detail.moduleHtml
        const isDraggable = this.element.classList.contains('draggable-module')
        document.removeEventListener('iawp:module-markup:' + this.moduleIdValue, this.handleMarkup)
        this.element.outerHTML = html

        // Update the new module with whatever draggable state the replaced module had
        setTimeout(() => {
            const module = document.querySelector(`[data-module-module-id-value="${event.detail.id}"]`)
            if(module) {
                module.classList.toggle('draggable-module', isDraggable)
            }
        }, 0)
    }


    signalEditingStarted() {
        document.dispatchEvent(
            new CustomEvent('iawp:moduleEditingStarted', {
                detail: {
                    moduleId: this.moduleIdValue
                }
            })
        )
    }

    signalEditingFinished() {
        document.dispatchEvent(
            new CustomEvent('iawp:moduleEditingFinished', {
                detail: {
                    moduleId: this.moduleIdValue
                }
            })
        )
    }


}