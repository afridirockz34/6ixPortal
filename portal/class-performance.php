<?php
/**
 * Performance over time, and whether it matches what we said would happen.
 *
 * Six_Dash_Metrics answers "what is true right now". That's enough for a
 * dashboard and useless for a review: "organic visitors are 3,400" means
 * nothing without knowing they were 900 when we started and that the strategy
 * said we'd be at 4,000 by now. This class supplies the two missing
 * dimensions.
 *
 *   Snapshots    What actually happened, captured on a schedule. One row per
 *                client per period, so a cron firing late or twice updates
 *                that period rather than duplicating it — worth having,
 *                because WordPress's pseudo-cron only runs when the site gets
 *                traffic and a quiet fortnight will delay a capture.
 *
 *   Projections  What a strategy committed to, stored per metric per target
 *                date. A check can then ask "are we where we said we'd be?"
 *                instead of only "has the number gone up?".
 *
 * compare() joins the two and is what a "check current performance" run reads.
 * It never invents a projection: a metric nobody projected comes back with
 * `projected => null` and `status => 'no_projection'`, because inventing the
 * target we're measuring against would make the whole comparison worthless.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Six_Performance {

	/** Capture at most one snapshot per ISO week. */
	const CRON_HOOK = 'six_capture_performance';

	public static function snapshots_table() {
		global $wpdb; return $wpdb->prefix . 'six_performance_snapshots';
	}
	public static function projections_table() {
		global $wpdb; return $wpdb->prefix . 'six_strategy_projections';
	}

	/** The period a timestamp belongs to, e.g. 2026-W45. */
	public static function period_key( $ts = null ) {
		$ts = $ts ?: current_time( 'timestamp' );
		return gmdate( 'o-\WW', $ts );
	}

	// ── Context ───────────────────────────────────────────────────────────

	/** The metric context for a client: their services, budgets and market CPC. */
	public static function context( $client_id ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT service_slug, budget FROM {$wpdb->prefix}six_client_services WHERE client_id=%d AND status='active'",
			$client_id
		) );
		$slugs = array(); $budgets = array();
		foreach ( (array) $rows as $r ) {
			$slugs[] = $r->service_slug;
			$budgets[ $r->service_slug ] = (float) $r->budget;
		}

		$kpi   = array();
		$ktbl  = $wpdb->prefix . 'six_client_kpis';
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$ktbl}'" ) === $ktbl ) {
			foreach ( (array) $wpdb->get_results( $wpdb->prepare(
				"SELECT kpi_key, kpi_value, kpi_prev FROM {$ktbl} WHERE client_id=%d", $client_id
			) ) as $k ) $kpi[ $k->kpi_key ] = $k;
		}

		return array(
			'slugs'   => $slugs,
			'budgets' => $budgets,
			'kpi'     => $kpi,
			'cpc'     => (float) get_user_meta( $client_id, 'six_market_avg_cpc', true ),
		);
	}

	// ── Snapshots ─────────────────────────────────────────────────────────

	/**
	 * Record where this client stands now.
	 *
	 * @param string $source  scheduled | manual | api
	 * @return array|null The stored snapshot, or null when there's nothing to record.
	 */
	public static function capture( $client_id, $source = 'scheduled' ) {
		global $wpdb;
		$client_id = (int) $client_id;
		if ( ! $client_id || ! class_exists( 'Six_Dash_Metrics' ) ) return null;

		$ctx = self::context( $client_id );
		if ( ! $ctx['slugs'] ) return null;        // nothing active to measure

		$raw = Six_Dash_Metrics::raw( $client_id, $ctx );
		if ( ! $raw['values'] ) return null;

		$period = self::period_key();
		$row = array(
			'client_id'   => $client_id,
			'period_key'  => $period,
			'captured_at' => current_time( 'mysql' ),
			'source'      => sanitize_key( $source ),
			'services'    => implode( ',', $ctx['slugs'] ),
			'metrics'     => wp_json_encode( array( 'values' => $raw['values'], 'sources' => $raw['sources'] ) ),
		);

		$tbl      = self::snapshots_table();
		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$tbl} WHERE client_id=%d AND period_key=%s", $client_id, $period
		) );
		if ( $existing ) {
			$wpdb->update( $tbl, $row, array( 'id' => (int) $existing ) );
		} else {
			$wpdb->insert( $tbl, $row );
		}

		$row['metrics'] = $raw;
		return $row;
	}

	/** Snapshots for a client, newest first. */
	public static function history( $client_id, $limit = 26 ) {
		global $wpdb;
		$tbl = self::snapshots_table();
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$tbl}'" ) !== $tbl ) return array();

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$tbl} WHERE client_id=%d ORDER BY captured_at DESC LIMIT %d",
			(int) $client_id, (int) $limit
		) );
		return array_map( function ( $r ) {
			$m = json_decode( $r->metrics, true ) ?: array();
			// JSON drops the decimal on a whole number, so 800.0 comes back as
			// int. Cast on read: a consumer comparing or charting these should
			// never see one metric as int and the next as float.
			$values = array();
			foreach ( (array) ( $m['values'] ?? array() ) as $k => $v ) {
				if ( is_numeric( $v ) ) $values[ $k ] = (float) $v;
			}
			return array(
				'period'      => $r->period_key,
				'captured_at' => $r->captured_at,
				'source'      => $r->source,
				'services'    => array_filter( explode( ',', (string) $r->services ) ),
				'values'      => $values,
				'sources'     => $m['sources'] ?? array(),
			);
		}, (array) $rows );
	}

	/** The first snapshot we ever took — where they were when we started. */
	public static function baseline( $client_id ) {
		$all = self::history( $client_id, 500 );
		return $all ? end( $all ) : null;
	}

	// ── Projections ───────────────────────────────────────────────────────

	/**
	 * Record what a strategy commits to.
	 *
	 * @param array $rows Each: metric_key, target_date (Y-m-d), projected_value, note.
	 * @param int   $artifact_id The strategy artifact these came from, if any.
	 * @return array{saved:int,rejected:array}
	 */
	public static function save_projections( $client_id, array $rows, $artifact_id = 0 ) {
		global $wpdb;
		$client_id = (int) $client_id;
		$tbl = self::projections_table();
		$saved = 0; $rejected = array();

		foreach ( $rows as $r ) {
			$metric = sanitize_key( $r['metric_key'] ?? '' );
			$date   = isset( $r['target_date'] ) ? substr( (string) $r['target_date'], 0, 10 ) : '';
			$value  = isset( $r['projected_value'] ) ? (float) $r['projected_value'] : null;

			if ( ! $metric || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) || $value === null ) {
				$rejected[] = $r;
				continue;
			}

			$data = array(
				'client_id'       => $client_id,
				'metric_key'      => $metric,
				'target_date'     => $date,
				'projected_value' => $value,
				'note'            => sanitize_text_field( $r['note'] ?? '' ),
				'artifact_id'     => (int) $artifact_id,
				'created_at'      => current_time( 'mysql' ),
				'created_by'      => get_current_user_id(),
			);

			// Re-running the strategy supersedes the previous target for that
			// metric and date rather than stacking a second one beside it.
			$existing = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$tbl} WHERE client_id=%d AND metric_key=%s AND target_date=%s",
				$client_id, $metric, $date
			) );
			if ( $existing ) $wpdb->update( $tbl, $data, array( 'id' => (int) $existing ) );
			else             $wpdb->insert( $tbl, $data );
			$saved++;
		}
		return array( 'saved' => $saved, 'rejected' => $rejected );
	}

	/** Every projection for a client, oldest target first. */
	public static function projections( $client_id ) {
		global $wpdb;
		$tbl = self::projections_table();
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$tbl}'" ) !== $tbl ) return array();
		return (array) $wpdb->get_results( $wpdb->prepare(
			"SELECT metric_key, target_date, projected_value, note, artifact_id
			   FROM {$tbl} WHERE client_id=%d ORDER BY target_date ASC", (int) $client_id
		) );
	}

	/**
	 * The projection that is due for a metric as of a date — the most recent
	 * target that has already come around. A target still in the future isn't
	 * something to judge today's number against.
	 */
	public static function projection_due( $client_id, $metric_key, $as_of = null ) {
		global $wpdb;
		$tbl = self::projections_table();
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$tbl}'" ) !== $tbl ) return null;
		$as_of = $as_of ?: current_time( 'Y-m-d' );
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$tbl} WHERE client_id=%d AND metric_key=%s AND target_date<=%s
			  ORDER BY target_date DESC LIMIT 1",
			(int) $client_id, sanitize_key( $metric_key ), $as_of
		) );
		return $row ?: null;
	}

	// ── The comparison ────────────────────────────────────────────────────

	/**
	 * Where they are, against where they started, where they were last time,
	 * and where the strategy said they'd be.
	 *
	 * @return array{has:bool,captured_at:string,metrics:array,summary:array}
	 */
	public static function compare( $client_id, $as_of = null ) {
		$client_id = (int) $client_id;
		$history   = self::history( $client_id, 500 );

		if ( ! $history ) {
			return array( 'has' => false, 'reason' => 'no_snapshots',
				'message' => 'No performance has been captured for this client yet.',
				'metrics' => array(), 'summary' => array() );
		}

		$current  = $history[0];
		$previous = $history[1] ?? null;
		$baseline = end( $history );
		if ( count( $history ) === 1 ) $baseline = null;   // the only snapshot IS current

		$out = array();
		foreach ( $current['values'] as $key => $now ) {
			$proj = self::projection_due( $client_id, $key, $as_of );
			$projected = $proj ? (float) $proj->projected_value : null;

			// Average position and cost per click improve as they fall. The
			// percentage change is still stated as the raw arithmetic, but
			// whether that change is good news depends on the metric, so say
			// which way is better and name the trend outright.
			$lower = class_exists( 'Six_Dash_Metrics' ) && Six_Dash_Metrics::lower_is_better( $key );

			$entry = array(
				'metric'      => $key,
				'current'     => $now,
				'better_when' => $lower ? 'lower' : 'higher',
				'source'      => $current['sources'][ $key ] ?? 'unknown',
				'baseline'    => $baseline !== null && isset( $baseline['values'][ $key ] ) ? $baseline['values'][ $key ] : null,
				'previous'    => $previous !== null && isset( $previous['values'][ $key ] ) ? $previous['values'][ $key ] : null,
				'projected'   => $projected,
				'target_date' => $proj->target_date ?? null,
			);

			$entry['change_since_baseline'] = self::pct( $entry['baseline'], $now );
			$entry['change_since_previous'] = self::pct( $entry['previous'], $now );
			$entry['trend_since_baseline']  = self::trend( $entry['change_since_baseline'], $lower );
			$entry['trend_since_previous']  = self::trend( $entry['change_since_previous'], $lower );

			if ( $projected === null ) {
				$entry['status']  = 'no_projection';
				$entry['vs_plan'] = null;
			} else {
				$entry['vs_plan'] = self::pct( $projected, $now );
				// A 10% shortfall is noise on a small number and a problem on
				// a large one, so judge on the ratio and leave the calling
				// skill to decide what to do about it.
				if ( $lower ) {
					$ratio = $now > 0 ? $projected / $now : ( $projected > 0 ? 2 : 1 );
				} else {
					$ratio = $projected > 0 ? $now / $projected : ( $now > 0 ? 2 : 1 );
				}
				$entry['status'] = $ratio >= 1 ? 'ahead' : ( $ratio >= 0.85 ? 'on_track' : 'behind' );
			}
			$out[ $key ] = $entry;
		}

		$counts = array_count_values( wp_list_pluck( $out, 'status' ) );
		return array(
			'has'            => true,
			'captured_at'    => $current['captured_at'],
			'period'         => $current['period'],
			'compared_with'  => array(
				'baseline' => $baseline['captured_at'] ?? null,
				'previous' => $previous['captured_at'] ?? null,
			),
			'metrics' => $out,
			'summary' => array(
				'ahead'          => $counts['ahead'] ?? 0,
				'on_track'       => $counts['on_track'] ?? 0,
				'behind'         => $counts['behind'] ?? 0,
				'no_projection'  => $counts['no_projection'] ?? 0,
				'snapshots_held' => count( $history ),
			),
		);
	}

	/** Percentage change from $from to $to, or null when there's no basis. */
	protected static function pct( $from, $to ) {
		if ( $from === null || $from <= 0 ) return null;
		return round( ( ( $to - $from ) / $from ) * 100, 1 );
	}

	/**
	 * Whether a percentage change is good news, given which way the metric
	 * improves. Stated outright so a report can't read "+167%" on average
	 * position as a win. A move under half a percent is called flat.
	 */
	protected static function trend( $change, $lower_is_better ) {
		if ( $change === null ) return null;
		if ( abs( $change ) < 0.5 ) return 'flat';
		$rose = $change > 0;
		return ( $rose xor $lower_is_better ) ? 'better' : 'worse';
	}

	// ── Scheduling ────────────────────────────────────────────────────────

	public static function init() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'run_scheduled_capture' ) );
		add_action( 'init', function () {
			if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
				wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
			}
		} );
	}

	/**
	 * Capture every active client once per period. Runs daily but the unique
	 * period key means only the first run in a period writes anything new —
	 * which is also what makes a late or repeated cron harmless.
	 */
	public static function run_scheduled_capture() {
		global $wpdb;
		$ids = (array) $wpdb->get_col(
			"SELECT DISTINCT client_id FROM {$wpdb->prefix}six_client_services WHERE status='active'"
		);
		foreach ( $ids as $id ) self::capture( (int) $id, 'scheduled' );
	}
}

Six_Performance::init();
