<?php

namespace IAWPSCOPED;

use IAWP\Click_Tracking\Click_Processing_Job;
use IAWP\Click_Tracking\Config_File_Manager;
use IAWP\Cron\Unscheduler;
use IAWP\Custom_WordPress_Columns\Views_Column;
use IAWP\Dashboard_Options;
use IAWP\Data_Pruning\Pruning_Scheduler;
use IAWP\Database;
use IAWP\Date_Range\Exact_Date_Range;
use IAWP\Ecommerce\SureCart_Event_Sync_Job;
use IAWP\Ecommerce\SureCart_Store;
use IAWP\Env;
use IAWP\FetchFaviconsJob;
use IAWP\Geo_Database_Health_Check_Job;
use IAWP\Geo_Database_Manager;
use IAWP\Independent_Analytics;
use IAWP\Interrupt;
use IAWP\Journey\JourneyStatisticsJob;
use IAWP\MainWP;
use IAWP\Migration_Fixer_Job;
use IAWP\Migrations;
use IAWP\Overview\Module_Refresh_Job;
use IAWP\Overview\Modules\Module;
use IAWP\Overview\Sync_Module_Background_Job;
use IAWP\Patch;
use IAWP\Public_API\Analytics;
use IAWP\Public_API\Singular_Analytics;
use IAWP\Public_API\Top_Posts;
use IAWP\Report_Finder;
use IAWP\Utils\BladeOne;
use IAWP\WP_Option_Cache_Bust;
\define( 'IAWP_DIRECTORY', \rtrim( \plugin_dir_path( __FILE__ ), \DIRECTORY_SEPARATOR ) );
\define( 'IAWP_URL', \rtrim( \plugin_dir_url( __FILE__ ), '/' ) );
\define( 'IAWP_VERSION', '2.14.1' );
\define( 'IAWP_DATABASE_VERSION', '52' );
\define( 'IAWP_LANGUAGES_DIRECTORY', \dirname( \plugin_basename( __FILE__ ) ) . '/languages' );
\define( 'IAWP_PLUGIN_FILE', __DIR__ . '/iawp.php' );
if ( \file_exists( \IAWPSCOPED\iawp_path_to( 'vendor/scoper-autoload.php' ) ) ) {
    require_once \IAWPSCOPED\iawp_path_to( 'vendor/scoper-autoload.php' );
} else {
    require_once \IAWPSCOPED\iawp_path_to( 'vendor/autoload.php' );
}
/**
 * @param $log
 *
 * @return void
 * @internal
 */
function iawp_log(  $log  ) : void {
    if ( \WP_DEBUG === \true && \WP_DEBUG_LOG === \true ) {
        if ( \is_array( $log ) || \is_object( $log ) ) {
            \error_log( \print_r( $log, \true ) );
        } else {
            \error_log( $log );
        }
    }
}

/** @internal */
function iawp_path_to(  string $path  ) : string {
    $path = \trim( $path, \DIRECTORY_SEPARATOR );
    return \implode( \DIRECTORY_SEPARATOR, [\IAWP_DIRECTORY, $path] );
}

/** @internal */
function iawp_url_to(  string $path  ) : string {
    $path = \trim( $path, '/' );
    return \implode( '/', [\IAWP_URL, $path] );
}

/**
 * add_filter('iawp_temp_directory_path', function ($value) {
 *     return '/Users/andrew/site/wp-content/uploads/iawp';
 * });
 *
 * @param string $path
 *
 * @return string
 * @throws Exception
 * @internal
 */
function iawp_temp_path_to(  string $path  ) : string {
    $temp_directory = ( \defined( 'IAWP_TEMP_DIR' ) ? \IAWP_TEMP_DIR : \apply_filters( 'iawp_temp_directory_path', 'temp' ) );
    $path = \rtrim( $path, \DIRECTORY_SEPARATOR );
    if ( $temp_directory === 'temp' ) {
        return \IAWPSCOPED\iawp_path_to( \implode( \DIRECTORY_SEPARATOR, [$temp_directory, $path] ) );
    }
    $temp_directory = \rtrim( $temp_directory, \DIRECTORY_SEPARATOR );
    if ( !\is_writable( $temp_directory ) ) {
        \wp_mkdir_p( $temp_directory );
        // Separate condition to see if wp_mkdir_p call fixed the issue
        if ( !\is_writable( $temp_directory ) ) {
            throw new \Exception('The temp directory set with the iawp_temp_directory_path filter is missing or is not writable: ' . $temp_directory);
        }
    }
    return \implode( \DIRECTORY_SEPARATOR, [$temp_directory, $path] );
}

/**
 * @param string $path
 * @param bool $prefer_parent_site_upload_path If it's a multisite installation, use the parent sites upload folder
 * @return string
 * @internal
 */
function iawp_upload_path_to(  string $path, bool $prefer_parent_site_upload_path = \false  ) : string {
    $path = \trim( $path, \DIRECTORY_SEPARATOR );
    $upload_directory = \wp_upload_dir()['basedir'];
    if ( $prefer_parent_site_upload_path && \is_multisite() ) {
        $site = \get_site();
        if ( $site !== null ) {
            \switch_to_blog( \intval( $site->site_id ) );
            $upload_directory = \wp_upload_dir()['basedir'];
            \restore_current_blog();
        }
    }
    return \implode( \DIRECTORY_SEPARATOR, [$upload_directory, $path] );
}

/** @internal */
function iawp_upload_url_to(  string $path  ) : string {
    $upload_url = \wp_upload_dir()['baseurl'];
    $path = \trim( $path, '/' );
    return \implode( '/', [$upload_url, $path] );
}

/**
 * Determines if the user is running a licensed pro version
 *
 * @return bool
 * @internal
 */
function iawp_is_pro() : bool {
    return \true; // Changed to true for local dev - bypasses all pro checks
}

/**
 * Determines if the user is running a free version or an unlicensed pro version
 * @return bool
 * @internal
 */
function iawp_is_free() : bool {
    return !\IAWPSCOPED\iawp_is_pro();
}

/** @internal */
function iawp_dashboard_url(  array $query_arguments = []  ) : string {
    $default_query_arguments = [
        'page' => 'independent-analytics',
    ];
    return \add_query_arg( \array_merge( $default_query_arguments, $query_arguments ), \admin_url( 'admin.php' ) );
}

/** @internal */
function iawp_blade() {
    if ( !\file_exists( \IAWPSCOPED\iawp_temp_path_to( 'template-cache' ) ) ) {
        \wp_mkdir_p( \IAWPSCOPED\iawp_temp_path_to( 'template-cache' ) );
    }
    $blade = BladeOne::create();
    $blade->share( 'env', new Env() );
    $blade->share( 'is_pro', \IAWPSCOPED\iawp_is_pro() );
    $blade->share( 'security', new \IAWP\Utils\Security() );
    return $blade;
}

/** @internal */
function iawp_render(  string $view, array $variables = []  ) : string {
    if ( !\file_exists( \IAWPSCOPED\iawp_temp_path_to( 'template-cache' ) ) ) {
        \wp_mkdir_p( \IAWPSCOPED\iawp_temp_path_to( 'template-cache' ) );
    }
    $blade = BladeOne::create();
    $blade->share( 'env', new Env() );
    $blade->share( 'is_pro', \IAWPSCOPED\iawp_is_pro() );
    $blade->share( 'security', new \IAWP\Utils\Security() );
    return $blade->run( $view, $variables );
}

/** @internal */
function iawp_icon(  string $icon  ) : string {
    try {
        return \IAWPSCOPED\iawp_blade()->run( 'icons.plugins.' . $icon );
    } catch ( \Throwable $e ) {
        return '';
    }
}

/**
 * Get the currently installed database version
 *
 * @return int
 * @internal
 */
function iawp_db_version() : int {
    return \intval( \get_option( 'iawp_db_version', '0' ) );
}

/** @internal */
function iawp_intify(  $value  ) {
    if ( \is_string( $value ) && \ctype_digit( $value ) ) {
        return \intval( $value );
    }
    return $value;
}

/** @internal */
function iawp_custom_log(  $line  ) {
    try {
        $filePath = \IAWPSCOPED\iawp_path_to( 'public-custom-log.txt' );
        // Normalize line endings
        $line = \rtrim( $line, "\r\n" );
        // Append the line
        \file_put_contents( $filePath, $line . \PHP_EOL, \FILE_APPEND );
    } catch ( \Throwable $e ) {
    }
}

/**
 * iawp_singular_analytics('60', new DateTime('-3 days'), new DateTime());
 *
 * @param string|int $singular_id
 * @param DateTime $from
 * @param DateTime $to
 *
 * @return Singular_Analytics|null
 * @internal
 */
function iawp_singular_analytics(  $singular_id, \DateTime $from, \DateTime $to  ) : ?Singular_Analytics {
    $date_range = new Exact_Date_Range($from, $to);
    return Singular_Analytics::for( $singular_id, $date_range );
}

/**
 * iawp_top_posts();
 *
 * Retrieves top posts with customizable options.
 *
 * @param array{
 *     post_type: string,          // Default: 'post'
 *     category: ?int,             // Default: null
 *     limit: int,                 // Default: 10
 *     from: \DateTimeInterface,   // Default: 30 days ago
 *     to: \DateTimeInterface,     // Default: today
 *     sort_by: string,            // Default: 'views' - supports 'views', 'visitors', or 'sessions'
 * } $options
 *
 * @return array
 * @internal
 */
function iawp_top_posts(  array $options = []  ) : array {
    return ( new Top_Posts($options) )->get();
}

/**
 * iawp_analytics(new DateTime('-3 days'), new DateTime());
 *
 * @param DateTime $from
 * @param DateTime $to
 *
 * @return Analytics
 * @internal
 */
function iawp_analytics(  \DateTime $from, \DateTime $to  ) : Analytics {
    $date_range = new Exact_Date_Range($from, $to);
    return Analytics::for( $date_range );
}

if ( !\extension_loaded( 'pdo' ) || !\extension_loaded( 'pdo_mysql' ) ) {
    $interrupt = new Interrupt('interrupt.pdo');
    $interrupt->render();
    return;
}
if ( \IAWPSCOPED\iawp_db_version() === 0 && !Database::has_correct_database_privileges() ) {
    $interrupt = new Interrupt('interrupt.missing-database-permissions');
    $interrupt->render( [
        'missing_privileges' => Database::missing_database_privileges(),
    ] );
    return;
}
global $wpdb;
if ( \strlen( $wpdb->prefix ) > 25 ) {
    $interrupt = new Interrupt('interrupt.database-prefix-too-long');
    $interrupt->render( [
        'prefix' => $wpdb->prefix,
        'length' => \strlen( $wpdb->prefix ),
    ] );
    return;
}
if ( Migrations\Migrations::is_database_ahead_of_plugin() ) {
    $interrupt = new Interrupt('interrupt.database-ahead-of-plugin');
    $interrupt->render();
    return;
}
if ( \get_option( 'iawp_missing_tables' ) === '1' ) {
    if ( \IAWPSCOPED\iawp_db_version() === 0 ) {
        \delete_option( 'iawp_missing_tables' );
    } else {
        $interrupt = new Interrupt('interrupt.missing-database-tables');
        $interrupt->render();
        return;
    }
}
// These can be updated in background jobs. Always get the actual value from the database.
WP_Option_Cache_Bust::register( 'iawp_is_migrating' );
WP_Option_Cache_Bust::register( 'iawp_should_refresh_modules' );
WP_Option_Cache_Bust::register( 'iawp_migration_started_at' );
WP_Option_Cache_Bust::register( 'iawp_is_database_downloading' );
WP_Option_Cache_Bust::register( 'iawp_db_version' );
WP_Option_Cache_Bust::register( 'iawp_geo_database_version' );
WP_Option_Cache_Bust::register( 'iawp_overview_modules' );
WP_Option_Cache_Bust::register( 'iawp_default_modules_added' );
/** @internal */
function iawp() {
    return Independent_Analytics::getInstance();
}

\IAWPSCOPED\iawp();
\register_activation_hook( \IAWP_PLUGIN_FILE, function () {
    \wp_mkdir_p( \IAWPSCOPED\iawp_temp_path_to( 'template-cache' ) );
    if ( \IAWPSCOPED\iawp_db_version() === 0 ) {
        // If there is no database installed, run migration on current process
        Migrations\Migrations::create_or_migrate();
    } else {
        // If there is a database, run migration in a background process
        Migrations\Migration_Job::maybe_dispatch();
    }
    ( new Geo_Database_Manager() )->health_check();
    \update_option( 'iawp_need_clear_cache', \true, \true );
    if ( \get_option( 'iawp_show_gsg' ) == '' ) {
        \update_option( 'iawp_show_gsg', '1', \true );
    }
    \IAWPSCOPED\iawp()->cron_manager->schedule();
    ( new Pruning_Scheduler() )->schedule();
    if ( \IAWPSCOPED\iawp_is_pro() ) {
        \IAWPSCOPED\iawp()->email_reports->schedule();
    }
    // Set current version for changelog notifications
    \update_option( 'iawp_last_update_viewed', \IAWP_VERSION, \true );
    if ( \IAWPSCOPED\iawp_db_version() > 0 && Database::is_missing_all_tables() ) {
        \update_option( 'iawp_missing_tables', '1', \true );
    }
} );
\register_deactivation_hook( \IAWP_PLUGIN_FILE, function () {
    Unscheduler::unschedule_all_events();
    ( new Geo_Database_Manager() )->delete_database();
    \wp_delete_file( \trailingslashit( \WPMU_PLUGIN_DIR ) . 'iawp-performance-boost.php' );
    \delete_option( 'iawp_must_use_directory_not_writable' );
} );
// This fires for the original version of the plugin and not the updated version of the plugin
\add_action( 'upgrader_process_complete', function () {
    // Trigger the click processing cron job so no clicks are lost
    \do_action( 'iawp_click_processing' );
} );
\add_action( 'init', function () {
    Config_File_Manager::ensure();
    ( new Geo_Database_Health_Check_Job() )->schedule();
    ( new Migration_Fixer_Job() )->schedule();
    ( new FetchFaviconsJob() )->schedule();
    if ( \IAWPSCOPED\iawp_is_pro() ) {
        ( new Click_Processing_Job() )->schedule();
        ( new Module_Refresh_Job() )->schedule();
        ( new JourneyStatisticsJob() )->schedule();
    }
    if ( \IAWPSCOPED\iawp()->is_surecart_support_enabled() ) {
        ( new SureCart_Event_Sync_Job() )->schedule();
    }
    if ( \IAWPSCOPED\iawp()->is_surecart_support_enabled() && \in_array( \get_option( 'iawp_surecart_currency_code' ), ['', \false] ) ) {
        SureCart_Store::cache_currency_code();
    }
} );
\add_action( 'admin_init', function () {
    Migrations\Migrations::handle_migration_18_error();
    Migrations\Migrations::handle_migration_22_error();
    Migrations\Migrations::handle_migration_29_error();
    Migrations\Migrations::handle_migration_45_collation_error();
    Migrations\Migrations::handle_migration_46_error();
    Patch::patch_2_6_2_incorrect_email_report_schedule();
    Patch::patch_2_8_7_potential_duplicates();
    Config_File_Manager::ensure();
    $options = Dashboard_Options::getInstance();
    $options->maybe_redirect();
    new Migrations\Migration_Job();
    if ( \get_option( 'iawp_db_version', '0' ) === '0' ) {
        // If there is no database installed, run migration on current process
        Migrations\Migrations::create_or_migrate();
    } else {
        // If there is a database, run migration in a background process
        Migrations\Migration_Job::maybe_dispatch();
    }
    if ( \get_option( 'iawp_should_refresh_modules', '0' ) === '1' ) {
        \update_option( 'iawp_should_refresh_modules', '0', \true );
        Module::queue_full_module_refresh();
    }
    if ( \get_option( 'iawp_has_added_journey_default_reports', '0' ) === '0' ) {
        \update_option( 'iawp_has_added_journey_default_reports', '1', \true );
        $report_finder = new Report_Finder();
        if ( empty( $report_finder->get_saved_reports_for_type( 'journeys' ) ) ) {
            Report_Finder::insert_default_user_journey_reports();
        }
    }
} );
new Sync_Module_Background_Job();
Views_Column::initialize();
MainWP::initialize();