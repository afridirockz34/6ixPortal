<?php
/**
 * What we did, and what it did to the numbers.
 *
 * Four tables, one class. None of it is SEO-specific: a client on Google Ads
 * or a website build needs the same four questions answered as a client on
 * SEO — what did you change, when, what were the numbers before it, and what
 * are they now. `service_slug` is what keeps them apart.
 *
 *   six_work_log         every change we made, with before and after
 *   six_entity_metrics   where one page / keyword / campaign stood on a date
 *   six_daily_metrics    one number per metric per day, for the graphs
 *   six_metric_proposals a figure Claude read, waiting for an advisor
 *
 * The rule the whole file is built around: **nothing Claude produces writes
 * straight to the customer's dashboard.** It becomes a proposal, an advisor
 * confirms it, and confirming is what moves it onto the stat cards.
 *
 * @package SixClaude
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Six_Tracking {

	public static function work_table()      { global $wpdb; return $wpdb->prefix . 'six_work_log'; }
	public static function entity_table()    { global $wpdb; return $wpdb->prefix . 'six_entity_metrics'; }
	public static function daily_table()     { global $wpdb; return $wpdb->prefix . 'six_daily_metrics'; }
	public static function proposals_table() { global $wpdb; return $wpdb->prefix . 'six_metric_proposals'; }

	/**
	 * A table we can actually write to, or a reason we cannot.
	 *
	 * Every write in this file goes through here first. The alternative —
	 * attempting the insert and reporting success from the return value of a
	 * function that was never called — is how this codebase has lost writes
	 * before, and the UI said "saved" each time.
	 *
	 * @return true|string true, or the message to show.
	 */
	protected static function writable( $table ) {
		if ( ! function_exists( 'six_table_exists' ) ) return true;   // can't tell; let the write speak
		if ( six_table_exists( $table ) ) return true;
		if ( function_exists( 'six_create_tables' ) ) six_create_tables();
		if ( six_table_exists( $table ) ) return true;
		return 'The ' . $table . ' table is missing. Open any WordPress admin page once to build it.';
	}

	/** Entities are URLs and keywords, which outrun a sane index. Hash to key. */
	protected static function hash( $entity ) {
		return md5( strtolower( trim( (string) $entity ) ) );
	}

	// ── The work log ──────────────────────────────────────────────────────

	/**
	 * Record one change.
	 *
	 * @param array $row {
	 *   @type string $service_slug  seo | google-ads | google-business | website
	 *   @type string $category      'Title Tags', 'Schema Markup', 'Ad Copy' …
	 *   @type string $action        created | rewrote | retitled | deployed | fixed
	 *   @type string $entity        The URL, campaign, listing or keyword changed
	 *   @type string $title         One line a human reads
	 *   @type string $detail        Longer description
	 *   @type string $before_value  What it was. An empty string for new work
	 *   @type string $after_value   What it is now
	 *   @type string $status        deployed | pending | planned
	 *   @type string $keyword       Primary keyword, where the work has one
	 *   @type int    $artifact_id   The saved document this came from
	 *   @type string $approver      Who signed it off
	 *   @type int    $score         The content gate's score, where there was one
	 *   @type string $verify_url    Where a client can check it themselves
	 *   @type string $verify_how    How to check it
	 *   @type string $origin        claude | advisor
	 *   @type string $performed_at  Y-m-d H:i:s. Defaults to now
	 * }
	 * @return array{ok:bool,id:int,error:string}
	 */
	public static function log_work( $client_id, array $row ) {
		global $wpdb;
		$client_id = (int) $client_id;
		if ( ! $client_id ) return array( 'ok' => false, 'id' => 0, 'error' => 'No client.' );

		$tbl = self::work_table();
		$ok  = self::writable( $tbl );
		if ( $ok !== true ) return array( 'ok' => false, 'id' => 0, 'error' => $ok );

		$title = trim( (string) ( $row['title'] ?? '' ) );
		if ( $title === '' ) return array( 'ok' => false, 'id' => 0, 'error' => 'A log entry needs a title.' );

		$status = in_array( $row['status'] ?? '', array( 'deployed', 'pending', 'planned' ), true )
			? $row['status'] : 'deployed';

		$data = array(
			'client_id'    => $client_id,
			'service_slug' => sanitize_key( $row['service_slug'] ?? '' ),
			'category'     => sanitize_text_field( substr( (string) ( $row['category'] ?? '' ), 0, 80 ) ),
			'action'       => sanitize_text_field( substr( (string) ( $row['action'] ?? 'changed' ), 0, 40 ) ),
			'entity'       => esc_url_raw( (string) ( $row['entity'] ?? '' ) ) ?: sanitize_text_field( substr( (string) ( $row['entity'] ?? '' ), 0, 500 ) ),
			'title'        => sanitize_text_field( substr( $title, 0, 255 ) ),
			'detail'       => wp_kses_post( (string) ( $row['detail'] ?? '' ) ),
			'before_value' => wp_kses_post( (string) ( $row['before_value'] ?? '' ) ),
			'after_value'  => wp_kses_post( (string) ( $row['after_value'] ?? '' ) ),
			'status'       => $status,
			'keyword'      => sanitize_text_field( substr( (string) ( $row['keyword'] ?? '' ), 0, 255 ) ),
			'artifact_id'  => (int) ( $row['artifact_id'] ?? 0 ),
			'approver'     => sanitize_text_field( substr( (string) ( $row['approver'] ?? '' ), 0, 190 ) ),
			'score'        => isset( $row['score'] ) && $row['score'] !== '' ? (int) $row['score'] : null,
			'verify_url'   => esc_url_raw( (string) ( $row['verify_url'] ?? '' ) ),
			'verify_how'   => wp_kses_post( (string) ( $row['verify_how'] ?? '' ) ),
			'origin'       => in_array( $row['origin'] ?? '', array( 'claude', 'advisor' ), true ) ? $row['origin'] : 'advisor',
			'performed_at' => self::datetime( $row['performed_at'] ?? '' ),
			'created_by'   => get_current_user_id(),
		);

		$wpdb->suppress_errors( true );
		$done = $wpdb->insert( $tbl, $data );
		$err  = $wpdb->last_error;
		$wpdb->suppress_errors( false );

		if ( $done === false ) {
			error_log( '6ix Tracking: work log write failed for client ' . $client_id . ': ' . $err );
			return array( 'ok' => false, 'id' => 0, 'error' => $err ?: 'The write failed.' );
		}
		return array( 'ok' => true, 'id' => (int) $wpdb->insert_id, 'error' => '' );
	}

	/** A sane datetime, or now. Never a silently-wrong 1970. */
	protected static function datetime( $raw ) {
		$raw = trim( (string) $raw );
		if ( $raw === '' ) return current_time( 'mysql' );
		$ts = strtotime( $raw );
		return $ts ? gmdate( 'Y-m-d H:i:s', $ts ) : current_time( 'mysql' );
	}

	/**
	 * The log, newest first.
	 *
	 * @param array $args service_slug, category, status, since (Y-m-d), limit
	 */
	public static function work_log( $client_id, array $args = array() ) {
		global $wpdb;
		$tbl = self::work_table();
		if ( function_exists( 'six_table_exists' ) && ! six_table_exists( $tbl ) ) return array();

		$where = array( $wpdb->prepare( 'client_id=%d', (int) $client_id ) );
		if ( ! empty( $args['service_slug'] ) ) $where[] = $wpdb->prepare( 'service_slug=%s', sanitize_key( $args['service_slug'] ) );
		if ( ! empty( $args['category'] ) )     $where[] = $wpdb->prepare( 'category=%s', $args['category'] );
		if ( ! empty( $args['status'] ) )       $where[] = $wpdb->prepare( 'status=%s', $args['status'] );
		if ( ! empty( $args['since'] ) )        $where[] = $wpdb->prepare( 'performed_at >= %s', self::datetime( $args['since'] ) );
		$limit = max( 1, min( 500, (int) ( $args['limit'] ?? 100 ) ) );

		$rows = $wpdb->get_results(
			'SELECT * FROM ' . $tbl . ' WHERE ' . implode( ' AND ', $where )
			. ' ORDER BY performed_at DESC, id DESC LIMIT ' . $limit
		);
		return array_map( array( __CLASS__, 'shape_work' ), (array) $rows );
	}

	protected static function shape_work( $r ) {
		return array(
			'id'           => (int) $r->id,
			'service_slug' => $r->service_slug,
			'category'     => $r->category,
			'action'       => $r->action,
			'entity'       => $r->entity,
			'title'        => $r->title,
			'detail'       => $r->detail,
			'before'       => $r->before_value,
			'after'        => $r->after_value,
			'status'       => $r->status,
			'keyword'      => $r->keyword,
			'artifact_id'  => (int) $r->artifact_id,
			'approver'     => $r->approver,
			'score'        => $r->score === null ? null : (int) $r->score,
			'verify_url'   => $r->verify_url,
			'verify_how'   => $r->verify_how,
			'origin'       => $r->origin,
			'performed_at' => $r->performed_at,
		);
	}

	/**
	 * The work log rolled up by category — the "optimization proof" table.
	 *
	 * Counts only. The detail is one click away in the log itself, and a
	 * customer reading a dashboard wants to know that 844 of 896 title tags
	 * are live, not to read 896 rows.
	 *
	 * @return array[] category, found, deployed, pending, completion (0-1), verify_url, verify_how, last_at
	 */
	public static function proof( $client_id, $service_slug = '' ) {
		global $wpdb;
		$tbl = self::work_table();
		if ( function_exists( 'six_table_exists' ) && ! six_table_exists( $tbl ) ) return array();

		$where = array( $wpdb->prepare( 'client_id=%d', (int) $client_id ), "category<>''" );
		if ( $service_slug ) $where[] = $wpdb->prepare( 'service_slug=%s', sanitize_key( $service_slug ) );

		// Every fragment of the WHERE is already prepared, so the status
		// comparison is prepared on its own and concatenated rather than
		// running the whole statement through prepare() a second time.
		$deployed_is = $wpdb->prepare( 'status=%s', 'deployed' );
		$rows = $wpdb->get_results(
			'SELECT category,
			        COUNT(*) AS found,
			        SUM(CASE WHEN ' . $deployed_is . ' THEN 1 ELSE 0 END) AS deployed,
			        MAX(performed_at) AS last_at,
			        MAX(verify_url) AS verify_url,
			        MAX(verify_how) AS verify_how
			   FROM ' . $tbl . '
			  WHERE ' . implode( ' AND ', $where ) . '
			  GROUP BY category
			  ORDER BY deployed DESC, found DESC'
		);

		$out = array();
		foreach ( (array) $rows as $r ) {
			$found    = (int) $r->found;
			$deployed = (int) $r->deployed;
			$out[] = array(
				'category'   => $r->category,
				'found'      => $found,
				'deployed'   => $deployed,
				'pending'    => max( 0, $found - $deployed ),
				'completion' => $found > 0 ? round( $deployed / $found, 4 ) : 0,
				'last_at'    => $r->last_at,
				'verify_url' => $r->verify_url,
				'verify_how' => $r->verify_how,
			);
		}
		return $out;
	}

	// ── Per-entity metrics ────────────────────────────────────────────────

	/**
	 * Record where pages, keywords or campaigns stood on a date.
	 *
	 * @param array $rows Each: entity, entity_type, position, clicks,
	 *                    impressions, ctr, extra, captured_on
	 * @return array{saved:int,failed:array}
	 */
	public static function record_entities( $client_id, array $rows, $service_slug = '', $source = '' ) {
		global $wpdb;
		$client_id = (int) $client_id;
		$tbl = self::entity_table();
		$ok  = self::writable( $tbl );
		if ( $ok !== true ) return array( 'saved' => 0, 'failed' => array( '_table' => $ok ) );

		$saved = 0; $failed = array();
		$wpdb->suppress_errors( true );
		foreach ( $rows as $i => $r ) {
			$entity = trim( (string) ( $r['entity'] ?? '' ) );
			if ( $entity === '' ) { $failed[ $i ] = 'no entity'; continue; }

			$type = sanitize_key( $r['entity_type'] ?? 'page' );
			$day  = self::date( $r['captured_on'] ?? '' );
			$data = array(
				'client_id'    => $client_id,
				'service_slug' => sanitize_key( $service_slug ),
				'entity_type'  => $type ?: 'page',
				'entity'       => substr( $entity, 0, 500 ),
				'entity_hash'  => self::hash( $entity ),
				'captured_on'  => $day,
				'position'     => self::num( $r['position'] ?? null ),
				'clicks'       => self::int_or_null( $r['clicks'] ?? null ),
				'impressions'  => self::int_or_null( $r['impressions'] ?? null ),
				'ctr'          => self::num( $r['ctr'] ?? null ),
				'extra'        => isset( $r['extra'] ) ? wp_json_encode( $r['extra'] ) : null,
				'source'       => sanitize_text_field( substr( (string) $source, 0, 60 ) ),
			);

			// One row per entity per day. A re-run updates rather than stacking.
			$existing = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$tbl} WHERE client_id=%d AND entity_type=%s AND entity_hash=%s AND captured_on=%s",
				$client_id, $data['entity_type'], $data['entity_hash'], $day
			) );
			$done = $existing
				? $wpdb->update( $tbl, $data, array( 'id' => (int) $existing ) )
				: $wpdb->insert( $tbl, $data );

			if ( $done === false ) $failed[ $i ] = $wpdb->last_error ?: 'write failed';
			else $saved++;
		}
		$wpdb->suppress_errors( false );
		if ( $failed ) error_log( '6ix Tracking: ' . count( $failed ) . ' entity rows failed for client ' . $client_id );
		return array( 'saved' => $saved, 'failed' => $failed );
	}

	/**
	 * One entity's history, oldest first.
	 *
	 * This is what answers "where did this page rank before we rewrote it".
	 */
	public static function entity_history( $client_id, $entity, $entity_type = 'page', $limit = 180 ) {
		global $wpdb;
		$tbl = self::entity_table();
		if ( function_exists( 'six_table_exists' ) && ! six_table_exists( $tbl ) ) return array();

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT captured_on, position, clicks, impressions, ctr, source
			   FROM {$tbl}
			  WHERE client_id=%d AND entity_type=%s AND entity_hash=%s
			  ORDER BY captured_on ASC LIMIT %d",
			(int) $client_id, sanitize_key( $entity_type ), self::hash( $entity ), (int) $limit
		) );
		return array_map( function ( $r ) {
			return array(
				'date'        => $r->captured_on,
				'position'    => $r->position === null ? null : (float) $r->position,
				'clicks'      => $r->clicks === null ? null : (int) $r->clicks,
				'impressions' => $r->impressions === null ? null : (int) $r->impressions,
				'ctr'         => $r->ctr === null ? null : (float) $r->ctr,
				'source'      => $r->source,
			);
		}, (array) $rows );
	}

	/**
	 * Before and after a date, for one entity.
	 *
	 * Returns null for a side with no data rather than a zero — "we have no
	 * reading from before the change" and "it ranked nowhere" are different
	 * facts and a chart cannot tell them apart once they are both 0.
	 */
	public static function before_after( $client_id, $entity, $on, $entity_type = 'page' ) {
		$hist = self::entity_history( $client_id, $entity, $entity_type );
		if ( ! $hist ) return array( 'before' => null, 'after' => null, 'changed_on' => $on );

		$cut = self::date( $on );
		$before = null; $after = null;
		foreach ( $hist as $h ) {
			if ( $h['date'] < $cut ) $before = $h;          // last reading before
			elseif ( $after === null && $h['date'] >= $cut ) $after = $h;   // first after
		}
		// The most recent reading is more useful as "after" than the first one
		// taken the day of the change, which is usually unchanged.
		$latest = end( $hist );
		if ( $latest && $latest['date'] >= $cut ) $after = $latest;

		return array( 'before' => $before, 'after' => $after, 'changed_on' => $cut );
	}

	// ── Daily series ──────────────────────────────────────────────────────

	/**
	 * Record one number per metric per day.
	 *
	 * @param array $rows metric_key => value, or a list of
	 *                    {metric_key, value, captured_on}
	 */
	public static function record_daily( $client_id, array $rows, $source = '', $captured_on = '' ) {
		global $wpdb;
		$client_id = (int) $client_id;
		$tbl = self::daily_table();
		$ok  = self::writable( $tbl );
		if ( $ok !== true ) return array( 'saved' => 0, 'failed' => array( '_table' => $ok ) );

		$normalised = array();
		foreach ( $rows as $k => $v ) {
			if ( is_array( $v ) ) {
				$normalised[] = array(
					'metric_key'  => sanitize_key( $v['metric_key'] ?? $k ),
					'value'       => $v['value'] ?? null,
					'captured_on' => $v['captured_on'] ?? $captured_on,
				);
			} else {
				$normalised[] = array( 'metric_key' => sanitize_key( $k ), 'value' => $v, 'captured_on' => $captured_on );
			}
		}

		$saved = 0; $failed = array();
		$wpdb->suppress_errors( true );
		foreach ( $normalised as $i => $r ) {
			if ( ! $r['metric_key'] || ! is_numeric( $r['value'] ) ) { $failed[ $i ] = 'not a number'; continue; }
			$day  = self::date( $r['captured_on'] );
			$data = array(
				'client_id'   => $client_id,
				'metric_key'  => $r['metric_key'],
				'captured_on' => $day,
				'value'       => (float) $r['value'],
				'source'      => sanitize_text_field( substr( (string) $source, 0, 40 ) ),
			);
			$existing = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$tbl} WHERE client_id=%d AND metric_key=%s AND captured_on=%s",
				$client_id, $data['metric_key'], $day
			) );
			$done = $existing
				? $wpdb->update( $tbl, $data, array( 'id' => (int) $existing ) )
				: $wpdb->insert( $tbl, $data );
			if ( $done === false ) $failed[ $i ] = $wpdb->last_error ?: 'write failed';
			else $saved++;
		}
		$wpdb->suppress_errors( false );
		return array( 'saved' => $saved, 'failed' => $failed );
	}

	/**
	 * A metric's daily series, oldest first.
	 *
	 * @return array[] date => value, as a list of {date, value, source}
	 */
	public static function daily( $client_id, $metric_key, $days = 180 ) {
		global $wpdb;
		$tbl = self::daily_table();
		if ( function_exists( 'six_table_exists' ) && ! six_table_exists( $tbl ) ) return array();

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT captured_on, value, source FROM {$tbl}
			  WHERE client_id=%d AND metric_key=%s AND captured_on >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
			  ORDER BY captured_on ASC",
			(int) $client_id, sanitize_key( $metric_key ), (int) $days
		) );
		return array_map( function ( $r ) {
			return array( 'date' => $r->captured_on, 'value' => (float) $r->value, 'source' => $r->source );
		}, (array) $rows );
	}

	/** The first day we have any reading for this client — when measurement began. */
	public static function measurement_started( $client_id, $metric_key = '' ) {
		global $wpdb;
		$tbl = self::daily_table();
		if ( function_exists( 'six_table_exists' ) && ! six_table_exists( $tbl ) ) return '';
		$sql = $metric_key
			? $wpdb->prepare( "SELECT MIN(captured_on) FROM {$tbl} WHERE client_id=%d AND metric_key=%s", (int) $client_id, sanitize_key( $metric_key ) )
			: $wpdb->prepare( "SELECT MIN(captured_on) FROM {$tbl} WHERE client_id=%d", (int) $client_id );
		return (string) $wpdb->get_var( $sql );
	}

	// ── Metric proposals ──────────────────────────────────────────────────

	/**
	 * Stage figures for an advisor to confirm.
	 *
	 * A proposal for a metric that already has one pending replaces it —
	 * otherwise a weekly check leaves the advisor with six versions of the
	 * same row and no way to tell which is current.
	 *
	 * @return array{saved:int,failed:array}
	 */
	public static function propose_metrics( $client_id, array $rows, $service_slug = '', $origin = 'claude' ) {
		global $wpdb;
		$client_id = (int) $client_id;
		$tbl = self::proposals_table();
		$ok  = self::writable( $tbl );
		if ( $ok !== true ) return array( 'saved' => 0, 'failed' => array( '_table' => $ok ) );

		$saved = 0; $failed = array();
		$wpdb->suppress_errors( true );
		foreach ( $rows as $i => $r ) {
			$key   = sanitize_key( $r['metric_key'] ?? '' );
			$label = sanitize_text_field( (string) ( $r['label'] ?? '' ) );
			if ( ! $key && ! $label ) { $failed[ $i ] = 'needs a metric_key or a label'; continue; }
			if ( ( $r['current_value'] ?? '' ) === '' ) { $failed[ $i ] = 'no value'; continue; }

			$data = array(
				'client_id'        => $client_id,
				'service_slug'     => sanitize_key( $service_slug ),
				'metric_key'       => $key,
				'label'            => substr( $label, 0, 120 ),
				'unit'             => sanitize_text_field( substr( (string) ( $r['unit'] ?? '' ), 0, 60 ) ),
				'current_value'    => sanitize_text_field( substr( (string) $r['current_value'], 0, 100 ) ),
				'previous_value'   => sanitize_text_field( substr( (string) ( $r['previous_value'] ?? '' ), 0, 100 ) ),
				'suggested_target' => sanitize_text_field( substr( (string) ( $r['suggested_target'] ?? '' ), 0, 100 ) ),
				'period_start'     => ! empty( $r['period_start'] ) ? self::date( $r['period_start'] ) : null,
				'period_end'       => ! empty( $r['period_end'] )   ? self::date( $r['period_end'] )   : null,
				'source'           => sanitize_text_field( substr( (string) ( $r['source'] ?? '' ), 0, 190 ) ),
				'origin'           => in_array( $origin, array( 'claude', 'integration', 'advisor' ), true ) ? $origin : 'claude',
				'note'             => wp_kses_post( (string) ( $r['note'] ?? '' ) ),
				'status'           => 'pending',
				'created_by'       => get_current_user_id(),
			);

			$dupe_key = $key ?: $label;
			$existing = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$tbl}
				  WHERE client_id=%d AND status='pending' AND origin=%s
				    AND (metric_key=%s OR (metric_key='' AND label=%s))",
				$client_id, $data['origin'], $key, $label
			) );
			$done = $existing
				? $wpdb->update( $tbl, $data, array( 'id' => (int) $existing ) )
				: $wpdb->insert( $tbl, $data );
			if ( $done === false ) $failed[ $i ] = $wpdb->last_error ?: 'write failed';
			else $saved++;
		}
		$wpdb->suppress_errors( false );
		return array( 'saved' => $saved, 'failed' => $failed );
	}

	/** Pending proposals for a client, newest first. */
	public static function proposals( $client_id, $status = 'pending', $service_slug = '' ) {
		global $wpdb;
		$tbl = self::proposals_table();
		if ( function_exists( 'six_table_exists' ) && ! six_table_exists( $tbl ) ) return array();

		$where = array( $wpdb->prepare( 'client_id=%d', (int) $client_id ) );
		if ( $status )       $where[] = $wpdb->prepare( 'status=%s', $status );
		if ( $service_slug ) $where[] = $wpdb->prepare( 'service_slug=%s', sanitize_key( $service_slug ) );

		$rows = $wpdb->get_results(
			'SELECT * FROM ' . $tbl . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY created_at DESC LIMIT 100'
		);
		return array_map( function ( $r ) {
			return array(
				'id'               => (int) $r->id,
				'service_slug'     => $r->service_slug,
				'metric_key'       => $r->metric_key,
				'label'            => $r->label,
				'unit'             => $r->unit,
				'current_value'    => $r->current_value,
				'previous_value'   => $r->previous_value,
				'suggested_target' => $r->suggested_target,
				'period_start'     => $r->period_start,
				'period_end'       => $r->period_end,
				'period_label'     => self::period_label( $r->period_start, $r->period_end ),
				'source'           => $r->source,
				'origin'           => $r->origin,
				'note'             => $r->note,
				'status'           => $r->status,
				'created_at'       => $r->created_at,
			);
		}, (array) $rows );
	}

	/** "15 Oct – 12 Nov 2026", or an empty string when nobody said. */
	public static function period_label( $start, $end ) {
		if ( ! $start && ! $end ) return '';
		if ( $start && $end ) {
			$same_year = gmdate( 'Y', strtotime( $start ) ) === gmdate( 'Y', strtotime( $end ) );
			return gmdate( $same_year ? 'j M' : 'j M Y', strtotime( $start ) ) . ' – ' . gmdate( 'j M Y', strtotime( $end ) );
		}
		return gmdate( 'j M Y', strtotime( $start ?: $end ) );
	}

	/** Mark a proposal accepted or dismissed. The caller does the writing. */
	public static function resolve_proposal( $id, $status ) {
		global $wpdb;
		$tbl = self::proposals_table();
		if ( function_exists( 'six_table_exists' ) && ! six_table_exists( $tbl ) ) return false;
		if ( ! in_array( $status, array( 'accepted', 'dismissed' ), true ) ) return false;
		return false !== $wpdb->update( $tbl, array(
			'status'      => $status,
			'reviewed_at' => current_time( 'mysql' ),
			'reviewed_by' => get_current_user_id(),
		), array( 'id' => (int) $id ) );
	}

	// ── Daily sync from the connected accounts ────────────────────────────

	const CRON_HOOK = 'six_tracking_daily_sync';

	public static function init() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'run_daily_sync' ) );
		add_action( 'init', function () {
			if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
				wp_schedule_event( time() + 2 * HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
			}
		} );
	}

	/** Every active client, once a day. */
	public static function run_daily_sync() {
		global $wpdb;
		$ids = (array) $wpdb->get_col(
			"SELECT DISTINCT client_id FROM {$wpdb->prefix}six_client_services WHERE status='active'"
		);
		foreach ( $ids as $id ) self::sync_client( (int) $id );
	}

	/**
	 * Pull the day-by-day figures the graphs need, and the per-page figures a
	 * before/after needs, from whatever this client has connected.
	 *
	 * The first run for a client reaches back as far as Search Console will
	 * serve. That history is not decoration: the "where they were heading
	 * without us" line is fitted on the readings from *before* the engagement
	 * started, and without a backfill there are none, so the comparison can
	 * never be drawn — only asserted.
	 *
	 * @return array What was pulled, per source, including the reason for
	 *               each source that produced nothing.
	 */
	public static function sync_client( $client_id, $days = 0 ) {
		$client_id = (int) $client_id;
		$out = array( 'client_id' => $client_id, 'daily' => 0, 'pages' => 0, 'notes' => array() );
		if ( ! $client_id || ! class_exists( 'Six_Analytics' ) ) {
			$out['notes'][] = 'Analytics client not available.';
			return $out;
		}

		$first = self::measurement_started( $client_id );
		// No history yet: reach back. Search Console serves about 16 months.
		$window = $days ?: ( $first ? 30 : 480 );

		// ── Search Console ────────────────────────────────────────────────
		$site = (string) get_user_meta( $client_id, 'six_gsc_site', true );
		if ( ! $site ) $site = (string) get_user_meta( $client_id, 'six_site_url', true );
		if ( $site ) {
			$d = Six_Analytics::gsc_daily( $site, $window );
			if ( empty( $d['error'] ) && ! empty( $d['rows'] ) ) {
				$rows = array();
				foreach ( $d['rows'] as $day => $v ) {
					$rows[] = array( 'metric_key' => 'gsc_clicks',      'value' => $v['clicks'],      'captured_on' => $day );
					$rows[] = array( 'metric_key' => 'gsc_impressions', 'value' => $v['impressions'], 'captured_on' => $day );
					$rows[] = array( 'metric_key' => 'gsc_position',    'value' => $v['position'],    'captured_on' => $day );
				}
				$r = self::record_daily( $client_id, $rows, 'search-console' );
				$out['daily'] += $r['saved'];
			} elseif ( ! empty( $d['error'] ) ) {
				$out['notes'][] = 'Search Console: ' . $d['error'];
			}

			$p = Six_Analytics::gsc_pages( $site, min( 28, $window ), 500 );
			if ( empty( $p['error'] ) && ! empty( $p['rows'] ) ) {
				$ents = array();
				foreach ( $p['rows'] as $row ) {
					if ( empty( $row['page'] ) ) continue;
					$ents[] = array(
						'entity'      => $row['page'],
						'entity_type' => 'page',
						'position'    => $row['position'],
						'clicks'      => $row['clicks'],
						'impressions' => $row['impressions'],
						'ctr'         => $row['ctr'],
						'captured_on' => $p['end'] ?? '',
					);
				}
				$r = self::record_entities( $client_id, $ents, 'seo', 'search-console' );
				$out['pages'] += $r['saved'];
			} elseif ( ! empty( $p['error'] ) ) {
				$out['notes'][] = 'Search Console pages: ' . $p['error'];
			}
		} else {
			$out['notes'][] = 'No Search Console property saved for this client.';
		}

		// ── GA4 ───────────────────────────────────────────────────────────
		$prop = (string) get_user_meta( $client_id, 'six_ga4_property_id', true );
		if ( $prop ) {
			$g = Six_Analytics::ga4_daily( $prop, min( 365, $window ) );
			if ( empty( $g['error'] ) && ! empty( $g['rows'] ) ) {
				$rows = array();
				foreach ( $g['rows'] as $day => $v ) {
					$rows[] = array( 'metric_key' => 'sessions',         'value' => $v['sessions'],         'captured_on' => $day );
					$rows[] = array( 'metric_key' => 'organic_sessions', 'value' => $v['organic_sessions'], 'captured_on' => $day );
					$rows[] = array( 'metric_key' => 'users',            'value' => $v['users'],            'captured_on' => $day );
				}
				$r = self::record_daily( $client_id, $rows, 'ga4' );
				$out['daily'] += $r['saved'];
			} elseif ( ! empty( $g['error'] ) ) {
				$out['notes'][] = 'GA4: ' . $g['error'];
			}
		} else {
			$out['notes'][] = 'No GA4 property saved for this client.';
		}

		return $out;
	}

	/**
	 * The day this engagement started — where the two lines on the graph part.
	 *
	 * The earliest approval on an active service, falling back to the first
	 * reading we ever took. Never "today": a start date of today makes the
	 * whole history look like our work.
	 */
	public static function engagement_start( $client_id ) {
		global $wpdb;
		$approved = $wpdb->get_var( $wpdb->prepare(
			"SELECT MIN(approved_at) FROM {$wpdb->prefix}six_client_services
			  WHERE client_id=%d AND status='active' AND approved_at IS NOT NULL",
			(int) $client_id
		) );
		if ( $approved ) return gmdate( 'Y-m-d', strtotime( $approved ) );
		$first = self::measurement_started( $client_id );
		return $first ?: '';
	}

	/**
	 * Where this metric was heading on its own, projected from before we started.
	 *
	 * A least-squares line fitted to the readings BEFORE the engagement start,
	 * extended across the window. This is the dashed "without us" line, and it
	 * is the only honest version of that claim: it is their own trend, drawn
	 * forward, and it is drawn only where there is enough history to fit it.
	 *
	 * Returns an empty array when there are fewer than 14 readings before the
	 * start date — a line fitted on a week of data is a guess wearing a chart's
	 * clothes, and a flat line asserted as "without us" is worse than no line.
	 *
	 * @return array{points:array<string,float>,fitted_on:int,from:string}|array{}
	 */
	public static function baseline_projection( $client_id, $metric_key, array $dates ) {
		$start = self::engagement_start( $client_id );
		if ( ! $start || ! $dates ) return array();

		$hist = self::daily( $client_id, $metric_key, 480 );
		$before = array();
		foreach ( $hist as $h ) {
			if ( $h['date'] < $start ) $before[] = $h;
		}
		$points = self::project( $before, $dates );
		if ( ! $points ) return array();

		return array(
			'points'    => $points,
			'fitted_on' => count( $before ),
			'from'      => $before[0]['date'],
			'start'     => $start,
		);
	}

	/** Readings needed before a trend line is honest rather than decorative. */
	const MIN_FIT_POINTS = 14;

	/**
	 * Fit a straight line to dated readings and extend it over given dates.
	 *
	 * Pure arithmetic, deliberately separated from the database so it can be
	 * tested on numbers whose answer is known. The visitors chart is the most
	 * prominent claim the portal makes and the easiest place for a quietly
	 * wrong slope to look convincing.
	 *
	 * @param array $before Each: date (Y-m-d), value.
	 * @param array $dates  Y-m-d dates to evaluate the fitted line at.
	 * @return array<string,float> date => value, or empty when it cannot be fitted.
	 */
	public static function project( array $before, array $dates ) {
		if ( count( $before ) < self::MIN_FIT_POINTS || ! $dates ) return array();

		// x in days from the first reading, so the numbers stay small and the
		// slope reads as "per day".
		$origin = strtotime( $before[0]['date'] );
		$n  = count( $before );
		$sx = $sy = $sxy = $sxx = 0.0;
		foreach ( $before as $h ) {
			$x = ( strtotime( $h['date'] ) - $origin ) / DAY_IN_SECONDS;
			$y = (float) $h['value'];
			$sx += $x; $sy += $y; $sxy += $x * $y; $sxx += $x * $x;
		}
		$denom = ( $n * $sxx ) - ( $sx * $sx );
		if ( abs( $denom ) < 1e-9 ) return array();          // every reading on one day
		$slope     = ( ( $n * $sxy ) - ( $sx * $sy ) ) / $denom;
		$intercept = ( $sy - ( $slope * $sx ) ) / $n;

		$points = array();
		foreach ( $dates as $d ) {
			$x = ( strtotime( $d ) - $origin ) / DAY_IN_SECONDS;
			// Negative traffic is not a forecast, it is an artefact of
			// extending a downward slope past zero. Clamp, don't draw it.
			$points[ $d ] = max( 0, round( $intercept + ( $slope * $x ), 2 ) );
		}
		return $points;
	}

	/**
	 * Everything the visitors graph needs, in one call.
	 *
	 * @return array{
	 *   has:bool, metric:string, dates:string[], actual:array, projected:array,
	 *   start:string, source:string, note:string
	 * }
	 */
	public static function graph_series( $client_id, $metric_key = '', $days = 180 ) {
		// Prefer the measured organic series; fall back to Search Console
		// clicks, which most clients have connected before GA4.
		$candidates = $metric_key ? array( $metric_key ) : array( 'organic_sessions', 'sessions', 'gsc_clicks' );
		$series = array(); $used = '';
		foreach ( $candidates as $k ) {
			$series = self::daily( $client_id, $k, $days );
			if ( count( $series ) >= 2 ) { $used = $k; break; }
		}
		if ( count( $series ) < 2 ) {
			return array(
				'has'  => false,
				'note' => 'No daily readings yet. Connect Search Console or GA4 under Data Sources; '
				        . 'the first sync reaches back about 16 months.',
			);
		}

		$dates = array_column( $series, 'date' );
		$proj  = self::baseline_projection( $client_id, $used, $dates );

		return array(
			'has'       => true,
			'metric'    => $used,
			'dates'     => $dates,
			'actual'    => array_map( function ( $h ) { return (float) $h['value']; }, $series ),
			'projected' => $proj ? array_values( $proj['points'] ) : array(),
			'start'     => $proj['start'] ?? self::engagement_start( $client_id ),
			'fitted_on' => $proj['fitted_on'] ?? 0,
			'source'    => $series[0]['source'] ?? '',
			'note'      => $proj
				? ''
				: 'The "without us" line needs at least 14 daily readings from before the '
				  . 'engagement started. There are not that many yet, so only the measured line is drawn.',
		);
	}

	// ── Small shared helpers ──────────────────────────────────────────────

	protected static function date( $raw ) {
		$raw = trim( (string) $raw );
		if ( $raw === '' ) return current_time( 'Y-m-d' );
		$ts = strtotime( $raw );
		return $ts ? gmdate( 'Y-m-d', $ts ) : current_time( 'Y-m-d' );
	}

	protected static function num( $v ) {
		return ( $v === null || $v === '' || ! is_numeric( $v ) ) ? null : (float) $v;
	}

	protected static function int_or_null( $v ) {
		return ( $v === null || $v === '' || ! is_numeric( $v ) ) ? null : (int) $v;
	}
}

Six_Tracking::init();
