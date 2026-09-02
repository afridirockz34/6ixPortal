<?php
/**
 * 6ix Portal — Forms system: one-time seed.
 *
 * Creates the real forms from the original 6ixdevelopers.com — verified
 * this session field-by-field against the live site's actual HTML — as
 * six_form posts, so the site has working forms the moment this code goes
 * live with no admin setup required. Idempotent (checked by key) and
 * guarded by its own option, matching marketing/setup.php's pattern.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function six_forms_seed_defaults() {
	return array(
		// ── 1. Google Ads $1800 credit eligibility — 3 steps ────────────
		array(
			'key'          => 'eligibility',
			'title'        => 'Google Ads $1800 Credit Eligibility',
			'heading'      => 'Check Your Eligibility To Get Up To $1800 In Google Ads Credit',
			'sub'          => 'Fill out the form below to apply the offer to your account.',
			'submit_label' => 'Check Eligibility',
			'fields'       => array(
				array( 'key' => 'company1', 'type' => 'text', 'label' => 'Business name', 'required' => true, 'placeholder' => '', 'step' => 1, 'options' => array() ),
				array( 'key' => 'inquiry-typed', 'type' => 'select', 'label' => 'Choose a sign-up offer', 'required' => true, 'placeholder' => '', 'step' => 1, 'options' => array(
					'$600 in ad credit (Spend $600 with Google Ads in the first 60 days to unlock the credit)',
					'$1200 in ad credit (Spend $1800 with Google Ads in the first 60 days to unlock the credit)',
					'$1800 in ad credit (Spend $3600 with Google Ads in the first 60 days to unlock the credit)',
				) ),
				array( 'key' => 'account-type', 'type' => 'select', 'label' => 'Do you already have a Google Ads account?', 'required' => true, 'placeholder' => '', 'step' => 1, 'options' => array( 'Yes', 'No' ) ),
				array( 'key' => 'website1', 'type' => 'text', 'label' => 'Provide website URL', 'required' => true, 'placeholder' => 'https://', 'step' => 2, 'options' => array() ),
				array( 'key' => 'email1', 'type' => 'email', 'label' => 'Email address', 'required' => true, 'placeholder' => '', 'step' => 2, 'options' => array() ),
				array( 'key' => 'username1', 'type' => 'text', 'label' => 'Full name', 'required' => true, 'placeholder' => 'Name', 'step' => 3, 'options' => array() ),
				array( 'key' => 'phone1', 'type' => 'tel', 'label' => 'Phone number', 'required' => false, 'placeholder' => 'Phone', 'step' => 3, 'options' => array() ),
			),
			'owner_subject' => 'New {form_title} submission',
		),

		// ── 2. Google Ads audit request — 8 steps ───────────────────────
		array(
			'key'          => 'audit',
			'title'        => 'Google Ads Audit Request',
			'heading'      => 'Google Ads Audit Request',
			'sub'          => 'Tell us about your account and our certified specialists will audit it for you.',
			'submit_label' => 'SEND MESSAGE',
			'fields'       => array(
				array( 'key' => 'audit-inquiry-type', 'type' => 'select', 'label' => 'Are you requesting an account audit for your business or someone else?', 'required' => true, 'placeholder' => '', 'step' => 1, 'options' => array( 'My Business', "Someone Else's Business" ) ),
				array( 'key' => 'aboutbusiness', 'type' => 'text', 'label' => 'Tell us about your Business / Industry', 'required' => true, 'placeholder' => '', 'step' => 1, 'options' => array() ),
				array( 'key' => 'audit-company-name', 'type' => 'text', 'label' => 'Business name', 'required' => true, 'placeholder' => '', 'step' => 2, 'options' => array() ),
				array( 'key' => 'audit-website', 'type' => 'text', 'label' => 'Provide website URL', 'required' => true, 'placeholder' => 'https://', 'step' => 2, 'options' => array() ),
				array( 'key' => 'audit-goals', 'type' => 'text', 'label' => 'Google Ads Marketing Objective', 'required' => true, 'placeholder' => 'E.g. 30 leads/month', 'step' => 3, 'options' => array() ),
				array( 'key' => 'audit-services', 'type' => 'text', 'label' => 'Description of services / products', 'required' => false, 'placeholder' => '', 'step' => 3, 'options' => array() ),
				array( 'key' => 'audit-comp', 'type' => 'text', 'label' => 'Your top online competitors', 'required' => false, 'placeholder' => '', 'step' => 4, 'options' => array() ),
				array( 'key' => 'audit-selling', 'type' => 'text', 'label' => 'Your Unique Selling Proposition', 'required' => false, 'placeholder' => '', 'step' => 4, 'options' => array() ),
				array( 'key' => 'audit-current-leads', 'type' => 'number', 'label' => 'Current number of leads / month', 'required' => false, 'placeholder' => '', 'step' => 5, 'options' => array() ),
				array( 'key' => 'audit-desired-leads', 'type' => 'number', 'label' => 'Desired number of leads / month', 'required' => false, 'placeholder' => '', 'step' => 5, 'options' => array() ),
				array( 'key' => 'audit-monthly-ads', 'type' => 'number', 'label' => 'Your current monthly ad spend', 'required' => true, 'placeholder' => '', 'step' => 6, 'options' => array() ),
				array( 'key' => 'audit-account', 'type' => 'text', 'label' => 'Google Ads account ID', 'required' => false, 'placeholder' => 'We will not send an access request without your permission', 'step' => 6, 'options' => array() ),
				array( 'key' => 'audit-username', 'type' => 'text', 'label' => 'Full name', 'required' => true, 'placeholder' => 'Name', 'step' => 7, 'options' => array() ),
				array( 'key' => 'audit-email', 'type' => 'email', 'label' => 'Email address', 'required' => true, 'placeholder' => 'Email', 'step' => 7, 'options' => array() ),
				array( 'key' => 'audit-phone', 'type' => 'tel', 'label' => 'Phone number', 'required' => false, 'placeholder' => 'Phone', 'step' => 8, 'options' => array() ),
			),
			'owner_subject' => 'New {form_title} submission',
		),

		// ── 3. Website Design — Get Quote Now (flat, single page) ──────
		array(
			'key'          => 'quote-website-design',
			'title'        => 'Get Quote Now — Website Design',
			'heading'      => 'Get Quote Now',
			'sub'          => "Tell us about your website project and we'll send you a quote within one business day.",
			'submit_label' => 'SEND MESSAGE',
			'fields'       => array(
				array( 'key' => 'username', 'type' => 'text', 'label' => 'Full name', 'required' => false, 'placeholder' => 'Name', 'step' => 1, 'options' => array() ),
				array( 'key' => 'email', 'type' => 'email', 'label' => 'Email address', 'required' => false, 'placeholder' => 'Email', 'step' => 1, 'options' => array() ),
				array( 'key' => 'phone', 'type' => 'tel', 'label' => 'Phone number', 'required' => false, 'placeholder' => 'Phone', 'step' => 1, 'options' => array() ),
				array( 'key' => 'website', 'type' => 'text', 'label' => 'Provide website URL', 'required' => false, 'placeholder' => 'Current Website', 'step' => 1, 'options' => array() ),
				array( 'key' => 'package', 'type' => 'select_placeholder', 'label' => 'Website Type', 'required' => false, 'placeholder' => 'Website Type', 'step' => 1, 'options' => array(
					'Starter (1 to 5 Pages)', 'Standard (6 to 12 Pages)', 'Advanced / E-Commerce (13+ Pages)',
				) ),
				array( 'key' => 'textarea', 'type' => 'textarea', 'label' => 'Additional information', 'required' => false, 'placeholder' => 'Message', 'step' => 1, 'options' => array() ),
				array( 'key' => 'claim-google-ads', 'type' => 'checkbox', 'label' => 'Claim Free Google Ads Setup Valued $1500', 'required' => false, 'placeholder' => '', 'step' => 1, 'options' => array() ),
			),
			'owner_subject' => 'New {form_title} submission',
		),

		// ── 4. SEO — Schedule SEO Call Today (flat, single page) ───────
		array(
			'key'          => 'quote-seo',
			'title'        => 'Schedule SEO Call Today',
			'heading'      => 'Schedule SEO Call Today',
			'sub'          => 'Book a call with our SEO team and start seeing results in 30 days.',
			'submit_label' => 'SEND MESSAGE',
			'fields'       => array(
				array( 'key' => 'username', 'type' => 'text', 'label' => 'Full name', 'required' => false, 'placeholder' => 'Name', 'step' => 1, 'options' => array() ),
				array( 'key' => 'email', 'type' => 'email', 'label' => 'Email address', 'required' => false, 'placeholder' => 'Email', 'step' => 1, 'options' => array() ),
				array( 'key' => 'phone', 'type' => 'tel', 'label' => 'Phone number', 'required' => false, 'placeholder' => 'Phone', 'step' => 1, 'options' => array() ),
				array( 'key' => 'company', 'type' => 'text', 'label' => 'Business name', 'required' => false, 'placeholder' => 'Company', 'step' => 1, 'options' => array() ),
				array( 'key' => 'website', 'type' => 'text', 'label' => 'Provide website URL', 'required' => false, 'placeholder' => 'Current Website', 'step' => 1, 'options' => array() ),
				array( 'key' => 'keywords', 'type' => 'text', 'label' => 'Keywords', 'required' => false, 'placeholder' => 'Enter keywords separated by comma', 'step' => 1, 'options' => array() ),
				array( 'key' => 'textarea', 'type' => 'textarea', 'label' => 'Additional information', 'required' => false, 'placeholder' => 'Message', 'step' => 1, 'options' => array() ),
			),
			'owner_subject' => 'New {form_title} submission',
		),

		// ── 5. Social Media — Get Quote Now (flat, single page) ────────
		array(
			'key'          => 'quote-social-media',
			'title'        => 'Get Quote Now — Social Media',
			'heading'      => 'Get Quote Now',
			'sub'          => 'Tell us about your brand and our social media team will put a plan together for you.',
			'submit_label' => 'SEND MESSAGE',
			'fields'       => array(
				array( 'key' => 'username', 'type' => 'text', 'label' => 'Full name', 'required' => false, 'placeholder' => 'Name', 'step' => 1, 'options' => array() ),
				array( 'key' => 'email', 'type' => 'email', 'label' => 'Email address', 'required' => false, 'placeholder' => 'Email', 'step' => 1, 'options' => array() ),
				array( 'key' => 'phone', 'type' => 'tel', 'label' => 'Phone number', 'required' => false, 'placeholder' => 'Phone', 'step' => 1, 'options' => array() ),
				array( 'key' => 'company', 'type' => 'text', 'label' => 'Business name', 'required' => false, 'placeholder' => 'Company', 'step' => 1, 'options' => array() ),
				array( 'key' => 'website', 'type' => 'text', 'label' => 'Provide website URL', 'required' => false, 'placeholder' => 'Current Website', 'step' => 1, 'options' => array() ),
				array( 'key' => 'chk', 'type' => 'checkbox_group', 'label' => 'Social Media Inquiry', 'required' => false, 'placeholder' => '', 'step' => 1, 'options' => array(
					'Social Media Management', 'Social Media Paid Advertising', 'Social Media Organic Engagement', 'Social Media Brand Awareness',
				) ),
				array( 'key' => 'textarea', 'type' => 'textarea', 'label' => 'Additional information', 'required' => false, 'placeholder' => 'Message', 'step' => 1, 'options' => array() ),
			),
			'owner_subject' => 'New {form_title} submission',
		),

		// ── 6. Google Ads — Book Your Consultation — 4 steps ────────────
		// No live version of this one exists on the original site anymore
		// (its markup is dead/commented-out there), but it's real code that
		// was once live, so it keeps the same 4-step shape.
		array(
			'key'          => 'consultation-form',
			'title'        => 'Book Your Google Ads Consultation',
			'heading'      => 'Book Your Google Ads Consultation',
			'sub'          => 'Tell us about your business and a Google Ads specialist will reach out to you.',
			'submit_label' => 'Book My Consultation',
			'fields'       => array(
				array( 'key' => 'inquiry-type', 'type' => 'select', 'label' => 'Google Ads Marketing Objective', 'required' => true, 'placeholder' => '', 'step' => 1, 'options' => array(
					'More qualified leads', 'More phone calls', 'More sales / bookings', 'More website traffic', 'Not sure yet',
				) ),
				array( 'key' => 'website', 'type' => 'text', 'label' => 'Provide website URL', 'required' => false, 'placeholder' => 'Current website', 'step' => 1, 'options' => array() ),
				array( 'key' => 'company', 'type' => 'text', 'label' => 'Business name', 'required' => true, 'placeholder' => '', 'step' => 2, 'options' => array() ),
				array( 'key' => 'username', 'type' => 'text', 'label' => 'Full name', 'required' => true, 'placeholder' => '', 'step' => 2, 'options' => array() ),
				array( 'key' => 'email', 'type' => 'email', 'label' => 'Email address', 'required' => true, 'placeholder' => '', 'step' => 3, 'options' => array() ),
				array( 'key' => 'phone', 'type' => 'tel', 'label' => 'Phone number', 'required' => false, 'placeholder' => '', 'step' => 3, 'options' => array() ),
				array( 'key' => 'textarea', 'type' => 'textarea', 'label' => 'Additional information', 'required' => false, 'placeholder' => 'Message', 'step' => 4, 'options' => array() ),
			),
			'owner_subject' => 'New {form_title} submission',
		),

		// ── 7. Contact — Book a Call (flat, single page) ───────────────
		array(
			'key'          => 'contact',
			'title'        => 'Contact Form — Book a Call',
			'heading'      => 'Book a Call',
			'sub'          => '',
			'submit_label' => 'SEND MESSAGE',
			'fields'       => array(
				array( 'key' => 'username', 'type' => 'text', 'label' => 'Full name', 'required' => false, 'placeholder' => 'Name', 'step' => 1, 'options' => array() ),
				array( 'key' => 'email', 'type' => 'email', 'label' => 'Email address', 'required' => false, 'placeholder' => 'Email', 'step' => 1, 'options' => array() ),
				array( 'key' => 'phone', 'type' => 'tel', 'label' => 'Phone number', 'required' => false, 'placeholder' => 'Phone', 'step' => 1, 'options' => array() ),
				array( 'key' => 'company', 'type' => 'text', 'label' => 'Business name', 'required' => false, 'placeholder' => 'Company', 'step' => 1, 'options' => array() ),
				array( 'key' => 'website', 'type' => 'text', 'label' => 'Provide website URL', 'required' => false, 'placeholder' => 'Current Website', 'step' => 1, 'options' => array() ),
				array( 'key' => 'textarea', 'type' => 'textarea', 'label' => 'How can we help?', 'required' => false, 'placeholder' => 'Message', 'step' => 1, 'options' => array() ),
			),
			'owner_subject' => 'New {form_title} submission',
		),
	);
}

add_action( 'wp_loaded', function () {
	if ( get_option( 'six_forms_seeded_v1' ) ) return;
	update_option( 'six_forms_seeded_v1', 1 );

	foreach ( six_forms_seed_defaults() as $f ) {
		// Already seeded? (defensive extra check on top of the option guard above)
		if ( get_posts( array( 'post_type' => 'six_form', 'meta_key' => 'six_form_key', 'meta_value' => $f['key'], 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ) ) ) continue;

		$post_id = wp_insert_post( array(
			'post_title'  => $f['title'],
			'post_type'   => 'six_form',
			'post_status' => 'publish',
			'post_name'   => $f['key'],
		) );
		if ( ! $post_id || is_wp_error( $post_id ) ) continue;

		update_post_meta( $post_id, 'six_form_key', $f['key'] );
		update_post_meta( $post_id, 'six_form_heading', $f['heading'] );
		update_post_meta( $post_id, 'six_form_sub', $f['sub'] );
		update_post_meta( $post_id, 'six_form_submit_label', $f['submit_label'] );
		update_post_meta( $post_id, 'six_form_fields_json', wp_json_encode( $f['fields'] ) );
		update_post_meta( $post_id, 'six_form_owner_subject', $f['owner_subject'] );
		// One intro sentence — the submitted fields render as their own clean
		// table below it (see six_forms_handle_submit()), so the body itself
		// stays a short line rather than duplicating {all_fields} as text.
		update_post_meta( $post_id, 'six_form_owner_body', 'A new {form_title} submission was just received on the website — see the details below.' );
		update_post_meta( $post_id, 'six_form_customer_enabled', 0 );
		update_post_meta( $post_id, 'six_form_customer_subject', 'Thanks for reaching out!' );
		update_post_meta( $post_id, 'six_form_customer_body', "Thanks — we've received your submission and will be in touch shortly.\n\nThe 6ix Developers team" );
	}
}, 20 );

/**
 * System-generated notification templates — NOT visitor-facing forms (no
 * shortcode, no fields, never rendered by six_forms_render()). Reuses the
 * exact same six_form post type + Emails meta box as the 7 real lead forms
 * above, purely so every email template in the system — form or event —
 * lives in one editable list with one familiar editor, per the "under all
 * forms admin dashboard have all the forms for system forms/notifications"
 * request. Distinguished by six_form_is_system=1 (checked by
 * class-forms-admin.php to hide the irrelevant Fields/Settings UI and by
 * the "Type" admin-list column).
 *
 * Sent via six_send_system_email() in class-system-emails.php, which merge-
 * tags {snake_case_key} placeholders the same way form submissions do —
 * see each event's call site (class-odoo.php, ajax-onboarding.php,
 * ajax-handlers.php) for exactly which merge keys it passes.
 */
function six_system_email_seed_defaults() {
	return array(
		array(
			'key'             => 'onboarding_abandoned',
			'title'           => 'System: Onboarding Abandoned',
			'owner_subject'   => 'Onboarding Abandoned — {client_name} (stopped at {stopped_at_step})',
			'owner_body'      => "{client_name} started onboarding but didn't finish.\n\n{all_fields}\n\nOur automated nurture email and SMS have already gone out — this is for visibility. Reach out personally if this looks like a high-value lead.",
			'customer_enabled'=> 0, // The existing automated nurture email/SMS already covers the customer for this event — this template is admin-only unless enabled.
			'customer_subject'=> "Let's finish setting up your account",
			'customer_body'   => "Hi {client_name},\n\nWe noticed you didn't finish onboarding with 6ix Developers. Pick up right where you left off whenever you're ready — it only takes a couple of minutes.\n\nQuestions in the meantime? Just reply to this email.\n\nThe 6ix Developers team",
		),
		array(
			'key'             => 'onboarding_completed',
			'title'           => 'System: Onboarding Completed',
			'owner_subject'   => 'Onboarding Completed — {client_name}',
			'owner_body'      => "{client_name} finished onboarding and is now a client.\n\n{all_fields}",
			'customer_enabled'=> 0, // The account-creation email (with login details) already confirms this to the customer — enable here only if you want a SEPARATE welcome email in addition to it.
			'customer_subject'=> 'Welcome to 6ix Developers — you\'re all set!',
			'customer_body'   => "Hi {client_name},\n\nThanks for completing your onboarding with 6ix Developers! Your services ({services}) are now being set up, and your advisor will be in touch shortly to get started.\n\nThe 6ix Developers team",
		),
		array(
			'key'             => 'budget_change',
			'title'           => 'System: Budget Change',
			'owner_subject'   => 'Budget Change Request — {client_name} ({service_name})',
			'owner_body'      => "{client_name} requested a budget change.\n\n{all_fields}",
			'customer_enabled'=> 1,
			'customer_subject'=> 'Your {service_name} budget has been updated',
			'customer_body'   => "Hi {client_name},\n\nYour monthly budget for {service_name} has been updated to \${new_budget}/mo, effective now.\n\nQuestions about this change? Just reply to this email or reach out to your advisor.\n\nThe 6ix Developers team",
		),
		array(
			'key'             => 'service_added',
			'title'           => 'System: New Service Requested',
			'owner_subject'   => 'New Service Request — {client_name} ({service_name})',
			'owner_body'      => "{client_name} requested a new service.\n\n{all_fields}",
			'customer_enabled'=> 1,
			'customer_subject'=> "We've received your request for {service_name}",
			'customer_body'   => "Hi {client_name},\n\nThanks for requesting {service_name} — we've received it and your advisor will review it shortly. We'll follow up as soon as it's ready to go.\n\nThe 6ix Developers team",
		),
		array(
			'key'             => 'service_activated',
			'title'           => 'System: Service Activated',
			'owner_subject'   => 'Service Activated — {client_name} ({service_name})',
			'owner_body'      => "{service_name} was activated for {client_name}.\n\n{all_fields}",
			'customer_enabled'=> 1,
			'customer_subject'=> 'Your {service_name} is now active!',
			'customer_body'   => "Hi {client_name},\n\nGreat news — {service_name} is now active on your account at \${budget}/mo. Your advisor will be in touch with next steps.\n\nYou can track progress anytime from your dashboard.\n\nThe 6ix Developers team",
		),
	);
}

add_action( 'wp_loaded', function () {
	if ( get_option( 'six_system_emails_seeded_v1' ) ) return;
	update_option( 'six_system_emails_seeded_v1', 1 );

	foreach ( six_system_email_seed_defaults() as $f ) {
		if ( get_posts( array( 'post_type' => 'six_form', 'meta_key' => 'six_form_key', 'meta_value' => $f['key'], 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ) ) ) continue;

		$post_id = wp_insert_post( array(
			'post_title'  => $f['title'],
			'post_type'   => 'six_form',
			'post_status' => 'publish',
			'post_name'   => $f['key'],
		) );
		if ( ! $post_id || is_wp_error( $post_id ) ) continue;

		update_post_meta( $post_id, 'six_form_key', $f['key'] );
		update_post_meta( $post_id, 'six_form_is_system', 1 );
		update_post_meta( $post_id, 'six_form_fields_json', wp_json_encode( array() ) );
		update_post_meta( $post_id, 'six_form_owner_subject', $f['owner_subject'] );
		update_post_meta( $post_id, 'six_form_owner_body', $f['owner_body'] );
		update_post_meta( $post_id, 'six_form_customer_enabled', $f['customer_enabled'] );
		update_post_meta( $post_id, 'six_form_customer_subject', $f['customer_subject'] );
		update_post_meta( $post_id, 'six_form_customer_body', $f['customer_body'] );
	}
}, 21 );

/**
 * One-time upgrade for the 7 real lead-capture forms above, now that emails
 * are branded (see class-email-chrome.php):
 *   1. Switch on the customer confirmation email — previously defaulted OFF.
 *   2. Replace a still-default "{all_fields}" owner body with a proper intro
 *      sentence, since the submitted fields now render as their own table
 *      below it (see six_forms_handle_submit()) rather than as inline text.
 * Both only touch a form still at its untouched original default, so a form
 * an admin already customized in its Emails box is never overridden.
 */
add_action( 'wp_loaded', function () {
	if ( get_option( 'six_forms_customer_email_default_on_v1' ) ) return;
	update_option( 'six_forms_customer_email_default_on_v1', 1 );

	foreach ( six_forms_seed_defaults() as $f ) {
		$posts = get_posts( array( 'post_type' => 'six_form', 'meta_key' => 'six_form_key', 'meta_value' => $f['key'], 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ) );
		if ( ! $posts ) continue;
		$post_id = $posts[0];
		if ( ! get_post_meta( $post_id, 'six_form_customer_enabled', true ) ) {
			update_post_meta( $post_id, 'six_form_customer_enabled', 1 );
		}
		if ( trim( (string) get_post_meta( $post_id, 'six_form_owner_body', true ) ) === '{all_fields}' ) {
			update_post_meta( $post_id, 'six_form_owner_body', 'A new {form_title} submission was just received on the website — see the details below.' );
		}
	}
}, 22 );
