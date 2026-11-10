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
		// carry no Origin. A browser-issued Origin we don't recognize is
		// rejected outright — belt and braces, since token auth (never
		// cookies) already means a hostile page can't authenticate at all.
		$origin = $request->get_header( 'origin' );
		if ( $origin && untrailingslashit( $origin ) !== untrailingslashit( site_url() )
		     && untrailingslashit( $origin ) !== untrailingslashit( home_url() ) ) {
			return new WP_Error( 'six_bad_origin', 'Unrecognized Origin.', array( 'status' => 403 ) );
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
				'description' => 'The full profile for one client: business identity, services and budgets, positioning and competitors, the SEO / Google Ads / Google Business inputs they gave during onboarding, BOTH questionnaires (`questionnaire.audit` answered at signup, `questionnaire.strategy` answered after the audit wins the work) each with its own completion, website project progress, and their current dashboard metrics. Read `stage` to see where they are: audit_intake_incomplete, ready_for_audit, awaiting_strategy_answers, or ready_for_strategy. Read this before writing any strategy, audit or analysis for a client, so the work reflects what they actually told us. Also returns `integrations`, which decides what the work may state as fact about this client\'s own domain. Two separate verdicts: `traffic_source` (`ga4_measured` — use GA4 for visitors, sessions and engagement and call them measured — or `ahrefs_estimate_only`) and `ranking_source` (`gsc_measured` — use Search Console for impressions, clicks, CTR, average position and the real query list, which is what Google served rather than what a crawler sampled — or `ahrefs_estimate_only`), with `own_domain_data` summarising both as full/partial/none. An `ahrefs_estimate_only` figure must be labelled an estimate and never presented as the client\'s own analytics. Ahrefs stays correct for competitors, backlinks and keyword volume, which no client account can see; never plot a measured series against estimated ones in the same comparison. Plus `field_provenance`, showing which fields an automation has changed and when.',
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
				'name'        => 'issue_strategy_questionnaire',
				'title'       => 'Issue the strategy questionnaire',
				'description' => 'Release the SEO strategy questionnaire to a client and return a shareable link they can fill in without logging in. Do this once the audit has been reviewed and the client wants to proceed — not before, since the strategy questions are long and nobody answers them before they have decided to work with us. Returns the link to send them. Requires a write-scoped token. Generating a link again invalidates the previous one.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'client_id' => array( 'type' => 'integer', 'description' => 'The client id, from list_clients.' ),
					),
					'required'   => array( 'client_id' ),
				),
			),
			array(
				'name'        => 'check_performance',
				'title'       => 'Check current performance',
				'description' => 'Where a client stands now, against where they started, where they were at the last check, and where the strategy said they would be by now. Returns a per-metric status of ahead / on_track / behind / no_projection, plus a summary count. This is the read for a bi-weekly or monthly review. A metric nobody projected comes back as no_projection with projected=null — that means no target was ever set, not that they are failing, so do not treat it as a miss. If there are no snapshots at all, capture one first.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'client_id' => array( 'type' => 'integer', 'description' => 'The client id, from list_clients.' ),
						'as_of'     => array( 'type' => 'string', 'description' => 'Optional YYYY-MM-DD to judge projections against. Defaults to today.' ),
					),
					'required'   => array( 'client_id' ),
				),
			),
			array(
				'name'        => 'capture_performance_snapshot',
				'title'       => 'Capture a performance snapshot',
				'description' => 'Record where the client stands right now, so future checks have something to compare against. Snapshots are also captured automatically each period; calling this is for taking one on demand, e.g. a baseline at the moment a strategy is approved. At most one snapshot is kept per client per week — calling it again in the same week refreshes that week rather than adding a duplicate. Requires a write-scoped token.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'client_id' => array( 'type' => 'integer', 'description' => 'The client id, from list_clients.' ),
					),
					'required'   => array( 'client_id' ),
				),
			),
			array(
				'name'        => 'save_strategy_projections',
				'title'       => 'Save what the strategy projects',
				'description' => 'Record what a strategy commits to, so a later check can ask whether we are where we said we would be rather than only whether the number went up. Save these when a strategy is approved. Each projection is a metric, a target date and a value — e.g. organic_visitors reaching 4000 by 2027-03-01. Re-running the strategy supersedes an earlier target for the same metric and date. Only project what the strategy actually argues for; an invented target makes every future comparison meaningless. Requires a write-scoped token.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'client_id'   => array( 'type' => 'integer', 'description' => 'The client id, from list_clients.' ),
						'artifact_id' => array( 'type' => 'integer', 'description' => 'Optional id of the saved strategy artifact these came from.' ),
						'projections' => array(
							'type'  => 'array',
							'description' => 'The targets the strategy sets.',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'metric_key'      => array( 'type' => 'string', 'description' => 'e.g. organic_visitors, keywords_page1, paid_clicks, gmb_views' ),
									'target_date'     => array( 'type' => 'string', 'description' => 'YYYY-MM-DD the target should be met by' ),
									'projected_value' => array( 'type' => 'number', 'description' => 'The figure projected for that date' ),
									'note'            => array( 'type' => 'string', 'description' => 'Optional one line on what drives it' ),
								),
								'required' => array( 'metric_key', 'target_date', 'projected_value' ),
							),
						),
					),
					'required'   => array( 'client_id', 'projections' ),
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
				'description' => 'Store output you produced against a client — a keyword analysis, a GMB post schedule, an audit report — so it shows on their dashboard and is available to later sessions. Requires a write-scoped token. Give it a stable `type` so the dashboard can recognize it.',
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
			array(
				'name'        => 'get_live_metrics',
				'title'       => 'Live data from the client\'s connected accounts',
				'description' => 'Everything the portal can currently read for one client, from the accounts they have actually connected: Google Analytics 4 (sessions, users, pageviews, and the split by channel — the organic figure is the Organic Search channel, not the total), Search Console (clicks, impressions, CTR, average position), Google Ads (clicks, impressions, conversions, cost) and Meta Ads. This is MEASURED data about their own property, which is a different and much stronger claim than any third-party estimate: state it as fact and say the period. Every source reports its own status — ok, not_connected, or error with the reason — so a missing number is never silently filled by a guess. Use this before writing any audit, strategy, report or performance review, and use get_search_console_queries for the query-level detail.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'client_id' => array( 'type' => 'integer', 'description' => 'The client id, from list_clients.' ),
						'days'      => array( 'type' => 'integer', 'description' => 'How many days back, 1-365. Default 30.' ),
						'sources'   => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string' ),
							'description' => 'Optional subset: ga4, search_console, google_ads, meta_ads. Default is everything connected.',
						),
					),
					'required'   => array( 'client_id' ),
				),
			),
			array(
				'name'        => 'get_search_console_queries',
				'title'       => 'What Google actually shows this client for',
				'description' => 'The client\'s real search queries from Google Search Console — what Google served them for, with impressions, clicks, CTR and average position for each. This is what happened, not what a crawler sampled, so it outranks any third-party ranking estimate for this domain. Three findings come out of it that nothing external can produce: queries with high impressions and almost no clicks (they rank but the title is not earning the click), queries sitting at positions 11-20 (one page of effort from real traffic), and the gap between what the client said they want to be found for and what they are actually found for — which is often the most useful line in an audit. Needs Search Console connected for this client; returns a clear reason if not.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'client_id' => array( 'type' => 'integer', 'description' => 'The client id, from list_clients.' ),
						'days'      => array( 'type' => 'integer', 'description' => 'How many days back, 1-480. Default 28.' ),
						'limit'     => array( 'type' => 'integer', 'description' => 'How many queries, 1-100. Default 25.' ),
					),
					'required'   => array( 'client_id' ),
				),
			),
			array(
				'name'        => 'get_pagespeed',
				'title'       => 'Measure a page with Google PageSpeed Insights',
				'description' => 'Runs Google\'s own PageSpeed Insights against any URL and returns the performance score plus Core Web Vitals (First Contentful Paint, Largest Contentful Paint, Total Blocking Time, Cumulative Layout Shift, Speed Index). Takes a URL rather than a client, so it works for a prospect we hold no accounts for. Google\'s tool on purpose: the reader can check the figure at pagespeed.web.dev and get the same number, which a timing run of our own could never promise. It is a lab measurement of one load, not an average of real visitors — say so. Returns a clear error rather than a number if the quota is exhausted.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'url'      => array( 'type' => 'string', 'description' => 'The page to measure, e.g. https://example.ca/services.' ),
						'strategy' => array( 'type' => 'string', 'description' => 'mobile (default) or desktop. Mobile is what Google ranks on.' ),
					),
					'required'   => array( 'url' ),
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
	 * same JSON serialized goes in a text block — the spec asks for both, so
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
				"The portal is also a live window onto the accounts each client has connected. get_live_metrics returns their Google Analytics 4, Search Console, Google Ads and Meta figures; get_search_console_queries returns what Google actually serves them for; get_pagespeed measures any URL with Google's own tool. Read these before writing numbers into an audit, strategy or report.\n\n" .
				"The distinction those tools exist to protect: data from a client's own connected account is MEASURED and may be stated as fact with its period named. Anything from a third party — Ahrefs above all — is an ESTIMATE and must say so, because the client can check the first kind against an analytics tab they already have open. Ahrefs stays correct for competitors, backlinks and keyword volume, which no client account can see. Never put a measured series and an estimated series on the same axis. Every source in get_live_metrics reports its own status, so not_connected and error are distinguishable from each other and from zero; none of the three is a reason to invent a figure.\n\n" .
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

		$needs_client = array( 'get_client_profile', 'update_client_profile', 'list_client_artifacts', 'save_client_artifact', 'check_performance', 'capture_performance_snapshot', 'save_strategy_projections' );
		$client_id    = (int) ( $args['client_id'] ?? 0 );
		if ( in_array( $name, $needs_client, true ) && $client_id <= 0 ) {
			return self::result( $id, self::tool_result( array(
				'error'   => 'missing_client_id',
				'message' => 'client_id is required. Use list_clients to find it from a name or business.',
			), true ) );
		}

		// Write tools need a write-scoped token. Reported as a tool error
		// rather than a protocol error so the model can explain it.
		if ( in_array( $name, array( 'update_client_profile', 'save_client_artifact', 'issue_strategy_questionnaire', 'capture_performance_snapshot', 'save_strategy_projections' ), true ) ) {
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

			case 'issue_strategy_questionnaire':
				if ( ! function_exists( 'six_issue_strategy_questionnaire' ) ) {
					return self::result( $id, self::tool_result( array(
						'error' => 'unavailable', 'message' => 'The questionnaire module is not loaded on this site.',
					), true ) );
				}
				six_issue_strategy_questionnaire( $client_id );
				$link = six_questionnaire_link( $client_id, true );
				return self::result( $id, self::tool_result( array(
					'issued'       => true,
					'link'         => $link,
					'expires_days' => SIX_Q_TOKEN_DAYS,
					'note'         => 'Send this link to the client. Any link issued earlier no longer works.',
				) ) );

			case 'check_performance':
				if ( ! class_exists( 'Six_Performance' ) ) {
					return self::result( $id, self::tool_result( array( 'error' => 'unavailable',
						'message' => 'Performance tracking is not loaded on this site.' ), true ) );
				}
				return self::result( $id, self::tool_result(
					Six_Performance::compare( $client_id, (string) ( $args['as_of'] ?? '' ) ?: null )
				) );

			case 'capture_performance_snapshot':
				if ( ! class_exists( 'Six_Performance' ) ) {
					return self::result( $id, self::tool_result( array( 'error' => 'unavailable',
						'message' => 'Performance tracking is not loaded on this site.' ), true ) );
				}
				$snap = Six_Performance::capture( $client_id, 'api' );
				if ( ! $snap ) {
					// capture() returns null both when there is nothing to
					// measure and when the write failed. Distinguish them, so
					// a storage fault is never reported as "this client has no
					// services" — a wrong answer that sends the caller to the
					// wrong place entirely.
					$ctx   = Six_Performance::context( $client_id );
					$no_svc = empty( $ctx['slugs'] );
					return self::result( $id, self::tool_result( array(
						'error'   => $no_svc ? 'nothing_to_capture' : 'capture_failed',
						'message' => $no_svc
							? 'This client has no active services, so there is nothing to measure yet.'
							: 'The snapshot could not be stored, so nothing was captured. The performance store may not exist on this site yet — an administrator can create it from WP Admin → 6ix Portal → Integrations, or by loading any admin page once after the latest deploy.',
					), true ) );
				}
				return self::result( $id, self::tool_result( array(
					'captured_at' => $snap['captured_at'],
					'period'      => $snap['period_key'],
					'values'      => $snap['metrics']['values'],
					'sources'     => $snap['metrics']['sources'],
				) ) );

			case 'save_strategy_projections':
				if ( ! class_exists( 'Six_Performance' ) ) {
					return self::result( $id, self::tool_result( array( 'error' => 'unavailable',
						'message' => 'Performance tracking is not loaded on this site.' ), true ) );
				}
				$rows = isset( $args['projections'] ) && is_array( $args['projections'] ) ? $args['projections'] : array();
				if ( ! $rows ) {
					return self::result( $id, self::tool_result( array(
						'error'   => 'no_projections',
						'message' => 'Pass a `projections` array of {metric_key, target_date, projected_value}.',
					), true ) );
				}
				$res = Six_Performance::save_projections( $client_id, $rows, (int) ( $args['artifact_id'] ?? 0 ) );
				// A storage error is reported as its own message rather than
				// being folded into "0 saved", which reads as "your rows were
				// all rejected" and sends the caller looking at their data.
				if ( ! empty( $res['error'] ) ) {
					return self::result( $id, self::tool_result( array(
						'error'   => 'storage_unavailable',
						'message' => $res['error'],
					), true ) );
				}
				return self::result( $id, self::tool_result( array(
					'saved'    => $res['saved'],
					'rejected' => $res['rejected'],
					'note'     => $res['rejected'] ? 'Rejected entries were missing a metric_key, a YYYY-MM-DD target_date, or a value.' : '',
				), $res['saved'] === 0 ) );

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

			case 'get_live_metrics':
				return self::result( $id, self::tool_result(
					self::live_metrics( $client_id, (int) ( $args['days'] ?? 30 ), (array) ( $args['sources'] ?? array() ) )
				) );

			case 'get_search_console_queries':
				return self::result( $id, self::tool_result(
					self::gsc_queries( $client_id, (int) ( $args['days'] ?? 28 ), (int) ( $args['limit'] ?? 25 ) )
				) );

			case 'get_pagespeed':
				if ( ! class_exists( 'Six_Analytics' ) ) {
					return self::result( $id, self::tool_result( array(
						'error' => 'unavailable', 'message' => 'The analytics integration is not loaded on this site.',
					), true ) );
				}
				$ps = Six_Analytics::pagespeed( (string) ( $args['url'] ?? '' ), (string) ( $args['strategy'] ?? 'mobile' ) );
				return self::result( $id, self::tool_result( $ps, isset( $ps['error'] ) ) );
		}

		return self::error( $id, -32602, 'Unknown tool: ' . $name );
	}

	// ── Live integration reads ────────────────────────────────────────────
	// The point of connecting accounts in the portal is that a session doing
	// client work can read them. Every source reports its own status, because
	// a source that failed and a source that is not connected are different
	// facts and neither is "zero".

	/**
	 * A nonsense period falls back to the default rather than clamping to 1.
	 * Clamping would quietly return a one-day window, and a one-day window of
	 * a normal site looks exactly like a site with almost no traffic — a
	 * wrong answer that reads as a finding.
	 */
	protected static function period( $days, $default, $max ) {
		$days = (int) $days;
		if ( $days < 1 ) $days = $default;
		return min( $max, $days );
	}

	protected static function live_metrics( $client_id, $days, $want ) {
		$days = self::period( $days, 30, 365 );
		$want = array_filter( array_map( 'sanitize_key', $want ) );
		$all  = array( 'ga4', 'search_console', 'google_ads', 'meta_ads' );
		$want = $want ? array_values( array_intersect( $all, $want ) ) : $all;

		$meta = function ( $k ) use ( $client_id ) {
			return trim( (string) get_user_meta( $client_id, $k, true ) );
		};
		$out = array(
			'client_id'   => $client_id,
			'period_days' => $days,
			'sources'     => array(),
		);

		if ( in_array( 'ga4', $want, true ) ) {
			$prop = $meta( 'six_ga4_property_id' ) ?: get_option( 'six_ga4_property_id', '' );
			if ( ! $prop || ! class_exists( 'Six_Analytics' ) ) {
				$out['sources']['ga4'] = array( 'status' => 'not_connected',
					'message' => 'No GA4 property for this client. Any traffic figure must be labelled an Ahrefs estimate.' );
			} else {
				$r = Six_Analytics::ga4_summary( $prop, $days );
				if ( isset( $r['error'] ) ) {
					$out['sources']['ga4'] = array( 'status' => 'error', 'message' => $r['error'] );
				} else {
					$organic = null;
					foreach ( (array) ( $r['by_channel'] ?? array() ) as $ch ) {
						if ( stripos( (string) $ch['channel'], 'organic search' ) !== false ) {
							$organic = (int) $organic + (int) $ch['sessions'];
						}
					}
					$r['organic_sessions'] = $organic;
					$r['status']  = 'ok';
					$r['measured'] = true;
					$r['note']    = 'Their own analytics. `organic_sessions` is the Organic Search channel only; '
						. '`totals.sessions` includes paid, direct and referral.';
					$out['sources']['ga4'] = $r;
				}
			}
		}

		if ( in_array( 'search_console', $want, true ) ) {
			$site = $meta( 'six_gsc_site' );
			if ( ! $site || ! class_exists( 'Six_Analytics' ) ) {
				$out['sources']['search_console'] = array( 'status' => 'not_connected',
					'message' => 'No Search Console property for this client. Ranking figures must be labelled Ahrefs estimates.' );
			} else {
				$r = Six_Analytics::gsc_summary( $site, min( 480, $days ) );
				if ( isset( $r['error'] ) ) {
					$out['sources']['search_console'] = array( 'status' => 'error', 'message' => $r['error'] );
				} else {
					unset( $r['top_queries'] );   // get_search_console_queries serves those
					$r['status']   = 'ok';
					$r['measured'] = true;
					$r['note']     = 'What Google actually served. Use get_search_console_queries for the query list.';
					$out['sources']['search_console'] = $r;
				}
			}
		}

		if ( in_array( 'google_ads', $want, true ) ) {
			if ( ! $meta( 'six_gads_customer_id' ) || ! class_exists( 'Six_Google_Ads' ) ) {
				$out['sources']['google_ads'] = array( 'status' => 'not_connected',
					'message' => 'No Google Ads customer id for this client.' );
			} else {
				$r = Six_Google_Ads::get_campaign_metrics_for_client( $client_id, 'LAST_30_DAYS' );
				$out['sources']['google_ads'] = ( is_array( $r ) && $r )
					? array_merge( array( 'status' => 'ok', 'measured' => true, 'period' => 'last 30 days' ), $r )
					: array( 'status' => 'error', 'message' => method_exists( 'Six_Google_Ads', 'get_last_error' )
						? (string) Six_Google_Ads::get_last_error() : 'Google Ads returned nothing for this client.' );
			}
		}

		if ( in_array( 'meta_ads', $want, true ) ) {
			if ( ! $meta( 'six_meta_ad_account_id' ) || ! class_exists( 'Six_Meta' ) ) {
				$out['sources']['meta_ads'] = array( 'status' => 'not_connected',
					'message' => 'No Meta ad account for this client.' );
			} else {
				$r = Six_Meta::client_report( $client_id, $days );
				$out['sources']['meta_ads'] = isset( $r['error'] )
					? array( 'status' => 'error', 'message' => $r['error'] )
					: array_merge( array( 'status' => 'ok', 'measured' => true ), $r );
			}
		}

		$ok = array();
		foreach ( $out['sources'] as $k => $v ) if ( ( $v['status'] ?? '' ) === 'ok' ) $ok[] = $k;
		$out['measured_sources'] = $ok;
		$out['guidance'] = $ok
			? 'These are measured figures from the client\'s own accounts — state them as fact and name the period. '
			  . 'Ahrefs remains correct for competitors, backlinks and keyword volume, which no client account can see. '
			  . 'Never plot a measured series against estimated ones in the same comparison.'
			: 'Nothing is connected or answering for this client, so every figure must be labelled an estimate and '
			  . 'attributed to the tool that produced it. Connecting analytics is itself a finding worth reporting.';
		return $out;
	}

	protected static function gsc_queries( $client_id, $days, $limit ) {
		$days  = self::period( $days, 28, 480 );
		$limit = self::period( $limit, 25, 100 );
		$site  = trim( (string) get_user_meta( $client_id, 'six_gsc_site', true ) );
		if ( ! $site || ! class_exists( 'Six_Analytics' ) ) {
			return array(
				'error'   => 'not_connected',
				'message' => 'No Search Console property is saved for this client, so there is no query data to read. '
					. 'An advisor connects it under the client\'s Data Sources tab. Until then, ranking figures can '
					. 'only come from Ahrefs and must be labelled estimates.',
			);
		}
		$r = Six_Analytics::gsc_summary( $site, $days );
		if ( isset( $r['error'] ) ) return array( 'error' => 'search_console_error', 'message' => $r['error'] );

		$queries = array_slice( (array) ( $r['top_queries'] ?? array() ), 0, $limit );
		return array(
			'client_id' => $client_id,
			'site'      => $r['site'] ?? $site,
			'period'    => $r['period'] ?? '',
			'totals'    => $r['totals'] ?? array(),
			'queries'   => $queries,
			'measured'  => true,
			'note'      => 'What Google actually served this site for, with impressions, clicks, CTR and average '
				. 'position per query. Worth reading for three things nothing external can show: high impressions '
				. 'with near-zero CTR (ranking but not earning the click), positions 11-20 (one page of effort from '
				. 'real traffic), and queries that differ from what the client said they want to be found for.',
		);
	}
}

Six_MCP_Server::init();
