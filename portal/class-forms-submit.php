<?php
/**
 * 6ix Portal — Forms system: submission handling.
 *
 * Every submission is logged to {$wpdb->prefix}six_form_submissions BEFORE
 * this handler returns, regardless of whether the notification email(s)
 * actually sent — the whole point of the log is to be the record of a lead
 * that survives an SMTP outage, a wrong "to" address, or anything else
 * going wrong with email. The insert only ever gets skipped if the request
 * fails a basic security check (bad nonce, unknown form) — even a failed
 * CAPTCHA still gets logged (status 'blocked'), rather than silently
 * disappearing.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_ajax_six_forms_submit', 'six_forms_handle_submit' );
add_action( 'wp_ajax_nopriv_six_forms_submit', 'six_forms_handle_submit' );

function six_forms_handle_submit() {
	try {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'six_forms_submit' ) ) {
			wp_send_json_error( array( 'message' => 'Your session expired — please refresh the page and try again.' ) );
		}

		$form_key = isset( $_POST['form_key'] ) ? sanitize_text_field( wp_unslash( $_POST['form_key'] ) ) : '';
		$form     = $form_key ? six_forms_get( $form_key ) : null;
		if ( ! $form ) {
			wp_send_json_error( array( 'message' => 'This form is no longer available. Please refresh the page and try again.' ) );
		}

		// Gather + sanitize only the fields this form actually defines (never
		// trust arbitrary POST keys) — keyed by field key, each carrying its
		// label along so the log/emails can render a readable field name
		// even after the form definition later changes.
		$data = array();
		foreach ( $form['fields'] as $fdef ) {
			$key = $fdef['key'] ?? '';
			if ( $key === '' || ! isset( $_POST[ $key ] ) ) continue;
			$raw = wp_unslash( $_POST[ $key ] );
			$val = is_array( $raw ) ? implode( ', ', array_map( 'sanitize_text_field', $raw ) ) : sanitize_text_field( $raw );
			if ( $val === '' ) continue;
			$data[ $key ] = array( 'label' => $fdef['label'] ?? $key, 'value' => $val );
		}

		// Math captcha (mirrors the original site's calVal check).
		$cal_val     = isset( $_POST['calVal'] ) ? (int) $_POST['calVal'] : null;
		$cal_sum     = isset( $_POST['calSum'] ) ? (int) $_POST['calSum'] : null;
		$captcha_ok  = ( $cal_val !== null && $cal_sum !== null && $cal_val === $cal_sum );
		if ( ! $captcha_ok ) {
			six_forms_log_submission( $form, $data, 'blocked', 'skipped', 'Blocked before sending — security check failed.', 'skipped', '' );
			wp_send_json_error( array( 'message' => 'The security check answer is incorrect — please try again.' ) );
		}

		// ── Owner notification ──────────────────────────────────────────
		$to      = get_option( 'admin_email' );
		$subject = six_forms_merge_tags( $form['owner_subject'] ?: ( 'New ' . $form['title'] . ' submission' ), $data, $form );
		$body    = six_forms_merge_tags( $form['owner_body'] ?: "{all_fields}", $data, $form );
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		foreach ( $data as $f ) {
			if ( is_email( $f['value'] ) ) { $headers[] = 'Reply-To: ' . sanitize_email( $f['value'] ); break; }
		}
		$owner_sent   = wp_mail( $to, $subject, $body, $headers );
		$owner_status = $owner_sent ? 'sent' : 'failed';
		$owner_err    = $owner_sent ? '' : 'wp_mail() returned false — check the SMTP plugin\'s configuration and its own log.';

		// ── Customer confirmation (optional) ────────────────────────────
		$customer_status = 'skipped';
		$customer_err    = '';
		if ( $form['customer_enabled'] ) {
			$email_field    = $form['customer_email_field'];
			$customer_email = ( $email_field && isset( $data[ $email_field ] ) ) ? $data[ $email_field ]['value'] : '';
			if ( ! $customer_email || ! is_email( $customer_email ) ) {
				foreach ( $data as $f ) { if ( is_email( $f['value'] ) ) { $customer_email = $f['value']; break; } }
			}
			if ( $customer_email && is_email( $customer_email ) ) {
				$csubject = six_forms_merge_tags( $form['customer_subject'] ?: 'Thanks for reaching out!', $data, $form );
				$cbody    = six_forms_merge_tags( $form['customer_body'] ?: "Thanks — we've received your submission and will be in touch shortly.", $data, $form );
				$csent    = wp_mail( $customer_email, $csubject, $cbody, array( 'Content-Type: text/plain; charset=UTF-8' ) );
				$customer_status = $csent ? 'sent' : 'failed';
				$customer_err    = $csent ? '' : 'wp_mail() returned false.';
			} else {
				$customer_status = 'skipped';
				$customer_err    = 'No valid email address found in the submission to confirm to.';
			}
		}

		$overall = ( $owner_status === 'failed' ) ? 'partial' : 'success'; // logged either way — never dropped
		six_forms_log_submission( $form, $data, $overall, $owner_status, $owner_err, $customer_status, $customer_err );

		$resp = array(
			'message' => $owner_status === 'sent'
				? "Thanks — we've received your submission and will be in touch shortly."
				: "Thanks — we've received your submission. If you don't hear from us soon, please call us directly.",
		);
		if ( $form['redirect_url'] ) $resp['redirect'] = esc_url_raw( $form['redirect_url'] );
		wp_send_json_success( $resp );

	} catch ( \Throwable $e ) {
		// Last-resort net: even an unexpected fatal here must not silently
		// swallow a lead. Try the DB insert one more time with whatever we
		// managed to gather; if that also fails, at least get it into the
		// PHP error log so it isn't gone without a trace.
		$fallback_form = isset( $form ) && $form ? $form : array( 'id' => 0, 'key' => $form_key ?? '', 'title' => $form_key ?? 'Unknown form' );
		$fallback_data = isset( $data ) ? $data : array();
		try {
			six_forms_log_submission( $fallback_form, $fallback_data, 'failed', 'failed', 'Handler exception: ' . $e->getMessage(), 'skipped', '' );
		} catch ( \Throwable $e2 ) {
			error_log( '[six_forms] submit handler exception (and logging also failed): ' . $e->getMessage() . ' — POST: ' . wp_json_encode( $_POST ) );
		}
		wp_send_json_error( array( 'message' => 'Something went wrong — please try again, or call us directly.' ) );
	}
}

/** Inserts one row into six_form_submissions. Never throws on its own — callers can rely on it being safe to call from a catch block. */
function six_forms_log_submission( $form, $data, $status, $owner_status, $owner_err, $customer_status, $customer_err ) {
	global $wpdb;
	$flat = array();
	foreach ( (array) $data as $k => $f ) $flat[ $k ] = array( 'label' => $f['label'] ?? $k, 'value' => $f['value'] ?? '' );

	$row = array(
		'form_id'               => intval( $form['id'] ?? 0 ),
		'form_key'              => (string) ( $form['key'] ?? '' ),
		'form_title'            => (string) ( $form['title'] ?? '' ),
		'data'                  => wp_json_encode( $flat ),
		'status'                => $status,
		'owner_email_status'    => $owner_status,
		'owner_email_error'     => $owner_err,
		'customer_email_status' => $customer_status,
		'customer_email_error'  => $customer_err,
		'ip'                    => six_forms_client_ip(),
		'user_agent'            => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( substr( $_SERVER['HTTP_USER_AGENT'], 0, 490 ) ) : '',
		'source_url'            => isset( $_POST['source_url'] ) ? esc_url_raw( wp_unslash( $_POST['source_url'] ) ) : '',
		'lead_status'           => 'new',
		'created_at'            => current_time( 'mysql' ),
	);
	$wpdb->insert( $wpdb->prefix . 'six_form_submissions', $row );
	$submission_id = $wpdb->insert_id;

	// Let other systems (Odoo sync, advisor notifications) react to a logged
	// submission without this file needing to know about them. Skipped for
	// 'blocked' (failed-captcha / spam) submissions — those aren't real leads.
	if ( $submission_id && $status !== 'blocked' ) {
		$row['id']   = $submission_id;
		$row['data'] = $flat; // structured array, not the JSON string, for hook consumers
		do_action( 'six_form_submission_logged', $submission_id, $row );
	}

	return $submission_id;
}

/** Best-effort real client IP, respecting a trusted reverse-proxy header when present. */
function six_forms_client_ip() {
	foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ) as $key ) {
		if ( ! empty( $_SERVER[ $key ] ) ) {
			$ip = trim( explode( ',', $_SERVER[ $key ] )[0] );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) return $ip;
		}
	}
	return '';
}

/**
 * Merge tags available in email templates: {field_key} for any one field,
 * {all_fields} for every non-empty submitted field as "Label: value" lines,
 * {form_title} for the form's admin title.
 */
function six_forms_merge_tags( $template, $data, $form ) {
	$all = array();
	foreach ( (array) $data as $f ) {
		if ( ( $f['value'] ?? '' ) !== '' ) $all[] = $f['label'] . ': ' . $f['value'];
	}
	$out = str_replace( '{all_fields}', implode( "\n", $all ), (string) $template );
	$out = str_replace( '{form_title}', (string) ( $form['title'] ?? '' ), $out );
	foreach ( (array) $data as $k => $f ) {
		$out = str_replace( '{' . $k . '}', $f['value'] ?? '', $out );
	}
	return $out;
}
