<?php
/**
 * Admin menu and screens. Each screen is a shell; assets/admin/app.js fills it in
 * from the REST API. Settings are a normal server-side form.
 */

namespace SNN_Analytics;

defined( 'ABSPATH' ) || exit;

final class Admin {

	const MENU_CAP      = 'snn_a_view';
	const MANAGE_CAP    = 'manage_options';
	const SLUG          = 'snn-analytics';

	/** Dashboard cards, in default order. Users can reorder and hide them. */
	const CARDS = [ 'chart', 'realtime', 'geo', 'pages', 'newreturning', 'devices', 'sources', 'recent', 'campaigns', 'goals' ];

	/** Screen slug => [ view name, menu title, capability ]. */
	const SCREENS = [
		'snn-analytics'           => [ 'dashboard', 'Dashboard', self::MENU_CAP ],
		'snn-analytics-realtime'  => [ 'realtime', 'Realtime', self::MENU_CAP ],
		'snn-analytics-pages'     => [ 'pages', 'Pages', self::MENU_CAP ],
		'snn-analytics-referrers' => [ 'referrers', 'Referrers', self::MENU_CAP ],
		'snn-analytics-geo'       => [ 'geo', 'Geographic', self::MENU_CAP ],
		'snn-analytics-devices'   => [ 'devices', 'Devices', self::MENU_CAP ],
		'snn-analytics-campaigns' => [ 'campaigns', 'Campaigns', self::MENU_CAP ],
		'snn-analytics-journeys'  => [ 'journeys', 'Journeys', self::MENU_CAP ],
		'snn-analytics-goals'     => [ 'goals', 'Goals & Funnels', self::MENU_CAP ],
		'snn-analytics-import'    => [ 'import', 'Import', self::MANAGE_CAP ],
		'snn-analytics-settings'  => [ 'settings', 'Settings', self::MANAGE_CAP ],
	];

	/** Screens that stay in the left sidebar. Dashboard, Import and Settings are always there. */
	const SIDEBAR = [ 'snn-analytics', 'snn-analytics-import', 'snn-analytics-settings' ];

	/** Top bar order. Dashboard comes first and Settings last. Import is reached from the sidebar only. */
	const TOP_BAR = [
		'snn-analytics',
		'snn-analytics-realtime',
		'snn-analytics-pages',
		'snn-analytics-referrers',
		'snn-analytics-geo',
		'snn-analytics-devices',
		'snn-analytics-campaigns',
		'snn-analytics-journeys',
		'snn-analytics-goals',
		'snn-analytics-settings',
	];

	public static function register(): void {
		add_action( 'admin_menu', [ self::class, 'menu' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'assets' ] );
		add_action( 'admin_post_snn_a_save_settings', [ self::class, 'save_settings' ] );
		add_action( 'admin_init', [ self::class, 'prepare' ] );
	}

	public static function grant_capability(): void {
		$role = get_role( 'administrator' );
		if ( $role ) {
			$role->add_cap( self::MENU_CAP );
		}
	}

	public static function menu(): void {
		add_menu_page( 'SNN Analytics', 'SNN Analytics', self::MENU_CAP, self::SLUG, [ self::class, 'screen' ], 'dashicons-chart-line', 3 );

		foreach ( self::SCREENS as $slug => [ $view, $title, $cap ] ) {
			// An empty parent keeps a top-bar screen reachable by URL without listing it in the sidebar.
			$parent = in_array( $slug, self::SIDEBAR, true ) ? self::SLUG : '';
			add_submenu_page( $parent, $title, $title, $cap, $slug, [ self::class, 'screen' ] );
		}
	}

	/**
	 * Top-bar screens are not listed under a parent, so WordPress finds no title for them and
	 * highlights nothing. Setting the globals here, before the header prints, fixes both.
	 */
	public static function prepare(): void {
		$page = sanitize_key( wp_unslash( $_GET['page'] ?? '' ) );
		if ( ! isset( self::SCREENS[ $page ] ) || in_array( $page, self::SIDEBAR, true ) ) {
			return;
		}

		global $title, $parent_file, $submenu_file;
		$title        = self::SCREENS[ $page ][1];
		$parent_file  = self::SLUG;
		$submenu_file = $page;
	}

	/** Shell for every screen. The page slug decides which view the app shows. */
	public static function screen(): void {
		$slug = sanitize_key( wp_unslash( $_GET['page'] ?? self::SLUG ) );
		[ $view, $title, $cap ] = self::SCREENS[ $slug ] ?? self::SCREENS[ self::SLUG ];

		if ( ! current_user_can( $cap ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'snn-analytics' ), 403 );
		}

		?>
		<div class="wrap snn-a">
			<h1 class="screen-reader-text"><?php echo esc_html( 'SNN Analytics · ' . $title ); ?></h1>
			<?php self::tabs( $slug ); ?>
			<div id="snn-a-app" data-view="<?php echo esc_attr( $view ); ?>">
				<p class="snn-a-loading">Loading…</p>
			</div>
			<?php if ( $view === 'settings' ) : ?>
				<?php self::settings_form(); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Top bar with the screens in TOP_BAR. The date range carries over, so switching
	 * screens keeps the period the admin is looking at.
	 */
	private static function tabs( string $current ): void {
		$range = array_map( 'sanitize_text_field', array_intersect_key( wp_unslash( $_GET ), array_flip( [ 'range', 'from', 'to' ] ) ) );

		?>
		<nav class="snn-a-tabs" aria-label="SNN Analytics screens">
			<?php foreach ( self::TOP_BAR as $slug ) : ?>
				<?php $url = add_query_arg( $range + [ 'page' => $slug ], admin_url( 'admin.php' ) ); ?>
				<a href="<?php echo esc_url( $url ); ?>" class="snn-a-tab<?php echo $slug === $current ? ' is-current' : ''; ?>"<?php echo $slug === $current ? ' aria-current="page"' : ''; ?>><?php echo esc_html( self::SCREENS[ $slug ][1] ); ?></a>
			<?php endforeach; ?>
		</nav>
		<?php
	}

	public static function assets( string $hook ): void {
		$page = sanitize_key( wp_unslash( $_GET['page'] ?? '' ) );
		if ( ! isset( self::SCREENS[ $page ] ) ) {
			return;
		}

		wp_enqueue_style( 'snn-a-admin', plugins_url( 'assets/admin/app.css', SNN_A_FILE ), [], SNN_A_VERSION );
		wp_enqueue_script( 'snn-a-chart', plugins_url( 'assets/vendor/chart.umd.min.js', SNN_A_FILE ), [], '4.4.1', true );
		wp_enqueue_script( 'snn-a-admin', plugins_url( 'assets/admin/app.js', SNN_A_FILE ), [ 'snn-a-chart' ], SNN_A_VERSION, true );

		wp_localize_script( 'snn-a-admin', 'SNNA', [
			'rest'      => esc_url_raw( rest_url( API::NS . '/' ) ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'world'     => plugins_url( 'assets/geo/world.json', SNN_A_FILE ),
			'flags'     => plugins_url( 'assets/flags/4x3/', SNN_A_FILE ),
			'canManage' => current_user_can( self::MANAGE_CAP ),
			'cards'     => self::CARDS,
			'timezone'  => wp_timezone_string(),
			'pageUrl'   => admin_url( 'admin.php?page=' ),
			'version'   => SNN_A_VERSION,
		] );
	}

	// ----------------------------------------------------------- settings

	private static function settings_form(): void {
		$s = Settings::get();

		$rotations = [
			'never'   => 'Never (journeys stay linked across days)',
			'yearly'  => 'Once a year (default)',
			'monthly' => 'Once a month',
			'daily'   => 'Every day (most private)',
		];
		$retention = [
			0  => 'Keep everything',
			3  => '3 months',
			6  => '6 months',
			12 => '12 months (default)',
			24 => '24 months',
			36 => '36 months',
		];
		$trails = [
			14   => '14 days',
			30   => '30 days (default)',
			365  => '1 year',
			3650 => '10 years',
		];

		?>
		<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="snn-a-form">
			<input type="hidden" name="action" value="snn_a_save_settings">
			<?php wp_nonce_field( 'snn_a_save_settings' ); ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="salt_rotation">Visitor salt rotation</label></th>
					<td>
						<select name="salt_rotation" id="salt_rotation">
							<?php foreach ( $rotations as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $s['salt_rotation'], $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description">Visitors are identified by a salted hash of the IP, browser, OS and device type. Never by stored IPs. Each rotation makes returning visitors count as new.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="retention_months">Keep raw data</label></th>
					<td>
						<select name="retention_months" id="retention_months">
							<?php foreach ( $retention as $months => $label ) : ?>
								<option value="<?php echo esc_attr( (string) $months ); ?>" <?php selected( (int) $s['retention_months'], $months ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description">Individual visits and journeys older than this are deleted. Daily totals are kept.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Click heatmap</th>
					<td>
						<label>
							<input type="checkbox" name="heatmap" value="1" <?php checked( $s['heatmap'] ); ?>>
							Record where visitors click on each page
						</label>
						<p class="description">Counts only, per page, day and device. No click is stored on its own. Shown on the Pages screen.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Cursor trails</th>
					<td>
						<label>
							<input type="checkbox" name="cursor_trails" value="1" <?php checked( $s['cursor_trails'] ); ?>>
							Record how the mouse moves on each page
						</label>
						<p class="description">Off by default. Each view keeps one path of at most 60 seconds, sampled four times a second. Visitors are not identified by it.</p>
						<p>
							<label for="cursor_retention_days">Keep trails for</label>
							<select name="cursor_retention_days" id="cursor_retention_days">
								<?php foreach ( $trails as $days => $label ) : ?>
									<option value="<?php echo esc_attr( (string) $days ); ?>" <?php selected( (int) $s['cursor_retention_days'], $days ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Respect Do Not Track</th>
					<td>
						<label>
							<input type="checkbox" name="respect_dnt" value="1" <?php checked( $s['respect_dnt'] ); ?>>
							Do not track visitors who send DNT or Global Privacy Control
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row">Do not track these roles</th>
					<td>
						<?php foreach ( wp_roles()->get_names() as $role => $name ) : ?>
							<label class="snn-a-check-row">
								<input type="checkbox" name="exclude_roles[]" value="<?php echo esc_attr( $role ); ?>" <?php checked( in_array( $role, $s['exclude_roles'], true ) ); ?>>
								<?php echo esc_html( translate_user_role( $name ) ); ?>
							</label>
						<?php endforeach; ?>
						<p class="description">Logged-in users with these roles are not tracked.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="session_timeout">Session timeout (seconds)</label></th>
					<td>
						<input type="number" name="session_timeout" id="session_timeout" min="300" max="86400" step="60" value="<?php echo esc_attr( (string) $s['session_timeout'] ); ?>">
						<p class="description">A visitor without activity for this long starts a new session. Between 300 and 86400.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Uninstall</th>
					<td>
						<label>
							<input type="checkbox" name="delete_on_uninstall" value="1" <?php checked( $s['delete_on_uninstall'] ); ?>>
							Delete all data and tables when the plugin is deleted
						</label>
						<p class="description">Off by default, so your statistics survive a reinstall. Turn it on only if you want a clean removal.</p>
					</td>
				</tr>
			</table>

			<?php submit_button( 'Save settings' ); ?>
		</form>

		<h2>Tools</h2>
		<div id="snn-a-tools" data-view="settings-tools"></div>
		<?php
	}

	public static function save_settings(): void {
		if ( ! current_user_can( self::MANAGE_CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'snn-analytics' ), 403 );
		}
		check_admin_referer( 'snn_a_save_settings' );

		$defaults  = Settings::defaults();
		$rotations = [ 'never', 'yearly', 'monthly', 'daily' ];
		$retention = [ 0, 3, 6, 12, 24, 36 ];
		$trails    = [ 14, 30, 365, 3650 ];

		$rotation = (string) wp_unslash( $_POST['salt_rotation'] ?? '' );
		$rotation = in_array( $rotation, $rotations, true ) ? $rotation : $defaults['salt_rotation'];

		$months = (int) wp_unslash( $_POST['retention_months'] ?? 0 );
		$months = in_array( $months, $retention, true ) ? $months : 0;

		$days = (int) wp_unslash( $_POST['cursor_retention_days'] ?? $defaults['cursor_retention_days'] );
		$days = in_array( $days, $trails, true ) ? $days : $defaults['cursor_retention_days'];

		$submitted = array_map( 'sanitize_key', (array) wp_unslash( $_POST['exclude_roles'] ?? [] ) );
		$excluded  = array_values( array_intersect( $submitted, array_keys( wp_roles()->get_names() ) ) );

		$timeout = (int) wp_unslash( $_POST['session_timeout'] ?? $defaults['session_timeout'] );
		$timeout = min( 86400, max( 300, $timeout ) );

		Settings::update( [
			'salt_rotation'         => $rotation,
			'retention_months'      => $months,
			'heatmap'               => isset( $_POST['heatmap'] ),
			'cursor_trails'         => isset( $_POST['cursor_trails'] ),
			'cursor_retention_days' => $days,
			'respect_dnt'           => isset( $_POST['respect_dnt'] ),
			'exclude_roles'         => $excluded,
			'session_timeout'       => $timeout,
			'delete_on_uninstall'   => isset( $_POST['delete_on_uninstall'] ),
		] );

		wp_safe_redirect( add_query_arg(
			[ 'page' => 'snn-analytics-settings', 'saved' => 1 ],
			admin_url( 'admin.php' )
		) );
		exit;
	}
}
