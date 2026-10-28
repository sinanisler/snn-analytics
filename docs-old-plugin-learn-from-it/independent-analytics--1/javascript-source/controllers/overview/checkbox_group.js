import {Controller} from "@hotwired/stimulus"

export default class extends Controller {
    static targets = ['groupTab', 'group']

    static values = {
        max: { type: Number, default: -1 }
    }

    connect() {
        this.updateCheckboxStates()
        this.element.addEventListener('change', (event) => {
            this.updateCheckboxStates()
        })
    }

    updateCheckboxStates() {
        const checkboxes = Array.from(
            this.element.querySelectorAll('input[type=checkbox]')
        )
        const totalChecked = checkboxes.filter(checkbox => checkbox.checked).length

        if (totalChecked === 1) {
            // Disable checked boxes so they can't select 0
            checkboxes.forEach(checkbox => {
                if (checkbox.checked) {
                    checkbox.disabled = true
                }
            })
        } else if (totalChecked === this.maxValue) {
            // Disable unchecked boxes so they can't select more than 4
            checkboxes.forEach(checkbox => {
                if (!checkbox.checked) {
                    checkbox.disabled = true
                }
            })
        } else {
            // Ensure everything is enabled
            checkboxes.forEach(checkbox => {
                checkbox.disabled = false
            })
        }
    }
    
    changeTab(event) {
        const selectedGroup = event.currentTarget.dataset.groupName

        this.groupTabTargets.forEach(groupTab => {
            groupTab.classList.toggle('selected', groupTab.dataset.groupName === selectedGroup)
        })

        this.groupTargets.forEach(group => {
            group.classList.toggle('selected', group.dataset.groupName === selectedGroup)
        })
    }
}