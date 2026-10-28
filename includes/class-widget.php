<?php
/**
 * WordPress dashboard widget: the key numbers with small charts.
 *
 * Rendered on the server with inline SVG, so the Dashboard screen loads no extra scripts.
 * Each user configures their own copy (range, numbers, charts, lists) through the widget's
 * Configure link. Who sees the widget is decided under Settings > Who can see what.
 */

namespace SNN_Analytics;

defined( 'ABSPATH' ) || exit;

final class Widget {

	const ID   = 'snn_a_widget';
	const META = 'snn_a_widget';

	/** Metric key => label. Order here is the order on screen. */
	const METRICS = [
		'visitors' => 'Visitors',
		'views'    => 'Page views',
		'sessions' => 'Sessions',
		'bounce'   => 'Bounce rate',
		'duration' => 'Avg. visit time',
		'depth'    => 'Pages per visit',
	];

	const RANGES = [
		'today' => 'Today',
		'7d'    => 'Last 7 days',
		'30d'   => 'Last 30 days',
		'90d'   => 'Last 90 days',
	];

	public static function register(): void {
		add_action( 'wp_dashboard_setup', [ self::class, 'setup' ] );
	}

	public static function setup(): void {
		if ( ! Access::can( 'widget' ) ) {
			return;
		}

		wp_add_dashboard_widget( self::ID, 'SNN Analytics', [ self::class, 'render' ], [ self::class, 'configure' ] );
	}

	public static function defaults(): array {
		return [
			'range'     => '7d',
			'metrics'   => [ 'visitors', 'views', 'sessions', 'bounce' ],
			'sparks'    => true,   // small chart inside each number
			'chart'     => true,   // larger views and visitors chart
			'compare'   => true,   // change against the previous period
			'live'      => true,   // visitors online now
			'top_pages' => 5,
			'sources'   => 3,
		];
	}

	public static function prefs(): array {
		$saved = get_user_meta( get_current_user_id(), self::META, true );

		return array_merge( self::defaults(), is_array( $saved ) ? $saved : [] );
	}

	// ------------------------------------------------------------- configure

	public static function configure(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce is checked on the next line
		if ( isset( $_POST[ self::META ] ) && current_user_can( Access::cap( 'widget' ) ) ) {
			check_admin_referer( self::ID );

			$in      = (array) wp_unslash( $_POST[ self::META ] );
			$metrics = array_values( array_intersect( array_keys( self::METRICS ), array_map( 'sanitize_key', (array) ( $in['metrics'] ?? [] ) ) ) );
			$range   = sanitize_key( (string) ( $in['range'] ?? '' ) );

			update_user_meta( get_current_user_id(), self::META, [
				'range'     => isset( self::RANGES[ $range ] ) ? $range : '7d',
				'metrics'   => $metrics,
				'sparks'    => isset( $in['sparks'] ),
				'chart'     => isset( $in['chart'] ),
				'compare'   => isset( $in['compare'] ),
				'live'      => isset( $in['live'] ),
				'top_pages' => min( 10, max( 0, (int) ( $in['top_pages'] ?? 5 ) ) ),
				'sources'   => min( 10, max( 0, (int) ( $in['sources'] ?? 3 ) ) ),
			] );
		}
		// phpcs:enable

		$p = self::prefs();
		wp_nonce_field( self::ID );

		$name = static fn( string $key ): string => self::META . '[' . $key . ']';
		$list = [ 0 => 'Hidden', 3 => '3', 5 => '5', 10 => '10' ];

		?>
		<p>
			<label for="snn-a-w-range"><strong>Period</strong></label><br>
			<select id="snn-a-w-range" name="<?php echo esc_attr( $name( 'range' ) ); ?>">
				<?php foreach ( self::RANGES as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $p['range'], $key ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<fieldset>
			<legend><strong>Numbers to show</strong></legend>
			<?php foreach ( self::METRICS as $key => $label ) : ?>
				<label style="display:inline-block;margin:0 14px 4px 0">
					<input type="checkbox" name="<?php echo esc_attr( $name( 'metrics' ) ); ?>[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, (array) $p['metrics'], true ) ); ?>>
					<?php echo esc_html( $label ); ?>
				</label>
			<?php endforeach; ?>
		</fieldset>
		<p>
			<label style="display:block"><input type="checkbox" name="<?php echo esc_attr( $name( 'sparks' ) ); ?>" value="1" <?php checked( $p['sparks'] ); ?>> Small chart inside each number</label>
			<label style="display:block"><input type="checkbox" name="<?php echo esc_attr( $name( 'chart' ) ); ?>" value="1" <?php checked( $p['chart'] ); ?>> Views and visitors chart</label>
			<label style="display:block"><input type="checkbox" name="<?php echo esc_attr( $name( 'compare' ) ); ?>" value="1" <?php checked( $p['compare'] ); ?>> Show change against the previous period</label>
			<label style="display:block"><input type="checkbox" name="<?php echo esc_attr( $name( 'live' ) ); ?>" value="1" <?php checked( $p['live'] ); ?>> Show visitors online now</label>
		</p>
		<p>
			<label for="snn-a-w-pages"><strong>Top pages</strong></label>
			<select id="snn-a-w-pages" name="<?php echo esc_attr( $name( 'top_pages' ) ); ?>">
				<?php foreach ( $list as $value => $label ) : ?>
					<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( (int) $p['top_pages'], $value ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			&nbsp;
			<label for="snn-a-w-sources"><strong>Traffic sources</strong></label>
			<select id="snn-a-w-sources" name="<?php echo esc_attr( $name( 'sources' ) ); ?>">
				<?php foreach ( $list as $value => $label ) : ?>
					<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( (int) $p['sources'], $value ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php
	}

	// ---------------------------------------------------------------- render

	public static function render(): void {
		$p       = self::prefs();
		$r       = Ranges::resolve( (string) $p['range'] );
		$data    = Stats::overview( $r );
		$metrics = array_values( array_intersect( array_keys( self::METRICS ), (array) $p['metrics'] ) );

		self::styles();

		echo '<div class="snn-a-w">';

		if ( $p['live'] ) {
			$now = Stats::active_now();
			printf(
				'<p class="snn-a-w-live"><span class="snn-a-w-dot" aria-hidden="true"></span><strong>%s</strong> %s</p>',
				esc_html( number_format_i18n( $now ) ),
				esc_html( _n( 'visitor online now', 'visitors online now', $now, 'snn-analytics' ) )
			);
		}

		if ( $metrics !== [] ) {
			echo '<div class="snn-a-w-tiles">';
			foreach ( $metrics as $key ) {
				self::tile( $key, $data, (bool) $p['sparks'], (bool) $p['compare'] );
			}
			echo '</div>';
		}

		if ( $p['chart'] ) {
			self::big_chart( $data['series'] );
		}

		if ( (int) $p['top_pages'] > 0 ) {
			$pages = Stats::pages( $r, [ 'search' => '', 'orderby' => 'views', 'order' => 'desc', 'page' => 1, 'per_page' => (int) $p['top_pages'] ] )['rows'];
			self::list(
				'Top pages',
				array_map( static fn( $row ) => [ $row['title'] !== '' ? $row['title'] : $row['path'], (int) $row['views'] ], $pages ),
				'views'
			);
		}

		if ( (int) $p['sources'] > 0 ) {
			$sources = array_slice( Stats::referrers( $r )['domains'], 0, (int) $p['sources'] );
			self::list(
				'Traffic sources',
				array_map( static fn( $row ) => [ $row['domain'], (int) $row['sessions'] ], $sources ),
				'visits'
			);
		}

		printf( '<p class="snn-a-w-foot"><span>%s</span>', esc_html( self::RANGES[ $p['range'] ] ?? '' ) );
		$first = Admin::first_screen();
		if ( $first !== null ) {
			printf( '<a href="%s">View full report →</a>', esc_url( add_query_arg( 'page', $first, admin_url( 'admin.php' ) ) ) );
		}
		echo '</p></div>';
	}

	/** One number with its change and, optionally, a small chart. */
	private static function tile( string $key, array $data, bool $spark, bool $compare ): void {
		$cur  = $data['current'];
		$prev = $data['previous'];

		// Value, previous value, formatted value, per-day series, and whether a higher number is better.
		[ $value, $before, $text, $series, $up_is_good ] = match ( $key ) {
			'visitors' => [ $cur['visitors'], $prev['visitors'], number_format_i18n( $cur['visitors'] ), array_column( $data['series'], 'visitors' ), true ],
			'views'    => [ $cur['views'], $prev['views'], number_format_i18n( $cur['views'] ), array_column( $data['series'], 'views' ), true ],
			'sessions' => [ $cur['sessions'], $prev['sessions'], number_format_i18n( $cur['sessions'] ), array_column( $data['series'], 'sessions' ), true ],
			'bounce'   => [ $cur['bounce_rate'], $prev['bounce_rate'], $cur['bounce_rate'] . '%', array_map( static fn( $d ) => $d['sessions'] > 0 ? 100 * $d['bounces'] / $d['sessions'] : 0, $data['series'] ), false ],
			'duration' => [ $cur['avg_duration'], $prev['avg_duration'], self::clock( (int) $cur['avg_duration'] ), array_map( static fn( $d ) => $d['sessions'] > 0 ? $d['duration_sum'] / $d['sessions'] : 0, $data['series'] ), true ],
			default    => [ $cur['views_per_session'], $prev['views_per_session'], (string) $cur['views_per_session'], array_map( static fn( $d ) => $d['sessions'] > 0 ? $d['views'] / $d['sessions'] : 0, $data['series'] ), true ],
		};

		echo '<div class="snn-a-w-tile">';
		printf( '<span class="snn-a-w-label">%s</span>', esc_html( self::METRICS[ $key ] ) );
		printf( '<span class="snn-a-w-value">%s</span>', esc_html( $text ) );

		if ( $compare ) {
			if ( (float) $before > 0 ) {
				$change = ( $value - $before ) / $before * 100;
				$good   = $up_is_good ? $change >= 0 : $change <= 0;
				printf(
					'<span class="snn-a-w-delta %s">%s%s%%</span>',
					$change == 0 ? '' : ( $good ? 'is-good' : 'is-bad' ), // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual
					$change > 0 ? '+' : '',
					esc_html( number_format_i18n( $change, 0 ) )
				);
			} else {
				echo '<span class="snn-a-w-delta">–</span>';
			}
		}

		if ( $spark ) {
			echo self::sparkline( $series ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from numbers only
		}

		echo '</div>';
	}

	/** Views and visitors over the period. */
	private static function big_chart( array $series ): void {
		$views    = array_column( $series, 'views' );
		$visitors = array_column( $series, 'visitors' );

		if ( count( $series ) < 2 || ( array_sum( $views ) === 0 && array_sum( $visitors ) === 0 ) ) {
			return;
		}

		$max = max( 1, max( $views ), max( $visitors ) );
		$w   = 300;
		$h   = 70;

		$points = static function ( array $values ) use ( $max, $w, $h ): string {
			$last = count( $values ) - 1;
			$out  = [];
			foreach ( $values as $i => $v ) {
				$out[] = round( $i / $last * $w, 1 ) . ',' . round( $h - 4 - ( $v / $max ) * ( $h - 8 ), 1 );
			}

			return implode( ' ', $out );
		};

		$line = $points( $views );

		echo '<div class="snn-a-w-chart">';
		printf( '<svg viewBox="0 0 %d %d" preserveAspectRatio="none" role="img" aria-label="%s">', $w, $h, esc_attr( 'Views and visitors per day' ) );
		printf( '<polygon points="0,%1$d %2$s %3$d,%1$d" class="snn-a-w-area"/>', $h, esc_attr( $line ), $w );
		printf( '<polyline points="%s" class="snn-a-w-line-views"/>', esc_attr( $line ) );
		printf( '<polyline points="%s" class="snn-a-w-line-visitors"/>', esc_attr( $points( $visitors ) ) );
		echo '</svg>';
		echo '<p class="snn-a-w-legend"><span><i class="v"></i>Views</span><span><i class="u"></i>Visitors</span></p>';
		echo '</div>';
	}

	/** Small line chart for one number, drawn from its daily values. Returns an SVG string. */
	private static function sparkline( array $values ): string {
		$values = array_map( 'floatval', array_values( $values ) );
		$count  = count( $values );
		if ( $count < 2 ) {
			return '';
		}

		$w   = 100;
		$h   = 26;
		$max = max( $values );
		$min = min( $values );
		$gap = $max - $min;
		$pts = [];

		foreach ( $values as $i => $v ) {
			$y     = $gap > 0 ? $h - 3 - ( ( $v - $min ) / $gap ) * ( $h - 6 ) : $h - 3;
			$pts[] = round( $i / ( $count - 1 ) * $w, 1 ) . ',' . round( $y, 1 );
		}

		$line = implode( ' ', $pts );

		return sprintf(
			'<svg class="snn-a-w-spark" viewBox="0 0 %1$d %2$d" preserveAspectRatio="none" aria-hidden="true"><polygon points="0,%2$d %3$s %1$d,%2$d"/><polyline points="%3$s"/></svg>',
			$w,
			$h,
			esc_attr( $line )
		);
	}

	/** A short ranked list with proportional bars. Rows are [ label, number ]. */
	private static function list( string $title, array $rows, string $unit ): void {
		echo '<div class="snn-a-w-list">';
		printf( '<h3>%s</h3>', esc_html( $title ) );

		if ( $rows === [] ) {
			echo '<p class="snn-a-w-empty">No data in this period.</p></div>';
			return;
		}

		$max = max( 1, max( array_column( $rows, 1 ) ) );

		echo '<ul>';
		foreach ( $rows as [ $label, $count ] ) {
			printf(
				'<li><span class="snn-a-w-bar" style="width:%s%%"></span><span class="snn-a-w-name" title="%s">%s</span><span class="snn-a-w-count">%s</span></li>',
				esc_attr( (string) round( $count / $max * 100 ) ),
				esc_attr( $label ),
				esc_html( $label ),
				esc_html( number_format_i18n( $count ) )
			);
		}
		echo '</ul></div>';
	}

	private static function clock( int $seconds ): string {
		return floor( $seconds / 60 ) . ':' . str_pad( (string) ( $seconds % 60 ), 2, '0', STR_PAD_LEFT );
	}

	/** Printed once per page, next to the widget it styles. */
	private static function styles(): void {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;

		?>
		<style>
			.snn-a-w { --c: var(--wp-admin-theme-color, #2271b1); --c2: #8c5cd6; }
			.snn-a-w-live { margin: 0 0 12px; display: flex; align-items: center; gap: 6px; color: #50575e; }
			.snn-a-w-dot { width: 8px; height: 8px; border-radius: 50%; background: #00a32a; box-shadow: 0 0 0 0 rgba(0,163,42,.5); animation: snn-a-w-pulse 2s infinite; }
			@keyframes snn-a-w-pulse { 70% { box-shadow: 0 0 0 7px rgba(0,163,42,0); } 100% { box-shadow: 0 0 0 0 rgba(0,163,42,0); } }
			@media (prefers-reduced-motion: reduce) { .snn-a-w-dot { animation: none; } }
			.snn-a-w-tiles { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 10px; }
			.snn-a-w-tile { display: flex; flex-direction: column; gap: 2px; padding: 10px 12px; border: 1px solid #e3e5e8; border-radius: 10px; background: #fafafa; min-width: 0; }
			.snn-a-w-label { font-size: 12px; color: #646970; }
			.snn-a-w-value { font-size: 22px; line-height: 1.2; font-weight: 600; color: #1d2327; }
			.snn-a-w-delta { font-size: 12px; color: #646970; }
			.snn-a-w-delta.is-good { color: #00843d; }
			.snn-a-w-delta.is-bad { color: #b32d2e; }
			.snn-a-w-spark { width: 100%; height: 26px; margin-top: 4px; overflow: visible; }
			.snn-a-w-spark polyline { fill: none; stroke: var(--c); stroke-width: 2; vector-effect: non-scaling-stroke; stroke-linejoin: round; stroke-linecap: round; }
			.snn-a-w-spark polygon { fill: var(--c); opacity: .1; }
			.snn-a-w-chart { margin-top: 14px; }
			.snn-a-w-chart svg { display: block; width: 100%; height: 80px; }
			.snn-a-w-chart polyline { fill: none; stroke-width: 2; vector-effect: non-scaling-stroke; stroke-linejoin: round; }
			.snn-a-w-line-views { stroke: var(--c2); }
			.snn-a-w-line-visitors { stroke: var(--c); stroke-dasharray: 4 3; }
			.snn-a-w-area { fill: var(--c2); opacity: .1; }
			.snn-a-w-legend { display: flex; gap: 14px; margin: 4px 0 0; font-size: 12px; color: #646970; }
			.snn-a-w-legend i { display: inline-block; width: 10px; height: 3px; margin-right: 5px; vertical-align: middle; border-radius: 2px; }
			.snn-a-w-legend .v { background: var(--c2); }
			.snn-a-w-legend .u { background: var(--c); }
			.snn-a-w-list { margin-top: 16px; }
			.snn-a-w-list h3 { margin: 0 0 6px; padding: 0; font-size: 13px; }
			.snn-a-w-list ul { margin: 0; }
			.snn-a-w-list li { position: relative; display: flex; justify-content: space-between; gap: 10px; margin: 0 0 4px; padding: 5px 8px; border-radius: 6px; overflow: hidden; }
			.snn-a-w-bar { position: absolute; inset: 0 auto 0 0; background: var(--c); opacity: .09; border-radius: 6px; }
			.snn-a-w-name { position: relative; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
			.snn-a-w-count { position: relative; font-variant-numeric: tabular-nums; color: #50575e; }
			.snn-a-w-empty { margin: 0; color: #646970; }
			.snn-a-w-foot { display: flex; justify-content: space-between; margin: 14px 0 0; padding-top: 10px; border-top: 1px solid #f0f0f1; font-size: 12px; color: #646970; }
		</style>
		<?php
	}
}
