import {Controller} from "@hotwired/stimulus"
import Sortable from 'sortablejs'
import {StickySidebar} from "../../modules/sticky-sidebar"

export default class extends Controller {
    static targets = ['list']

    connect() {
        const modulesSortable = new Sortable(this.listTarget, {
            animation: 400,
            ghostClass: 'iawp-sortable-ghost',
            handle: '.draggable-module', // ANDREW: you could use .module and just toggle "disabled" on/off instead. I don't have access to modulesSortable in my JS file
            delay: 2000,
            delayOnTouchOnly: true,
            onUpdate: (event) => {
                this.unwrapSideBySideModules()
                this.saveModuleOrder(modulesSortable, event)
            },
            onStart: (event) => {
                document.getElementById('module-list').classList.add('dragging-active')

                if(this.isFullWidthModule(event.item)) {
                    this.wrapSideBySideModules()
                }
            },
            onEnd: (event) => {
                document.getElementById('module-list').classList.remove('dragging-active')
                this.unwrapSideBySideModules()
            }
        })
    }

    wrapSideBySideModules() {
        if (window.innerWidth < 900
            || (window.innerWidth >= 1000 && window.innerWidth < 1200 && !document.getElementById('iawp-layout').classList.contains('collapsed'))
        ) {
            return;
        }
        const modules = Array.from(this.listTarget.querySelectorAll('.iawp-module:not(.module-picker)'))
        const pairsToWrap = []
        let columnsRemaining = 2

        modules.forEach((current, index) => {
            const next = current.nextElementSibling
            const isLast = !next || next.matches('.iawp-module.module-picker')

            if (this.isFullWidthModule(current)) {
                columnsRemaining = 2 // Next module will always be on the next row
                return
            }

            columnsRemaining--;

            if(columnsRemaining === 1 && !isLast && !this.isFullWidthModule(next)) {
                pairsToWrap.push(current)
            }

            if(columnsRemaining === 0) {
                columnsRemaining = 2
            }
        })

        pairsToWrap.forEach(module => {
            const nextSibling = module.nextElementSibling

            const wrapper = document.createElement('div')
            wrapper.classList.add('iawp-module-reorder-wrapper')

            module.parentNode.insertBefore(wrapper, module)
            wrapper.appendChild(module)
            wrapper.appendChild(nextSibling)
        })
    }

    unwrapSideBySideModules() {
        const wrappers = this.listTarget.querySelectorAll('.iawp-module-reorder-wrapper')

        wrappers.forEach(wrapper => {
            const parent = wrapper.parentNode
            const fragment = document.createDocumentFragment()

            // Move children into a fragment to avoid reflow during insert
            while (wrapper.firstChild) {
                fragment.appendChild(wrapper.firstChild)
            }

            parent.replaceChild(fragment, wrapper)
        })
    }

    isFullWidthModule(element) {
        return element.classList.contains('full-width')
    }

    saveModuleOrder(sortable, event) {
        const elements = Array.from(event.target.querySelectorAll('.iawp-module'))
        const ids = elements.map((element) => {
            return this.application.getControllerForElementAndIdentifier(element, 'module')
        }).filter((controller) => {
            return controller !== null
        }).map((controller) => {
            return controller.moduleIdValue
        })

        const data = {
            ...iawpActions.reorder_modules,
            module_ids: ids,
        }

        jQuery.post(ajaxurl, data, (response) => {
            // Do nothing
        }).fail((error) => {
            sortable.sort(
                this.moveArrayItem(sortable.toArray(), event.newIndex, event.oldIndex)
            )
        })
    }

    moveArrayItem(array, fromIndex, toIndex) {
        const newArray = [...array]

        if (fromIndex === toIndex) {
            return newArray
        }

        const itemToMove = newArray.splice(fromIndex, 1)[0]
        newArray.splice(toIndex, 0, itemToMove)

        return newArray
    }
}