<?php
/**
 * Lead Automation Flow — response-window tracking + the shared 4-touch
 * recovery sequence, per the "Lead Automation Flow" proposal.
 *
 * Two lead sources feed this, each with its own storage (they don't share
 * a table — see the design note below) but both fire through the SAME
 * touch templates (data-forms-seed.php's six_lead_recovery_seed_defaults())
 * and the same six_lead_pipeline_fire_touch() sender, so editing a touch's
 * copy in one place updates it everywhere it's used:
 *
 *  - Website-form leads: {$wpdb->prefix}six_form_submissions rows
 *    (response_status/response_due_at/recovery_stage/recovery_next_at
 *    columns, class-forms.php v4). A lead starts 'pending' with a 10-minute
 *    response_due_at; if nobody marks it responded in time, the sweep cron
 *    marks it 'abandoned' and starts the recovery sequence at touch 0.
 *  - Onboarding-abandonment leads: WP user meta (six_recovery_active/
 *    _stage/_next_at) on the WP user account. Six_Odoo::
 *    handle_abandoned_checkout() already sends its own tuned "touch 0"
 *    (SMS+email) the moment a checkout is abandoned — left untouched here
 *    — and seeds this usermeta so THIS file's cron picks the user up for
 *    touches 1-3, sharing the same templates/timing as website-form leads.
 *
 * Meta Ads lead ingestion is NOT built here — there's no Meta Lead Ads
 * webhook integration in this codebase yet, so those leads can't reach
 * "New Auto" until that's built separately (needs a Meta App ID/access
 * token this session doesn't have). Once it exists, feeding a lead through
 * Six_Odoo::sync_form_submission()-style logic into the 'New Auto' stage
 * would plug straight into the same response-window + recovery mechanism.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/** Minutes a paid/website lead has to be marked "Responded" before it's swept to Abandoned. Filterable. */
function six_call_reminder_minutes() {
	return max( 1, (int) apply_filters( 'six_call_reminder_minutes', 10 ) );
}

// ═════════════════════════════════════════════════════════════════════════
// 1. Start the response-window countdown the moment a website-form lead logs
// ═════════════════════════════════════════════════════════════════════════
add_action( 'six_form_submission_logged', 'six_lead_pipeline_start_response_window', 20, 2 );
function six_lead_pipeline_start_response_window( $submission_id, $row ) {
	global $wpdb;
	$base = ! empty( $row['created_at'] ) ? strtotime( $row['created_at'] ) : current_time( 'timestamp' );
	$due  = date( 'Y-m-d H:i:s', $base + six_call_reminder_minutes() * MINUTE_IN_SECONDS );
	$wpdb->update( $wpdb->prefix . 'six_form_submissions', array(
		'response_status' => 'pending',
		'response_due_at' => $due,
	), array( 'id' => $submission_id ) );
}

// ═════════════════════════════════════════════════════════════════════════
// 2. "Mark Responded" — advisor/sales action (per the chosen design: a
// human marks it, rather than guessing from an ambiguous signal).
// ═════════════════════════════════════════════════════════════════════════
function six_lead_can_mark_responded() {
	return current_user_can( 'manage_options' )
		|| ( class_exists( 'Six_Roles' ) && ( Six_Roles::is_advisor() || Six_Roles::is_sales() ) );
}

/** Marks a submission responded. Safe to call from either the AJAX handler below or a plain form POST. */
function six_lead_mark_responded( $submission_id ) {
	global $wpdb;
	return $wpdb->update( $wpdb->prefix . 'six_form_submissions', array(
		'response_status' => 'responded',
		'responded_at'    => current_time( 'mysql' ),
		'responded_by'    => get_current_user_id(),
	), array( 'id' => intval( $submission_id ) ) );
}

add_action( 'wp_ajax_six_lead_mark_responded', function () {
	check_ajax_referer( 'six_nonce', 'nonce' );
	if ( ! six_lead_can_mark_responded() ) wp_send_json_error( 'Permission denied' );
	$id = intval( $_POST['submission_id'] ?? 0 );
	if ( ! $id ) wp_send_json_error( 'Missing submission id' );
	six_lead_mark_responded( $id );
	wp_send_json_success( array( 'message' => 'Marked as responded — removed from the recovery sequence.' ) );
} );

// ═════════════════════════════════════════════════════════════════════════
// 3. Cron schedule — a 5-minute sweep (response-window + recovery touches)
// and a quarterly win-back pass.
// ═════════════════════════════════════════════════════════════════════════
add_filter( 'cron_schedules', function ( $schedules ) {
	if ( ! isset( $schedules['six_five_minutes'] ) ) {
		$schedules['six_five_minutes'] = array( 'interval' => 5 * MINUTE_IN_SECONDS, 'display' => 'Every 5 minutes (6ix lead pipeline)' );
	}
	if ( ! isset( $schedules['six_quarterly'] ) ) {
		$schedules['six_quarterly'] = array( 'interval' => 91 * DAY_IN_SECONDS, 'display' => 'Quarterly (6ix win-back)' );
	}
	return $schedules;
} );
add_action( 'wp_loaded', function () {
	if ( ! wp_next_scheduled( 'six_lead_pipeline_sweep' ) ) wp_schedule_event( time(), 'six_five_minutes', 'six_lead_pipeline_sweep' );
	if ( ! wp_next_scheduled( 'six_lead_pipeline_winback' ) ) wp_schedule_event( time(), 'six_quarterly', 'six_lead_pipeline_winback' );
} );
add_action( 'six_lead_pipeline_sweep', 'six_lead_pipeline_run_sweep' );
add_action( 'six_lead_pipeline_winback', 'six_lead_pipeline_run_winback' );

function six_lead_pipeline_run_sweep() {
	six_lead_pipeline_abandon_overdue();
	six_lead_pipeline_fire_due_touches_forms();
	six_lead_pipeline_fire_due_touches_onboarding();
}

// ── 3a. No response within the window → Abandoned, recovery sequence starts ─
function six_lead_pipeline_abandon_overdue() {
	global $wpdb;
	$table = $wpdb->prefix . 'six_form_submissions';
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT id, odoo_lead_id FROM {$table} WHERE response_status='pending' AND response_due_at IS NOT NULL AND response_due_at <= %s",
		current_time( 'mysql' )
	) );
	foreach ( $rows as $row ) {
		$wpdb->update( $table, array(
			'response_status'  => 'abandoned',
			'recovery_stage'   => 0,
			'recovery_next_at' => current_time( 'mysql' ), // touch 0 fires on the very next sweep tick
		), array( 'id' => $row->id ) );
		if ( class_exists( 'Six_Odoo' ) && $row->odoo_lead_id ) {
			Six_Odoo::update_lead_stage( intval( $row->odoo_lead_id ), 'Abandoned' );
		}
		error_log( "6ix Lead Pipeline: submission #{$row->id} auto-abandoned — no response within " . six_call_reminder_minutes() . " minute window." );
	}
}

// ── 3b. Fire due recovery touches — website-form leads ─────────────────────
function six_lead_pipeline_fire_due_touches_forms() {
	global $wpdb;
	$table = $wpdb->prefix . 'six_form_submissions';
	$now = current_time( 'mysql' );
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM {$table} WHERE response_status='abandoned' AND recovery_stage < 4 AND recovery_next_at IS NOT NULL AND recovery_next_at <= %s",
		$now
	) );
	foreach ( $rows as $row ) {
		$decoded = json_decode( $row->data, true );
		$contact = six_lead_pipeline_extract_contact( is_array( $decoded ) ? $decoded : array() );
		$stage   = intval( $row->recovery_stage );

		six_lead_pipeline_fire_touch( $stage, $contact['name'], $contact['email'], $contact['phone'], intval( $row->odoo_lead_id ) );

		$next_stage = $stage + 1;
		$update = array( 'recovery_stage' => $next_stage );
		if ( $next_stage >= 4 ) {
			$update['response_status']  = 'nurture';
			$update['recovery_next_at'] = null;
			if ( class_exists( 'Six_Odoo' ) && $row->odoo_lead_id ) Six_Odoo::update_lead_stage( intval( $row->odoo_lead_id ), 'Nurture List' );
		} else {
			$update['recovery_next_at'] = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) + six_lead_pipeline_next_delay( $stage ) );
		}
		$wpdb->update( $table, $update, array( 'id' => $row->id ) );
	}
}

// ── 3c. Fire due recovery touches — onboarding-abandonment leads (touches 1-3;
// touch 0 already happened in Six_Odoo::handle_abandoned_checkout()) ───────
function six_lead_pipeline_fire_due_touches_onboarding() {
	$users = get_users( array(
		'number'     => 50,
		'meta_query' => array(
			'relation' => 'AND',
			array( 'key' => 'six_recovery_active', 'value' => 1 ),
			array( 'key' => 'six_recovery_stage', 'value' => 4, 'compare' => '<', 'type' => 'NUMERIC' ),
			array( 'key' => 'six_recovery_next_at', 'value' => current_time( 'mysql' ), 'compare' => '<=', 'type' => 'DATETIME' ),
		),
	) );
	foreach ( $users as $user ) {
		$stage        = intval( get_user_meta( $user->ID, 'six_recovery_stage', true ) );
		$phone        = get_user_meta( $user->ID, 'billing_phone', true );
		$lead_id_odoo = intval( get_user_meta( $user->ID, 'six_odoo_lead_id', true ) );
		$name         = trim( (string) $user->first_name ) ?: $user->display_name;

		six_lead_pipeline_fire_touch( $stage, $name, $user->user_email, $phone, $lead_id_odoo );

		$next_stage = $stage + 1;
		update_user_meta( $user->ID, 'six_recovery_stage', $next_stage );
		if ( $next_stage >= 4 ) {
			update_user_meta( $user->ID, 'six_recovery_active', 0 );
			update_user_meta( $user->ID, 'six_recovery_nurture', 1 );
			delete_user_meta( $user->ID, 'six_recovery_next_at' );
			if ( class_exists( 'Six_Odoo' ) && $lead_id_odoo ) Six_Odoo::update_lead_stage( $lead_id_odoo, 'Nurture List' );
		} else {
			update_user_meta( $user->ID, 'six_recovery_next_at', date( 'Y-m-d H:i:s', current_time( 'timestamp' ) + six_lead_pipeline_next_delay( $stage ) ) );
		}
	}
}

/** Delay, in seconds, from firing touch $stage to when the NEXT touch should fire. 0/1h/24h/3d total from abandonment. */
function six_lead_pipeline_next_delay( $stage ) {
	$delays = array(
		0 => HOUR_IN_SECONDS,       // touch 0 -> touch 1 @ +1h
		1 => 23 * HOUR_IN_SECONDS,  // touch 1 -> touch 2 @ +24h total
		2 => 2 * DAY_IN_SECONDS,    // touch 2 -> touch 3 @ +3d total
	);
	return $delays[ $stage ] ?? 0;
}

/** Sends one recovery touch's email (if it has one) + SMS (if it has one and a phone is available). */
function six_lead_pipeline_fire_touch( $stage, $name, $email, $phone, $lead_id_odoo = 0 ) {
	$template_key = 'lead_recovery_touch' . max( 0, min( 3, intval( $stage ) ) );
	$merge = array( 'client_name' => $name ?: 'there' );

	if ( $email && is_email( $email ) && function_exists( 'six_send_system_email' ) ) {
		six_send_system_email( $template_key, $merge, array(
			'send_admin'     => false, // one admin email per touch per lead would be noisy — the lead's already visible in Form Submissions
			'send_customer'  => true,
			'customer_email' => $email,
		) );
	}
	if ( $phone ) {
		six_lead_pipeline_send_sms( $template_key, $merge, $phone, $lead_id_odoo );
	}
}

function six_lead_pipeline_send_sms( $template_key, array $merge, $phone, $lead_id_odoo = 0 ) {
	if ( ! $phone || ! function_exists( 'six_forms_get' ) ) return false;
	$tpl = six_forms_get( $template_key );
	if ( ! $tpl || empty( $tpl['sms_body'] ) ) return false; // touch has no SMS configured — fine, e.g. the 24h email-only touch

	$data = array();
	foreach ( $merge as $k => $v ) {
		$data[ sanitize_key( (string) $k ) ] = array( 'label' => ucwords( str_replace( array( '_', '-' ), ' ', (string) $k ) ), 'value' => (string) $v );
	}
	$text = six_forms_merge_tags( $tpl['sms_body'], $data, $tpl );
	return class_exists( 'Six_Odoo' ) ? Six_Odoo::send_sms_twilio( $phone, $text, $lead_id_odoo ) : false;
}

/**
 * Best-effort name/email/phone out of a submission's flattened field data —
 * a small local copy of the same heuristic Six_Odoo::extract_contact_fields()
 * uses (that one's private to the Odoo class), since this cron only needs
 * it for addressing an email/SMS, not for the fuller Odoo sync.
 */
function six_lead_pipeline_extract_contact( array $data ) {
	$out = array( 'name' => '', 'email' => '', 'phone' => '' );
	foreach ( $data as $f ) {
		$label = strtolower( (string) ( $f['label'] ?? '' ) );
		$value = trim( (string) ( $f['value'] ?? '' ) );
		if ( $value === '' ) continue;
		if ( ! $out['email'] && is_email( $value ) ) { $out['email'] = $value; continue; }
		if ( ! $out['phone'] && preg_match( '/phone|tel|mobile|contact number/', $label ) ) { $out['phone'] = $value; continue; }
		if ( ! $out['name'] && preg_match( '/^(your\s+)?(full\s+)?name$|first\s*name|contact\s*name/', $label ) ) { $out['name'] = $value; continue; }
	}
	if ( ! $out['name'] ) {
		foreach ( $data as $f ) {
			$value = trim( (string) ( $f['value'] ?? '' ) );
			if ( $value !== '' && $value !== $out['email'] && $value !== $out['phone'] && preg_match( '/^[A-Za-z ,.\'-]{2,60}$/', $value ) ) {
				$out['name'] = $value;
				break;
			}
		}
	}
	return $out;
}

// ═════════════════════════════════════════════════════════════════════════
// 4. Quarterly win-back — Nurture list (both sources) + Odoo "Lost" leads.
// ═════════════════════════════════════════════════════════════════════════
function six_lead_pipeline_run_winback() {
	global $wpdb;
	$sent = 0;

	// Website-form leads that finished the recovery sequence with no response.
	$table = $wpdb->prefix . 'six_form_submissions';
	$rows = $wpdb->get_results( "SELECT * FROM {$table} WHERE response_status='nurture'" );
	foreach ( $rows as $row ) {
		$decoded = json_decode( $row->data, true );
		$contact = six_lead_pipeline_extract_contact( is_array( $decoded ) ? $decoded : array() );
		if ( $contact['email'] ) { six_lead_pipeline_send_winback( $contact['name'], $contact['email'] ); $sent++; }
	}

	// Onboarding-abandonment leads that finished the recovery sequence.
	$users = get_users( array( 'meta_key' => 'six_recovery_nurture', 'meta_value' => 1, 'number' => 200 ) );
	foreach ( $users as $user ) {
		$name = trim( (string) $user->first_name ) ?: $user->display_name;
		six_lead_pipeline_send_winback( $name, $user->user_email );
		$sent++;
	}

	// Odoo's own "Lost" leads (active=false) — not tracked in WordPress at
	// all, so these are read straight from Odoo rather than a local table.
	if ( class_exists( 'Six_Odoo' ) && method_exists( 'Six_Odoo', 'get_lost_leads' ) ) {
		foreach ( Six_Odoo::get_lost_leads() as $lead ) {
			if ( ! empty( $lead['email_from'] ) && is_email( $lead['email_from'] ) ) {
				six_lead_pipeline_send_winback( $lead['partner_name'] ?? '', $lead['email_from'] );
				$sent++;
			}
		}
	}

	error_log( "6ix Lead Pipeline: quarterly win-back sent to {$sent} lead(s)." );
}

function six_lead_pipeline_send_winback( $name, $email ) {
	if ( ! $email || ! is_email( $email ) || ! function_exists( 'six_send_system_email' ) ) return;
	six_send_system_email( 'lead_quarterly_winback', array( 'client_name' => $name ?: 'there' ), array(
		'send_admin'     => false,
		'send_customer'  => true,
		'customer_email' => $email,
	) );
}
