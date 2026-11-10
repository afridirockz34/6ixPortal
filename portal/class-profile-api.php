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
		// The questionnaire's own fields are writable too — an advisor's Claude
		// session can capture answers given on a call, and a skill can write
		// back a refined answer. Derived from the questionnaire schema rather
		// than restated, so the two can't drift apart, and still an explicit
		// allowlist: a column that isn't a declared question stays unwritable.
		$questionnaire = array();
		if ( function_exists( 'six_seo_questionnaire_fields' ) ) {
			foreach ( six_seo_questionnaire_fields() as $key => $f ) {
				// Identity and contact details are not an agent's to rewrite.
				if ( in_array( $f['section'] ?? '', array( 'contact' ), true ) ) continue;
				$questionnaire[ $key ] = $f['maps_to'] ?? $key;
			}
		}

		return $questionnaire + array(
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
	/**
	 * Pull the token out of whichever header the caller used.
	 *
	 * Deliberately permissive about WHERE the token arrives, while staying
	 * strict about whether it is valid. claude.ai's custom-connector dialog
	 * only lets you pick from a short list of pre-approved header names
	 * (authorization, x-api-key, x-auth-token) — anything else is rejected at
	 * save time — and it sends the value exactly as typed, adding no "Bearer "
	 * prefix of its own. Accepting only one spelling turned a reasonable
	 * choice in that dialog into a silent 401 with nothing to debug.
	 *
	 * Every path below still ends at the same constant-time hash comparison,
	 * so this widens the ways a correct token can be presented, not the set
	 * of tokens that are accepted.
	 */
	protected static function presented_token( $request ) {
		$auth = trim( (string) $request->get_header( 'authorization' ) );
		if ( $auth !== '' ) {
			// "Bearer <token>", or the bare token if the prefix was omitted.
			if ( stripos( $auth, 'bearer ' ) === 0 ) return trim( substr( $auth, 7 ) );
			return $auth;
		}
		foreach ( array( 'x_6ix_token', 'x_api_key', 'x_auth_token' ) as $h ) {
			$v = trim( (string) $request->get_header( $h ) );
			if ( $v !== '' ) {
				if ( stripos( $v, 'bearer ' ) === 0 ) return trim( substr( $v, 7 ) );
				return $v;
			}
		}
		return '';
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
		$page   = max( 1, (int) $request->get_param( 'page' ) );
		$per    = min( 100, max( 1, (int) ( $request->get_param( 'per_page' ) ?: 50 ) ) );
		$search = trim( (string) $request->get_param( 'search' ) );

		$args = array(
			'role'    => 'six_customer',
			'number'  => $per,
			'paged'   => $page,
			'orderby' => 'ID',
			'order'   => 'DESC',
		);
		// Callers generally know a person or a business, not a numeric id, so
		// searching covers the WP user fields AND the business name captured
		// during onboarding.
		if ( $search !== '' ) {
			$args['search']         = '*' . $search . '*';
			$args['search_columns'] = array( 'user_login', 'user_email', 'display_name', 'user_nicename' );
		}
		$users = get_users( $args );

		if ( $search !== '' ) {
			global $wpdb;
			$by_biz = (array) $wpdb->get_col( $wpdb->prepare(
				"SELECT user_id FROM {$wpdb->prefix}six_checkout_progress WHERE business_name LIKE %s LIMIT 50",
				'%' . $wpdb->esc_like( $search ) . '%'
			) );
			$seen = wp_list_pluck( $users, 'ID' );
			foreach ( $by_biz as $uid ) {
				if ( in_array( (int) $uid, array_map( 'intval', $seen ), true ) ) continue;
				$u = get_userdata( (int) $uid );
				if ( $u ) $users[] = $u;
			}
		}

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

	/**
	 * Which data sources this client is actually connected to.
	 *
	 * This exists so a session doing client work can tell measurement from
	 * estimate before it writes a number down. The difference matters most for
	 * traffic: with GA4 connected we can state the client's own sessions as
	 * fact, and without it the only organic figure available is an Ahrefs
	 * estimate of visits from the keywords Ahrefs happens to track — a
	 * different claim, and one the client can check against their analytics.
	 *
	 * Reports whether an id is on file, not whether Google will honour it;
	 * granting the service account access happens outside this system, so a
	 * `true` here means "configured", and the Test connection button in the
	 * advisor dashboard is what proves it answers.
	 */
	protected static function integrations( $id ) {
		$meta = function ( $key ) use ( $id ) {
			return trim( (string) get_user_meta( $id, $key, true ) ) !== '';
		};
		$ga4 = $meta( 'six_ga4_property_id' );
		return array(
			'ga4'              => $ga4,
			'search_console'   => $meta( 'six_gsc_site' ),
			'google_ads'       => $meta( 'six_gads_customer_id' ),
			'google_business'  => $meta( 'six_gbp_location_id' ),
			'meta_ads'         => $meta( 'six_meta_ad_account_id' ),
			// Said in full, because this is the sentence that decides how a
			// traffic figure may be described in a client-facing document.
			'traffic_source'   => $ga4
				? 'ga4_measured'
				: 'ahrefs_estimate_only',
			'notes'            => $ga4
				? 'GA4 is connected: use it for visitor and session figures and describe them as measured.'
				: 'No GA4 on this client. Any traffic figure is an Ahrefs estimate of visits from the keywords it tracks — label it as an estimate and never state it as their analytics.',
		);
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
			'integrations'     => self::integrations( $id ),
			'field_provenance' => (array) get_user_meta( $id, self::ORIGIN_META, true ),
		);

		// Both questionnaires, each with its own completion, because they're
		// answered at different points in the engagement: the audit intake at
		// signup, the strategy questionnaire once the audit has won the work.
		// A consumer can tell from `stage` which step this client is at.
		if ( function_exists( 'six_seo_questionnaire_answers' ) ) {
			$sets = array();
			foreach ( array( 'audit', 'strategy' ) as $set ) {
				$answers = six_seo_questionnaire_answers( $id, $slugs, $set );
				$grouped = array();
				foreach ( six_seo_questionnaire_for_client( $slugs, $set ) as $skey => $section ) {
					foreach ( $section['fields'] as $f ) {
						$val = $answers[ $f['key'] ]['value'] ?? '';
						if ( $val === '' ) continue;   // don't pad the payload with blanks
						$grouped[ $skey ][ $f['key'] ] = array(
							'question' => $f['label'],
							'answer'   => $val,
						);
					}
				}
				$sets[ $set ] = array(
					'progress' => six_seo_questionnaire_progress( $id, $slugs, $set ),
					'sections' => $grouped,
				);
			}
			$issued = function_exists( 'six_strategy_questionnaire_issued' )
				&& six_strategy_questionnaire_issued( $id );
			$sets['strategy']['issued']    = $issued;
			$sets['strategy']['issued_at'] = $issued && function_exists( 'six_strategy_questionnaire_issued_at' )
				? six_strategy_questionnaire_issued_at( $id ) : '';
			// Answered and handed in are different facts. 100% can mean the
			// client is still editing; submitted is them saying they are done,
			// which is when the strategy should actually be built.
			$submitted_at = function_exists( 'six_strategy_questionnaire_submitted_at' )
				? six_strategy_questionnaire_submitted_at( $id ) : '';
			$sets['strategy']['submitted']    = (bool) $submitted_at;
			$sets['strategy']['submitted_at'] = $submitted_at;

			$profile['questionnaire'] = $sets;

			// Where this client is in the engagement, so a skill doesn't have to
			// infer it: ready to audit, awaiting the strategy questionnaire, or
			// ready to build the strategy.
			// Deliberately keyed on the answers, not on submission: a client who
			// answered everything before there was a Submit button is still
			// ready, and `questionnaire.strategy.submitted` is there for a
			// consumer that wants to wait for the client to say they're done.
			$profile['stage'] = ! $issued
				? ( $sets['audit']['progress']['percent'] >= 100 ? 'ready_for_audit' : 'audit_intake_incomplete' )
				: ( $sets['strategy']['progress']['percent'] >= 100 ? 'ready_for_strategy' : 'awaiting_strategy_answers' );
		}

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

		$table = self::artifacts_table();

		// Fail loudly when the table is not there.
		//
		// This used to insert and return insert_id without checking, so a
		// missing table produced {"id":0} with a 200 — a success shape for a
		// write that did not happen. A caller then has no way to tell storage
		// from silence, and the obvious wrong conclusion ("my token must be
		// read-only") is exactly the one that got drawn.
		if ( ! function_exists( 'six_table_exists' ) || ! six_table_exists( $table ) ) {
			if ( function_exists( 'six_create_tables' ) ) six_create_tables();   // self-heal, then re-check
		}
		if ( function_exists( 'six_table_exists' ) && ! six_table_exists( $table ) ) {
			return new WP_Error(
				'six_storage_missing',
				'The artifact store does not exist on this site, so nothing was saved. An administrator can create it from WP Admin → 6ix Portal → Integrations, or by loading any admin page once after the latest deploy.',
				array( 'status' => 500 )
			);
		}

		$token = self::resolve_token( $request );
		$ok = $wpdb->insert( $table, array(
			'client_id'  => $id,
			'type'       => $type,
			'source'     => sanitize_text_field( $body['source'] ?? ( $token['label'] ?? 'api' ) ),
			'payload'    => wp_json_encode( $body['payload'] ?? array() ),
			'created_at' => current_time( 'mysql' ),
		) );

		if ( $ok === false || ! $wpdb->insert_id ) {
			return new WP_Error(
				'six_storage_failed',
				'The database rejected the write, so nothing was saved' . ( $wpdb->last_error ? ': ' . $wpdb->last_error : '.' ),
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response( array( 'id' => (int) $wpdb->insert_id, 'type' => $type ) );
	}
}

Six_Profile_API::init();
