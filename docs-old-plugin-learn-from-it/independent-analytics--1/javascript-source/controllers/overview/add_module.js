import {Controller} from "@hotwired/stimulus"

export default class extends Controller {
    addModule() {
        this.dispatch('addModule')
    }
}