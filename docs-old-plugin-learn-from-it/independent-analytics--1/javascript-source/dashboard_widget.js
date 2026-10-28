import {Application} from "@hotwired/stimulus"
import ChartController from "./controllers/chart_controller"

window.Stimulus = Application.start()

Stimulus.register("chart", ChartController)