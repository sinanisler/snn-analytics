import {Controller} from "@hotwired/stimulus"

export default class extends Controller {
    selectInput(e) {
        e.target.select();
    }
}