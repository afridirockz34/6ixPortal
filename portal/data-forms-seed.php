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
	$owner_body    = "{all_fields}";
	$customer_body = "Thanks — we've received your submission and will be in touch shortly.\n\nThe 6ix Developers team";

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
		update_post_meta( $post_id, 'six_form_owner_body', "{all_fields}" );
		update_post_meta( $post_id, 'six_form_customer_enabled', 0 );
		update_post_meta( $post_id, 'six_form_customer_subject', 'Thanks for reaching out!' );
		update_post_meta( $post_id, 'six_form_customer_body', "Thanks — we've received your submission and will be in touch shortly.\n\nThe 6ix Developers team" );
	}
}, 20 );
