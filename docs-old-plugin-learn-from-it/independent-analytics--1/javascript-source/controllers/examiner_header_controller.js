import {Controller} from "@hotwired/stimulus"

class ExaminerHeaderController extends Controller {
    connect() {
        document.addEventListener('keydown', this.keyPress)
    }

    disconnect() {
        document.removeEventListener('keydown', this.keyPress)
    }

    keyPress = (event) => {
        // Ignore non-escape keys
        if (event.key !== "Escape") {
            return;
        }

        this.askToBeClosed()
    }

    askToBeClosed() {
        window.parent.postMessage('iawpCloseExaminer');
    }
}

export default ExaminerHeaderController