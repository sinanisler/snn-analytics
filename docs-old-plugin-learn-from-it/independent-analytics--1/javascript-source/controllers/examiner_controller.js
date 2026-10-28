import {Controller} from "@hotwired/stimulus"
import MicroModal from "micromodal"

class ExaminerController extends Controller {
    static targets = ['content']

    options = null

    // Track last opened frame to know if the loading screen should be disabled or not
    lastOpenedFrame = null

    // Track which windows are loaded so the loading state can be restored if examiner is closed and reopened
    loadedWindows = new Set()

    connect() {
        document.addEventListener('iawp:showExaminer', this.showExaminer)
        window.addEventListener('message', this.processMessage)
    }

    disconnect() {
        document.removeEventListener('iawp:showExaminer', this.showExaminer)
        window.removeEventListener('message', this.processMessage)
    }

    showExaminer = (event) => {
        this.options = event.detail

        this.element.querySelector('.examiner-skeleton .date-picker-parent .iawp-label').innerText = this.options.dateLabel
        this.element.querySelector('.examiner-skeleton .report-title').innerText = this.options.title
        this.element.querySelector('.examiner-skeleton .report-subtitle').innerText = this.options.reportName

        const currentSvg =  document.querySelector(`.reports-list .menu-section.current svg`)
        if(currentSvg) {
            const clonedSVG = currentSvg.cloneNode(true)
            this.element.querySelector('.examiner-skeleton .report-subtitle').prepend(clonedSVG)
        }

        MicroModal.show('iawp-examiner-modal', {
            onClose: () => {
                this.cleanUp()
            }
        })

        const existingFrame = Array.from(this.contentTarget.querySelectorAll('iframe.preserved')).find((frame) => {
            return frame.src === this.options.url
        })

        if(existingFrame) {
            this.lastOpenedFrame = existingFrame

            if(this.loadedWindows.has(existingFrame.contentWindow)) {
                this.hideLoadingScreen()
            }

            existingFrame.classList.remove('preserved')
            return
        }

        const iframe = this.createInlineFrame(this.options.url)
        this.contentTarget.appendChild(iframe)
        this.lastOpenedFrame = iframe

        clearTimeout(this.fallbackTimeout)
        this.fallbackTimeout = setTimeout(() => {
            this.hideLoadingScreen()
        }, 10000)
    }

    processMessage = (event) => {
        const fromCurrentWindow = event.source === window
        const fromLastOpenedFrame = event.source === (this.lastOpenedFrame && this.lastOpenedFrame.contentWindow)

        // Ignore events from the current window
        if(fromCurrentWindow) {
            return
        }

        if (event.data === 'iawpPageReady') {
            this.loadedWindows.add(event.source)
        }

        if (event.data === 'iawpPageReady' && fromLastOpenedFrame) {
            this.hideLoadingScreen()
        }

        if (event.data === 'iawpCloseExaminer') {
            this.close()
        }
    }

    createInlineFrame(url) {
        const iframe = document.createElement('iframe');

        iframe.src = url
        iframe.classList.add('examiner-iframe')

        return iframe
    }

    close() {
        MicroModal.close('iawp-examiner-modal')
    }

    cleanUp() {
        this.options = null
        this.showLoadingScreen()
        clearTimeout(this.fallbackTimeout)

        const currentFrame = this.element.querySelector('iframe:not(.preserved)')

        if(currentFrame) {
            currentFrame.classList.add('preserved')
        }
    }

    showLoadingScreen() {
        this.element.querySelector('.examiner').classList.add('examiner--loading')
    }

    hideLoadingScreen() {
        this.element.querySelector('.examiner').classList.remove('examiner--loading')
    }
}

export default ExaminerController