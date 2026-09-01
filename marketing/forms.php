<?php
/**
 * 6ix Developers — marketing lead-capture forms.
 *
 * Faithful clones of the original 6ixdevelopers.com forms — same fields,
 * labels, step structure (Eligibility and Audit are genuine multi-step
 * forms, matching the original's r1/r2/r3-style sliders exactly — see
 * mk_step_open()/mk_step_close() below and the multi-step handler in
 * marketing.js), restyled in the marketing design system. Rendered by
 * mk_form(). Every form submits via AJAX to
 * six_mk_handle_form_submit() (marketing/form-handler.php), which emails
 * the submission through wp_mail() — no forms plugin is required for these
 * to actually work.
 *
 * SWAP-READY (optional, off by default): each form first checks for an
 * override option named "ninja_<key>" (WP Admin → 6ix Portal → Website
 * Forms, or mk_update_opt()). Paste a shortcode — Ninja Forms, Contact
 * Form 7, anything — into that option and it renders in place of the
 * built-in form. Only use this for a form you deliberately want replaced;
 * most plugins (Ninja Forms included) don't support multi-step without a
 * separate paid add-on, so swapping Eligibility/Audit loses that behaviour.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Render a marketing form by key.
 *
 * @param string $key  One of: eligibility, audit, calc, quote, contact.
 * @param array  $args Optional overrides (heading, sub, goal_options, etc.).
 *                      $args['id'], when set, also becomes the override
 *                      lookup key (see below) — used by 'quote', which
 *                      renders with different copy on four different
 *                      service pages and needs each to be independently
 *                      swappable rather than all four collapsing onto one
 *                      shared "quote" override.
 */
function mk_form( $key, $args = array() ) {
	// 1) Ninja Forms (or any shortcode) override — set once WP mail is wired.
	// Looked up by $args['id'] when the caller passed one (e.g. each service
	// page's distinct quote-form variant), else by the plain $key — see
	// marketing/ninja-forms.php, which provisions the real Ninja Forms and
	// sets these automatically via mk_update_opt( 'ninja_' . $override_key, … ).
	$override_key = $args['id'] ?? $key;
	$override      = mk_opt( 'ninja_' . $override_key, '' );
	if ( $override ) {
		echo '<div class="mk-form-embed">' . do_shortcode( $override ) . '</div>';
		return;
	}

	// 2) Built-in styled clone.
	$fn = 'mk_form_' . preg_replace( '/[^a-z]/', '', $key );
	if ( function_exists( $fn ) ) { $fn( $args ); }
}

/** A simple server-generated math captcha (mirrors the original calVal check). */
function mk_form_captcha() {
	$a = wp_rand( 2, 9 );
	$b = wp_rand( 2, 9 );
	ob_start(); ?>
	<div class="mk-field mk-field-captcha">
		<label>Security check — what is <?php echo (int) $a; ?> + <?php echo (int) $b; ?>? <span class="mk-req">*</span></label>
		<input type="text" name="calVal" inputmode="numeric" autocomplete="off" required>
		<input type="hidden" name="calSum" value="<?php echo (int) ( $a + $b ); ?>">
	</div>
	<?php return ob_get_clean();
}

/**
 * Shared open/close wrappers so every form looks consistent.
 *
 * Every form submits natively via AJAX (marketing.js, data-mk-ajax) to
 * six_mk_handle_form_submit() (marketing/form-handler.php), which emails the
 * submission through wp_mail() — no forms plugin required for a form to
 * actually work. $title is the human label used in that email's subject
 * line; defaults to $heading.
 */
function mk_form_open( $id, $heading, $sub = '', $title = null ) {
	ob_start(); ?>
	<div class="mk-formwrap" id="<?php echo esc_attr( $id ); ?>-wrap">
		<?php if ( $heading ) : ?><h3 class="mk-form-title"><?php echo esc_html( $heading ); ?></h3><?php endif; ?>
		<?php if ( $sub ) : ?><p class="mk-form-sub"><?php echo esc_html( $sub ); ?></p><?php endif; ?>
		<form class="mk-form" id="<?php echo esc_attr( $id ); ?>" method="post" action="#" novalidate data-mk-ajax>
			<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'six_mk_form' ) ); ?>">
			<input type="hidden" name="form_title" value="<?php echo esc_attr( $title ?? $heading ); ?>">
	<?php return ob_get_clean();
}
/** Renders the fields wrapper for a single (non-stepped) form's fields. */
function mk_form_close( $submit = 'Submit' ) {
	ob_start(); ?>
			<?php echo mk_form_captcha(); ?>
			<?php echo mk_form_submit_block( $submit ); ?>
		</form>
	</div>
	<?php return ob_get_clean();
}
/** Just the submit button + note — used standalone inside the final step of a multi-step form (see mk_step_open()/mk_step_close()), where mk_form_close() would wrongly close the whole form early. */
function mk_form_submit_block( $submit = 'Submit' ) {
	ob_start(); ?>
	<div class="mk-form-actions">
		<button type="submit" class="mk-btn mk-btn-primary mk-btn-lg mk-form-submit"><?php echo esc_html( $submit ); ?></button>
		<p class="mk-form-note">By submitting, you agree to be contacted by 6ix Developers about your enquiry.</p>
	</div>
	<?php return ob_get_clean();
}
/** Closes a multi-step form opened with mk_form_open() + mk_step_open()/mk_step_close() blocks (see mk_form_stepnav() for the Previous/Next bar that goes between the steps and this). */
function mk_form_end() {
	echo '</form></div>';
}

/* ── Multi-step forms ───────────────────────────────────────────────────
   Mirrors the original site's step-by-step Eligibility/Audit forms: each
   mk_step_open()/mk_step_close() pair is one step (only the first is shown
   until the visitor clicks Next); marketing.js validates the active step's
   fields (native HTML5 validity) before advancing. The real submit button
   lives inside the LAST step's content (via mk_form_submit_block()), same
   as the original — there's no separate "submit step" to fake. */
function mk_step_open( $active = false ) {
	echo '<section class="mk-form-step' . ( $active ? ' mk-form-step-active' : '' ) . '"><div class="mk-form-grid">';
}
/**
 * @param bool $grid_open Pass false when the grid was already closed inside
 *                        this step (e.g. to add the captcha/submit block,
 *                        which must NOT be a 2-column grid item) so this
 *                        only closes the <section>.
 */
function mk_step_close( $grid_open = true ) {
	if ( $grid_open ) echo '</div>';
	echo '</section>';
}
/** The persistent Previous/Next bar for a multi-step form — sits after every mk_step_*() block, before mk_form_end(). */
function mk_form_stepnav() {
	?>
	<div class="mk-form-stepnav">
		<button type="button" class="mk-btn mk-btn-ghost mk-step-prev" disabled>Previous</button>
		<button type="button" class="mk-btn mk-btn-primary mk-step-next">Next</button>
	</div>
	<?php
}

/* ── Field helpers ──────────────────────────────────────────────────────── */
function mk_f_text( $name, $label, $req = false, $ph = '', $type = 'text' ) {
	printf(
		'<div class="mk-field"><label>%s%s</label><input type="%s" name="%s" placeholder="%s"%s></div>',
		esc_html( $label ), $req ? ' <span class="mk-req">*</span>' : '',
		esc_attr( $type ), esc_attr( $name ), esc_attr( $ph ), $req ? ' required' : ''
	);
}
function mk_f_select( $name, $label, $options, $req = false ) {
	echo '<div class="mk-field"><label>' . esc_html( $label ) . ( $req ? ' <span class="mk-req">*</span>' : '' ) . '</label>';
	echo '<select name="' . esc_attr( $name ) . '"' . ( $req ? ' required' : '' ) . '>';
	echo '<option value="">Please select…</option>';
	foreach ( (array) $options as $o ) { echo '<option value="' . esc_attr( $o ) . '">' . esc_html( $o ) . '</option>'; }
	echo '</select></div>';
}
function mk_f_textarea( $name, $label, $req = false, $ph = '' ) {
	printf(
		'<div class="mk-field mk-field-full"><label>%s%s</label><textarea name="%s" rows="4" placeholder="%s"%s></textarea></div>',
		esc_html( $label ), $req ? ' <span class="mk-req">*</span>' : '', esc_attr( $name ), esc_attr( $ph ), $req ? ' required' : ''
	);
}
/** A <select> whose first option is a disabled placeholder (matches the original site's "Website Type" picker) rather than a labelled field. */
function mk_f_select_placeholder( $name, $placeholder, $options ) {
	echo '<div class="mk-field mk-field-full"><select name="' . esc_attr( $name ) . '">';
	echo '<option disabled selected value="">' . esc_html( $placeholder ) . '</option>';
	foreach ( (array) $options as $value => $label ) {
		$value = is_int( $value ) ? $label : $value;
		echo '<option value="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</option>';
	}
	echo '</select></div>';
}
/** A single standalone checkbox with its own label (e.g. an upsell opt-in). */
function mk_f_checkbox( $name, $label, $value = '1' ) {
	echo '<div class="mk-field mk-field-full mk-field-check">';
	echo '<label class="mk-check"><input type="checkbox" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"> ' . esc_html( $label ) . '</label>';
	echo '</div>';
}
/** A titled group of checkboxes sharing one array field name (e.g. "Social Media Inquiry"). */
function mk_f_checkbox_group( $name, $title, $options ) {
	echo '<div class="mk-field mk-field-full mk-field-checkgroup">';
	if ( $title ) echo '<label>' . esc_html( $title ) . '</label>';
	echo '<div class="mk-check-grid">';
	foreach ( (array) $options as $o ) {
		echo '<label class="mk-check"><input type="checkbox" name="' . esc_attr( $name ) . '[]" value="' . esc_attr( $o ) . '"> ' . esc_html( $o ) . '</label>';
	}
	echo '</div></div>';
}

/* ── 1. Google Ads $1800 credit eligibility ─────────────────────────────
   Three steps, matching the original site's r1/r2/r3 slider form exactly. */
function mk_form_eligibility( $args = array() ) {
	echo mk_form_open( 'eligibility-form',
		$args['heading'] ?? 'Check Your Eligibility To Get Up To $1800 In Google Ads Credit',
		$args['sub'] ?? 'Fill out the form below to apply the offer to your account.',
		'Google Ads $1800 Credit Eligibility' );

	mk_step_open( true );
	mk_f_text( 'company1', 'Business name', true );
	mk_f_select( 'inquiry-typed', 'Choose a sign-up offer', array(
		'$600 in ad credit (Spend $600 with Google Ads in the first 60 days to unlock the credit)',
		'$1200 in ad credit (Spend $1800 with Google Ads in the first 60 days to unlock the credit)',
		'$1800 in ad credit (Spend $3600 with Google Ads in the first 60 days to unlock the credit)',
	), true );
	mk_f_select( 'account-type', 'Do you already have a Google Ads account?', array( 'Yes', 'No' ), true );
	mk_step_close();

	mk_step_open();
	mk_f_text( 'website1', 'Provide website URL', true, 'https://' );
	mk_f_text( 'email1', 'Email address', true, '', 'email' );
	mk_step_close();

	mk_step_open();
	mk_f_text( 'username1', 'Full name', true, 'Name' );
	mk_f_text( 'phone1', 'Phone number', false, 'Phone', 'tel' );
	echo '</div>';
	echo mk_form_captcha();
	echo mk_form_submit_block( 'Check Eligibility' );
	mk_step_close( false );

	mk_form_stepnav();
	mk_form_end();
}

/* ── 2. Google Ads audit request ──────────────────────────────────────────
   Eight steps (two fields each), matching the original site's r1-2..r8-2
   slider form exactly. */
function mk_form_audit( $args = array() ) {
	echo mk_form_open( 'audit-form',
		$args['heading'] ?? 'Google Ads Audit Request',
		$args['sub'] ?? 'Tell us about your account and our certified specialists will audit it for you.' );

	mk_step_open( true );
	mk_f_select( 'audit-inquiry-type', 'Are you requesting an account audit for your business or someone else?', array( 'My Business', "Someone Else's Business" ), true );
	mk_f_text( 'aboutbusiness', 'Tell us about your Business / Industry', true );
	mk_step_close();

	mk_step_open();
	mk_f_text( 'audit-company-name', 'Business name', true );
	mk_f_text( 'audit-website', 'Provide website URL', true, 'https://' );
	mk_step_close();

	mk_step_open();
	mk_f_text( 'audit-goals', 'Google Ads Marketing Objective', true, 'E.g. 30 leads/month' );
	mk_f_text( 'audit-services', 'Description of services / products' );
	mk_step_close();

	mk_step_open();
	mk_f_text( 'audit-comp', 'Your top online competitors' );
	mk_f_text( 'audit-selling', 'Your Unique Selling Proposition' );
	mk_step_close();

	mk_step_open();
	mk_f_text( 'audit-current-leads', 'Current number of leads / month', false, '', 'number' );
	mk_f_text( 'audit-desired-leads', 'Desired number of leads / month', false, '', 'number' );
	mk_step_close();

	mk_step_open();
	mk_f_text( 'audit-monthly-ads', 'Your current monthly ad spend', true, '', 'number' );
	mk_f_text( 'audit-account', 'Google Ads account ID', false, 'We will not send an access request without your permission' );
	mk_step_close();

	mk_step_open();
	mk_f_text( 'audit-username', 'Full name', true, 'Name' );
	mk_f_text( 'audit-email', 'Email address', true, 'Email', 'email' );
	mk_step_close();

	mk_step_open();
	mk_f_text( 'audit-phone', 'Phone number', false, 'Phone', 'tel' );
	echo '</div>';
	echo mk_form_captcha();
	echo mk_form_submit_block( 'SEND MESSAGE' );
	mk_step_close( false );

	mk_form_stepnav();
	mk_form_end();
}

/* ── 3. Monthly management cost calculator ──────────────────────────────── */
function mk_form_calc( $args = array() ) { ?>
	<div class="mk-formwrap mk-card mk-card-accent" id="calculate-management-wrap">
		<h3 class="mk-form-title"><?php echo esc_html( $args['heading'] ?? 'Find out your monthly management cost' ); ?></h3>
		<div class="mk-calc-row">
			<input type="text" id="mk-calc-field" inputmode="numeric" placeholder="Enter your monthly Google Ads budget">
			<button type="button" class="mk-btn mk-btn-primary" onclick="mkCalcManagement()">Calculate Now</button>
		</div>
		<div class="mk-calc-out" id="mk-calc-out"></div>
		<p class="mk-form-note">Our management fee is $799/month or 15% of your monthly Google Ads budget, whichever is greater.</p>
	</div>
	<script>
	function mkCalcManagement(){
		var v=parseFloat((document.getElementById('mk-calc-field').value||'').replace(/[^0-9.]/g,''))||0;
		var fee=Math.max(799, v*0.15);
		var out=document.getElementById('mk-calc-out');
		out.innerHTML = v>0
			? 'Estimated management fee: <strong>$'+fee.toLocaleString(undefined,{maximumFractionDigits:0})+'/month</strong>'
			: 'Enter your monthly budget to see your estimated management fee.';
	}
	</script>
	<?php
}

/* ── 4. Quote / consultation (used across service pages) ─────────────────
   Each service page's quote form on the original site is genuinely a
   different form, not one generic template — Website Design has a
   package picker + a Google Ads upsell checkbox, SEO has a keywords
   field, Social Media has a checkbox group instead of a dropdown, and
   none of their fields are marked required (unlike the Eligibility/Audit
   forms). $args['variant'] picks which of those exact field sets to
   render; anything else (currently just the Google Ads page's
   consultation form, which has no live counterpart on the original site
   to copy) falls back to a generic goal-select form. */
function mk_form_quote( $args = array() ) {
	echo mk_form_open( $args['id'] ?? 'quote-form',
		$args['heading'] ?? 'Get Your Free Quote',
		$args['sub'] ?? 'Tell us what you need and a specialist will get back to you within one business day.' );

	// The Google Ads consultation form is the one variant that's a
	// multi-step form on the (dead-code, but still real) original site —
	// four steps, r1-1..r4-1 — so it's handled separately from the three
	// flat single-page variants below.
	if ( ( $args['variant'] ?? '' ) === '' ) {
		$goals = $args['goal_options'] ?? array( 'Google Ads / PPC', 'SEO', 'Website Design', 'Social Media', 'Not sure yet' );

		mk_step_open( true );
		mk_f_select( 'inquiry-type', $args['goal_label'] ?? 'Choose your marketing goal', $goals, true );
		mk_f_text( 'website', 'Provide website URL', false, 'Current website' );
		mk_step_close();

		mk_step_open();
		mk_f_text( 'company', 'Business name', true );
		mk_f_text( 'username', 'Full name', true );
		mk_step_close();

		mk_step_open();
		mk_f_text( 'email', 'Email address', true, '', 'email' );
		mk_f_text( 'phone', 'Phone number', false, '', 'tel' );
		mk_step_close();

		mk_step_open();
		mk_f_textarea( 'textarea', 'Additional information', false, 'Message' );
		echo '</div>';
		echo mk_form_captcha();
		echo mk_form_submit_block( $args['submit'] ?? 'Get My Quote' );
		mk_step_close( false );

		mk_form_stepnav();
		mk_form_end();
		return;
	}

	echo '<div class="mk-form-grid">';
	switch ( $args['variant'] ) {
	case 'website-design':
		mk_f_text( 'username', 'Full name', false, 'Name' );
		mk_f_text( 'email', 'Email address', false, 'Email', 'email' );
		mk_f_text( 'phone', 'Phone number', false, 'Phone', 'tel' );
		mk_f_text( 'website', 'Provide website URL', false, 'Current Website' );
		mk_f_select_placeholder( 'package', 'Website Type', array(
			'Starter'  => 'Starter (1 to 5 Pages)',
			'Standard' => 'Standard (6 to 12 Pages)',
			'Advanced' => 'Advanced / E-Commerce (13+ Pages)',
		) );
		mk_f_textarea( 'textarea', 'Additional information', false, 'Message' );
		mk_f_checkbox( 'claim-google-ads', 'Claim Free Google Ads Setup Valued $1500' );
		break;

	case 'seo':
		mk_f_text( 'username', 'Full name', false, 'Name' );
		mk_f_text( 'email', 'Email address', false, 'Email', 'email' );
		mk_f_text( 'phone', 'Phone number', false, 'Phone', 'tel' );
		mk_f_text( 'company', 'Business name', false, 'Company' );
		mk_f_text( 'website', 'Provide website URL', false, 'Current Website' );
		mk_f_text( 'keywords', 'Keywords', false, 'Enter keywords separated by comma' );
		mk_f_textarea( 'textarea', 'Additional information', false, 'Message' );
		break;

	case 'social-media':
		mk_f_text( 'username', 'Full name', false, 'Name' );
		mk_f_text( 'email', 'Email address', false, 'Email', 'email' );
		mk_f_text( 'phone', 'Phone number', false, 'Phone', 'tel' );
		mk_f_text( 'company', 'Business name', false, 'Company' );
		mk_f_text( 'website', 'Provide website URL', false, 'Current Website' );
		mk_f_checkbox_group( 'chk', 'Social Media Inquiry', array(
			'Social Media Management', 'Social Media Paid Advertising', 'Social Media Organic Engagement', 'Social Media Brand Awareness',
		) );
		mk_f_textarea( 'textarea', 'Additional information', false, 'Message' );
		break;
	}

	echo '</div>';
	echo mk_form_close( $args['submit'] ?? 'SEND MESSAGE' );
}

/* ── 5. Contact form ────────────────────────────────────────────────────── */
function mk_form_contact( $args = array() ) {
	echo mk_form_open( 'contact-form', $args['heading'] ?? 'Book a Call', $args['sub'] ?? '' );
	echo '<div class="mk-form-grid">';
	mk_f_text( 'username', 'Full name', false, 'Name' );
	mk_f_text( 'email', 'Email address', false, 'Email', 'email' );
	mk_f_text( 'phone', 'Phone number', false, 'Phone', 'tel' );
	mk_f_text( 'company', 'Business name', false, 'Company' );
	mk_f_text( 'website', 'Provide website URL', false, 'Current Website' );
	echo '</div>';
	mk_f_textarea( 'textarea', 'How can we help?', false, 'Message' );
	echo mk_form_close( 'SEND MESSAGE' );
}
