import {Application} from "@hotwired/stimulus"
import MicroModal from "micromodal"

document.addEventListener("DOMContentLoaded", () => MicroModal.init())

document.addEventListener("DOMContentLoaded", () => {
    setTimeout(() => {
        // The Examiner is loaded using an iframe. This messages lets the iframed element alert
        // the parent that's completely loaded and ready to be shown.
        window.parent.postMessage('iawpPageReady');
    }, 0)
})

import CampaignBuilderController from "./controllers/campaign_builder_controller"
import ChartController from "./controllers/chart_controller"
import ChartIntervalController from "./controllers/chart_interval_controller"
import ClipboardController from "./controllers/clipboard_controller"
import CopyReportController from "./controllers/copy_report_controller"
import CreateReportController from "./controllers/create_report_controller"
import DeleteDataController from "./controllers/delete_data_controller"
import DeleteReportController from "./controllers/delete_report_controller"
import EasepickController from "./controllers/easepick_controller"
import ExaminerController from "./controllers/examiner_controller"
import ExaminerHeaderController from "./controllers/examiner_header_controller"
import ExportOverviewController from "./controllers/export_overview_controller"
import ExportReportsController from "./controllers/export_reports_controller"
import FiltersController from "./controllers/filters_controller"
import GroupController from "./controllers/group_controller"
import ImportReportController from "./controllers/import_reports_controller"
import MapController from "./controllers/map_controller"
import JourneyController from "./controllers/journey_controller"
import MigrationRedirectController from "./controllers/migration_redirect_controller"
import ModalController from "./controllers/modal_controller"
import PauseEmailsController from "./controllers/pause_emails_controller"
import PieChartController from "./controllers/pie_chart_controller"
import PluginGroupOptions from "./controllers/plugin_group_options_controller"
import PrunerController from "./controllers/pruner_controller"
import QuickStatsController from "./controllers/quick_stats_controller"
import RealTimeController from "./controllers/real_time_controller"
import RefreshOverviewController from "./controllers/refresh_overview_controller"
import RenameReportController from "./controllers/rename_report_controller"
import ReportController from "./controllers/report_controller"
import ResetAnalyticsController from "./controllers/reset_analytics_controller";
import ResetOverviewController from "./controllers/reset_overview_controller";
import SaveReportController from "./controllers/save_report_controller";
import SelectInputController from "./controllers/select_input_controller"
import SetFavoriteReportController from "./controllers/set_favorite_report_controller"
import SortController from "./controllers/sort_controller"
import SortableReportsController from "./controllers/sortable_reports_controller"
import TableColumnsController from "./controllers/table_columns_controller"
import TooltipController from "./controllers/tooltip_controller"
import WooCommerceSettingsController from "./controllers/woocommerce_settings_controller"
// Overview
import AddModuleController from "./controllers/overview/add_module"
import CheckboxGroupController from "./controllers/overview/checkbox_group"
import ModuleController from "./controllers/overview/module"
import ModuleEditorController from "./controllers/overview/module_editor"
import ModuleListController from "./controllers/overview/module_list"
import ModulePickerController from "./controllers/overview/module_picker"
import ReorderModulesController from "./controllers/overview/reorder_modules"

window.Stimulus = Application.start()

Stimulus.register("campaign-builder", CampaignBuilderController)
Stimulus.register("chart", ChartController)
Stimulus.register("chart-interval", ChartIntervalController)
Stimulus.register("clipboard", ClipboardController)
Stimulus.register("copy-report", CopyReportController)
Stimulus.register("delete-data", DeleteDataController)
Stimulus.register('delete-report', DeleteReportController)
Stimulus.register("easepick", EasepickController)
Stimulus.register("examiner", ExaminerController)
Stimulus.register("examiner-header", ExaminerHeaderController)
Stimulus.register("export-overview", ExportOverviewController)
Stimulus.register("export-reports", ExportReportsController)
Stimulus.register("filters", FiltersController)
Stimulus.register("group", GroupController)
Stimulus.register('import-reports', ImportReportController)
Stimulus.register("map", MapController)
Stimulus.register("journey", JourneyController)
Stimulus.register("migration-redirect", MigrationRedirectController)
Stimulus.register("modal", ModalController)
Stimulus.register("pause-emails", PauseEmailsController)
Stimulus.register("pie-chart", PieChartController)
Stimulus.register("plugin-group-options", PluginGroupOptions)
Stimulus.register("pruner", PrunerController)
Stimulus.register("quick-stats", QuickStatsController)
Stimulus.register("create-report", CreateReportController)
Stimulus.register("real-time", RealTimeController)
Stimulus.register("refresh-overview", RefreshOverviewController)
Stimulus.register("rename-report", RenameReportController)
Stimulus.register("report", ReportController)
Stimulus.register("reset-analytics", ResetAnalyticsController)
Stimulus.register("reset-overview", ResetOverviewController)
Stimulus.register("save-report", SaveReportController)
Stimulus.register("select-input", SelectInputController)
Stimulus.register("set-favorite-report", SetFavoriteReportController)
Stimulus.register("sort", SortController)
Stimulus.register("sortable-reports", SortableReportsController)
Stimulus.register("table-columns", TableColumnsController)
Stimulus.register("tooltip", TooltipController)
Stimulus.register("woocommerce-settings", WooCommerceSettingsController)
// Overview
Stimulus.register("add-module", AddModuleController)
Stimulus.register("checkbox-group", CheckboxGroupController)
Stimulus.register("module", ModuleController)
Stimulus.register("module-editor", ModuleEditorController)
Stimulus.register("module-list", ModuleListController)
Stimulus.register("module-picker", ModulePickerController)
Stimulus.register("reorder-modules", ReorderModulesController)
