<?php
/**
 * The client profile API — the contract external tools read and write.
 *
 * This is the piece that turns the portal into the system of record: one
 * canonical view of a customer (who they are, what they bought, the keywords,
 * competitors and market they told us about, and their current numbers) that
 * the SEO/GMB Claude skills, Ahrefs, BrightLocal and anything else can pull
 * from instead of each tool keeping its own half-stale copy.
 *
 * Transport is REST under /wp-json/six/v1/. An MCP server, when it comes, is a
 * thin wrapper over these same routes rather than a second implementation.
 *
 * Auth: a bearer token minted in WP Admin, sent as `Authorization: Bearer …`
 * or `X-6ix-Token: …`. Tokens are stored hashed, carry an explicit scope
 * (read / write) and can be revoked individually. There is no cookie or
 * nonce path into these routes — they are for machines, not browsers.
 *
 * Writes keep provenance. Agents are allowed to update profile fields, but
 * every write records which token changed which field and when, and the
 * customer's original onboarding answer is preserved the first time a field is
 * overwritten, so "what did the customer actually tell us" is always
 * recoverable.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Six_Profile_API {

	const NS           = 'six/v1';
	const TOKENS_OPT   = 'six_api_tokens';
	const ORIGIN_META  = 'six_profile_field_origin';
	const ORIGINAL_META= 'six_profile_original_answers';

	/**
	 * API field name => six_checkout_progress column. This allowlist is the
	 * ONLY way a write can reach the database; anything not named here is
	 * rejected rather than passed through to a column name we didn't vet.
	 */
	public static function writable_fields() {
		return array(
			'business_name'   => 'business_name',
			'website'         => 'website',
			'industry'        => 'industry',
			'location'        => 'location',
			'goal'            => 'goal',
			'competitors'     => 'competitors',
			'ads_keywords'    => 'ads_keywords',
			'ads_locations'   => 'ads_locations',
			'ads_products'    => 'ads_products',
			'ads_usp'         => 'ads_usp',
			'seo_keywords'    => 'seo_keywords',
			'seo_locations'   => 'seo_locations',
			'seo_pages'       => 'seo_pages',
			'seo_competitors' => 'seo_competitors',
			'seo_usp'         => 'seo_usp',
			'gbp_name'        => 'gbp_name',
			'gbp_category'    => 'gbp_category',
			'gbp_services'    => 'gbp_services',
		);
	}

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function routes() {
		$auth_read  = array( __CLASS__, 'require_read' );
		$auth_write = array( __CLASS__, 'require_write' );

		register_rest_route( self::NS, '/clients', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'list_clients' ),
			'permission_callback' => $auth_read,
		) );

		register_rest_route( self::NS, '/clients/(?P<id>\d+)/profile', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_profile' ),
				'permission_callback' => $auth_read,
			),
			array(
				'methods'             => 'PATCH',
				'callback'            => array( __CLASS__, 'patch_profile' ),
				'permission_callback' => $auth_write,
			),
		) );

		register_rest_route( self::NS, '/clients/(?P<id>\d+)/artifacts', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'list_artifacts' ),
				'permission_callback' => $auth_read,
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'create_artifact' ),
				'permission_callback' => $auth_write,
			),
		) );
	}

	// ── Auth ──────────────────────────────────────────────────────────────

	/** Pull the bearer token off the request, from either accepted header. */
	protected static function presented_token( $request ) {
		$auth = $request->get_header( 'authorization' );
		if ( $auth && stripos( $auth, 'bearer ' ) === 0 ) return trim( substr( $auth, 7 ) );
		return trim( (string) $request->get_header( 'x_6ix_token' ) );
	}

	/**
	 * Resolve a presented token to its stored record, or null.
	 * Compared by hash with hash_equals so a wrong token can't be narrowed down
	 * by timing, and so a database leak doesn't hand over working credentials.
	 */
	public static function resolve_token( $request ) {
		$presented = self::presented_token( $request );
		if ( $presented === '' ) return null;

		$hash = hash( 'sha256', $presented );
		foreach ( (array) get_option( self::TOKENS_OPT, array() ) as $rec ) {
			if ( empty( $rec['hash'] ) || ! empty( $rec['revoked'] ) ) continue;
			if ( hash_equals( (string) $rec['hash'], $hash ) ) return $rec;
		}
		return null;
	}

	public static function require_read( $request ) {
		$tok = self::resolve_token( $request );
		if ( ! $tok ) return new WP_Error( 'six_unauthorised', 'A valid API token is required.', array( 'status' => 401 ) );
		return true;
	}

	public static function require_write( $request ) {
		$tok = self::resolve_token( $request );
		if ( ! $tok ) return new WP_Error( 'six_unauthorised', 'A valid API token is required.', array( 'status' => 401 ) );
		if ( ( $tok['scope'] ?? 'read' ) !== 'write' ) {
			return new WP_Error( 'six_forbidden', 'This token is read-only.', array( 'status' => 403 ) );
		}
		return true;
	}

	/** Mint a token. The plaintext is returned ONCE and never stored. */
	public static function create_token( $label, $scope = 'read' ) {
		$plain = 'six_' . bin2hex( random_bytes( 24 ) );
		$all   = (array) get_option( self::TOKENS_OPT, array() );
		$all[] = array(
			'id'      => wp_generate_uuid4(),
			'label'   => sanitize_text_field( $label ),
			'scope'   => $scope === 'write' ? 'write' : 'read',
			'hash'    => hash( 'sha256', $plain ),
			'created' => current_time( 'mysql' ),
			'revoked' => 0,
		);
		update_option( self::TOKENS_OPT, $all, false );
		return $plain;
	}

	public static function revoke_token( $id ) {
		$all = (array) get_option( self::TOKENS_OPT, array() );
		foreach ( $all as &$rec ) if ( ( $rec['id'] ?? '' ) === $id ) $rec['revoked'] = 1;
		update_option( self::TOKENS_OPT, $all, false );
	}

	// ── Reads ─────────────────────────────────────────────────────────────

	public static function list_clients( $request ) {
		$page  = max( 1, (int) $request->get_param( 'page' ) );
		$per   = min( 100, max( 1, (int) ( $request->get_param( 'per_page' ) ?: 50 ) ) );
		$users = get_users( array(
			'role'    => 'six_customer',
			'number'  => $per,
			'paged'   => $page,
			'orderby' => 'ID',
			'order'   => 'DESC',
		) );

		$out = array();
		foreach ( $users as $u ) {
			$out[] = array(
				'id'            => $u->ID,
				'name'          => $u->display_name,
				'email'         => $u->user_email,
				'business_name' => self::checkout( $u->ID )->business_name ?? '',
				'services'      => self::service_slugs( $u->ID ),
				'profile_url'   => rest_url( self::NS . '/clients/' . $u->ID . '/profile' ),
			);
		}
		return rest_ensure_response( array( 'page' => $page, 'per_page' => $per, 'clients' => $out ) );
	}

	protected static function checkout( $client_id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}six_checkout_progress WHERE user_id=%d", $client_id
		) );
		return $row ?: (object) array();
	}

	protected static function service_slugs( $client_id ) {
		global $wpdb;
		return (array) $wpdb->get_col( $wpdb->prepare(
			"SELECT service_slug FROM {$wpdb->prefix}six_client_services WHERE client_id=%d AND status='active'",
			$client_id
		) );
	}

	/** Split a stored comma-separated answer into a clean list. */
	protected static function listify( $csv ) {
		return array_values( array_filter( array_map( 'trim', explode( ',', (string) $csv ) ) ) );
	}

	/**
	 * The canonical client profile. This is the document every external tool
	 * reads — keep its shape stable, because skills will depend on it.
	 */
	public static function get_profile( $request ) {
		$id   = (int) $request['id'];
		$user = get_userdata( $id );
		if ( ! $user ) return new WP_Error( 'six_not_found', 'No such client.', array( 'status' => 404 ) );

		global $wpdb;
		$c      = self::checkout( $id );
		$slugs  = self::service_slugs( $id );
		$svcs   = $wpdb->get_results( $wpdb->prepare(
			"SELECT service_slug, service_name, status, budget FROM {$wpdb->prefix}six_client_services WHERE client_id=%d", $id
		) );

		$budgets = array();
		foreach ( $svcs as $s ) $budgets[ $s->service_slug ] = (float) $s->budget;

		$metrics = class_exists( 'Six_Dash_Metrics' )
			? Six_Dash_Metrics::cards( $id, array(
				'slugs'   => $slugs,
				'budgets' => $budgets,
				'kpi'     => array(),
				'cpc'     => (float) get_user_meta( $id, 'six_market_avg_cpc', true ),
			) )
			: array();

		$profile = array(
			'id'       => $id,
			'identity' => array(
				'name'          => $user->display_name,
				'email'         => $user->user_email,
				'phone'         => get_user_meta( $id, 'billing_phone', true ),
				'business_name' => $c->business_name ?? '',
				'website'       => $c->website ?? '',
				'industry'      => $c->industry ?? '',
				'location'      => $c->location ?? ( $c->business_address ?? '' ),
				'years_in_business' => $c->years_in_business ?? '',
			),
			'commercial' => array(
				'services'          => $slugs,
				'budgets'           => $budgets,
				'monthly_recurring' => array_sum( array_map(
					fn( $slug ) => $slug === 'website' ? 0 : ( $budgets[ $slug ] ?? 0 ),
					array_keys( $budgets )
				) ),
				'website_maintenance' => class_exists( 'Six_Website_Project' ) && Six_Website_Project::maintenance_on( $id ),
			),
			'positioning' => array(
				'goal'        => $c->goal ?? '',
				'competitors' => self::listify( $c->competitors ?? '' ),
			),
			'seo' => array(
				'keywords'    => self::listify( $c->seo_keywords ?? '' ),
				'locations'   => self::listify( $c->seo_locations ?? '' ),
				'pages'       => $c->seo_pages ?? '',
				'competitors' => self::listify( $c->seo_competitors ?? '' ),
				'usp'         => $c->seo_usp ?? '',
			),
			'google_ads' => array(
				'keywords'  => self::listify( $c->ads_keywords ?? '' ),
				'locations' => self::listify( $c->ads_locations ?? '' ),
				'products'  => $c->ads_products ?? '',
				'usp'       => $c->ads_usp ?? '',
			),
			'google_business' => array(
				'listing_name' => $c->gbp_name ?? '',
				'category'     => $c->gbp_category ?? '',
				'services'     => $c->gbp_services ?? '',
				'rating'       => $c->gbp_rating ?? '',
			),
			'website_project' => class_exists( 'Six_Website_Project' )
				? Six_Website_Project::progress( $id ) : null,
			'current_metrics'  => $metrics,
			'field_provenance' => (array) get_user_meta( $id, self::ORIGIN_META, true ),
		);

		return rest_ensure_response( $profile );
	}

	// ── Writes ────────────────────────────────────────────────────────────

	/**
	 * Update profile fields. Only the allowlisted fields above can be written.
	 *
	 * The first time a field is changed by anything other than the customer,
	 * their original answer is stashed in six_profile_original_answers so it's
	 * never lost, and every change records which token made it. That's what
	 * makes full agent write access safe to live with: you can always see what
	 * the customer said versus what an automation decided.
	 */
	public static function patch_profile( $request ) {
		global $wpdb;
		$id = (int) $request['id'];
		if ( ! get_userdata( $id ) ) return new WP_Error( 'six_not_found', 'No such client.', array( 'status' => 404 ) );

		$allowed = self::writable_fields();
		$body    = (array) $request->get_json_params();
		$token   = self::resolve_token( $request );
		$who     = $token['label'] ?? 'api';

		$updates = array();
		$rejected = array();
		foreach ( $body as $field => $value ) {
			if ( ! isset( $allowed[ $field ] ) ) { $rejected[] = $field; continue; }
			// Lists are stored as the comma-separated strings the rest of the
			// system already reads.
			if ( is_array( $value ) ) $value = implode( ', ', array_map( 'strval', $value ) );
			$updates[ $allowed[ $field ] ] = sanitize_text_field( (string) $value );
		}

		if ( ! $updates ) {
			return new WP_Error( 'six_nothing_to_write',
				'No writable fields supplied.' . ( $rejected ? ' Rejected: ' . implode( ', ', $rejected ) : '' ),
				array( 'status' => 400, 'writable' => array_keys( $allowed ) ) );
		}

		$current  = self::checkout( $id );
		$original = (array) get_user_meta( $id, self::ORIGINAL_META, true );
		$origin   = (array) get_user_meta( $id, self::ORIGIN_META, true );

		foreach ( $updates as $col => $new ) {
			// Preserve the customer's own answer the first time it's replaced.
			if ( ! array_key_exists( $col, $original ) ) $original[ $col ] = $current->$col ?? '';
			$origin[ $col ] = array( 'source' => $who, 'at' => current_time( 'mysql' ) );
		}

		$wpdb->update( $wpdb->prefix . 'six_checkout_progress', $updates, array( 'user_id' => $id ) );
		update_user_meta( $id, self::ORIGINAL_META, $original );
		update_user_meta( $id, self::ORIGIN_META, $origin );

		if ( class_exists( 'Six_Odoo' ) ) {
			$lead = (int) get_user_meta( $id, 'six_odoo_lead_id', true );
			if ( $lead ) {
				Six_Odoo::post_note( $lead, 'Profile updated via API by "' . $who . '": '
					. implode( ', ', array_keys( $updates ) ) );
			}
		}

		return rest_ensure_response( array(
			'updated'  => array_keys( $updates ),
			'rejected' => $rejected,
			'profile'  => self::get_profile( $request )->get_data(),
		) );
	}

	// ── Artifacts (what the skills produce) ───────────────────────────────

	protected static function artifacts_table() {
		global $wpdb;
		return $wpdb->prefix . 'six_client_artifacts';
	}

	public static function list_artifacts( $request ) {
		global $wpdb;
		$id   = (int) $request['id'];
		$type = sanitize_key( (string) $request->get_param( 'type' ) );
		$t    = self::artifacts_table();
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$t}'" ) !== $t ) return rest_ensure_response( array( 'artifacts' => array() ) );

		$rows = $type
			? $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE client_id=%d AND type=%s ORDER BY created_at DESC LIMIT 50", $id, $type ) )
			: $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE client_id=%d ORDER BY created_at DESC LIMIT 50", $id ) );

		return rest_ensure_response( array( 'artifacts' => array_map( function ( $r ) {
			return array(
				'id'         => (int) $r->id,
				'type'       => $r->type,
				'source'     => $r->source,
				'payload'    => json_decode( $r->payload, true ),
				'created_at' => $r->created_at,
			);
		}, $rows ) ) );
	}

	/**
	 * Store a result produced by a skill or connector — a keyword analysis, a
	 * post schedule, a report. Payload shape is up to the producer; the
	 * dashboard renders the types it recognises and ignores the rest, so a new
	 * kind of output never breaks an existing page.
	 */
	public static function create_artifact( $request ) {
		global $wpdb;
		$id   = (int) $request['id'];
		if ( ! get_userdata( $id ) ) return new WP_Error( 'six_not_found', 'No such client.', array( 'status' => 404 ) );

		$body = (array) $request->get_json_params();
		$type = sanitize_key( $body['type'] ?? '' );
		if ( $type === '' ) return new WP_Error( 'six_bad_request', 'An artifact "type" is required.', array( 'status' => 400 ) );

		$token = self::resolve_token( $request );
		$wpdb->insert( self::artifacts_table(), array(
			'client_id'  => $id,
			'type'       => $type,
			'source'     => sanitize_text_field( $body['source'] ?? ( $token['label'] ?? 'api' ) ),
			'payload'    => wp_json_encode( $body['payload'] ?? array() ),
			'created_at' => current_time( 'mysql' ),
		) );

		return rest_ensure_response( array( 'id' => (int) $wpdb->insert_id, 'type' => $type ) );
	}
}

Six_Profile_API::init();
