const isLightMode = () => {
    return document.body.classList.contains('iawp-light-mode') || prefersLightMode()
}

const isDarkMode = () => {
    return document.body.classList.contains('iawp-dark-mode') || prefersDarkMode()
}

const prefersLightMode = () => {
    return !document.body.classList.contains('iawp-light-mode')
        && !document.body.classList.contains('iawp-dark-mode')
        && window.matchMedia
        && window.matchMedia('(prefers-color-scheme: light)').matches
}

const prefersDarkMode = () => {
    return !document.body.classList.contains('iawp-light-mode')
        && !document.body.classList.contains('iawp-dark-mode')
        && window.matchMedia
        && window.matchMedia('(prefers-color-scheme: dark)').matches
}

module.exports = {
    isLightMode,
    isDarkMode,
}