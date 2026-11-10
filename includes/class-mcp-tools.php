<?php
/**
 * The tools the built-in MCP server offers. Each one reads or changes data the reports already show,
 * through the same Stats and Goals functions the REST routes use.
 *
 * A tool belongs to a group, and the MCP screen switches Read and Write per group. A tool is offered
 * only when its group allows it, a write tool needs a token with write access, and the user the token
 * acts as may use one of the features the tool reads from (the same checks as the REST routes).
 */

namespace SNN_Analytics;

defined( 'ABSPATH' ) || exit;

final class MCP_Tools {

	/** Group key => label, in the order the MCP screen lists them. */
	const GROUPS = [
		'overview' => 'Overview and realtime',
		'pages'    => 'Pages',
		'sources'  => 'Sources, places and devices',
		'visitors' => 'Visitors and journeys',
		'goals'    => 'Conversion Tracking: goals, funnels and clicks',
	];

	/** Goal type names an assistant sends => the stored type. */
	const GOAL_TYPES = [
		'page'  => Goals::TYPE_PAGE,
		'click' => Goals::TYPE_CLICK,
		'event' => Goals::TYPE_EVENT,
	];

	/** Match names an assistant sends => the stored match type. */
	const GOAL_MATCH = [
		'exact'    => Goals::MATCH_EXACT,
		'contains' => Goals::MATCH_CONTAINS,
		'prefix'   => Goals::MATCH_PREFIX,
	];

	/** Name => definition, built once per request. */
	private static ?array $tools = null;

	/** Every tool, whether or not the current caller may use it. */
	public static function all(): array {
		return self::$tools ??= self::build();
	}

	/**
	 * The tool with this name when the caller may use it, otherwise null. The access is the token's:
	 * 'read' or 'write'.
	 */
	public static function find( string $name, string $access ): ?array {
		$tool = self::all()[ $name ] ?? null;

		return $tool !== null && self::allowed( $tool, $access ) ? $tool : null;
	}

	/** Tools the caller may use, keyed by name. */
	public static function visible( string $access ): array {
		return array_filter( self::all(), static fn( array $tool ): bool => self::allowed( $tool, $access ) );
	}

	/** Entries for tools/list. */
	public static function describe( array $tools ): array {
		$out = [];

		foreach ( $tools as $name => $tool ) {
			$schema = [ 'type' => 'object', 'properties' => (object) $tool['properties'] ];
			if ( $tool['required'] !== [] ) {
				$schema['required'] = $tool['required'];
			}

			$out[] = [
				'name'        => (string) $name,
				'title'       => $tool['title'],
				'description' => $tool['description'],
				'inputSchema' => $schema,
				'annotations' => [
					'title'           => $tool['title'],
					'readOnlyHint'    => ! $tool['write'],
					'destructiveHint' => $tool['destructive'],
					'openWorldHint'   => false,
				],
			];
		}

		return $out;
	}

	/**
	 * Runs a tool and returns an MCP CallToolResult. Bad input and refusals come back as a tool error,
	 * so the assistant can read the reason and try again.
	 */
	public static function run( array $tool, array $args ): array {
		foreach ( $tool['required'] as $key ) {
			if ( ! isset( $args[ $key ] ) || $args[ $key ] === '' ) {
				return self::failed( 'Missing argument: ' . $key . '.' );
			}
		}

		try {
			$data = ( $tool['run'] )( $args );
		} catch ( \InvalidArgumentException $e ) {
			return self::failed( $e->getMessage() );
		}

		return self::succeeded( $data );
	}

	private static function allowed( array $tool, string $access ): bool {
		if ( $tool['write'] && $access !== 'write' ) {
			return false;
		}

		if ( ! MCP::group_allows( $tool['group'], $tool['write'] ? 'write' : 'read' ) ) {
			return false;
		}

		return Access::can_any( ...$tool['features'] );
	}

	private static function succeeded( mixed $data ): array {
		$data = is_array( $data ) ? $data : [ 'value' => $data ];

		// structuredContent must be a JSON object, so a plain list is wrapped.
		if ( self::is_list( $data ) ) {
			$data = [ 'items' => $data ];
		}

		$json = wp_json_encode( $data, JSON_INVALID_UTF8_SUBSTITUTE );

		return [
			'content'           => [ [ 'type' => 'text', 'text' => $json === false ? '{}' : $json ] ],
			'structuredContent' => $data,
			'isError'           => false,
		];
	}

	private static function failed( string $message ): array {
		return [
			'content' => [ [ 'type' => 'text', 'text' => $message ] ],
			'isError' => true,
		];
	}

	private static function is_list( array $array ): bool {
		return $array === [] || array_keys( $array ) === range( 0, count( $array ) - 1 );
	}

	// ------------------------------------------------------------ definitions

	/** The Read tools and the one Write group. Each entry is a read or write definition, see read() and write(). */
	private static function build(): array {
		$period = self::period_props();

		return [
			'analytics_overview' => self::read( 'overview', [ 'dashboard' ], 'Site overview',
				'Visitors, sessions, views, bounce rate and time on site for the period, the same figures for the period before it, and a daily series. Start here for the headline numbers.',
				$period, static fn( array $a ): array => Stats::overview( self::period( $a ) ) ),

			'analytics_realtime' => self::read( 'overview', [ 'realtime', 'dashboard' ], 'Live visitors',
				'Visitors on the site right now, and page views per minute for the last 30 minutes.',
				[], static fn(): array => Stats::realtime() ),

			'analytics_recent_views' => self::read( 'overview', [ 'realtime', 'dashboard', 'journeys' ], 'Latest page views',
				'The latest page views, newest first.',
				[ 'limit' => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 10, 'description' => 'How many views to return, up to 50.' ] ],
				static fn( array $a ): array => Stats::recent( self::int( $a, 'limit', 10, 1, 50 ) ) ),

			'analytics_pages' => self::read( 'pages', [ 'pages', 'dashboard', 'compare', 'heatmaps' ], 'Pages',
				'Pages ranked by views (or another measure), with visitors, entrances, exits and time on page. Each row has the page id that the other page tools take. Use search to find a page by its address or title.',
				$period + [
					'search'   => [ 'type' => 'string', 'description' => 'Text to look for in the page address or title.' ],
					'orderby'  => [ 'type' => 'string', 'enum' => [ 'views', 'visitors', 'duration', 'entrances', 'exits', 'title' ], 'default' => 'views' ],
					'order'    => [ 'type' => 'string', 'enum' => [ 'desc', 'asc' ], 'default' => 'desc' ],
				] + self::paging( 25, 100 ),
				static fn( array $a ): array => Stats::pages( self::period( $a ), [
					'search'   => (string) ( $a['search'] ?? '' ),
					'orderby'  => (string) ( $a['orderby'] ?? 'views' ),
					'order'    => (string) ( $a['order'] ?? 'desc' ),
					'page'     => self::int( $a, 'page', 1, 1, PHP_INT_MAX ),
					'per_page' => self::int( $a, 'per_page', 25, 1, 100 ),
				] ) ),

			'analytics_page_trend' => self::read( 'pages', [ 'pages', 'compare' ], 'Page trend',
				'Views and visitors per day for one page. The id comes from analytics_pages.',
				[ 'id' => self::id_prop( 'The page id, from analytics_pages.' ) ] + $period,
				static fn( array $a ): array => Stats::page_series( self::id( $a ), self::period( $a ) ), [ 'id' ] ),

			'analytics_compare_pages' => self::read( 'pages', [ 'compare' ], 'Compare pages',
				'Side-by-side figures for two to ten pages. The ids come from analytics_pages.',
				[ 'ids' => [ 'type' => 'array', 'items' => [ 'type' => 'integer', 'minimum' => 1 ], 'minItems' => 2, 'maxItems' => 10, 'description' => 'Page ids to compare.' ] ] + $period,
				static function ( array $a ): array {
					$ids = array_values( array_unique( array_filter( array_map( 'intval', (array) ( $a['ids'] ?? [] ) ), static fn( int $i ): bool => $i > 0 ) ) );
					if ( count( $ids ) < 2 || count( $ids ) > 10 ) {
						throw new \InvalidArgumentException( 'Give two to ten page ids.' );
					}

					return Stats::compare( $ids, self::period( $a ) );
				}, [ 'ids' ] ),

			'analytics_referrers' => self::read( 'sources', [ 'dashboard', 'referrers' ], 'Referrers',
				'Where visitors came from: search engines, other websites, social networks, and direct visits.',
				$period, static fn( array $a ): array => Stats::referrers( self::period( $a ) ) ),

			'analytics_campaigns' => self::read( 'sources', [ 'dashboard', 'goals' ], 'Campaigns',
				'Visits and conversions by UTM campaign, source and medium.',
				$period, static fn( array $a ): array => Stats::campaigns( self::period( $a ) ) ),

			'analytics_geo' => self::read( 'sources', [ 'dashboard', 'geo' ], 'Countries and cities',
				'Where visitors are: countries and cities, with visitors and views for each.',
				$period, static fn( array $a ): array => Stats::geo( self::period( $a ) ) ),

			'analytics_devices' => self::read( 'sources', [ 'dashboard', 'devices' ], 'Devices',
				'Device types, browsers and operating systems used by visitors.',
				$period, static fn( array $a ): array => Stats::devices( self::period( $a ) ) ),

			'analytics_journeys' => self::read( 'visitors', [ 'journeys', 'replay' ], 'Visits',
				'Recent visits, newest first: the pages seen and the actions taken in each. Use analytics_journey for one visit in full.',
				$period + self::paging( 25, 100 ),
				static fn( array $a ): array => Stats::journeys( self::period( $a ), self::int( $a, 'page', 1, 1, PHP_INT_MAX ), self::int( $a, 'per_page', 25, 1, 100 ) ) ),

			'analytics_journey' => self::read( 'visitors', [ 'journeys', 'replay' ], 'One visit',
				'One visit in full: every page view and action in order, with timing. The id comes from analytics_journeys.',
				[ 'id' => self::id_prop( 'The visit id, from analytics_journeys.' ) ],
				static function ( array $a ): array {
					$journey = Stats::journey( self::id( $a ) );
					if ( $journey === null ) {
						throw new \InvalidArgumentException( 'Visit not found.' );
					}

					return $journey;
				}, [ 'id' ] ),

			'analytics_goals' => self::read( 'goals', [ 'dashboard', 'goals' ], 'Goals',
				'Each goal and how many conversions it had in the period.',
				$period, static fn( array $a ): array => Goals::all( self::period( $a ) ) ),

			'analytics_funnels' => self::read( 'goals', [ 'goals' ], 'Funnels',
				'The funnels you have set up, with their steps. Get a funnel id here for analytics_funnel_report.',
				[], static fn(): array => Goals::funnels() ),

			'analytics_funnel_report' => self::read( 'goals', [ 'goals' ], 'Funnel report',
				'For each step of one funnel, how many visitors reached it in the period, and where they dropped off.',
				[ 'id' => self::id_prop( 'The funnel id, from analytics_funnels.' ) ] + $period,
				static function ( array $a ): array {
					$report = Goals::funnel_report( self::id( $a ), self::period( $a ) );
					if ( $report === null ) {
						throw new \InvalidArgumentException( 'Funnel not found.' );
					}

					return $report;
				}, [ 'id' ] ),

			'analytics_clicks' => self::read( 'goals', [ 'goals' ], 'Clicks',
				'Button and link clicks in the period, most recent first.',
				$period + self::paging( 10, 50 ),
				static fn( array $a ): array => Stats::clicks( self::period( $a ), self::int( $a, 'page', 1, 1, PHP_INT_MAX ), self::int( $a, 'per_page', 10, 1, 50 ) ) ),

			'analytics_conversions_over_time' => self::read( 'goals', [ 'goals' ], 'Clicks and conversions per day',
				'Clicks and goal conversions for every day in the period.',
				$period, static fn( array $a ): array => Stats::action_series( self::period( $a ) ) ),

			'analytics_save_goal' => self::write( 'goals', [ 'goals' ], 'Save goal',
				'Create a goal, or change one when an id is given. A goal counts a page view, a click on an element (its CSS selector) or a custom event. Changes take effect at once.',
				[
					'id'    => [ 'type' => 'integer', 'minimum' => 0, 'default' => 0, 'description' => 'Goal to change. Leave out or use 0 to create a new goal.' ],
					'name'  => [ 'type' => 'string', 'maxLength' => 100, 'description' => 'Name shown in the reports. Defaults to the value.' ],
					'type'  => [ 'type' => 'string', 'enum' => array_keys( self::GOAL_TYPES ), 'description' => 'page: a page address. click: a CSS selector. event: a custom event name.' ],
					'match' => [ 'type' => 'string', 'enum' => array_keys( self::GOAL_MATCH ), 'default' => 'exact', 'description' => 'How a page address is matched. Clicks always match exactly.' ],
					'value' => [ 'type' => 'string', 'maxLength' => 255, 'description' => 'The page address, CSS selector or event name.' ],
				],
				static function ( array $a ): array {
					$type = (string) ( $a['type'] ?? '' );
					if ( ! isset( self::GOAL_TYPES[ $type ] ) ) {
						throw new \InvalidArgumentException( 'Type must be page, click or event.' );
					}

					return [ 'id' => Goals::save( [
						'id'    => self::int( $a, 'id', 0, 0, PHP_INT_MAX ),
						'type'  => self::GOAL_TYPES[ $type ],
						'match' => self::GOAL_MATCH[ (string) ( $a['match'] ?? 'exact' ) ] ?? Goals::MATCH_EXACT,
						'name'  => (string) ( $a['name'] ?? '' ),
						'value' => (string) ( $a['value'] ?? '' ),
					] ) ];
				}, [ 'type', 'value' ] ),

			'analytics_delete_goal' => self::write( 'goals', [ 'goals' ], 'Delete goal',
				'Delete a goal and its conversion history. This cannot be undone.',
				[ 'id' => self::id_prop( 'The goal id.' ) ],
				static function ( array $a ): array {
					$id = self::id( $a );
					Goals::delete( $id );

					return [ 'deleted' => true, 'id' => $id ];
				}, [ 'id' ], true ),
		];
	}

	/** A read tool. Read tools need the Read switch of their group and nothing more. */
	private static function read( string $group, array $features, string $title, string $description, array $properties, callable $run, array $required = [] ): array {
		return [
			'group'       => $group,
			'write'       => false,
			'destructive' => false,
			'features'    => $features,
			'title'       => $title,
			'description' => $description,
			'properties'  => $properties,
			'required'    => $required,
			'run'         => $run,
		];
	}

	/** A write tool. It needs the Write switch of its group and a token with write access. */
	private static function write( string $group, array $features, string $title, string $description, array $properties, callable $run, array $required = [], bool $destructive = false ): array {
		// array_merge, not +: the right-hand values must win over the read defaults.
		return array_merge( self::read( $group, $features, $title, $description, $properties, $run, $required ), [
			'write'       => true,
			'destructive' => $destructive,
		] );
	}

	/** Date inputs shared by most tools. */
	private static function period_props(): array {
		return [
			'range' => [ 'type' => 'string', 'enum' => [ 'today', '7d', '30d', '90d', '12m', 'custom' ], 'default' => '30d', 'description' => 'Period: today, 7d, 30d (default), 90d, 12m, or custom with from and to.' ],
			'from'  => [ 'type' => 'string', 'description' => 'First day of a custom period, YYYY-MM-DD in the site\'s time zone.' ],
			'to'    => [ 'type' => 'string', 'description' => 'Last day of a custom period, YYYY-MM-DD in the site\'s time zone.' ],
		];
	}

	private static function paging( int $per_page, int $max ): array {
		return [
			'page'     => [ 'type' => 'integer', 'minimum' => 1, 'default' => 1, 'description' => 'Page of results, starting at 1.' ],
			'per_page' => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => $max, 'default' => $per_page, 'description' => 'Rows per page, up to ' . $max . '.' ],
		];
	}

	private static function id_prop( string $description ): array {
		return [ 'type' => 'integer', 'minimum' => 1, 'description' => $description ];
	}

	/** Resolved period for the Stats functions. */
	private static function period( array $args ): array {
		return Ranges::resolve(
			(string) ( $args['range'] ?? '30d' ),
			(string) ( $args['from'] ?? '' ),
			(string) ( $args['to'] ?? '' )
		);
	}

	private static function id( array $args ): int {
		$id = (int) ( $args['id'] ?? 0 );
		if ( $id < 1 ) {
			throw new \InvalidArgumentException( 'The id must be a whole number above zero.' );
		}

		return $id;
	}

	/** An integer argument, kept between the limits. */
	private static function int( array $args, string $key, int $default, int $min, int $max ): int {
		return min( $max, max( $min, (int) ( $args[ $key ] ?? $default ) ) );
	}
}
