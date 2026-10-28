import {Controller} from "@hotwired/stimulus"

export default class extends Controller {
    static targets = [
        "statusTextElement"
    ]

    static values = {
        text: String
    }

    copy(e) {
        const statusTextElement = this.hasStatusTextElementTarget ? this.statusTextElementTarget : this.element
        const initialText = statusTextElement.innerHTML
        const textToCopy = this.textValue

        this.copyTextToClipboard(textToCopy)
        statusTextElement.innerHTML = iawpText.copied
        setTimeout(() => {
            statusTextElement.innerHTML = initialText
        }, 1000)
    }

    copyTextToClipboard(text) {
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text)
        } else {
            const textArea = document.createElement("textarea")
            textArea.value = text
            textArea.style.position = "fixed"
            textArea.style.left = "-999999px"
            textArea.style.top = "-999999px"
            document.body.appendChild(textArea)
            textArea.focus()
            textArea.select()
            return new Promise((res, rej) => {
                document.execCommand('copy') ? res() : rej()
                textArea.remove()
            })
        }
    }
}