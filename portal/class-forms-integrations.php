<?php
/**
 * Forms → Odoo + Advisor Dashboard integration.
 *
 * Hooks the `six_form_submission_logged` action fired by
 * class-forms-submit.php right after every submission is written to
 * {$wpdb->prefix}six_form_submissions (success, partial, or failed — never
 * for 'blocked'/spam submissions). On each fire:
 *
 *   1. Pushes the submission into Odoo (contact + CRM lead + a 24-hour
 *      follow-up task) via Six_Odoo::sync_form_submission(), then writes the
 *      result back onto the submission row: odoo_lead_id/odoo_partner_id on
 *      success, or odoo_sync_status='failed' + a human-readable
 *      odoo_sync_error on failure — so a submission that never reached Odoo
 *      is visible and diagnosable from the dashboard, not just server logs.
 *   2. Notifies every advisor (six_advisor role) in-app: "New email from
 *      [name]", linking to that submission's detail view in the advisor
 *      portal's "Form Submissions" tab.
 *
 * Both steps are best-effort and independently fault-tolerant — a missing/
 * misconfigured Odoo connection, or zero advisor accounts, must never make
 * the submission itself fail or get lost; the row is already durably saved
 * by the time this file runs.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'six_form_submission_logged', 'six_forms_sync_to_odoo', 10, 2 );
add_action( 'six_form_submission_logged', 'six_forms_notify_advisors', 10, 2 );

/**
 * Push one submission into Odoo and persist the outcome (success or a
 * readable failure reason) onto its row. Shared by the automatic sync on
 * submit and the manual "Resync to Odoo" action on the detail screens.
 *
 * @param int        $submission_id
 * @param array|null $row Pre-fetched row (form_title/form_key/data/
 *                        created_at/source_url/ip) to avoid a re-query right
 *                        after insert; when omitted, loads it fresh — used
 *                        by the manual resync path.
 * @return array The status/error fields that were written, for callers that
 *               want to show an immediate result.
 */
function six_forms_do_odoo_sync( $submission_id, $row = null ) {
	global $wpdb;
	$table = $wpdb->prefix . 'six_form_submissions';

	if ( $row === null ) {
		$r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id=%d", $submission_id ), ARRAY_A );
		if ( ! $r ) return array( 'odoo_sync_status' => 'failed', 'odoo_sync_error' => 'Submission not found.' );
		$row = $r;
		$decoded = json_decode( $row['data'] ?? '', true );
		$row['data'] = is_array( $decoded ) ? $decoded : array();
	}

	if ( ! class_exists( 'Six_Odoo' ) ) {
		$update = array( 'odoo_sync_status' => 'failed', 'odoo_sync_error' => 'The Odoo integration is not installed on this site.' );
	} else {
		$result = Six_Odoo::sync_form_submission( array(
			'form_title' => $row['form_title'] ?? '',
			'form_key'   => $row['form_key']   ?? '',
			'data'       => is_array( $row['data'] ?? null ) ? $row['data'] : array(),
			'created_at' => $row['created_at'] ?? '',
			'source_url' => $row['source_url'] ?? '',
			'ip'         => $row['ip']         ?? '',
		) );

		if ( ! empty( $result['ok'] ) ) {
			$update = array(
				'odoo_lead_id'     => intval( $result['lead_id'] ),
				'odoo_partner_id'  => intval( $result['partner_id'] ?? 0 ),
				'odoo_sync_status' => 'synced',
				'odoo_sync_error'  => '',
			);
		} else {
			$update = array(
				'odoo_sync_status' => 'failed',
				'odoo_sync_error'  => substr( (string) ( $result['error'] ?? 'Unknown error syncing to Odoo.' ), 0, 2000 ),
			);
		}
	}

	$wpdb->update( $table, $update, array( 'id' => $submission_id ) );
	return $update;
}

/** Hook handler: automatic sync right after a submission is logged. */
function six_forms_sync_to_odoo( $submission_id, $row ) {
	six_forms_do_odoo_sync( $submission_id, $row );
}

/**
 * Manually retry the Odoo sync for one submission — used by the "Resync to
 * Odoo" button on both the wp-admin and advisor-portal detail screens, e.g.
 * after fixing credentials or a transient Odoo-side error.
 */
function six_forms_resync_odoo( $submission_id ) {
	return six_forms_do_odoo_sync( intval( $submission_id ) );
}

/**
 * Best-effort name for a submission — mirrors Six_Odoo's own extraction
 * (label-based, since field keys differ per form) but kept local since that
 * logic is private to the Odoo class.
 */
function six_forms_guess_name( array $data ) {
	foreach ( $data as $f ) {
		$label = strtolower( (string) ( $f['label'] ?? '' ) );
		$value = trim( (string) ( $f['value'] ?? '' ) );
		if ( $value !== '' && preg_match( '/^(your\s+)?(full\s+)?name$|first\s*name|contact\s*name/', $label ) ) {
			return $value;
		}
	}
	foreach ( $data as $f ) {
		$value = trim( (string) ( $f['value'] ?? '' ) );
		if ( $value !== '' && preg_match( '/^[A-Za-z ,.\'-]{2,60}$/', $value ) ) return $value;
	}
	foreach ( $data as $f ) {
		$value = trim( (string) ( $f['value'] ?? '' ) );
		if ( $value !== '' && is_email( $value ) ) return $value;
	}
	return 'a website visitor';
}

/**
 * In-app "New email from [name]" notification to every advisor, linking to
 * this submission's detail view under the advisor portal's Form Submissions
 * tab. Broadcast to all advisors since a fresh lead form submission isn't
 * yet assigned to any one advisor.
 */
function six_forms_notify_advisors( $submission_id, $row ) {
	if ( ! class_exists( 'Six_Notifications' ) ) return;

	$data = is_array( $row['data'] ?? null ) ? $row['data'] : array();
	$name = six_forms_guess_name( $data );
	$form_title = $row['form_title'] ?? ( $row['form_key'] ?? 'website form' );

	$advisors = get_users( array( 'role' => 'six_advisor', 'fields' => array( 'ID' ) ) );
	if ( ! $advisors ) return;

	$action_url = home_url( '/advisor-portal/?tab=form-submissions&submission=' . intval( $submission_id ) );

	foreach ( $advisors as $advisor ) {
		Six_Notifications::create( array(
			'user_id'    => $advisor->ID,
			'type'       => 'form_submission',
			'title'      => 'New email from ' . $name,
			'message'    => 'Submitted the "' . $form_title . '" form on the website.',
			'action_url' => $action_url,
		) );
	}
}
