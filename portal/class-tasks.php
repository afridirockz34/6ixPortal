<?php
/**
 * The plan, as rows somebody can tick off.
 *
 * An approved strategy is a document until it becomes a list of things with
 * owners and states. These rows are what the advisor works from, what a Claude
 * session reads to know what is already delivered or waiting on the client,
 * and what the work log closes out against.
 *
 * The lifecycle the whole file is shaped around:
 *
 *     audit  →  strategy  →  [approved]  →  tasks  →  content  →  implemented
 *                                            ↑                         │
 *                                            └──── work log closes ────┘
 *
 * Four states, in the order work moves through them. They are deliberately
 * about *where the work is*, not about who is blocking it, because the one
 * question everybody asks is "what is actually waiting on me".
 *
 * @package SixClaude
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Six_Tasks {

	/** key => label, in workflow order. */
	const STATUSES = array(
		'todo'           => 'To do',
		'client_review'  => 'Client review',
		'implementation' => 'For implementation',
		'complete'       => 'Complete',
	);

	/** A plain-words note on each, for the UI and for the connector. */
	const STATUS_MEANS = array(
		'todo'           => 'Not started.',
		'client_review'  => 'Sent to the client, waiting on their approval.',
		'implementation' => 'Approved and being built or deployed.',
		'complete'       => 'Done and verified.',
	);

	public static function table() { global $wpdb; return $wpdb->prefix . 'six_tasks'; }

	/** @return true|string true, or the reason nothing can be written. */
	protected static function writable() {
		$tbl = self::table();
		if ( ! function_exists( 'six_table_exists' ) ) return true;
		if ( six_table_exists( $tbl ) ) return true;
		if ( function_exists( 'six_create_tables' ) ) six_create_tables();
		if ( six_table_exists( $tbl ) ) return true;
		return 'The ' . $tbl . ' table is missing. Open any WordPress admin page once to build it.';
	}

	protected static function readable() {
		$tbl = self::table();
		return ! function_exists( 'six_table_exists' ) || six_table_exists( $tbl );
	}

	public static function status_label( $key ) {
		return self::STATUSES[ $key ] ?? $key;
	}

	// ── Creating ──────────────────────────────────────────────────────────

	/**
	 * Create the plan, in bulk.
	 *
	 * Tasks arrive unpublished. A freshly generated plan is a draft: the
	 * advisor edits the titles, dates and order before the client sees any of
	 * it, and publishing is a separate, deliberate act.
	 *
	 * @param array $rows Each: title (required), service_slug, phase, month_no,
	 *                    category, detail, deliverable, entity, keyword,
	 *                    priority, due_date, status, sort_order.
	 * @return array{saved:int,ids:int[],failed:array}
	 */
	public static function create( $client_id, array $rows, $origin = 'claude' ) {
		global $wpdb;
		$client_id = (int) $client_id;
		$ok = self::writable();
		if ( $ok !== true ) return array( 'saved' => 0, 'ids' => array(), 'failed' => array( '_table' => $ok ) );
		if ( ! $client_id ) return array( 'saved' => 0, 'ids' => array(), 'failed' => array( '_client' => 'No client.' ) );

		$tbl = self::table();
		$saved = 0; $ids = array(); $failed = array();

		// Where the existing plan ends, so a second batch appends rather than
		// interleaving with the first.
		$base_order = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(MAX(sort_order),0) FROM {$tbl} WHERE client_id=%d", $client_id ) );

		$wpdb->suppress_errors( true );
		foreach ( $rows as $i => $r ) {
			$title = sanitize_text_field( (string) ( $r['title'] ?? '' ) );
			if ( $title === '' ) { $failed[ $i ] = 'a task needs a title'; continue; }

			$status = isset( $r['status'] ) && isset( self::STATUSES[ $r['status'] ] ) ? $r['status'] : 'todo';
			$month  = max( 1, min( 24, (int) ( $r['month_no'] ?? 1 ) ) );
			// Phase follows from the month unless one was given: months 1-4 are
			// phase 1, 5-8 phase 2, 9-12 phase 3. Keeping them consistent is
			// what lets the board group a year's work without a second field to
			// get wrong.
			$phase  = isset( $r['phase'] ) ? max( 1, min( 6, (int) $r['phase'] ) ) : (int) ceil( $month / 4 );

			$data = array(
				'client_id'    => $client_id,
				'service_slug' => sanitize_key( $r['service_slug'] ?? '' ),
				'phase'        => $phase,
				'month_no'     => $month,
				'category'     => sanitize_text_field( substr( (string) ( $r['category'] ?? '' ), 0, 80 ) ),
				'title'        => substr( $title, 0, 255 ),
				'detail'       => wp_kses_post( (string) ( $r['detail'] ?? '' ) ),
				'deliverable'  => sanitize_text_field( substr( (string) ( $r['deliverable'] ?? '' ), 0, 255 ) ),
				'entity'       => substr( (string) ( $r['entity'] ?? '' ), 0, 500 ),
				'keyword'      => sanitize_text_field( substr( (string) ( $r['keyword'] ?? '' ), 0, 255 ) ),
				'status'       => $status,
				'priority'     => max( 1, min( 3, (int) ( $r['priority'] ?? 2 ) ) ),
				'due_date'     => ! empty( $r['due_date'] ) ? self::date( $r['due_date'] ) : null,
				'assigned_to'  => sanitize_text_field( substr( (string) ( $r['assigned_to'] ?? '' ), 0, 190 ) ),
				'artifact_id'  => (int) ( $r['artifact_id'] ?? 0 ),
				'sort_order'   => $base_order + ( isset( $r['sort_order'] ) ? (int) $r['sort_order'] : $i + 1 ),
				'published'    => 0,
				'origin'       => in_array( $origin, array( 'claude', 'advisor' ), true ) ? $origin : 'claude',
				'created_by'   => get_current_user_id(),
			);

			if ( $wpdb->insert( $tbl, $data ) === false ) {
				$failed[ $i ] = $wpdb->last_error ?: 'the write failed';
			} else {
				$saved++; $ids[] = (int) $wpdb->insert_id;
			}
		}
		$wpdb->suppress_errors( false );

		if ( $failed ) error_log( '6ix Tasks: ' . count( $failed ) . ' task(s) failed for client ' . $client_id );
		return array( 'saved' => $saved, 'ids' => $ids, 'failed' => $failed );
	}

	// ── Reading ───────────────────────────────────────────────────────────

	/**
	 * Tasks for a client.
	 *
	 * @param array $args status, service_slug, phase, month_no, published
	 *                    (true = only published, false = only drafts, null =
	 *                    both), limit.
	 */
	public static function all( $client_id, array $args = array() ) {
		global $wpdb;
		if ( ! self::readable() ) return array();
		$tbl = self::table();

		$where = array( $wpdb->prepare( 'client_id=%d', (int) $client_id ) );
		if ( ! empty( $args['status'] ) )       $where[] = $wpdb->prepare( 'status=%s', $args['status'] );
		if ( ! empty( $args['service_slug'] ) ) $where[] = $wpdb->prepare( 'service_slug=%s', sanitize_key( $args['service_slug'] ) );
		if ( ! empty( $args['phase'] ) )        $where[] = $wpdb->prepare( 'phase=%d', (int) $args['phase'] );
		if ( ! empty( $args['month_no'] ) )     $where[] = $wpdb->prepare( 'month_no=%d', (int) $args['month_no'] );
		if ( isset( $args['published'] ) && $args['published'] !== null ) {
			$where[] = $wpdb->prepare( 'published=%d', $args['published'] ? 1 : 0 );
		}
		$limit = max( 1, min( 1000, (int) ( $args['limit'] ?? 500 ) ) );

		$rows = $wpdb->get_results(
			'SELECT * FROM ' . $tbl . ' WHERE ' . implode( ' AND ', $where )
			. ' ORDER BY phase ASC, month_no ASC, sort_order ASC, id ASC LIMIT ' . $limit
		);
		return array_map( array( __CLASS__, 'shape' ), (array) $rows );
	}

	protected static function shape( $r ) {
		return array(
			'id'            => (int) $r->id,
			'service_slug'  => $r->service_slug,
			'phase'         => (int) $r->phase,
			'month_no'      => (int) $r->month_no,
			'category'      => $r->category,
			'title'         => $r->title,
			'detail'        => $r->detail,
			'deliverable'   => $r->deliverable,
			'entity'        => $r->entity,
			'keyword'       => $r->keyword,
			'status'        => $r->status,
			'status_label'  => self::status_label( $r->status ),
			'status_means'  => self::STATUS_MEANS[ $r->status ] ?? '',
			'priority'      => (int) $r->priority,
			'due_date'      => $r->due_date,
			'assigned_to'   => $r->assigned_to,
			'artifact_id'   => (int) $r->artifact_id,
			'work_log_id'   => (int) $r->work_log_id,
			'published'     => (int) $r->published === 1,
			'origin'        => $r->origin,
			'notes'         => $r->notes,
			'completed_at'  => $r->completed_at,
			'updated_at'    => $r->updated_at,
		);
	}

	/**
	 * How the plan stands, counted by state and by phase.
	 *
	 * This is what a monthly report and a Claude session both actually want:
	 * not the list, the shape of it.
	 */
	public static function summary( $client_id, $service_slug = '' ) {
		global $wpdb;
		if ( ! self::readable() ) return array( 'total' => 0, 'by_status' => array(), 'by_phase' => array(), 'published' => 0 );
		$tbl = self::table();

		$where = array( $wpdb->prepare( 'client_id=%d', (int) $client_id ) );
		if ( $service_slug ) $where[] = $wpdb->prepare( 'service_slug=%s', sanitize_key( $service_slug ) );
		$w = implode( ' AND ', $where );

		$by_status = array();
		foreach ( array_keys( self::STATUSES ) as $s ) $by_status[ $s ] = 0;
		foreach ( (array) $wpdb->get_results( 'SELECT status, COUNT(*) c FROM ' . $tbl . ' WHERE ' . $w . ' GROUP BY status' ) as $r ) {
			$by_status[ $r->status ] = (int) $r->c;
		}

		$by_phase = array();
		foreach ( (array) $wpdb->get_results(
			'SELECT phase, COUNT(*) c, SUM(status=\'complete\') done FROM ' . $tbl . ' WHERE ' . $w . ' GROUP BY phase ORDER BY phase' ) as $r ) {
			$by_phase[] = array( 'phase' => (int) $r->phase, 'total' => (int) $r->c, 'complete' => (int) $r->done );
		}

		$total = array_sum( $by_status );
		return array(
			'total'      => $total,
			'by_status'  => $by_status,
			'by_phase'   => $by_phase,
			'published'  => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $tbl . ' WHERE ' . $w . ' AND published=1' ),
			'complete_pct' => $total ? (int) round( $by_status['complete'] / $total * 100 ) : 0,
		);
	}

	// ── Changing ──────────────────────────────────────────────────────────

	/**
	 * Update one task.
	 *
	 * Moving a task to `complete` stamps completed_at, because "done" without
	 * a date cannot be reported on and cannot be argued with.
	 *
	 * @return array{ok:bool,error:string}
	 */
	public static function update( $client_id, $id, array $fields ) {
		global $wpdb;
		$ok = self::writable();
		if ( $ok !== true ) return array( 'ok' => false, 'error' => $ok );

		$tbl = self::table();
		$id  = (int) $id; $client_id = (int) $client_id;
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tbl} WHERE id=%d AND client_id=%d", $id, $client_id ) );
		if ( ! $existing ) return array( 'ok' => false, 'error' => 'That task no longer exists.' );

		$data = array();
		if ( isset( $fields['status'] ) ) {
			if ( ! isset( self::STATUSES[ $fields['status'] ] ) ) {
				return array( 'ok' => false, 'error' => 'Unknown status. Use one of: ' . implode( ', ', array_keys( self::STATUSES ) ) );
			}
			$data['status'] = $fields['status'];
			$data['completed_at'] = $fields['status'] === 'complete' ? current_time( 'mysql' ) : null;
		}
		foreach ( array( 'title', 'deliverable', 'assigned_to', 'keyword', 'category' ) as $f ) {
			if ( isset( $fields[ $f ] ) ) $data[ $f ] = sanitize_text_field( (string) $fields[ $f ] );
		}
		foreach ( array( 'detail', 'notes' ) as $f ) {
			if ( isset( $fields[ $f ] ) ) $data[ $f ] = wp_kses_post( (string) $fields[ $f ] );
		}
		if ( isset( $fields['entity'] ) )      $data['entity'] = substr( (string) $fields['entity'], 0, 500 );
		if ( isset( $fields['priority'] ) )    $data['priority'] = max( 1, min( 3, (int) $fields['priority'] ) );
		if ( isset( $fields['month_no'] ) ) {
			$data['month_no'] = max( 1, min( 24, (int) $fields['month_no'] ) );
			$data['phase']    = (int) ceil( $data['month_no'] / 4 );
		}
		if ( isset( $fields['phase'] ) )       $data['phase'] = max( 1, min( 6, (int) $fields['phase'] ) );
		if ( isset( $fields['due_date'] ) )    $data['due_date'] = $fields['due_date'] ? self::date( $fields['due_date'] ) : null;
		if ( isset( $fields['sort_order'] ) )  $data['sort_order'] = (int) $fields['sort_order'];
		if ( isset( $fields['published'] ) )   $data['published'] = $fields['published'] ? 1 : 0;
		if ( isset( $fields['artifact_id'] ) ) $data['artifact_id'] = (int) $fields['artifact_id'];
		if ( isset( $fields['work_log_id'] ) ) $data['work_log_id'] = (int) $fields['work_log_id'];

		if ( ! $data ) return array( 'ok' => false, 'error' => 'Nothing to change.' );

		$wpdb->suppress_errors( true );
		$done = $wpdb->update( $tbl, $data, array( 'id' => $id, 'client_id' => $client_id ) );
		$err  = $wpdb->last_error;
		$wpdb->suppress_errors( false );

		if ( $done === false ) {
			error_log( '6ix Tasks: update failed for task ' . $id . ': ' . $err );
			return array( 'ok' => false, 'error' => $err ?: 'The update failed.' );
		}
		return array( 'ok' => true, 'error' => '' );
	}

	/** Publish or unpublish a whole plan, or one phase of it. */
	public static function publish( $client_id, $published = true, $phase = 0 ) {
		global $wpdb;
		$ok = self::writable();
		if ( $ok !== true ) return array( 'ok' => false, 'count' => 0, 'error' => $ok );

		$where = array( 'client_id' => (int) $client_id );
		if ( $phase ) $where['phase'] = (int) $phase;

		$wpdb->suppress_errors( true );
		$n = $wpdb->update( self::table(), array( 'published' => $published ? 1 : 0 ), $where );
		$err = $wpdb->last_error;
		$wpdb->suppress_errors( false );

		if ( $n === false ) return array( 'ok' => false, 'count' => 0, 'error' => $err ?: 'The write failed.' );
		return array( 'ok' => true, 'count' => (int) $n, 'error' => '' );
	}

	/**
	 * Remove one task.
	 *
	 * Returns what the database did, not what we hoped. A delete that removed
	 * nothing — wrong id, wrong client — is reported as a failure, because a
	 * row still sitting on the board after a confirmed delete is how an
	 * advisor loses trust in the board.
	 *
	 * @return array{ok:bool,error:string}
	 */
	public static function delete( $client_id, $id ) {
		global $wpdb;
		if ( ! self::readable() ) return array( 'ok' => false, 'error' => 'The task table is missing.' );

		$wpdb->suppress_errors( true );
		$n   = $wpdb->delete( self::table(), array( 'id' => (int) $id, 'client_id' => (int) $client_id ) );
		$err = $wpdb->last_error;
		$wpdb->suppress_errors( false );

		if ( $n === false ) {
			error_log( '6ix Tasks: delete failed for task ' . (int) $id . ': ' . $err );
			return array( 'ok' => false, 'error' => $err ?: 'The delete failed and nothing was changed.' );
		}
		if ( ! $n ) return array( 'ok' => false, 'error' => 'That task no longer exists.' );
		return array( 'ok' => true, 'error' => '' );
	}

	/** Remove a whole unpublished draft, so a bad generation can be redone. */
	public static function clear_drafts( $client_id ) {
		global $wpdb;
		if ( ! self::readable() ) return 0;
		return (int) $wpdb->delete( self::table(), array( 'client_id' => (int) $client_id, 'published' => 0 ) );
	}

	protected static function date( $raw ) {
		$ts = strtotime( (string) $raw );
		return $ts ? gmdate( 'Y-m-d', $ts ) : null;
	}
}
