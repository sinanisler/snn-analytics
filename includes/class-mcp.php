<?php
/**
 * Built-in MCP server: the Streamable HTTP endpoint, access tokens and the MCP screen.
 * The tools themselves are in class-mcp-tools.php.
 *
 * Endpoint: /wp-json/snn-a/v1/mcp, served while the server is switched on. It is stateless: no
 * Mcp-Session-Id is issued, so every POST stands alone. A request must present one of the tokens made on
 * the MCP screen, as ?key=... in the address or as an Authorization: Bearer header. A token acts as the
 * user who created it, so every tool still checks that user's permissions.
 *
 * Stored with Store, as JSON:
 *   snn_a_mcp         { enabled, groups: { group: { read, write } } }
 *   snn_a_mcp_tokens  { id: { name, hash, access: read|write, user, created, last_used } }
 * Only a keyed hash of each token is kept. The token itself is shown once, right after it is made.
 */

namespace SNN_Analytics;

defined( 'ABSPATH' ) || exit;

final class MCP {

	const SETTINGS = 'snn_a_mcp';
	const TOKENS   = 'snn_a_mcp_tokens';
	const SCREEN   = 'snn-analytics-mcp';
	const ROUTE    = '/mcp';

	/** A token's last_used is written at most this often, so a busy assistant does not write on every call. */
	const TOUCH_EVERY = 300;

	/** Protocol versions a client may ask for, newest first. Same list the snn-block MCP server uses. */
	const VERSIONS = [ '2025-11-25', '2025-06-18', '2025-03-26', '2024-11-05' ];
	const LATEST   = '2025-11-25';

	const PARSE_ERROR      = -32700;
	const INVALID_REQUEST  = -32600;
	const METHOD_NOT_FOUND = -32601;
	const INVALID_PARAMS   = -32602;
	const INTERNAL_ERROR   = -32603;

	const INSTRUCTIONS = 'Visitor analytics for this WordPress site, from SNN Analytics. Start with analytics_overview for the headline numbers, then use analytics_pages, analytics_referrers, analytics_journeys or analytics_goals to dig in. Every period takes range (today, 7d, 30d, 90d, 12m or custom) or custom from and to dates. Visitors are anonymous: the data has no names, email addresses or IP addresses. Write tools (goals) change tracking at once.';

	/** Settings cache for one request. */
	private static ?array $settings = null;

	/** Token the current request presented: id, name and access. Null until the request is authenticated. */
	private static ?array $token = null;

	public static function register(): void {
		add_action( 'rest_api_init', [ self::class, 'routes' ] );
		add_filter( 'rest_pre_dispatch', [ self::class, 'reject_malformed' ], 10, 3 );
		add_filter( 'rest_post_dispatch', [ self::class, 'allow_header' ], 20, 3 );
		add_action( 'admin_enqueue_scripts', [ self::class, 'assets' ] );
		add_action( 'admin_post_snn_a_mcp_save', [ self::class, 'save' ] );
		add_action( 'admin_post_snn_a_mcp_create_token', [ self::class, 'create_token_action' ] );
		add_action( 'admin_post_snn_a_mcp_revoke_token', [ self::class, 'revoke_token_action' ] );
	}

	public static function routes(): void {
		// Always registered, which costs nothing. check_permission answers 404 while the server is off.
		register_rest_route( API::NS, self::ROUTE, [
			'methods'             => [ 'POST', 'GET', 'DELETE' ],
			'callback'            => [ self::class, 'handle' ],
			'permission_callback' => [ self::class, 'check_permission' ],
		] );
	}

	/**
	 * WordPress rejects a body that is not JSON before the handler runs, with its own error shape. This
	 * answers that one case with a JSON-RPC parse error instead. Only this route is affected.
	 *
	 * @param mixed $result
	 * @return mixed
	 */
	public static function reject_malformed( $result, $server, $request ) {
		if ( $result !== null || ! $request instanceof \WP_REST_Request || $request->get_route() !== '/' . API::NS . self::ROUTE || $request->get_method() !== 'POST' ) {
			return $result;
		}

		json_decode( (string) $request->get_body(), true, 512 );
		if ( json_last_error() === JSON_ERROR_NONE ) {
			return $result;
		}

		return new \WP_REST_Response( self::error( null, self::PARSE_ERROR, 'Parse error: ' . json_last_error_msg() ), 400 );
	}

	/**
	 * WordPress lists every method the route accepts in Allow, and that includes GET and DELETE, which this
	 * server answers with 405. For the 405 reply, Allow names only POST.
	 *
	 * @param mixed $response
	 * @return mixed
	 */
	public static function allow_header( $response, $server, $request ) {
		if ( $response instanceof \WP_REST_Response && $response->get_status() === 405 && $request->get_route() === '/' . API::NS . self::ROUTE ) {
			$response->header( 'Allow', 'POST' );
		}

		return $response;
	}

	/** Full address of the endpoint, as the assistants connect to it. */
	public static function endpoint_url(): string {
		return rest_url( API::NS . self::ROUTE );
	}

	/** Address with a token in it, for connectors that take only a link. */
	public static function connect_url( string $token ): string {
		return add_query_arg( 'key', $token, self::endpoint_url() );
	}

	// ------------------------------------------------------------- settings

	/** Saved settings with defaults: the server on, Read on and Write off in every group. */
	public static function settings(): array {
		if ( self::$settings !== null ) {
			return self::$settings;
		}

		$saved = Store::read( self::SETTINGS );
		$out   = [
			'enabled' => array_key_exists( 'enabled', $saved ) ? (bool) $saved['enabled'] : true,
			'groups'  => [],
		];

		foreach ( array_keys( MCP_Tools::GROUPS ) as $group ) {
			$row                     = is_array( $saved['groups'][ $group ] ?? null ) ? $saved['groups'][ $group ] : [];
			$out['groups'][ $group ] = [
				'read'  => array_key_exists( 'read', $row ) ? (bool) $row['read'] : true,
				'write' => ! empty( $row['write'] ),
			];
		}

		return self::$settings = $out;
	}

	public static function enabled(): bool {
		return self::settings()['enabled'];
	}

	/** Whether a group allows a kind of tool. Write needs Read, so an assistant can always look first. */
	public static function group_allows( string $group, string $kind ): bool {
		$row = self::settings()['groups'][ $group ] ?? null;
		if ( $row === null || ! $row['read'] ) {
			return false;
		}

		return $kind === 'read' ? true : $row['write'];
	}

	// --------------------------------------------------------------- tokens

	/** @return array<string, array> */
	private static function tokens(): array {
		return Store::read( self::TOKENS );
	}

	private static function hash( string $token ): string {
		return hash_hmac( 'sha256', $token, Settings::secrets()['hmac'] );
	}

	/** Makes a token for the current user. Returns the plain token, which is not stored. */
	public static function create_token( string $name, string $access ): string {
		$token  = 'snn_' . bin2hex( random_bytes( 24 ) );
		$tokens = self::tokens();

		$tokens[ bin2hex( random_bytes( 4 ) ) ] = [
			'name'      => $name,
			'hash'      => self::hash( $token ),
			'access'    => $access === 'write' ? 'write' : 'read',
			'user'      => get_current_user_id(),
			'created'   => time(),
			'last_used' => 0,
		];
		Store::write( self::TOKENS, $tokens, false );

		return $token;
	}

	/** Removes a token. Returns false when no token has that id. */
	public static function revoke_token( string $id ): bool {
		$tokens = self::tokens();
		if ( ! isset( $tokens[ $id ] ) ) {
			return false;
		}

		unset( $tokens[ $id ] );
		Store::write( self::TOKENS, $tokens, false );

		return true;
	}

	/** Id of the stored token that matches a plain token, or '' when none does. */
	private static function find_id( string $token ): string {
		$hash = self::hash( $token );

		foreach ( self::tokens() as $id => $record ) {
			if ( hash_equals( (string) ( $record['hash'] ?? '' ), $hash ) ) {
				return (string) $id;
			}
		}

		return '';
	}

	/** The token a request presents: a Bearer header first, then the ?key= parameter of the address. */
	private static function presented_token( \WP_REST_Request $request ): string {
		$header = (string) $request->get_header( 'authorization' );
		if ( $header === '' && isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
			// Some hosts pass the header only under this name.
			$header = (string) wp_unslash( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		}

		if ( preg_match( '/^Bearer\s+(\S+)$/i', $header, $match ) ) {
			return $match[1];
		}

		$key = $request->get_param( 'key' );

		return is_string( $key ) ? $key : '';
	}

	/** Access of the current request's token: 'read' or 'write'. */
	private static function access(): string {
		return self::$token['access'] ?? 'read';
	}

	// ------------------------------------------------------------- endpoint

	/**
	 * Permission check of the route. A request needs a valid token, and then it runs as the token's user.
	 *
	 * @return bool|\WP_Error
	 */
	public static function check_permission( \WP_REST_Request $request ) {
		if ( ! self::enabled() ) {
			return new \WP_Error( 'rest_no_route', __( 'No route was found matching the URL and request method.', 'snn-analytics' ), [ 'status' => 404 ] );
		}

		self::$token = null;
		$plain       = self::presented_token( $request );
		if ( $plain === '' ) {
			return new \WP_Error( 'snn_a_mcp_no_token', __( 'Add the token to the address as ?key=... or send it as an Authorization: Bearer header.', 'snn-analytics' ), [ 'status' => 401 ] );
		}

		$id     = self::find_id( $plain );
		$tokens = self::tokens();
		if ( $id === '' || ! get_user_by( 'id', (int) $tokens[ $id ]['user'] ) ) {
			return new \WP_Error( 'snn_a_mcp_bad_token', __( 'The token is unknown or was revoked.', 'snn-analytics' ), [ 'status' => 401 ] );
		}

		$record = $tokens[ $id ];
		wp_set_current_user( (int) $record['user'] );
		self::$token = [
			'id'     => $id,
			'name'   => (string) $record['name'],
			'access' => (string) $record['access'],
		];

		if ( time() - (int) $record['last_used'] >= self::TOUCH_EVERY ) {
			// Read again before writing, so a token revoked a moment ago is not written back.
			$fresh = self::tokens();
			if ( isset( $fresh[ $id ] ) ) {
				$fresh[ $id ]['last_used'] = time();
				Store::write( self::TOKENS, $fresh, false );
			}
		}

		return true;
	}

	public static function handle( \WP_REST_Request $request ): \WP_REST_Response {
		// Nothing is streamed and no sessions exist, so GET and DELETE have nothing to act on.
		if ( $request->get_method() !== 'POST' ) {
			$response = new \WP_REST_Response( null, 405 );
			$response->header( 'Allow', 'POST' );

			return $response;
		}

		if ( ! self::origin_allowed( $request ) ) {
			return new \WP_REST_Response( self::error( null, self::INVALID_REQUEST, 'Origin not allowed for this endpoint.' ), 403 );
		}

		$version = $request->get_header( 'mcp-protocol-version' );
		if ( $version !== null && $version !== '' && ! in_array( $version, self::VERSIONS, true ) ) {
			return new \WP_REST_Response( self::error( null, self::INVALID_REQUEST, 'Unsupported MCP-Protocol-Version: ' . $version ), 400 );
		}

		$message = json_decode( (string) $request->get_body(), true, 512 );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new \WP_REST_Response( self::error( null, self::PARSE_ERROR, 'Parse error: ' . json_last_error_msg() ), 400 );
		}

		if ( ! is_array( $message ) || self::is_list( $message ) || ( $message['jsonrpc'] ?? null ) !== '2.0' ) {
			return new \WP_REST_Response( self::error( null, self::INVALID_REQUEST, 'The payload must be one JSON-RPC 2.0 object. Batches are not supported.' ), 400 );
		}

		// A reply to a request this server never sent has nothing to answer.
		if ( ! isset( $message['method'] ) || ! is_string( $message['method'] ) ) {
			return new \WP_REST_Response( null, array_key_exists( 'result', $message ) || array_key_exists( 'error', $message ) ? 202 : 400 );
		}

		// Notifications, such as notifications/initialized, carry no id and get no body.
		if ( ! array_key_exists( 'id', $message ) ) {
			return new \WP_REST_Response( null, 202 );
		}

		$id = $message['id'];
		if ( ! is_string( $id ) && ! is_int( $id ) ) {
			return new \WP_REST_Response( self::error( null, self::INVALID_REQUEST, 'The id must be a string or an integer.' ), 400 );
		}

		$params = is_array( $message['params'] ?? null ) ? $message['params'] : [];

		try {
			$envelope = self::result( $id, self::dispatch( (string) $message['method'], $params ) );
		} catch ( MCP_Rpc_Exception $e ) {
			$envelope = self::error( $id, $e->getCode(), $e->getMessage() );
		} catch ( \Throwable $e ) {
			$envelope = self::error( $id, self::INTERNAL_ERROR, 'Failed to execute the request.' );
		}

		return new \WP_REST_Response( $envelope, self::http_status( $envelope ) );
	}

	private static function dispatch( string $method, array $params ): mixed {
		switch ( $method ) {
			case 'initialize':
				return self::initialize( $params );
			case 'ping':
				return new \stdClass();
			case 'tools/list':
				return [ 'tools' => MCP_Tools::describe( MCP_Tools::visible( self::access() ) ) ];
			case 'tools/call':
				return self::call_tool( $params );
		}

		throw new MCP_Rpc_Exception( 'Method not found: ' . $method, self::METHOD_NOT_FOUND );
	}

	private static function initialize( array $params ): array {
		$requested = is_string( $params['protocolVersion'] ?? null ) ? $params['protocolVersion'] : '';

		return [
			'protocolVersion' => in_array( $requested, self::VERSIONS, true ) ? $requested : self::LATEST,
			'capabilities'    => [ 'tools' => [ 'listChanged' => false ] ],
			'serverInfo'      => [ 'name' => 'snn-analytics', 'version' => SNN_A_VERSION ],
			'instructions'    => self::INSTRUCTIONS,
		];
	}

	private static function call_tool( array $params ): array {
		$name = is_string( $params['name'] ?? null ) ? $params['name'] : '';
		if ( $name === '' ) {
			throw new MCP_Rpc_Exception( 'Tool name is required.', self::INVALID_PARAMS );
		}

		// A tool the caller may not use reads as unknown, so its existence is not revealed.
		$tool = MCP_Tools::find( $name, self::access() );
		if ( $tool === null ) {
			throw new MCP_Rpc_Exception( 'Tool not found: ' . $name, self::INVALID_PARAMS );
		}

		$args = is_array( $params['arguments'] ?? null ) ? $params['arguments'] : [];

		return MCP_Tools::run( $tool, $args );
	}

	/**
	 * Browsers send Origin and server-side clients usually do not. Only this site's own origin is accepted,
	 * which blocks pages on other sites from calling the endpoint. The snn_a_mcp_allowed_origins filter adds more.
	 */
	private static function origin_allowed( \WP_REST_Request $request ): bool {
		$origin = $request->get_header( 'origin' );
		if ( $origin === null || $origin === '' ) {
			return true;
		}

		$allowed = apply_filters( 'snn_a_mcp_allowed_origins', [ self::site_origin() ] );

		return is_array( $allowed ) && in_array( rtrim( (string) $origin, '/' ), $allowed, true );
	}

	private static function site_origin(): string {
		$parts  = wp_parse_url( home_url() );
		$origin = ( $parts['scheme'] ?? 'https' ) . '://' . ( $parts['host'] ?? '' );
		if ( ! empty( $parts['port'] ) ) {
			$origin .= ':' . $parts['port'];
		}

		return $origin;
	}

	private static function result( $id, $result ): array {
		return [ 'jsonrpc' => '2.0', 'id' => $id, 'result' => $result ];
	}

	private static function error( $id, int $code, string $message ): array {
		return [ 'jsonrpc' => '2.0', 'id' => $id, 'error' => [ 'code' => $code, 'message' => $message ] ];
	}

	/** JSON-RPC errors travel in a 200 response, except malformed requests and server faults. */
	private static function http_status( array $envelope ): int {
		$code = $envelope['error']['code'] ?? null;

		if ( $code === self::PARSE_ERROR || $code === self::INVALID_REQUEST ) {
			return 400;
		}

		return $code === self::INTERNAL_ERROR ? 500 : 200;
	}

	private static function is_list( array $array ): bool {
		return $array === [] || array_keys( $array ) === range( 0, count( $array ) - 1 );
	}

	// ---------------------------------------------------------------- screen

	public static function assets(): void {
		if ( sanitize_key( wp_unslash( $_GET['page'] ?? '' ) ) !== self::SCREEN ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		wp_enqueue_script( 'snn-a-mcp', plugins_url( 'assets/admin/mcp.js', SNN_A_FILE ), [], SNN_A_VERSION, true );
	}

	/** Settings, the tokens and the form to make one. Shown inside the MCP screen. */
	public static function render(): void {
		$s   = self::settings();
		$new = get_transient( self::notice_key() );
		if ( $new ) {
			delete_transient( self::notice_key() );
		}

		$tokens = self::tokens();
		?>
		<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-success is-dismissible"><p>MCP settings saved.</p></div>
		<?php endif; ?>
		<?php if ( isset( $_GET['revoked'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-success is-dismissible"><p>Token revoked. That assistant has lost access.</p></div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="snn-a-form snn-a-settings snn-a-mcp">
			<input type="hidden" name="action" value="snn_a_mcp_save">
			<?php wp_nonce_field( 'snn_a_mcp_save' ); ?>

			<?php Admin::section( 'MCP (Model Context Protocol)', 'Connect AI assistants such as Claude to these reports. Each assistant gets its own token, so you can see and cut off each one separately. An assistant only ever sees what the user who created its token can see.' ); ?>
				<?php Admin::row( 'Built-in MCP server', 'Off means no endpoint at all, and every token stops working until it is turned on again.' ); ?>
					<?php Admin::toggle( 'mcp_enabled', $s['enabled'], 'Run the MCP server' ); ?>
				<?php Admin::row_end(); ?>

				<?php Admin::row( 'Endpoint', 'Assistants connect to this address. The token goes with it, either in the address or in an Authorization header.' ); ?>
					<?php if ( $s['enabled'] ) : ?>
						<?php self::copy_field( 'snn-a-mcp-endpoint', self::endpoint_url(), 'Endpoint address' ); ?>
					<?php else : ?>
						<span class="snn-a-muted">The server is off, so no endpoint is served.</span>
					<?php endif; ?>
				<?php Admin::row_end(); ?>
			</section>

			<?php Admin::section( 'Tools', 'What each assistant may use. Read is on by default. Write changes tracking, so it is off by default. Write needs Read, and a token with Read only access never gets a write tool, whatever is ticked here.' ); ?>
				<div class="snn-a-scroll">
					<table class="snn-a-matrix">
						<thead>
							<tr>
								<th scope="col">Group</th>
								<th scope="col">Read</th>
								<th scope="col">Write</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( MCP_Tools::GROUPS as $key => $label ) : ?>
								<tr>
									<th scope="row"><?php echo esc_html( $label ); ?></th>
									<td><input type="checkbox" name="groups[<?php echo esc_attr( $key ); ?>][read]" value="1" <?php checked( $s['groups'][ $key ]['read'] ); ?> aria-label="<?php echo esc_attr( 'Read: ' . $label ); ?>"></td>
									<td><input type="checkbox" name="groups[<?php echo esc_attr( $key ); ?>][write]" value="1" <?php checked( $s['groups'][ $key ]['write'] ); ?> aria-label="<?php echo esc_attr( 'Write: ' . $label ); ?>"></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</section>

			<div class="snn-a-savebar">
				<?php submit_button( 'Save changes', 'primary', 'submit', false ); ?>
			</div>
		</form>

		<?php Admin::section( 'AI assistants', 'Give each assistant its own link, so you can see and cut off each one separately. Revoking a token stops that assistant at once.' ); ?>
			<?php if ( $new ) : ?>
				<?php self::new_token_box( $new ); ?>
			<?php endif; ?>

			<div class="snn-a-scroll">
				<table class="snn-a-table snn-a-mcp-tokens">
					<thead>
						<tr>
							<th scope="col">Name</th>
							<th scope="col">Access</th>
							<th scope="col">Last used</th>
							<th scope="col">Created</th>
							<th scope="col"><span class="screen-reader-text">Actions</span></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( $tokens === [] ) : ?>
							<tr><td colspan="5">No tokens yet. Add one for each AI assistant below.</td></tr>
						<?php endif; ?>
						<?php foreach ( $tokens as $id => $record ) : ?>
							<tr>
								<td><strong><?php echo esc_html( (string) $record['name'] ); ?></strong></td>
								<td><?php echo 'write' === ( $record['access'] ?? '' ) ? esc_html__( 'Read and write', 'snn-analytics' ) : esc_html__( 'Read only', 'snn-analytics' ); ?></td>
								<td><?php echo ! empty( $record['last_used'] ) ? esc_html( sprintf( /* translators: %s: time since, e.g. 5 minutes */ __( '%s ago', 'snn-analytics' ), human_time_diff( (int) $record['last_used'] ) ) ) : esc_html__( 'Never', 'snn-analytics' ); ?></td>
								<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $record['created'] ) ); ?></td>
								<td>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-confirm="<?php echo esc_attr__( 'Revoke this token? That assistant loses access at once.', 'snn-analytics' ); ?>">
										<input type="hidden" name="action" value="snn_a_mcp_revoke_token">
										<input type="hidden" name="id" value="<?php echo esc_attr( (string) $id ); ?>">
										<?php wp_nonce_field( 'snn_a_mcp_revoke_' . $id ); ?>
										<button type="submit" class="button-link button-link-delete"><?php esc_html_e( 'Revoke', 'snn-analytics' ); ?></button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<h3 class="snn-a-subhead">New token</h3>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="snn-a-settings snn-a-mcp-new">
				<input type="hidden" name="action" value="snn_a_mcp_create_token">
				<?php wp_nonce_field( 'snn_a_mcp_create_token' ); ?>
				<label>
					<span>Name</span>
					<input type="text" name="name" maxlength="60" required placeholder="<?php esc_attr_e( 'e.g. Claude, Sinan', 'snn-analytics' ); ?>">
				</label>
				<label>
					<span>Access</span>
					<select name="access">
						<option value="read"><?php esc_html_e( 'Read only: look, scan, report', 'snn-analytics' ); ?></option>
						<option value="write"><?php esc_html_e( 'Read and write', 'snn-analytics' ); ?></option>
					</select>
				</label>
				<?php submit_button( 'Create token', 'secondary', 'submit', false ); ?>
			</form>
			<p class="snn-a-row-help">Read and write still respects the groups above. A group whose Write is off stays read only for every token.</p>
		</section>
		<?php
	}

	/** Connection details for the token just made. Shown once. */
	private static function new_token_box( array $new ): void {
		$url  = self::connect_url( $new['token'] );
		$slug = sanitize_title( (string) $new['name'] ) ?: 'snn-analytics';
		?>
		<div class="snn-a-mcp-created">
			<p><strong><?php echo esc_html( sprintf( /* translators: %s: token name */ __( 'Token for %s created. Copy its link now: the token is not shown again.', 'snn-analytics' ), $new['name'] ) ); ?></strong></p>
			<?php self::copy_field( 'snn-a-mcp-link', $url, __( 'claude.ai, Claude desktop app, Cowork: Settings > Connectors > Add custom connector, paste this address', 'snn-analytics' ) ); ?>
			<?php self::copy_field( 'snn-a-mcp-claude-code', 'claude mcp add --transport http ' . $slug . ' "' . $url . '"', __( 'Claude Code: run this in a terminal', 'snn-analytics' ) ); ?>
			<?php self::copy_field( 'snn-a-mcp-server', self::endpoint_url(), __( 'Any other MCP client: server address', 'snn-analytics' ) ); ?>
			<?php self::copy_field( 'snn-a-mcp-header', 'Authorization: Bearer ' . $new['token'], __( 'Any other MCP client: header', 'snn-analytics' ) ); ?>
		</div>
		<?php
	}

	/** A labelled read-only field with a Copy button. The script in mcp.js does the copying. */
	private static function copy_field( string $id, string $value, string $label = '' ): void {
		?>
		<div class="snn-a-copy">
			<?php if ( $label !== '' ) : ?>
				<label for="<?php echo esc_attr( $id ); ?>" class="snn-a-copy-label"><?php echo esc_html( $label ); ?></label>
			<?php endif; ?>
			<div class="snn-a-copy-row">
				<input type="text" readonly id="<?php echo esc_attr( $id ); ?>" class="snn-a-copy-field" value="<?php echo esc_attr( $value ); ?>">
				<button type="button" class="button" data-copy="<?php echo esc_attr( $id ); ?>">Copy</button>
			</div>
		</div>
		<?php
	}

	private static function notice_key(): string {
		return 'snn_a_mcp_new_' . get_current_user_id();
	}

	// --------------------------------------------------------- form handlers

	public static function save(): void {
		self::check( 'snn_a_mcp_save' );

		$posted = is_array( $_POST['groups'] ?? null ) ? $_POST['groups'] : []; // phpcs:ignore WordPress.Security.NonceVerification
		$groups = [];

		foreach ( array_keys( MCP_Tools::GROUPS ) as $key ) {
			$row   = is_array( $posted[ $key ] ?? null ) ? $posted[ $key ] : [];
			$read  = ! empty( $row['read'] );
			$write = $read && ! empty( $row['write'] ); // a write needs read

			$groups[ $key ] = [ 'read' => $read, 'write' => $write ];
		}

		Store::write( self::SETTINGS, [
			'enabled' => isset( $_POST['mcp_enabled'] ), // phpcs:ignore WordPress.Security.NonceVerification
			'groups'  => $groups,
		], false );
		self::$settings = null;

		wp_safe_redirect( add_query_arg( [ 'page' => self::SCREEN, 'saved' => 1 ], admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function create_token_action(): void {
		self::check( 'snn_a_mcp_create_token' );

		$name = is_string( $_POST['name'] ?? null ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$name = $name === '' ? __( 'AI assistant', 'snn-analytics' ) : mb_substr( $name, 0, 60 );

		$access = ( $_POST['access'] ?? '' ) === 'write' ? 'write' : 'read'; // phpcs:ignore WordPress.Security.NonceVerification

		$token = self::create_token( $name, $access );
		set_transient( self::notice_key(), [ 'name' => $name, 'access' => $access, 'token' => $token ], 10 * MINUTE_IN_SECONDS );

		wp_safe_redirect( add_query_arg( 'page', self::SCREEN, admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function revoke_token_action(): void {
		self::check_capability();

		$id = is_string( $_POST['id'] ?? null ) ? sanitize_key( $_POST['id'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		check_admin_referer( 'snn_a_mcp_revoke_' . $id );

		self::revoke_token( $id );

		wp_safe_redirect( add_query_arg( [ 'page' => self::SCREEN, 'revoked' => 1 ], admin_url( 'admin.php' ) ) );
		exit;
	}

	private static function check( string $nonce ): void {
		self::check_capability();
		check_admin_referer( $nonce );
	}

	private static function check_capability(): void {
		if ( ! current_user_can( Admin::MANAGE_CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'snn-analytics' ), 403 );
		}
	}
}

/** Thrown inside a handler to answer the request with a JSON-RPC error. The exception code is the JSON-RPC code. */
class MCP_Rpc_Exception extends \RuntimeException {
}
