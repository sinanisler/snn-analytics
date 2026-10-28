// Stores ids for modules to request
const requestedModules = new Set()

// Store the id of the setTimeout currently running
let pollingTimeout = null

let isFirstAttempt = true

function isPolling() {
    return Number.isInteger(pollingTimeout) && pollingTimeout > 0
}

function startPolling() {
    if(isPolling() || requestedModules.size === 0) {
        return
    }

    pollingTimeout = setTimeout(() => {
        isFirstAttempt = false
        pollingTimeout = null
        fetchMarkup()
    }, isFirstAttempt ? 0 : 500)
}

function dispatchEvent(id, moduleHtml) {
    document.dispatchEvent(
        new CustomEvent('iawp:module-markup:' + id, {
            detail: {
                id,
                moduleHtml,
            }
        })
    )
}

function fetchMarkup() {
    const data = {
        ...iawpActions.get_markup_for_modules,
        ids: Array.from(requestedModules),
    }

    jQuery.post(ajaxurl, data, (response) => {
        if(!Array.isArray(response.data)) {
            return
        }

        response.data.forEach(module => {
            if(module.hasDataset) {
                dispatchEvent(module.id, module.moduleHtml)
                requestedModules.delete(module.id)
            }
        })

        startPolling()
    }).fail((error) => {
        startPolling()
    })
}

module.exports = {
    /**
     * @return Markup for the module or null if markup is still being fetched.
     */
    getModuleMarkup(moduleId) {
        requestedModules.add(moduleId)
        startPolling()
    }
}