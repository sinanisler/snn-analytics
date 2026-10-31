<?php
/**
 * Icons for the admin menu, chosen in Settings. A dashicon is named by its class, so WordPress colours
 * it like the other menu icons. An outline icon is drawn in white and shown as a background image.
 *
 * Outline icons come from Lucide (ISC), Tabler (MIT) and Heroicons (MIT).
 */

namespace SNN_Analytics;

defined( 'ABSPATH' ) || exit;

final class Menu_Icons {

	/** Placeholder until the default is chosen. Must be a key of ICONS. */
	const DEFAULT = 'lucide-chart-no-axes-combined';

	/** Key => label, source, and either a dashicon name or the stroke width and SVG paths of an outline icon. */
	const ICONS = [
		'dash-chart-line' => [ 'label' => 'Chart line', 'source' => 'WordPress', 'dashicon' => 'chart-line' ],
		'dash-chart-area' => [ 'label' => 'Chart area', 'source' => 'WordPress', 'dashicon' => 'chart-area' ],
		'dash-chart-bar' => [ 'label' => 'Chart bars', 'source' => 'WordPress', 'dashicon' => 'chart-bar' ],
		'dash-analytics' => [ 'label' => 'Analytics report', 'source' => 'WordPress', 'dashicon' => 'analytics' ],
		'dash-performance' => [ 'label' => 'Gauge', 'source' => 'WordPress', 'dashicon' => 'performance' ],
		'lucide-chart-line' => [ 'label' => 'Chart line', 'source' => 'Lucide', 'stroke' => 2, 'paths' => '<path d="M3 3v16a2 2 0 0 0 2 2h16" /><path d="m19 9-5 5-4-4-3 3" />' ],
		'lucide-chart-spline' => [ 'label' => 'Chart spline', 'source' => 'Lucide', 'stroke' => 2, 'paths' => '<path d="M3 3v16a2 2 0 0 0 2 2h16" /><path d="M7 16c.5-2 1.5-7 4-7 2 0 2 3 4 3 2.5 0 4.5-5 5-7" />' ],
		'lucide-chart-no-axes-combined' => [ 'label' => 'Bars and line', 'source' => 'Lucide', 'stroke' => 2, 'paths' => '<path d="M12 16v5" /><path d="M16 14.639V21" /><path d="M20 10.656V21" /><path d="m22 3-8.646 8.646a.5.5 0 0 1-.708 0L9.354 8.354a.5.5 0 0 0-.707 0L2 15" /><path d="M4 18.463V21" /><path d="M8 14.656V21" />' ],
		'lucide-trending-up' => [ 'label' => 'Trending up', 'source' => 'Lucide', 'stroke' => 2, 'paths' => '<path d="M16 7h6v6" /><path d="m22 7-8.5 8.5-5-5L2 17" />' ],
		'lucide-activity' => [ 'label' => 'Activity', 'source' => 'Lucide', 'stroke' => 2, 'paths' => '<path d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2" />' ],
		'tabler-chart-area-line' => [ 'label' => 'Area line', 'source' => 'Tabler', 'stroke' => 2, 'paths' => '<path d="M4 19l4 -6l4 2l4 -5l4 4l0 5l-16 0" /><path d="M4 12l3 -4l4 2l5 -6l4 4" />' ],
		'tabler-chart-arrows' => [ 'label' => 'Chart arrows', 'source' => 'Tabler', 'stroke' => 2, 'paths' => '<path d="M3 18l14 0" /><path d="M9 9l3 3l-3 3" /><path d="M14 15l3 3l-3 3" /><path d="M3 3l0 18" /><path d="M3 12l9 0" /><path d="M18 3l3 3l-3 3" /><path d="M3 6l18 0" />' ],
		'tabler-chart-dots-2' => [ 'label' => 'Dots', 'source' => 'Tabler', 'stroke' => 2, 'paths' => '<path d="M3 3v18h18" /><path d="M7 15a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M11 5a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M16 12a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M21 3l-6 1.5" /><path d="M14.113 6.65l2.771 3.695" /><path d="M16 12.5l-5 2" />' ],
		'tabler-graph' => [ 'label' => 'Graph panel', 'source' => 'Tabler', 'stroke' => 2, 'paths' => '<path d="M4 18v-12a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2" /><path d="M7 14l3 -3l2 2l3 -3l2 2" />' ],
		'heroicons-presentation-chart-line' => [ 'label' => 'Presentation chart', 'source' => 'Heroicons', 'stroke' => 1.5, 'paths' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5m.75-9 3-3 2.148 2.148A12.061 12.061 0 0 1 16.5 7.605"/>' ],
		'heroicons-chart-bar' => [ 'label' => 'Outline bars', 'source' => 'Heroicons', 'stroke' => 1.5, 'paths' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/>' ],
	];

	/** Returns a known key, or the default for anything else. */
	public static function sanitize( string $key ): string {
		return isset( self::ICONS[ $key ] ) ? $key : self::DEFAULT;
	}

	/** The icon argument for add_menu_page(): a dashicon class, or a base64 SVG data URI. */
	public static function value( string $key ): string {
		$icon = self::ICONS[ self::sanitize( $key ) ];
		if ( isset( $icon['dashicon'] ) ) {
			return 'dashicons-' . $icon['dashicon'];
		}

		// svg-painter.js rewrites every fill="..." in a menu icon to the menu colour, which fills the outline.
		// fill-opacity keeps the fill invisible, and the painter leaves it alone.
		$svg = sprintf(
			'<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill-opacity="0" stroke="#fff" stroke-width="%d" stroke-linecap="round" stroke-linejoin="round">%s</svg>',
			$icon['stroke'],
			$icon['paths']
		);
		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}

	/** The icon as it appears in the settings picker, at menu size. */
	public static function preview( string $key ): string {
		$value = self::value( $key );
		if ( str_starts_with( $value, 'dashicons-' ) ) {
			return '<span class="dashicons ' . esc_attr( $value ) . '" aria-hidden="true"></span>';
		}
		return '<img src="' . esc_attr( $value ) . '" alt="" width="20" height="20">';
	}
}
