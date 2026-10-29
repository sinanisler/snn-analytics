<?php
/**
 * How much space the optional click and cursor data can take. Shown on the Settings screen as a
 * warning, with a popup that charts it against traffic.
 *
 * These are estimates from typical row sizes, not measurements. They are set on the high side so
 * nobody is surprised by the database growing.
 */

namespace SNN_Analytics;

defined( 'ABSPATH' ) || exit;

final class Scale {

	/** Bytes per row, counting its share of the indexes. */
	const SESSION_BYTES = 150;
	const VIEW_BYTES    = 100;
	const TRAIL_BYTES   = 80;  // one trail row, before its samples
	const HEAT_BYTES    = 50;  // one heatmap counter

	/** The tracker writes one 6-byte sample every 250 ms while the pointer moves. */
	const SAMPLE_BYTES       = 6;
	const SAMPLES_PER_SECOND = 4;
	const MAX_TRAIL_SECONDS  = 1800;

	const PAGES_PER_VISIT = 2;
	const DAYS_PER_MONTH  = 30.44;

	/** Heatmap: pages that get clicks, and the click spots kept for each page per day. */
	const BUSY_PAGES      = 10;
	const SPOTS_PER_PAGE  = 50;

	/** Seconds of pointer movement per page view, for each estimate. */
	const PACE = [ 'light' => 5, 'typical' => 20, 'heavy' => 60 ];

	/** Visits per month on the charts. Most shared hosts are in this range. */
	const LEVELS = [ 1000 => '1k', 10000 => '10k', 50000 => '50k', 100000 => '100k' ];

	const COLOR_VISITS = '#8c8f94';
	const COLOR_TYPICAL = '#b32d2e';
	const COLOR_HEAVY = '#6b1b1c';

	public static function visit_bytes(): int {
		return self::SESSION_BYTES + self::VIEW_BYTES * self::PAGES_PER_VISIT;
	}

	/** Bytes for one page view's trail, with the pointer moving for $seconds. */
	public static function trail_bytes( int $seconds ): int {
		$seconds = min( self::MAX_TRAIL_SECONDS, $seconds );

		return self::TRAIL_BYTES + $seconds * self::SAMPLES_PER_SECOND * self::SAMPLE_BYTES;
	}

	/** Trail bytes for one visit at a pace, which covers every page view in the visit. */
	public static function trail_visit_bytes( string $pace ): int {
		return self::trail_bytes( self::PACE[ $pace ] ) * self::PAGES_PER_VISIT;
	}

	/** Heatmap bytes per year for a number of pages that get clicks. Visits do not change it. */
	public static function heat_year_bytes( int $pages ): int {
		return $pages * 365 * self::SPOTS_PER_PAGE * self::HEAT_BYTES;
	}

	/** Visits in the last 30 days, from the daily totals. */
	public static function recent_visits(): int {
		global $wpdb;

		$from = Ranges::day_of( time() ) - 29;

		return (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COALESCE(SUM(sessions), 0) FROM ' . DB::table( 'd_site' ) . ' WHERE day >= %d',
			$from
		) );
	}

	/** "350 KB", "11 MB" or "1.1 GB". */
	public static function size( int $bytes ): string {
		if ( $bytes < 1048576 ) {
			return round( $bytes / 1024 ) . ' KB';
		}
		if ( $bytes < 1073741824 ) {
			return number_format( $bytes / 1048576, $bytes < 10 * 1048576 ? 1 : 0 ) . ' MB';
		}

		return number_format( $bytes / 1073741824, 1 ) . ' GB';
	}

	/** The warning for one optional feature: 'heat' for clicks, 'trails' for cursor trails. Returns escaped HTML. */
	public static function warning( string $feature ): string {
		return $feature === 'heat' ? self::heat_warning() : self::trail_warning();
	}

	private static function trail_warning(): string {
		$days   = (int) Settings::get()['cursor_retention_days'];
		$visits = self::recent_visits();
		$month  = $visits * self::trail_visit_bytes( 'typical' );
		$ratio  = (int) round( self::trail_visit_bytes( 'typical' ) / self::visit_bytes() );

		$live = $visits > 0
			? sprintf(
				'At your traffic (%s visits in the last 30 days), trails would add about %s a month. Kept for %d days, they would take about %s.',
				number_format( $visits ),
				self::size( $month ),
				$days,
				self::size( (int) round( $month * $days / self::DAYS_PER_MONTH ) )
			)
			: 'You have no visits in the last 30 days yet. The popup shows what different amounts of traffic would add.';

		return self::box(
			'trails',
			'Can fill a small database quickly',
			sprintf(
				'Every page view where the mouse moves keeps its path, sampled four times a second. This is the largest data the plugin stores, about %d times the size of the visit data itself. On shared hosting with a small database, keep it off, or keep trails for 14 days only.',
				$ratio
			),
			$live,
			self::trail_popup()
		);
	}

	private static function heat_warning(): string {
		return self::box(
			'heat',
			'Small, but it never goes away',
			'Each click adds one to a counter for its spot on the page. The table grows with the number of different spots people click, not with the number of visits. The retention setting does not remove these counters. Most hosts handle this easily. If you never open the Heatmaps screen, turn it off.',
			sprintf( 'Estimate: about %s a year for %d pages that get clicks.', self::size( self::heat_year_bytes( self::BUSY_PAGES ) ), self::BUSY_PAGES ),
			self::heat_popup()
		);
	}

	private static function trail_popup(): string {
		$levels = array_keys( self::LEVELS );

		return self::chart_block(
			'Each month, by visits per month',
			'Visits per month',
			array_values( self::LEVELS ),
			[
				[ 'Visit data', self::COLOR_VISITS, array_map( static fn( int $v ) => $v * self::visit_bytes(), $levels ) ],
				[ 'Trails, typical', self::COLOR_TYPICAL, array_map( static fn( int $v ) => $v * self::trail_visit_bytes( 'typical' ), $levels ) ],
				[ 'Trails, heavy', self::COLOR_HEAVY, array_map( static fn( int $v ) => $v * self::trail_visit_bytes( 'heavy' ), $levels ) ],
			],
			'Double the visits, double the data. Typical means about 20 seconds of mouse movement on each page view. Light is 5 seconds and heavy is 60. Trails are kept for the number of days you choose, so the stored total is about one month of trails for every 30 days kept.'
		);
	}

	private static function heat_popup(): string {
		$pages = [ 1, 5, 20, 50 ];

		return self::chart_block(
			'Each year, by pages that get clicks',
			'Pages that get clicks',
			array_map( static fn( int $p ) => $p . ( $p === 1 ? ' page' : ' pages' ), $pages ),
			[
				[ 'Click heatmap, per year', self::COLOR_TYPICAL, array_map( static fn( int $p ) => self::heat_year_bytes( $p ), $pages ) ],
			],
			'Each page keeps one counter for every spot clicked, per day. The size depends on how many pages get clicks, not on visits. Nothing removes these counters.'
		);
	}

	/** Wraps the warning text and the popup trigger. Every piece is escaped here, so the result can be echoed. */
	private static function box( string $feature, string $title, string $text, string $live, string $popup ): string {
		$id = 'snn-a-pop-' . $feature;

		return '<div class="snn-a-scale-warn" role="note">'
			. '<p class="snn-a-scale-title">' . esc_html( $title ) . '</p>'
			. '<p class="snn-a-scale-text">' . esc_html( $text ) . '</p>'
			. '<p class="snn-a-scale-live">' . esc_html( $live ) . '</p>'
			. '<div class="snn-a-qwrap">'
			. '<button type="button" class="snn-a-q" aria-describedby="' . esc_attr( $id ) . '" aria-label="' . esc_attr__( 'How this grows with traffic', 'snn-analytics' ) . '">?</button>'
			. '<div class="snn-a-pop" role="tooltip" id="' . esc_attr( $id ) . '">' . $popup . '</div>'
			. '</div>'
			. '</div>';
	}

	/**
	 * The popup body: a bar chart, its legend, and the same numbers as a table for screen readers
	 * and for anyone who wants exact values.
	 *
	 * @param array<int, array{0: string, 1: string, 2: int[]}> $series Name, colour and one value per label.
	 */
	private static function chart_block( string $caption, string $axis, array $labels, array $series, string $note ): string {
		$legend = '';
		foreach ( $series as [ $name, $color ] ) {
			$legend .= '<li><span class="snn-a-swatch" style="background:' . esc_attr( $color ) . '"></span>' . esc_html( $name ) . '</li>';
		}

		$head = '<th scope="col">' . esc_html( $axis ) . '</th>';
		foreach ( $series as [ $name ] ) {
			$head .= '<th scope="col">' . esc_html( $name ) . '</th>';
		}

		$body = '';
		foreach ( $labels as $i => $label ) {
			$body .= '<tr><th scope="row">' . esc_html( $label ) . '</th>';
			foreach ( $series as [ , , $values ] ) {
				$body .= '<td>' . esc_html( self::size( (int) ( $values[ $i ] ?? 0 ) ) ) . '</td>';
			}
			$body .= '</tr>';
		}

		return '<p class="snn-a-pop-title">' . esc_html( $caption ) . '</p>'
			. self::bars( $caption, $labels, $series )
			. '<ul class="snn-a-scale-legend">' . $legend . '</ul>'
			. '<table class="snn-a-scale-table"><thead><tr>' . $head . '</tr></thead><tbody>' . $body . '</tbody></table>'
			. '<p class="snn-a-pop-note">' . esc_html( $note ) . '</p>';
	}

	/** Grouped bars on one linear scale, so the tallest bar shows where the space goes. */
	private static function bars( string $caption, array $labels, array $series ): string {
		$width  = 360;
		$height = 200;
		$left   = 56;
		$right  = 8;
		$top    = 8;
		$bottom = 26;
		$plot_w = $width - $left - $right;
		$plot_h = $height - $top - $bottom;
		$base   = $top + $plot_h;

		$max = max( array_merge( [ 1 ], ...array_map( static fn( array $s ) => $s[2], $series ) ) );
		$group = $plot_w / max( 1, count( $labels ) );
		$bar   = min( 24, $group * 0.8 / count( $series ) - 3 );

		$svg = '<svg class="snn-a-scale-svg" viewBox="0 0 ' . $width . ' ' . $height . '" role="img" aria-label="' . esc_attr( $caption ) . '">'
			. '<line x1="' . $left . '" y1="' . $base . '" x2="' . ( $width - $right ) . '" y2="' . $base . '" stroke="#dadce0"/>'
			. '<text x="' . ( $left - 6 ) . '" y="' . ( $top + 4 ) . '" text-anchor="end">' . esc_html( self::size( $max ) ) . '</text>'
			. '<text x="' . ( $left - 6 ) . '" y="' . $base . '" text-anchor="end">0</text>';

		foreach ( $labels as $i => $label ) {
			$start = $left + $i * $group + ( $group - $bar * count( $series ) ) / 2;

			foreach ( $series as $j => [ $name, $color, $values ] ) {
				$value = (int) ( $values[ $i ] ?? 0 );
				$px    = $plot_h * $value / $max;
				$svg  .= '<rect x="' . round( $start + $j * $bar, 1 ) . '" y="' . round( $base - $px, 1 ) . '" width="' . round( $bar - 2, 1 ) . '" height="' . round( $px, 1 ) . '" fill="' . esc_attr( $color ) . '">'
					. '<title>' . esc_html( $name . ', ' . $label . ': ' . self::size( $value ) ) . '</title></rect>';
			}

			$svg .= '<text x="' . round( $left + ( $i + 0.5 ) * $group, 1 ) . '" y="' . ( $height - 6 ) . '" text-anchor="middle">' . esc_html( $label ) . '</text>';
		}

		return $svg . '</svg>';
	}
}
