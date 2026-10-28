import {Controller} from "@hotwired/stimulus"

export default class extends Controller {
    static targets = ['saveButton', 'cancelButton']

    static values = {
        reports: Array,
        moduleId: String,
        moduleToSwap: String
    }

    cachedModuleMarkup = null

    connect() {
        // React to changes in the selected report
        if (this.element.querySelector('#report')) {
            this.element.querySelector('#report').addEventListener('change', (event) => this.updateReport(event))
        }

        // Autofocus on the correct input. Since autofocus only works on page load, this needs to be
        // done manually when editing an existing module.
        this.element.querySelector('[autofocus]')?.focus()

        // Cancel editing if escape is pressed
        document.addEventListener('keydown', this.onKeydown)

        if (this.moduleIdValue) {
            this.cacheModuleMarkup()
        }

        this.signalEditingStarted()
    }

    disconnect() {
        document.removeEventListener('keydown', this.onKeydown)
        this.signalEditingFinished()
    }

    isEditingExistingModule() {
        return !!this.moduleIdValue
    }

    cancel() {
        if(this.cancelButtonTarget) {
            this.cancelButtonTarget.disabled = true
        }

        if (this.isSwapping()) {
            this.element.previousSibling.classList.remove('hidden')
            this.element.remove()
            return
        }

        if (!this.isEditingExistingModule()) {
            this.signalEditingFinished()
            this.element.outerHTML = document.getElementById('module-picker-template').innerHTML
        } else if (this.cachedModuleMarkup) {
            this.signalEditingFinished()
            this.element.outerHTML = this.cachedModuleMarkup
        } else {
            this.cancelEditingExistingModule()
        }
    }

    changeModuleType() {
        const templateString = document.getElementById('module-picker-template').innerHTML
        const templateDocument = new DOMParser().parseFromString(templateString, "text/html");
        const modulePicker = templateDocument.body.firstElementChild

        modulePicker.classList.remove('show-intro')
        modulePicker.classList.add('show-list')
        modulePicker.setAttribute('data-module-picker-module-to-swap-value', this.moduleIdValue)

        this.element.classList.add('hidden')
        this.element.insertAdjacentElement('afterend', modulePicker)
    }

    cacheModuleMarkup() {
        const data = {
            ...iawpActions.get_markup_for_module,
            id: this.moduleIdValue
        }

        jQuery.post(ajaxurl, data, (response) => {
            this.cachedModuleMarkup = response.data.module_html
        })
    }

    cancelEditingExistingModule() {
        const data = {
            ...iawpActions.get_markup_for_module,
            id: this.moduleIdValue
        }

        jQuery.post(ajaxurl, data, (response) => {
            this.signalEditingFinished()
            this.element.outerHTML = response.data.module_html
        }).fail((error) => {
            //
        })
    }

    onKeydown = (event) => {
        // Ignore non-escape keys
        if (event.key !== "Escape") {
            return;
        }

        // Cancel if the active element is in the editor
        if (this.element.contains(document.activeElement)) {
            this.cancel()
        }
    }

    updateReport(event) {
        const newReportId = event.target.value
        const report = this.reportsValue.find((report) => report.id === newReportId || report.id === parseInt(newReportId))

        if (report) {
            this.updateGroupSelect(report.columns, this.element.querySelector('#sort_by'))
            this.updateGroupSelect(report.aggregatable_columns, this.element.querySelector('#aggregatable_sort_by'))
            this.updateGroupSelect(report.statistics, this.element.querySelector('#primary_metric'))
            this.updateGroupSelect(report.statistics, this.element.querySelector('#secondary_metric'), true)
            this.updateGroupCheckboxes(report.statistics, this.element.querySelector('#statistics'))
        }
    }

    save(event) {
        event.preventDefault()

        if (this.isEditingExistingModule()) {
            this.updateExistingModule(event)
        } else {
            this.saveNewModule(event)
        }
    }

    updateExistingModule(event) {
        const data = {
            ...iawpActions.edit_module,
            module_id: this.moduleIdValue,
            fields: this.getFieldValues(event.target),
        }

        this.saveButtonTarget.setAttribute('disabled', 'disabled')
        this.saveButtonTarget.classList.add('sending')

        jQuery.post(ajaxurl, data, (response) => {
            this.saveButtonTarget.classList.remove('sending')
            this.saveButtonTarget.classList.add('sent')
            setTimeout((controller) => {
                controller.saveButtonTarget.removeAttribute('disabled')
                controller.saveButtonTarget.classList.remove('sent')
                controller.element.outerHTML = response.data.module_html
            }, 500, this)
        }).fail((error) => {
            this.saveButtonTarget.removeAttribute('disabled')
            this.saveButtonTarget.classList.remove('sending')
        })
    }

    saveNewModule(event) {
        const data = {
            ...iawpActions.save_module,
            module: this.getFieldValues(event.target),
            moduleToSwap: this.moduleToSwapValue,
        }

        this.saveButtonTarget.setAttribute('disabled', 'disabled')
        this.saveButtonTarget.classList.add('sending')

        jQuery.post(ajaxurl, data, (response) => {
            this.saveButtonTarget.classList.remove('sending')
            this.saveButtonTarget.classList.add('sent')
            setTimeout(() => {
                this.saveButtonTarget.removeAttribute('disabled')
                this.saveButtonTarget.classList.remove('sent')

                if(this.isSwapping()) {
                    this.element.previousSibling.remove()
                    this.element.insertAdjacentHTML("beforebegin", response.data.module_html)
                    this.element.remove()
                } else {
                    this.element.insertAdjacentHTML("beforebegin", response.data.module_html)
                    this.cancel()
                }

                // this.element.insertAdjacentHTML("beforebegin", response.data.module_html)
            }, 500, this)
        }).fail((error) => {
            this.saveButtonTarget.removeAttribute('disabled')
            this.saveButtonTarget.classList.remove('sending')
        })
    }

    getFieldValues(formElement) {
        const fields = {}
        const formElements = Array.from(formElement.elements)

        formElements.forEach((element) => {
            if (element.tagName === 'BUTTON' || (element.tagName === 'INPUT' && ['button', 'submit'].includes(element.type))) {
                // Skip buttons
            } else if (element.type === 'radio') {
                // Special case for radio buttons
                if (element.checked) {
                    fields[element.name] = element.value
                }
            } else if (element.type === 'checkbox') {
                // Special case for checkboxes
                if (!fields[element.name]) {
                    fields[element.name] = []
                }
                if (element.checked) {
                    fields[element.name].push(element.value)
                }
            } else {
                // Everything else
                fields[element.name] = element.value
            }
        })

        return fields
    }

    // /**
    //  * Rebuild the options for the sort by select box after the report is changed
    //  *
    //  * @param data
    //  * @param selectElement
    //  */
    // updateSelect(data, selectElement) {
    //     if (!selectElement) {
    //         return
    //     }
    //
    //     const previousValue = selectElement.value
    //
    //     selectElement.innerHTML = ''
    //
    //     data.forEach(([id, name]) => {
    //         const option = document.createElement('option')
    //         option.setAttribute('value', id)
    //         option.innerText = name
    //         selectElement.append(option)
    //     })
    //
    //     if (data.some(([id, name]) => id === previousValue)) {
    //         selectElement.value = previousValue
    //     }
    // }

    /**
     * Rebuild the options for the sort by select box after the report is changed
     *
     * @param data
     * @param selectElement
     */
    updateGroupSelect(data, selectElement, addNoComparisonOption = false) {
        if (!selectElement) {
            return
        }

        const previousValue = selectElement.value

        selectElement.innerHTML = ''

        if (addNoComparisonOption) {
            const optionGroup = document.createElement('optgroup')
            optionGroup.setAttribute('label', iawpText.noComparison)

            const option = document.createElement('option')
            option.setAttribute('value', 'no_comparison')
            option.innerText = iawpText.noComparison
            optionGroup.append(option)

            selectElement.appendChild(optionGroup)
        }

        const groups = []
        data.forEach(([id, name, group]) => {
            if (!groups.includes(group)) {
                groups.push(group)
            }
        })

        groups.forEach((group) => {
            const optionGroup = document.createElement('optgroup');
            optionGroup.setAttribute('label', group)

            data.filter(([id, name, groupName]) => {
                return groupName === group
            }).forEach(([id, name]) => {
                const option = document.createElement('option')
                option.setAttribute('value', id)
                option.innerText = name
                optionGroup.append(option)
            })

            if (data.some(([id, name]) => id === previousValue)) {
                selectElement.value = previousValue
            }

            selectElement.append(optionGroup)
        })
    }

    // /**
    //  * Rebuild the statistics checkboxes after the report is changed
    //  *
    //  * @param data
    //  * @param container
    //  */
    // updateCheckboxes(data, container) {
    //     if (!container) {
    //         return
    //     }
    //
    //     const previousName = container.querySelector('input').name
    //     const previousValues = Array.from(container.querySelectorAll('input:checked')).map((input) => input.value)
    //
    //     container.innerHTML = ''
    //
    //     data.forEach(([id, name], index) => {
    //         // Create the label element
    //         const label = document.createElement('label')
    //
    //         // Create the checkbox element
    //         const checkbox = document.createElement('input')
    //         checkbox.type = 'checkbox'
    //         checkbox.name = previousName
    //         checkbox.value = id
    //         checkbox.checked = previousValues.includes(id) || index === 0
    //
    //         // Create the text node
    //         const text = document.createTextNode(' ' + name)
    //
    //         // Append the checkbox and text to the label
    //         label.appendChild(checkbox)
    //         label.appendChild(text)
    //
    //         container.append(label)
    //     })
    // }

    /**
     * Rebuild the statistics checkboxes after the report is changed
     *
     * @param data
     * @param container
     */
    updateGroupCheckboxes(data, container) {
        if (!container) {
            return
        }

        const previousName = container.querySelector('input').name
        const previousValues = Array.from(container.querySelectorAll('input:checked')).map((input) => input.value)

        container.innerHTML = ''

        const checkboxGroup = document.createElement('div')
        checkboxGroup.classList.add('checkbox-group-container')
        checkboxGroup.dataset.controller = 'checkbox-group'

        const tabContainer = document.createElement('div')
        tabContainer.classList.add('tab-container')
        const addedTabs = []

        data.forEach(([id, name, group]) => {
            if (!addedTabs.includes(group)) {
                const tab = document.createElement('button')
                tab.setAttribute('type', 'button')
                tab.classList.add('checkbox-group-tab')
                tab.dataset.groupName = group
                tab.dataset.checkboxGroupTarget = 'groupTab'
                tab.dataset.action = 'checkbox-group#changeTab'
                tab.innerText = group

                // First tab should be selected
                if (addedTabs.length === 0) {
                    tab.classList.add('selected')
                }

                tabContainer.appendChild(tab)
                tabContainer.append(' ')
                addedTabs.push(group)
            }
        })

        checkboxGroup.appendChild(tabContainer)


        addedTabs.forEach((group, index) => {
            const groupData = data.filter(([id, name, groupName]) => group === groupName)
            const groupContainer = document.createElement('div');
            groupContainer.dataset.checkboxGroupTarget = 'group'
            groupContainer.dataset.groupName = group
            groupContainer.classList.add('checkbox-group')

            if (index === 0) {
                groupContainer.classList.add('selected')
            }

            groupData.forEach(([id, name], index) => {
                // Create the label element
                const label = document.createElement('label')

                // Create the checkbox element
                const checkbox = document.createElement('input')
                checkbox.type = 'checkbox'
                checkbox.name = previousName
                checkbox.value = id
                checkbox.checked = previousValues.includes(id) || index === 0

                // Create the text node
                const text = document.createTextNode(' ' + name)

                // Append the checkbox and text to the label
                label.appendChild(checkbox)
                label.appendChild(text)

                groupContainer.appendChild(label)
            })

            checkboxGroup.appendChild(groupContainer)
        })

        container.appendChild(checkboxGroup)
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


    isSwapping() {
        return !!this.moduleToSwapValue
    }
}