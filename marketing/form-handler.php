<?php
/**
 * 6ix Developers — native submit handler for the built-in marketing forms
 * (marketing/forms.php).
 *
 * Every mk_form_*() form posts via AJAX (see the data-mk-ajax handler in
 * marketing.js) to this single endpoint, which emails the submission
 * through wp_mail() — so a form actually works end-to-end with no forms
 * plugin installed. mk_form()'s Ninja Forms / Contact Form 7 / any other
 * shortcode override (marketing/forms.php's $override lookup) still takes
 * priority when an admin has explicitly set one; this only ever handles
 * the built-in clone forms.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_ajax_six_mk_form_submit', 'six_mk_handle_form_submit' );
add_action( 'wp_ajax_nopriv_six_mk_form_submit', 'six_mk_handle_form_submit' );

/** Turns a field's HTML `name` into a readable label for the notification email (e.g. "audit-company-name" -> "Audit Company Name"). */
function six_mk_prettify_field_key( $key ) {
	$key = str_replace( array( '-', '_', '[]' ), ' ', $key );
	return trim( ucwords( $key ) );
}

function six_mk_handle_form_submit() {
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'six_mk_form' ) ) {
		wp_send_json_error( array( 'message' => 'Your session expired — please refresh the page and try again.' ) );
	}

	// Math captcha (mirrors the original site's calVal/calSum check).
	$cal_val = isset( $_POST['calVal'] ) ? (int) $_POST['calVal'] : null;
	$cal_sum = isset( $_POST['calSum'] ) ? (int) $_POST['calSum'] : null;
	if ( $cal_val === null || $cal_sum === null || $cal_val !== $cal_sum ) {
		wp_send_json_error( array( 'message' => 'The security check answer is incorrect — please try again.' ) );
	}

	$skip = array( 'action', 'nonce', 'calVal', 'calSum', 'form_title' );
	$lines = array();
	foreach ( $_POST as $key => $val ) {
		if ( in_array( $key, $skip, true ) ) continue;
		$raw_key = preg_replace( '/\[\]$/', '', $key ); // strip a trailing "[]" from a checkbox-group name
		if ( is_array( $val ) ) {
			$val = implode( ', ', array_map( 'sanitize_text_field', wp_unslash( $val ) ) );
		} else {
			$val = sanitize_text_field( wp_unslash( $val ) );
		}
		if ( $val === '' ) continue;
		$lines[] = six_mk_prettify_field_key( $raw_key ) . ': ' . $val;
	}

	if ( ! $lines ) {
		wp_send_json_error( array( 'message' => 'Please fill in the form before submitting.' ) );
	}

	$form_title = isset( $_POST['form_title'] ) ? sanitize_text_field( wp_unslash( $_POST['form_title'] ) ) : 'Website Enquiry';
	$to      = get_option( 'admin_email' );
	$subject = 'New ' . $form_title . ' submission';
	$body    = implode( "\n", $lines );
	$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

	// A reply-to of the submitter's own email, when they gave one, so
	// replying to the notification goes straight back to them.
	foreach ( $_POST as $key => $val ) {
		if ( is_string( $val ) && is_email( $val ) ) { $headers[] = 'Reply-To: ' . sanitize_email( $val ); break; }
	}

	$sent = wp_mail( $to, $subject, $body, $headers );

	if ( $sent ) {
		wp_send_json_success( array( 'message' => "Thanks — we've received your submission and will be in touch shortly." ) );
	} else {
		wp_send_json_error( array( 'message' => "Something went wrong sending your message — please call us instead at 888-808-7265." ) );
	}
}
