import {Controller} from "@hotwired/stimulus"
import * as easepick from "@easepick/bundle";

export default class extends Controller {
    static values = {
        filters: Array,
        filterLogic: String
    }
    static targets = [
        'modal',
        'modalButton',
        'blueprint',
        'filters',
        'condition',
        'reset',
        'inclusion',
        'column',
        'operator',
        'operand',
        'conditionButtons',
        'spinner',
        'filterLogic'
    ]

    appliedConditions = 0

    connect() {
        document.addEventListener('click', this.maybeClose)
        document.addEventListener('iawp:filtersChanged', this.updateFilters)
        document.addEventListener('iawp:fetchingReport', this.onFetchingReport)

        if (this.filtersValue.length > 0) {
            this.createInitialFilters()
            this.setResetEnabled(true)
        } else {
            this.addCondition()
        }
    }

    disconnect() {
        document.removeEventListener('click', this.maybeClose)
        document.removeEventListener('iawp:filtersChanged', this.updateFilters)
        document.removeEventListener('iawp:fetchingReport', this.onFetchingReport)
    }

    onFetchingReport = () => {
        const enabledElements = this.element.querySelector('#modal-filters').querySelectorAll('input:not([disabled]), button:not([disabled]), select:not([disabled])')

        enabledElements.forEach(element => element.disabled = true)
        this.spinnerTarget.classList.remove('hidden')

        document.addEventListener('iawp:fetchedReport', () => {
            enabledElements.forEach(element => element.disabled = false)
            this.spinnerTarget.classList.add('hidden')
        }, {once: true})
    }

    updateFilters = (event) => {
        this.filtersTarget.innerHTML = ''
        this.blueprintTarget.innerHTML = event.detail.filtersTemplateHTML
        this.conditionButtonsTarget.innerHTML = event.detail.filtersButtonsHTML
        this.filtersValue = event.detail.filters
        this.setCount(event.detail.filters.length)

        if (this.filtersValue.length > 0) {
            this.createInitialFilters()
        } else {
            this.addCondition()
        }
    }

    createInitialFilters() {
        this.filtersValue.forEach((filter) => {
            const element = this.blueprintTarget.content.cloneNode(true)
            this.filtersTarget.appendChild(element)
            const condition = this.filtersTarget.lastElementChild

            const inclusionTarget = this.inclusionTargets.find((inclusion) => condition.contains(inclusion))
            const columnTarget = this.columnTargets.find((column) => condition.contains(column))

            inclusionTarget.value = filter.inclusion
            columnTarget.value = filter.column

            const type = columnTarget.options[columnTarget.selectedIndex].dataset.type

            // Show the correct operator
            this.operatorTargets.filter((operator) => {
                return condition.contains(operator)
            }).forEach((operator) => {
                const isMatch = operator.dataset.type === type

                operator.classList.toggle('show', isMatch)

                if(isMatch) {
                    operator.value = filter.operator
                }
            })

            // Show the correct operand field
            this.operandTargets.filter((operand) => {
                return condition.contains(operand)
            }).forEach((operand) => {
                const isMatch = operand.dataset.column === columnTarget.value

                operand.classList.toggle('show', isMatch)

                if(isMatch) {
                    operand.value = filter.operand

                    setTimeout(() => {
                        const easepickController = this.application.getControllerForElementAndIdentifier(operand, "easepick")

                        if(easepickController) {
                            const start = new easepick.DateTime(filter.operand * 1000);
                            operand.easepick.setDate(start)
                            operand.easepick.gotoDate(start)
                            easepickController.unixTimestampValue = filter.operand
                        }
                    }, 0)
                }
            })
        })
    }

    maybeClose = (event) => {
        const isOpen = this.modalTarget.classList.contains('show')
        const isInComponent = this.element.contains(event.target)

        if (isOpen && !isInComponent) {
            this.closeModal()
        }
    }

    toggleModal(e) {
        e.preventDefault();
        const isOpen = this.modalTarget.classList.contains('show')
        isOpen ? this.closeModal() : this.openModal()
    }

    openModal() {
        this.modalTarget.classList.add('show')
        this.modalButtonTarget.classList.add('open')
        document.getElementById('iawp-layout').classList.add('modal-open');
    }

    closeModal() {
        this.modalTarget.classList.remove('show')
        this.modalButtonTarget.classList.remove('open')
        document.getElementById('iawp-layout').classList.remove('modal-open');
    }

    addCondition() {
        this.filtersTarget.append(this.blueprintTarget.content.cloneNode(true))
        this.setResetEnabled(true)
    }

    removeCondition(e) {
        e.stopPropagation();

        this.conditionTargets.forEach((condition) => {
            if (condition.contains(e.target)) {
                condition.remove()
            }
        })

        if (this.conditionTargets.length === 0) {
            this.addCondition()
            document.dispatchEvent(
                new CustomEvent('iawp:changeFilters', {
                    detail: {filters: []}
                })
            )
            this.setResetEnabled(false)
        }

        if (this.conditionTargets.length === 1) {
            this.setResetEnabled(false)
            this.setCount(0)
        }

        if (this.conditionTargets.length === 0) {
            this.addCondition()
        }

        if (this.appliedConditions > 1) {
            this.apply()
        }
    }

    apply({showLoadingOverlay = true} = {}) {
        let errors = false

        this.conditionTargets.forEach((condition) => {
            const columnTarget = this.columnTargets.find((column) => condition.contains(column))
            const operandTarget = this.operandTargets.find((operand) => condition.contains(operand) && operand.classList.contains('show'))

            if (columnTarget.value === '') {
                columnTarget.classList.add('error')
                errors = true
            }

            if (operandTarget && operandTarget.value === '') {
                operandTarget.classList.add('error')
                errors = true
            }
        })

        if (errors) {
            return
        }

        const filters = this.conditionTargets.map((condition) => {
            const inclusionTarget = this.inclusionTargets.find((inclusion) => condition.contains(inclusion))
            const columnTarget = this.columnTargets.find((column) => condition.contains(column))
            const operatorTarget = this.operatorTargets.find((operator) => condition.contains(operator) && operator.classList.contains('show'))
            const operandTarget = this.operandTargets.find((operand) => condition.contains(operand) && operand.classList.contains('show'))
            const filter = {
                inclusion: inclusionTarget.value,
                column: columnTarget.value,
                operator: operatorTarget.value,
                operand: operandTarget.value
            }

            const easepickController = this.application.getControllerForElementAndIdentifier(operandTarget, "easepick")
            if (easepickController) {
                filter.operand = easepickController.unixTimestampValue.toString()
            }

            return filter
        })

        this.appliedConditions = filters.length
        this.setResetEnabled(true)
        this.setCount(filters.length)
        this.closeModal()

        document.dispatchEvent(
            new CustomEvent('iawp:changeFilters', {
                detail: {
                    filters,
                    filterLogic: this.filterLogicValue,
                    showLoadingOverlay
                }
            })
        )
    }

    reset() {
        this.filterLogicTarget.value = 'and'
        this.conditionTargets.forEach((condition) => {
            condition.remove()
        })
        this.addCondition()
        this.setResetEnabled(false)
        this.appliedConditions = 0
        this.setCount(0)
        this.closeModal()
        document.dispatchEvent(
            new CustomEvent('iawp:changeFilters', {
                detail: {filters: []}
            })
        )
    }

    setCount(count = 0) {
        document.getElementById('toolbar').setAttribute('data-filter-count', count);
    }

    setResetEnabled(enable = true) {
        if (enable) {
            this.resetTarget.removeAttribute('disabled')
        } else {
            this.resetTarget.setAttribute('disabled', 'disabled')
        }
    }

    // Todo - Need to clear anything that's been hidden
    columnSelect(e) {
        const condition = this.conditionTargets.find((condition) => condition.contains(e.target))
        const column = e.target.value
        const type = e.target.options[e.target.selectedIndex].dataset.type

        // Remove error class
        e.target.classList.remove('error')

        // Show the correct operator
        this.operatorTargets.filter((operator) => {
            return condition.contains(operator)
        }).forEach((operator) => {
            const matchingType = operator.dataset.type === type
            operator.classList.toggle('show', matchingType)
        })

        // Show the correct operand field
        this.operandTargets.filter((operand) => {
            return condition.contains(operand)
        }).forEach((operand) => {
            const matchingColumn = operand.dataset.column === column
            operand.classList.toggle('show', matchingColumn)
        })
    }

    changeFilterLogic(event) {
        this.filterLogicValue = event.target.value
    }

    operandChange(e) {
        e.target.classList.remove('error')
    }

    operandKeyDown(e) {
        if (e.keyCode === 13) {
            // Enter key pressed
            this.apply()
        }
    }
}
