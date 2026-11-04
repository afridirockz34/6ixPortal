<?php
/**
 * MCP server — the same client profile contract, spoken as Model Context
 * Protocol so it can be added to claude.ai as a custom connector and sit in
 * the tool list alongside Ahrefs.
 *
 * This is deliberately a thin wrapper, NOT a second implementation. Every tool
 * here builds a WP_REST_Request and hands it to Six_Profile_API, so the write
 * allowlist, the provenance trail and the token scoping all live in exactly
 * one place and can't drift apart between the two surfaces.
 *
 * Transport: Streamable HTTP (MCP 2025-06-18), the subset a request/response
 * tool server needs. The spec lets a server answer a POSTed JSON-RPC request
 * with a plain `application/json` body instead of an SSE stream, and lets it
 * answer GET with 405 when it never pushes server-initiated messages — both of
 * which is what this does. The server is stateless, so it issues no
 * Mcp-Session-Id (the spec makes that optional).
 *
 * Auth: the same bearer tokens as the REST API, minted in
 * WP Admin → 6ix Portal → API Tokens. claude.ai's custom connector has a
 * "Request headers" field for exactly this — a fixed credential it sends on
 * every request — so no OAuth implementation is required.
 *
 * Endpoint: https://<site>/wp-json/six/v1/mcp
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Six_MCP_Server {

	/** Protocol revisions we can speak, newest first. */
	const VERSIONS = array( '2025-06-18', '2025-03-26' );

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function routes() {
		register_rest_route( Six_Profile_API::NS, '/mcp', array(
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle' ),
				'permission_callback' => array( __CLASS__, 'authenticate' ),
			),
			array(
				// The spec allows a server with nothing to push to refuse GET.
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'no_stream' ),
				'permission_callback' => '__return_true',
			),
		) );
	}

	public static function no_stream() {
		return new WP_REST_Response(
			array( 'error' => 'This MCP endpoint does not provide a server-initiated stream. POST JSON-RPC messages instead.' ),
			405
		);
	}

	/**
	 * Any valid token gets through the door; per-tool scope is enforced when
	 * the tool actually runs, so a read-only token can still list tools and
	 * call the read ones.
	 */
	public static function authenticate( $request ) {
		// Requests from Anthropic's infrastructure are server-to-server and
		// carry no Origin. A browser-issued Origin we don't recognise is
		// rejected outright — belt and braces, since token auth (never
		// cookies) already means a hostile page can't authenticate at all.
		$origin = $request->get_header( 'origin' );
		if ( $origin && untrailingslashit( $origin ) !== untrailingslashit( site_url() )
		     && untrailingslashit( $origin ) !== untrailingslashit( home_url() ) ) {
			return new WP_Error( 'six_bad_origin', 'Unrecognised Origin.', array( 'status' => 403 ) );
		}
		return Six_Profile_API::require_read( $request );
	}

	// ── Tool catalogue ────────────────────────────────────────────────────

	/**
	 * What Claude sees. These descriptions are the whole interface as far as
	 * the model is concerned, so they say when to reach for each tool, not
	 * just what it does.
	 */
	public static function tools() {
		return array(
			array(
				'name'        => 'list_clients',
				'title'       => 'Find clients',
				'description' => 'Find 6ix Developers clients. Pass `search` to match a person\'s name, email or their business name — use this first when you know a business or person but not their numeric client id. Returns each match with its client_id, which every other tool needs.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'search'   => array( 'type' => 'string', 'description' => 'Name, email or business name to match. Omit to list all clients, newest first.' ),
						'page'     => array( 'type' => 'integer', 'description' => 'Page number, default 1.' ),
						'per_page' => array( 'type' => 'integer', 'description' => 'Results per page, max 100, default 50.' ),
					),
				),
			),
			array(
				'name'        => 'get_client_profile',
				'title'       => 'Get a client profile',
				'description' => 'The full profile for one client: business identity, services and budgets, positioning and competitors, the SEO / Google Ads / Google Business inputs they gave during onboarding, their completed SEO questionnaire (brand story, ideal client, team credentials, compliance limits, content capacity, tooling), website project progress, and their current dashboard metrics. Read this before writing any strategy, audit or analysis for a client, so the work reflects what they actually told us. Also returns `field_provenance`, showing which fields an automation has changed and when.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'client_id' => array( 'type' => 'integer', 'description' => 'The client id, from list_clients.' ),
					),
					'required'   => array( 'client_id' ),
				),
			),
			array(
				'name'        => 'update_client_profile',
				'title'       => 'Update a client profile',
				'description' => 'Update profile fields for a client — for example writing back refined target keywords after a keyword analysis. Requires a write-scoped token. Only these fields can be written: ' . implode( ', ', array_keys( Six_Profile_API::writable_fields() ) ) . '. The customer\'s original onboarding answer for any field is preserved the first time it is overwritten and is never lost, and every change is attributed to the token that made it.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'client_id' => array( 'type' => 'integer', 'description' => 'The client id, from list_clients.' ),
						'fields'    => array(
							'type'        => 'object',
							'description' => 'Field name => new value. A list-shaped field (keywords, competitors, locations) may be given as an array of strings.',
						),
					),
					'required'   => array( 'client_id', 'fields' ),
				),
			),
			array(
				'name'        => 'list_client_artifacts',
				'title'       => 'List saved results for a client',
				'description' => 'Previously saved outputs for a client — keyword analyses, post schedules, audit reports — newest first. Check here before regenerating something expensive; a recent artifact may already answer the question.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'client_id' => array( 'type' => 'integer', 'description' => 'The client id, from list_clients.' ),
						'type'      => array( 'type' => 'string', 'description' => 'Optional filter, e.g. keyword_analysis, post_schedule, audit_report.' ),
					),
					'required'   => array( 'client_id' ),
				),
			),
			array(
				'name'        => 'save_client_artifact',
				'title'       => 'Save a result for a client',
				'description' => 'Store output you produced against a client — a keyword analysis, a GMB post schedule, an audit report — so it shows on their dashboard and is available to later sessions. Requires a write-scoped token. Give it a stable `type` so the dashboard can recognise it.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'client_id' => array( 'type' => 'integer', 'description' => 'The client id, from list_clients.' ),
						'type'      => array( 'type' => 'string', 'description' => 'A stable kind, e.g. keyword_analysis, post_schedule, audit_report.' ),
						'payload'   => array( 'type' => 'object', 'description' => 'The result itself. Any JSON shape.' ),
						'source'    => array( 'type' => 'string', 'description' => 'Optional label for what produced it. Defaults to the token\'s name.' ),
					),
					'required'   => array( 'client_id', 'type' ),
				),
			),
		);
	}

	// ── JSON-RPC plumbing ─────────────────────────────────────────────────

	protected static function result( $id, $result ) {
		return array( 'jsonrpc' => '2.0', 'id' => $id, 'result' => $result );
	}

	protected static function error( $id, $code, $message, $data = null ) {
		$err = array( 'code' => $code, 'message' => $message );
		if ( $data !== null ) $err['data'] = $data;
		return array( 'jsonrpc' => '2.0', 'id' => $id, 'error' => $err );
	}

	/**
	 * A tool's return value. The JSON goes in `structuredContent`, and the
	 * same JSON serialised goes in a text block — the spec asks for both, so
	 * clients that only read text still get the data.
	 */
	protected static function tool_result( $data, $is_error = false ) {
		$json = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		$out  = array(
			'content' => array( array( 'type' => 'text', 'text' => $json ) ),
			'isError' => (bool) $is_error,
		);
		if ( ! $is_error && is_array( $data ) ) $out['structuredContent'] = $data;
		return $out;
	}

	public static function handle( $request ) {
		// A client MUST send MCP-Protocol-Version on everything after
		// initialize; an unsupported value is a 400 per the spec. A missing
		// one means an older client, which the spec says to treat as 2025-03-26.
		$hdr = $request->get_header( 'mcp_protocol_version' );
		if ( $hdr && ! in_array( $hdr, self::VERSIONS, true ) ) {
			return new WP_REST_Response( array( 'error' => 'Unsupported MCP-Protocol-Version: ' . $hdr ), 400 );
		}

		$msg = $request->get_json_params();
		if ( ! is_array( $msg ) ) {
			return new WP_REST_Response( self::error( null, -32700, 'Parse error: expected a JSON-RPC message.' ), 400 );
		}

		$method = isset( $msg['method'] ) ? (string) $msg['method'] : '';
		$id     = $msg['id'] ?? null;
		$params = isset( $msg['params'] ) && is_array( $msg['params'] ) ? $msg['params'] : array();

		// Notifications and responses carry no id and get 202 with no body.
		if ( ! array_key_exists( 'id', $msg ) ) {
			return new WP_REST_Response( null, 202 );
		}

		switch ( $method ) {
			case 'initialize':
				return new WP_REST_Response( self::result( $id, self::initialize( $params ) ), 200 );

			case 'ping':
				return new WP_REST_Response( self::result( $id, new stdClass() ), 200 );

			case 'tools/list':
				return new WP_REST_Response( self::result( $id, array( 'tools' => self::tools() ) ), 200 );

			case 'tools/call':
				return new WP_REST_Response( self::call_tool( $id, $params, $request ), 200 );

			case 'resources/list':
				return new WP_REST_Response( self::result( $id, array( 'resources' => array() ) ), 200 );

			case 'prompts/list':
				return new WP_REST_Response( self::result( $id, array( 'prompts' => array() ) ), 200 );
		}

		return new WP_REST_Response( self::error( $id, -32601, 'Method not found: ' . $method ), 200 );
	}

	protected static function initialize( $params ) {
		// Echo the client's version when we speak it, otherwise offer ours.
		$asked = $params['protocolVersion'] ?? '';
		$use   = in_array( $asked, self::VERSIONS, true ) ? $asked : self::VERSIONS[0];

		return array(
			'protocolVersion' => $use,
			'capabilities'    => array( 'tools' => array( 'listChanged' => false ) ),
			'serverInfo'      => array(
				'name'    => '6ix-developers-portal',
				'title'   => '6ix Developers Portal',
				'version' => '1.0.0',
			),
			'instructions' =>
				"Client profiles for 6ix Developers. Each client's profile holds what they told us during onboarding — business type, target market, keywords, competitors, services and budgets — plus their current performance numbers.\n\n" .
				"Start with list_clients to turn a business or person's name into a client_id, then get_client_profile before producing any audit, strategy or analysis, so the work is grounded in that client's real answers rather than assumptions.\n\n" .
				"Save what you produce with save_client_artifact so it reaches their dashboard and later sessions can see it. Writing to a profile needs a write-scoped token; the customer's original answers are always preserved.",
		);
	}

	// ── Tool dispatch ─────────────────────────────────────────────────────

	/**
	 * Build a REST request that carries the caller's credential, so the
	 * delegated handler resolves the same token — which is what makes
	 * provenance record the real actor rather than a generic "api".
	 */
	protected static function sub_request( $incoming, $method, $route, $params = array(), $body = null ) {
		$req = new WP_REST_Request( $method, '/' . Six_Profile_API::NS . $route );
		foreach ( array( 'authorization', 'x_6ix_token' ) as $h ) {
			$val = $incoming->get_header( $h );
			if ( $val ) $req->set_header( $h, $val );
		}
		foreach ( $params as $k => $v ) $req->set_param( $k, $v );
		if ( $body !== null ) {
			$req->set_header( 'content-type', 'application/json' );
			$req->set_body( wp_json_encode( $body ) );
		}
		return $req;
	}

	/** Turn whatever a delegated handler returned into an MCP tool result. */
	protected static function unwrap( $returned ) {
		if ( is_wp_error( $returned ) ) {
			return self::tool_result( array(
				'error'   => $returned->get_error_code(),
				'message' => $returned->get_error_message(),
				'details' => $returned->get_error_data(),
			), true );
		}
		$data = method_exists( $returned, 'get_data' ) ? $returned->get_data() : $returned;
		return self::tool_result( $data );
	}

	protected static function call_tool( $id, $params, $request ) {
		$name = isset( $params['name'] ) ? (string) $params['name'] : '';
		$args = isset( $params['arguments'] ) && is_array( $params['arguments'] ) ? $params['arguments'] : array();

		$needs_client = array( 'get_client_profile', 'update_client_profile', 'list_client_artifacts', 'save_client_artifact' );
		$client_id    = (int) ( $args['client_id'] ?? 0 );
		if ( in_array( $name, $needs_client, true ) && $client_id <= 0 ) {
			return self::result( $id, self::tool_result( array(
				'error'   => 'missing_client_id',
				'message' => 'client_id is required. Use list_clients to find it from a name or business.',
			), true ) );
		}

		// Write tools need a write-scoped token. Reported as a tool error
		// rather than a protocol error so the model can explain it.
		if ( in_array( $name, array( 'update_client_profile', 'save_client_artifact' ), true ) ) {
			$allowed = Six_Profile_API::require_write( $request );
			if ( is_wp_error( $allowed ) ) {
				return self::result( $id, self::tool_result( array(
					'error'   => 'read_only_token',
					'message' => 'This connector is using a read-only token, so it cannot write. Mint a read & write token in WP Admin → 6ix Portal → API Tokens.',
				), true ) );
			}
		}

		switch ( $name ) {
			case 'list_clients':
				return self::result( $id, self::unwrap( Six_Profile_API::list_clients(
					self::sub_request( $request, 'GET', '/clients', array(
						'search'   => (string) ( $args['search'] ?? '' ),
						'page'     => (int) ( $args['page'] ?? 1 ),
						'per_page' => (int) ( $args['per_page'] ?? 50 ),
					) )
				) ) );

			case 'get_client_profile':
				return self::result( $id, self::unwrap( Six_Profile_API::get_profile(
					self::sub_request( $request, 'GET', '/clients/' . $client_id . '/profile', array( 'id' => $client_id ) )
				) ) );

			case 'update_client_profile':
				$fields = isset( $args['fields'] ) && is_array( $args['fields'] ) ? $args['fields'] : array();
				if ( ! $fields ) {
					return self::result( $id, self::tool_result( array(
						'error'    => 'no_fields',
						'message'  => 'Pass a `fields` object of field => value.',
						'writable' => array_keys( Six_Profile_API::writable_fields() ),
					), true ) );
				}
				return self::result( $id, self::unwrap( Six_Profile_API::patch_profile(
					self::sub_request( $request, 'PATCH', '/clients/' . $client_id . '/profile', array( 'id' => $client_id ), $fields )
				) ) );

			case 'list_client_artifacts':
				return self::result( $id, self::unwrap( Six_Profile_API::list_artifacts(
					self::sub_request( $request, 'GET', '/clients/' . $client_id . '/artifacts', array(
						'id'   => $client_id,
						'type' => (string) ( $args['type'] ?? '' ),
					) )
				) ) );

			case 'save_client_artifact':
				$body = array(
					'type'    => (string) ( $args['type'] ?? '' ),
					'payload' => $args['payload'] ?? array(),
				);
				if ( ! empty( $args['source'] ) ) $body['source'] = (string) $args['source'];
				return self::result( $id, self::unwrap( Six_Profile_API::create_artifact(
					self::sub_request( $request, 'POST', '/clients/' . $client_id . '/artifacts', array( 'id' => $client_id ), $body )
				) ) );
		}

		return self::error( $id, -32602, 'Unknown tool: ' . $name );
	}
}

Six_MCP_Server::init();
