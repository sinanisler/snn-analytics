<?php
/**
 * Loads the tracker on public pages and prints the signed identity of the current page.
 *
 * Output is cached per page, and the signature depends only on the page identity,
 * not on the user, so full-page caching keeps working.
 */

namespace SNN_Analytics;

defined( 'ABSPATH' ) || exit;

final class Frontend {

	public static function register(): void {
		add_action( 'wp_enqueue_scripts', [ self::class, 'enqueue' ] );
	}

	public static function enqueue(): void {
		if ( is_preview() || ! self::should_track() ) {
			return;
		}

		wp_enqueue_script( 'snn-a-tracker', plugins_url( 'assets/t.js', SNN_A_FILE ), [], SNN_A_VERSION, true );
		wp_script_add_data( 'snn-a-tracker', 'strategy', 'defer' );

		$config = [
			'u'   => plugins_url( 't.php', SNN_A_FILE ),
			'f'   => rest_url( 'snn-a/v1/t' ),
			'dnt'   => (bool) Settings::get()['respect_dnt'],
			'pg'    => self::page(),
			'goals' => Goals::click_rules(),
		];

		// JSON_HEX_* keeps a title such as "</script>" from ending the inline script.
		wp_add_inline_script(
			'snn-a-tracker',
			'window.snnAConfig = ' . wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . ';',
			'before'
		);
	}

	/**
	 * Signed identity of the current page: key, path, title, post type, signature.
	 * Pages without an object use fixed keys, so no row can grow from user input.
	 */
	private static function page(): array {
		$key       = 'other';
		$path      = '/other';
		$title     = '';
		$post_type = '';

		if ( is_singular() ) {
			$post = get_queried_object();
			if ( $post instanceof \WP_Post ) {
				$key       = 's:' . $post->ID;
				$title     = get_the_title( $post );
				$post_type = $post->post_type;
				$path      = self::path( get_permalink( $post ) );
			}
		} elseif ( is_front_page() ) {
			$key   = 'home';
			$title = get_bloginfo( 'name' );
			$path  = self::path( home_url( '/' ) );
		} elseif ( is_home() ) {
			$posts_page = (int) get_option( 'page_for_posts' );
			$key        = 'blog';
			$title      = get_the_title( $posts_page );
			$path       = self::path( get_permalink( $posts_page ) );
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( $term instanceof \WP_Term ) {
				$key       = 't:' . $term->term_id;
				$title     = $term->name;
				$post_type = $term->taxonomy;
				$path      = self::path( get_term_link( $term ) );
			}
		} elseif ( is_author() ) {
			$user = get_queried_object();
			if ( $user instanceof \WP_User ) {
				$key   = 'a:' . $user->ID;
				$title = $user->display_name;
				$path  = self::path( get_author_posts_url( $user->ID ) );
			}
		} elseif ( is_post_type_archive() ) {
			$type = get_queried_object();
			if ( $type instanceof \WP_Post_Type ) {
				$key       = 'pta:' . $type->name;
				$title     = $type->labels->name;
				$post_type = $type->name;
				$path      = self::path( get_post_type_archive_link( $type->name ) );
			}
		} elseif ( is_date() ) {
			$key   = 'date';
			$title = __( 'Date archives', 'snn-analytics' );
			$path  = '/date';
		} elseif ( is_search() ) {
			$key   = 'search';
			$title = __( 'Search results', 'snn-analytics' );
			$path  = '/search';
		} elseif ( is_404() ) {
			$key   = '404';
			$title = __( 'Not found', 'snn-analytics' );
			$path  = '/404';
		}

		return [
			'k' => $key,
			'p' => $path,
			't' => $title,
			'y' => $post_type,
			's' => Validator::page_signature( $key, $path, $title, $post_type ),
		];
	}

	private static function path( mixed $url ): string {
		if ( ! is_string( $url ) ) {
			return '/';
		}

		$path  = (string) parse_url( $url, PHP_URL_PATH );
		$query = (string) parse_url( $url, PHP_URL_QUERY );

		// With plain permalinks every post is "/?p=ID". Keep the ID, or all posts share one path.
		if ( $query !== '' && preg_match( '/(^|&)(p|page_id|cat|tag|author)=/', $query ) ) {
			$path = ( $path === '' ? '/' : $path ) . '?' . $query;
		}

		return $path === '' ? '/' : $path;
	}

	/**
	 * Logged-in users in the excluded roles (administrators by default) are not tracked.
	 */
	private static function should_track(): bool {
		if ( ! is_user_logged_in() ) {
			return true;
		}

		return array_intersect( wp_get_current_user()->roles, (array) Settings::get()['exclude_roles'] ) === [];
	}
}
