<?php
/**
 * Country and city for a new session.
 *
 * Country comes from CDN headers when present (free and instant). Otherwise, and for
 * the city, the DB-IP Lite City database is used. It is optional. Without it, only
 * countries from headers are recorded.
 *
 * The database is downloaded by a background cron job, so an admin request never has
 * to wait for a 100 MB transfer.
 */

namespace SNN_Analytics;

defined( 'ABSPATH' ) || exit;

final class Geo {

	const DIR        = 'snn-analytics';
	const STATUS     = 'snn_a_geo_status';
	const HOOK       = 'snn_a_geo_download';
	const SOURCE     = 'https://download.db-ip.com/free/dbip-city-lite-%s.mmdb.gz';

	/** Headers set by common CDNs and proxies, in priority order. */
	private const HEADERS = [
		'HTTP_CF_IPCOUNTRY',               // Cloudflare
		'HTTP_CLOUDFRONT_VIEWER_COUNTRY',  // AWS CloudFront
		'HTTP_X_COUNTRY_CODE',
		'HTTP_X_GEOIP_COUNTRY_CODE',
	];

	private static ?MMDB $db = null;
	private static bool $db_checked = false;

	/**
	 * @return array{country_id:?int, city_id:?int}
	 */
	public static function locate( array $server, string $ip ): array {
		$code   = self::country_code( $server );
		$record = null;

		$db = self::db();
		if ( $db !== null && $ip !== '' ) {
			try {
				$record = $db->lookup( $ip );
			} catch ( \RuntimeException $e ) {
				$record = null;
			}
		}

		$record_country = $record['country']['iso_code'] ?? null;
		$code           = $code ?? ( is_string( $record_country ) && $record_country !== '' ? strtoupper( $record_country ) : null );

		$country_id = $code === null ? null : DB::upsert( DB::table( 'countries' ), [ 'code' => $code ] );

		$city_id = null;
		$city    = (string) ( $record['city']['names']['en'] ?? '' );
		if ( $country_id !== null && $city !== '' && $record_country === $code ) {
			$region  = (string) ( $record['subdivisions'][0]['names']['en'] ?? '' );
			$city    = mb_substr( $city, 0, 64 );
			$region  = mb_substr( $region, 0, 64 );
			$city_id = DB::upsert(
				DB::table( 'cities' ),
				[
					'hash'       => DB::key( $country_id . '|' . $region . '|' . $city ),
					'country_id' => $country_id,
					'region'     => $region,
					'name'       => $city,
				]
			);
		}

		return [ 'country_id' => $country_id, 'city_id' => $city_id ];
	}

	public static function country_code( array $server ): ?string {
		foreach ( self::HEADERS as $header ) {
			$code = strtoupper( Validator::text( $server[ $header ] ?? null, 8 ) );

			// XX and T1 are Cloudflare's codes for "unknown" and "Tor".
			if ( preg_match( '/^[A-Z]{2}$/', $code ) && ! in_array( $code, [ 'XX', 'T1' ], true ) ) {
				return $code;
			}
		}

		return null;
	}

	// ------------------------------------------------------------ database

	/** Absolute path of the installed city database, or null. */
	public static function path(): ?string {
		$path = (string) Settings::get()['geo_city_file'];

		return $path !== '' && is_readable( $path ) ? $path : null;
	}

	private static function db(): ?MMDB {
		if ( self::$db_checked ) {
			return self::$db;
		}
		self::$db_checked = true;

		$path = self::path();
		if ( $path !== null ) {
			try {
				self::$db = new MMDB( $path );
			} catch ( \RuntimeException $e ) {
				self::$db = null;
			}
		}

		return self::$db;
	}

	/** Folder for the database. It is blocked from web access by the files written here. */
	public static function data_dir(): string {
		$uploads = wp_upload_dir();
		$dir     = trailingslashit( $uploads['basedir'] ) . self::DIR;

		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		if ( ! file_exists( $dir . '/.htaccess' ) ) {
			file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" );
		}
		if ( ! file_exists( $dir . '/index.php' ) ) {
			file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" );
		}

		return $dir;
	}

	public static function status(): array {
		$path   = self::path();
		$state  = get_option( self::STATUS, [] );
		$edition = get_option( self::STATUS . '_edition', [] );

		return [
			'source'    => 'download.db-ip.com',
			'edition'   => $path !== null && is_array( $edition ) ? (string) ( $edition['month'] ?? '' ) : '',
			'installed_at' => $path !== null && is_array( $edition ) ? (int) ( $edition['installed'] ?? 0 ) : 0,
			'installed' => $path !== null,
			'size'      => $path !== null ? (int) filesize( $path ) : 0,
			'state'     => is_array( $state ) ? (string) ( $state['state'] ?? 'idle' ) : 'idle',
			'message'   => is_array( $state ) ? (string) ( $state['message'] ?? '' ) : '',
			'updated'   => is_array( $state ) ? (int) ( $state['updated'] ?? 0 ) : 0,
		];
	}

	/** Schedules the download in the background. */
	public static function queue_download(): void {
		self::set_status( 'queued', 'Download queued. It runs in the background.' );
		wp_schedule_single_event( time(), self::HOOK );
		spawn_cron();
	}

	public static function remove_city_db(): void {
		$path = self::path();
		if ( $path !== null ) {
			wp_delete_file( $path );
		}

		Settings::update( [ 'geo_city_file' => '' ] );
		self::set_status( 'idle', 'City database removed.' );
	}

	/**
	 * Runs in cron. Downloads the current month's file, or last month's if it is not out yet,
	 * unpacks it, checks that it reads, and only then makes it the active database.
	 */
	public static function run_download(): void {
		@set_time_limit( 600 );
		self::set_status( 'downloading', 'Downloading the DB-IP Lite city database.' );

		$dir = self::data_dir();
		$tmp = $dir . '/download.tmp';

		$response = null;
		$edition  = '';
		foreach ( [ gmdate( 'Y-m' ), gmdate( 'Y-m', strtotime( 'first day of last month' ) ) ] as $month ) {
			$edition  = $month;
			$response = wp_remote_get( sprintf( self::SOURCE, $month ), [
				'timeout'  => 600,
				'stream'   => true,
				'filename' => $tmp . '.gz',
			] );
			if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
				break;
			}
		}

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			self::fail( 'Could not download the database. Check that the server can reach download.db-ip.com.' );
			return;
		}

		$in  = gzopen( $tmp . '.gz', 'rb' );
		$out = fopen( $tmp, 'wb' );
		if ( $in === false || $out === false ) {
			self::fail( 'Could not unpack the database.' );
			return;
		}
		while ( ! gzeof( $in ) ) {
			fwrite( $out, (string) gzread( $in, 1 << 20 ) );
		}
		gzclose( $in );
		fclose( $out );
		wp_delete_file( $tmp . '.gz' );

		try {
			$db     = new MMDB( $tmp );
			$probe  = $db->lookup( '8.8.8.8' );
			if ( ! isset( $probe['country']['iso_code'] ) ) {
				throw new \RuntimeException( 'The file does not contain country data.' );
			}
		} catch ( \RuntimeException $e ) {
			wp_delete_file( $tmp );
			self::fail( 'The downloaded file is not a valid database: ' . $e->getMessage() );
			return;
		}

		// A random file name keeps the database unguessable, even on servers that ignore .htaccess.
		$final = $dir . '/dbip-city-' . bin2hex( random_bytes( 8 ) ) . '.mmdb';
		if ( ! rename( $tmp, $final ) ) {
			wp_delete_file( $tmp );
			self::fail( 'Could not save the database file.' );
			return;
		}

		$previous = self::path();
		Settings::update( [ 'geo_city_file' => $final ] );
		if ( $previous !== null && $previous !== $final ) {
			wp_delete_file( $previous );
		}
		self::$db_checked = false;
		self::$db         = null;

		update_option( self::STATUS . '_edition', [ 'month' => $edition, 'installed' => time() ], false );
		self::set_status( 'done', 'City database installed.' );
	}

	private static function fail( string $message ): void {
		self::set_status( 'error', $message );
	}

	private static function set_status( string $state, string $message ): void {
		update_option( self::STATUS, [ 'state' => $state, 'message' => $message, 'updated' => time() ], false );
	}
}
