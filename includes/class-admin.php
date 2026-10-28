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
		'snn-analytics-compare'   => [ 'compare', 'Compare', self::MENU_CAP ],
		'snn-analytics-heatmaps'  => [ 'heatmaps', 'Heatmaps', self::MENU_CAP ],
		'snn-analytics-replay'    => [ 'replay', 'Replay', self::MENU_CAP ],
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
		'snn-analytics-journeys',
		'snn-analytics-pages',
		'snn-analytics-compare',
		'snn-analytics-heatmaps',
		'snn-analytics-replay',
		'snn-analytics-referrers',
		'snn-analytics-geo',
		'snn-analytics-devices',
		'snn-analytics-campaigns',
		'snn-analytics-goals',
		'snn-analytics-settings',
	];

	/**
	 * Whether the current user may open a screen. Settings and Import need manage_options; every
	 * other screen follows the role choices made under Settings (administrators always pass).
	 */
	public static function can_open( string $slug ): bool {
		if ( ! isset( self::SCREENS[ $slug ] ) ) {
			return false;
		}

		[ $view, , $cap ] = self::SCREENS[ $slug ];

		return $cap === self::MANAGE_CAP ? current_user_can( $cap ) : Access::can( $view );
	}

	/** First top-bar screen the current user can open, or null. */
	public static function first_screen(): ?string {
		foreach ( self::TOP_BAR as $slug ) {
			if ( self::can_open( $slug ) ) {
				return $slug;
			}
		}

		return null;
	}

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

		// A role without the Dashboard lands on the first screen it does have.
		if ( $page === self::SLUG && current_user_can( self::MENU_CAP ) && ! self::can_open( $page ) ) {
			$first = self::first_screen();
			if ( $first !== null ) {
				wp_safe_redirect( add_query_arg( 'page', $first, admin_url( 'admin.php' ) ) );
				exit;
			}
		}

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
		if ( ! isset( self::SCREENS[ $slug ] ) ) {
			$slug = self::SLUG;
		}
		[ $view, $title ] = self::SCREENS[ $slug ];

		if ( ! self::can_open( $slug ) ) {
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
				<?php if ( ! self::can_open( $slug ) ) { continue; } ?>
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
			'can'       => array_map( static fn( $feature ) => Access::can( $feature ), array_combine( array_keys( Access::FEATURES ), array_keys( Access::FEATURES ) ) ),
			'cards'     => self::CARDS,
			'timezone'  => wp_timezone_string(),
			'pageUrl'   => admin_url( 'admin.php?page=' ),
			'site'      => (string) preg_replace( '#^(https?://[^/]+).*$#', '$1', home_url() ),
			'version'   => SNN_A_VERSION,
		] );
	}

	// ----------------------------------------------------------- settings

	/** Start of one setting: title and help on the left, the control on the right. */
	private static function row( string $title, string $help = '', string $for = '' ): void {
		?>
		<div class="snn-a-row">
			<div class="snn-a-row-text">
				<?php if ( $for !== '' ) : ?>
					<label for="<?php echo esc_attr( $for ); ?>" class="snn-a-row-title"><?php echo esc_html( $title ); ?></label>
				<?php else : ?>
					<span class="snn-a-row-title"><?php echo esc_html( $title ); ?></span>
				<?php endif; ?>
				<?php if ( $help !== '' ) : ?>
					<p class="snn-a-row-help"><?php echo esc_html( $help ); ?></p>
				<?php endif; ?>
			</div>
			<div class="snn-a-row-control">
		<?php
	}

	private static function row_end(): void {
		echo '</div></div>';
	}

	/** An on/off switch. The checkbox stays a real checkbox, so the form posts it as before. */
	private static function toggle( string $name, bool $on, string $label ): void {
		?>
		<label class="snn-a-switch">
			<input type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( $on ); ?>>
			<span class="snn-a-switch-ui" aria-hidden="true"></span>
			<span class="snn-a-switch-text"><?php echo esc_html( $label ); ?></span>
		</label>
		<?php
	}

	private static function select( string $name, array $options, $current ): void {
		?>
		<select name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $name ); ?>">
			<?php foreach ( $options as $value => $label ) : ?>
				<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( (string) $current, (string) $value ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	private static function section( string $title, string $text ): void {
		?>
		<section class="snn-a-set">
			<header class="snn-a-set-head">
				<h2><?php echo esc_html( $title ); ?></h2>
				<p><?php echo esc_html( $text ); ?></p>
			</header>
		<?php
	}

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

		$roles = wp_roles()->get_names();
		unset( $roles['administrator'] );

		?>
		<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="snn-a-form snn-a-settings">
			<input type="hidden" name="action" value="snn_a_save_settings">
			<?php wp_nonce_field( 'snn_a_save_settings' ); ?>

			<?php self::section( 'Privacy and tracking', 'Decide who is counted and how visitors are recognised.' ); ?>
				<?php self::row( 'Visitor salt rotation', 'Visitors are identified by a salted hash of the IP, browser, OS and device type. Never by stored IPs. Each rotation makes returning visitors count as new.', 'salt_rotation' ); ?>
					<?php self::select( 'salt_rotation', $rotations, $s['salt_rotation'] ); ?>
				<?php self::row_end(); ?>

				<?php self::row( 'Respect Do Not Track', 'Do not track visitors who send DNT or Global Privacy Control.' ); ?>
					<?php self::toggle( 'respect_dnt', (bool) $s['respect_dnt'], 'Enabled' ); ?>
				<?php self::row_end(); ?>

				<?php self::row( 'Do not track these roles', 'Logged-in users with these roles are not tracked.' ); ?>
					<div class="snn-a-chips">
						<?php foreach ( wp_roles()->get_names() as $role => $name ) : ?>
							<label class="snn-a-chip">
								<input type="checkbox" name="exclude_roles[]" value="<?php echo esc_attr( $role ); ?>" <?php checked( in_array( $role, $s['exclude_roles'], true ) ); ?>>
								<span><?php echo esc_html( translate_user_role( $name ) ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				<?php self::row_end(); ?>

				<?php self::row( 'Session timeout', 'A visitor without activity for this long starts a new session. Between 300 and 86400 seconds.', 'session_timeout' ); ?>
					<span class="snn-a-unit">
						<input type="number" name="session_timeout" id="session_timeout" min="300" max="86400" step="60" value="<?php echo esc_attr( (string) $s['session_timeout'] ); ?>">
						<span>seconds</span>
					</span>
				<?php self::row_end(); ?>
			</section>

			<?php self::section( 'Behaviour and data', 'What is recorded on each page and how long it is kept.' ); ?>
				<?php self::row( 'Keep raw data', 'Individual visits and journeys older than this are deleted. Daily totals are kept.', 'retention_months' ); ?>
					<?php self::select( 'retention_months', $retention, (int) $s['retention_months'] ); ?>
				<?php self::row_end(); ?>

				<?php self::row( 'Click heatmap', 'Counts only, per page, day and device. No click is stored on its own. Shown on the Heatmaps screen.' ); ?>
					<?php self::toggle( 'heatmap', (bool) $s['heatmap'], 'Record clicks' ); ?>
				<?php self::row_end(); ?>

				<?php self::row( 'Cursor trails', 'Off by default. Each view keeps one path of at most 60 seconds, sampled four times a second. Visitors are not identified by it.' ); ?>
					<?php self::toggle( 'cursor_trails', (bool) $s['cursor_trails'], 'Record pointer movement' ); ?>
				<?php self::row_end(); ?>

				<?php self::row( 'Keep trails for', 'Trails older than this are deleted.', 'cursor_retention_days' ); ?>
					<?php self::select( 'cursor_retention_days', $trails, (int) $s['cursor_retention_days'] ); ?>
				<?php self::row_end(); ?>

				<?php self::row( 'Delete data on uninstall', 'Off by default, so your statistics survive a reinstall. Turn it on only if you want a clean removal.' ); ?>
					<?php self::toggle( 'delete_on_uninstall', (bool) $s['delete_on_uninstall'], 'Delete all data and tables' ); ?>
				<?php self::row_end(); ?>
			</section>

			<?php self::section( 'Who can see what', 'Administrators always see everything. Tick the roles that may use each feature, which is useful on client sites. Settings and Import stay administrator-only.' ); ?>
				<?php if ( $roles === [] ) : ?>
					<p class="snn-a-row-help">There are no roles other than administrator.</p>
				<?php else : ?>
					<div class="snn-a-scroll">
						<table class="snn-a-matrix">
							<thead>
								<tr>
									<th scope="col">Feature</th>
									<?php foreach ( $roles as $name ) : ?>
										<th scope="col"><?php echo esc_html( translate_user_role( $name ) ); ?></th>
									<?php endforeach; ?>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( Access::FEATURES as $feature => $label ) : ?>
									<?php $allowed = Access::roles_for( $feature ); ?>
									<tr>
										<th scope="row"><?php echo esc_html( $label ); ?></th>
										<?php foreach ( $roles as $role => $name ) : ?>
											<td>
												<input type="checkbox" name="role_access[<?php echo esc_attr( $feature ); ?>][]" value="<?php echo esc_attr( $role ); ?>" <?php checked( in_array( $role, $allowed, true ) ); ?> aria-label="<?php echo esc_attr( $label . ': ' . translate_user_role( $name ) ); ?>">
											</td>
										<?php endforeach; ?>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<p class="snn-a-row-help">The Dashboard also shows summary numbers from the other reports. Give Dashboard access only to roles that may see them.</p>
				<?php endif; ?>
			</section>

			<div class="snn-a-savebar">
				<?php submit_button( 'Save settings', 'primary', 'submit', false ); ?>
			</div>
		</form>

		<h2 class="snn-a-tools-title">Tools</h2>
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
			'role_access'           => Access::sanitize( wp_unslash( $_POST['role_access'] ?? [] ) ),
		] );

		wp_safe_redirect( add_query_arg(
			[ 'page' => 'snn-analytics-settings', 'saved' => 1 ],
			admin_url( 'admin.php' )
		) );
		exit;
	}
}
