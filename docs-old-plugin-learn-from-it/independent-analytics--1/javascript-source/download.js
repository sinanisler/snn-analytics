function downloadCSV(fileName, data) {
    const BOM = '\uFEFF' // UTF-8 byte-order mark (BOM)
    const blob = new Blob([BOM + data], { type: 'text/csv;charset=utf-8' })

    const element = window.document.createElement('a')
    element.href = window.URL.createObjectURL(blob)
    element.download = fileName

    document.body.appendChild(element)
    element.click()
    document.body.removeChild(element)
}

function downloadJSON(fileName, data) {
    const blob = new Blob([data], {type: 'application/json'})
    const element = window.document.createElement('a')
    element.href = window.URL.createObjectURL(blob)
    element.download = fileName
    document.body.appendChild(element)
    element.click()
    document.body.removeChild(element)
}

module.exports = {downloadCSV, downloadJSON}