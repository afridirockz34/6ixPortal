<?php
/**
 * System-generated notification emails — onboarding abandonment/completion,
 * budget changes, new service requests, and service activation. Each event
 * has an editable template (a `six_form` post with six_form_is_system=1,
 * seeded in data-forms-seed.php) with an admin (owner) copy and a customer
 * copy, exactly like a lead-capture form's Emails meta box — reusing
 * six_forms_get()/six_forms_merge_tags() so there's one merge-tag syntax
 * and one editor across the whole site's email system.
 *
 * Call sites (class-odoo.php, ajax-onboarding.php, ajax-handlers.php) build
 * a flat label=>value $merge array and call six_send_system_email(). This
 * file never invents business logic of its own — it only renders + sends.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Who gets the admin/owner copy of every system + form-submission
 * notification email. Comma-separated, editable under 6ix Portal →
 * Settings → Notifications (admin-settings.php) without a code change.
 */
function six_admin_notify_emails() {
	$raw = get_option( 'six_admin_notify_emails', '' );
	if ( $raw === '' ) $raw = 'musab@6ixdevelopers.com,faheem@6ixdevelopers.com';
	$emails = array_filter( array_map( 'trim', explode( ',', $raw ) ), 'is_email' );
	return $emails ?: array( 'musab@6ixdevelopers.com' );
}

/**
 * Send the admin + customer copies of a system-generated notification.
 *
 * @param string $type_key Matches the template's six_form_key (see
 *                          six_system_email_seed_defaults() in
 *                          data-forms-seed.php for the 5 built-in keys).
 * @param array  $merge     Flat label=>value pairs. Keys become
 *                          {snake_case_key} merge tags (auto sanitized);
 *                          all of them together become {all_fields} as
 *                          "Human Label: value" lines.
 * @param array  $opts {
 *   @type bool   $send_admin      Default true.
 *   @type bool   $send_customer   Default true (still gated by the
 *                                 template's own "customer_enabled" toggle).
 *   @type string $customer_email  Required for the customer copy to send.
 *   @type int    $odoo_lead_id    Adds an "Open in Odoo" button to the admin copy.
 *   @type string $dashboard_url   Adds an "Open in Dashboard" button to the admin copy.
 * }
 * @return array array('admin'=>array('sent','skipped','error'), 'customer'=>array(...))
 */
function six_send_system_email( $type_key, array $merge, array $opts = array() ) {
	$result = array(
		'admin'    => array( 'sent' => false, 'skipped' => true, 'error' => '' ),
		'customer' => array( 'sent' => false, 'skipped' => true, 'error' => '' ),
	);

	$tpl = function_exists( 'six_forms_get' ) ? six_forms_get( $type_key ) : null;
	if ( ! $tpl ) {
		$msg = "6ix Emails: template '{$type_key}' not found — did the seed run?";
		error_log( $msg );
		$result['admin']['error'] = $result['customer']['error'] = $msg;
		return $result;
	}

	// Build the {key: {label, value}} shape six_forms_merge_tags() expects,
	// from a flat caller-supplied label=>value array.
	$data = array();
	foreach ( $merge as $k => $v ) {
		$data[ sanitize_key( (string) $k ) ] = array(
			'label' => ucwords( str_replace( array( '_', '-' ), ' ', (string) $k ) ),
			'value' => (string) $v,
		);
	}

	$send_admin    = $opts['send_admin']    ?? true;
	$send_customer = $opts['send_customer'] ?? true;

	if ( $send_admin ) {
		$subject = six_forms_merge_tags( $tpl['owner_subject'] ?: ( $tpl['title'] . ' — ' . current_time( 'M j, Y g:i a' ) ), $data, $tpl );
		$body    = six_forms_merge_tags( $tpl['owner_body'] ?: '{all_fields}', $data, $tpl );

		$links = array();
		if ( ! empty( $opts['dashboard_url'] ) ) $links[] = array( 'label' => 'Open in Dashboard', 'url' => $opts['dashboard_url'] );
		if ( ! empty( $opts['odoo_lead_id'] ) && get_option( 'six_odoo_url' ) ) {
			$links[] = array( 'label' => 'Open in Odoo', 'url' => rtrim( get_option( 'six_odoo_url' ), '/' ) . '/odoo/crm/' . intval( $opts['odoo_lead_id'] ) );
		}

		$html = six_email_chrome( array(
			'preheader' => wp_strip_all_tags( $body ),
			'heading'   => $tpl['title'],
			'body_html' => '<p>' . nl2br( esc_html( trim( strtok( $body, "\n" ) ?: '' ) ) ) . '</p>',
			'info_rows' => wp_list_pluck( $data, 'value', 'label' ),
			'links'     => $links,
			'footer_note' => 'System notification: ' . $type_key,
		) );

		$to   = six_admin_notify_emails();
		$sent = wp_mail( $to, $subject, $html, array( 'Content-Type: text/html; charset=UTF-8' ) );
		$result['admin'] = array( 'sent' => (bool) $sent, 'skipped' => false, 'error' => $sent ? '' : 'wp_mail() returned false — check the SMTP plugin\'s configuration.' );
	}

	if ( $send_customer ) {
		$customer_email = $opts['customer_email'] ?? '';
		if ( ! $tpl['customer_enabled'] ) {
			$result['customer']['error'] = 'Customer email is turned off for this template.';
		} elseif ( ! $customer_email || ! is_email( $customer_email ) ) {
			$result['customer']['error'] = 'No valid customer email address was provided.';
		} else {
			$subject = six_forms_merge_tags( $tpl['customer_subject'] ?: $tpl['title'], $data, $tpl );
			$body    = six_forms_merge_tags( $tpl['customer_body'] ?: '', $data, $tpl );
			$html    = six_email_chrome( array(
				'preheader' => wp_strip_all_tags( $body ),
				'heading'   => $subject,
				'body_html' => nl2br( esc_html( $body ) ),
			) );
			$sent = wp_mail( $customer_email, $subject, $html, array( 'Content-Type: text/html; charset=UTF-8' ) );
			$result['customer'] = array( 'sent' => (bool) $sent, 'skipped' => false, 'error' => $sent ? '' : 'wp_mail() returned false — check the SMTP plugin\'s configuration.' );
		}
	}

	return $result;
}
