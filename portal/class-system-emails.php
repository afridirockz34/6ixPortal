<?php
/**
 * System-generated notifications — onboarding abandonment/completion, budget
 * changes, new service requests, service activation, the onboarding-abandon
 * 30-minute reach-out, and the instant new-lead SMS. Each event has an
 * editable template (a `six_form` post with six_form_is_system=1, seeded in
 * data-forms-seed.php) with an admin (owner) copy, a customer email copy,
 * AND a customer SMS copy — exactly like a lead-capture form's Email
 * Notifications meta box — reusing six_forms_get()/six_forms_merge_tags() so
 * there's one merge-tag syntax and one editor across the whole site's
 * automated email + SMS system (6ix Portal → Automation).
 *
 * Call sites (class-odoo.php, ajax-onboarding.php, ajax-handlers.php,
 * class-growth-engine.php) build a flat label=>value $merge array and call
 * six_send_system_email(). This file never invents business logic of its
 * own — it only renders + sends.
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
 *                          data-forms-seed.php for the built-in keys).
 * @param array  $merge     Flat label=>value pairs. Keys become
 *                          {snake_case_key} merge tags (auto sanitized);
 *                          all of them together become {all_fields} as
 *                          "Human Label: value" lines.
 * @param array  $opts {
 *   @type bool   $send_admin      Default true.
 *   @type bool   $send_customer   Default true (still gated by the
 *                                 template's own "customer_enabled" toggle).
 *   @type string $customer_email  Required for the customer copy to send.
 *   @type int    $odoo_lead_id    Adds an "Open in Odoo" button to the admin copy,
 *                                 and — when the customer copy actually sends —
 *                                 logs it to that lead's Odoo chatter via
 *                                 Six_Odoo::log_communication() (see class-odoo.php),
 *                                 so Odoo keeps the full customer-communication
 *                                 history regardless of which part of the site
 *                                 triggered the email.
 *   @type string $dashboard_url   Adds an "Open in Dashboard" button to the admin copy.
 *   @type string $customer_phone  Required for the SMS half to send — independent
 *                                 of $customer_email; a template can be email-only,
 *                                 SMS-only, or both depending on which of these
 *                                 two are provided and which fields the template has.
 *   @type bool   $send_via_odoo   PILOT. When true (and $odoo_lead_id + $customer_partner_id
 *                                 are both set), sends the customer email through Odoo's own
 *                                 mail server instead of wp_mail(), so a reply threads back
 *                                 onto the lead's chatter automatically (see
 *                                 Six_Odoo::send_email_threaded()). Falls back to the normal
 *                                 wp_mail() path on any failure. Off by default — opt in per
 *                                 call site while this is still being validated end-to-end.
 *   @type int    $customer_partner_id  The customer's res.partner ID — required for
 *                                 $send_via_odoo (a bare email string isn't enough for
 *                                 Odoo's message_post()). See Six_Odoo::get_or_create_partner_id().
 * }
 * @return array array('admin'=>array('sent','skipped','error'), 'customer'=>array(...), 'sms'=>array(...))
 */
function six_send_system_email( $type_key, array $merge, array $opts = array() ) {
	$result = array(
		'admin'    => array( 'sent' => false, 'skipped' => true, 'error' => '' ),
		'customer' => array( 'sent' => false, 'skipped' => true, 'error' => '' ),
		'sms'      => array( 'sent' => false, 'skipped' => true, 'error' => '' ),
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

		$to  = six_admin_notify_emails();
		$out = six_wp_mail( $to, $subject, $html, array( 'Content-Type: text/html; charset=UTF-8' ) );
		$result['admin'] = array( 'sent' => $out['sent'], 'skipped' => false, 'error' => $out['error'] );
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

			// PILOT — send through Odoo's own mail server instead of
			// wp_mail() so a reply threads back onto this lead's chatter
			// automatically (Six_Odoo::send_email_threaded()). Opt-in per
			// call site via $opts['send_via_odoo'] — everything else about
			// this function is unchanged for every other caller. Falls
			// straight back to the normal wp_mail() path below on any
			// failure (missing partner ID, Odoo unreachable, etc.) so an
			// Odoo-side hiccup never costs the customer their email.
			$sent_via_odoo = false;
			if ( ! empty( $opts['send_via_odoo'] ) && ! empty( $opts['odoo_lead_id'] ) && ! empty( $opts['customer_partner_id'] ) && class_exists( 'Six_Odoo' ) ) {
				$sent_via_odoo = Six_Odoo::send_email_threaded( intval( $opts['odoo_lead_id'] ), intval( $opts['customer_partner_id'] ), $subject, $body );
			}

			if ( $sent_via_odoo ) {
				// send_email_threaded() already posted the message on the
				// lead itself (that IS the send) — no separate wp_mail() or
				// log_communication() call needed; doing both would send it
				// twice and double the chatter entry.
				$result['customer'] = array( 'sent' => true, 'skipped' => false, 'error' => '' );
			} else {
				$html = six_email_chrome( array(
					'preheader' => wp_strip_all_tags( $body ),
					'heading'   => $subject,
					'body_html' => nl2br( esc_html( $body ) ),
				) );
				$out = six_wp_mail( $customer_email, $subject, $html, array( 'Content-Type: text/html; charset=UTF-8' ) );
				$result['customer'] = array( 'sent' => $out['sent'], 'skipped' => false, 'error' => $out['error'] );

				if ( ! empty( $opts['odoo_lead_id'] ) && class_exists( 'Six_Odoo' ) ) {
					Six_Odoo::log_communication(
						intval( $opts['odoo_lead_id'] ), 'Email',
						$out['sent'] ? 'Sent' : 'Failed',
						$subject,
						$out['sent'] ? "To: {$customer_email}" : "To: {$customer_email}\nError: {$out['error']}"
					);
				}
			}
		}

		// SMS half of the same template — independent of the "Send this email
		// to the customer" toggle above (that checkbox is email-only), gated
		// only on the template actually having SMS text and a phone number
		// being available for this send. Lets a template be SMS-only,
		// email-only, or both, same as a lead-capture form's own SMS field.
		$customer_phone = $opts['customer_phone'] ?? '';
		if ( $tpl['sms_body'] && $customer_phone && class_exists( 'Six_Odoo' ) ) {
			$sms = six_forms_merge_tags( $tpl['sms_body'], $data, $tpl );
			$sms_ok = Six_Odoo::send_sms_twilio( $customer_phone, $sms, intval( $opts['odoo_lead_id'] ?? 0 ) );
			$result['sms'] = array( 'sent' => (bool) $sms_ok, 'skipped' => false, 'error' => $sms_ok ? '' : 'Twilio send failed — see error log.' );
		} elseif ( $tpl['sms_body'] ) {
			$result['sms'] = array( 'sent' => false, 'skipped' => true, 'error' => 'No phone number was provided for this send.' );
		} else {
			$result['sms'] = array( 'sent' => false, 'skipped' => true, 'error' => '' );
		}
	}

	return $result;
}
