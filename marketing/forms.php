<?php
/**
 * 6ix Developers — marketing lead-capture forms.
 *
 * Faithful clones of the original 6ixdevelopers.com forms (same fields and
 * labels), restyled in the marketing design system. Rendered by mk_form().
 *
 * SWAP-READY: each form first checks for an override option named
 * "mk_ninja_<key>" (set in WP Admin → 6ix Site, or via the mk_opt filter).
 * When you set up Ninja Forms + WP Mail SMTP (Gmail API), paste the Ninja
 * Forms shortcode — e.g. [ninja_form id="3"] — into that option and it will
 * render in place of the built-in form. No template changes needed.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Render a marketing form by key.
 *
 * @param string $key  One of: eligibility, audit, calc, quote, contact.
 * @param array  $args Optional overrides (heading, sub, goal_options, etc.).
 */
function mk_form( $key, $args = array() ) {
	// 1) Ninja Forms (or any shortcode) override — set once WP mail is wired.
	$override = mk_opt( 'ninja_' . $key, '' );
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

/** Shared open/close wrappers so every form looks consistent. */
function mk_form_open( $id, $heading, $sub = '', $submit = 'Submit' ) {
	ob_start(); ?>
	<div class="mk-formwrap mk-card mk-card-accent" id="<?php echo esc_attr( $id ); ?>-wrap">
		<?php if ( $heading ) : ?><h3 class="mk-form-title"><?php echo esc_html( $heading ); ?></h3><?php endif; ?>
		<?php if ( $sub ) : ?><p class="mk-form-sub"><?php echo esc_html( $sub ); ?></p><?php endif; ?>
		<form class="mk-form" id="<?php echo esc_attr( $id ); ?>" method="post" action="#" novalidate>
	<?php return ob_get_clean();
}
function mk_form_close( $submit = 'Submit' ) {
	ob_start(); ?>
			<?php echo mk_form_captcha(); ?>
			<button type="submit" class="mk-btn mk-btn-primary mk-btn-lg mk-form-submit"><?php echo esc_html( $submit ); ?></button>
			<p class="mk-form-note">By submitting, you agree to be contacted by 6ix Developers about your enquiry.</p>
		</form>
	</div>
	<?php return ob_get_clean();
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

/* ── 1. Google Ads $1800 credit eligibility ─────────────────────────────── */
function mk_form_eligibility( $args = array() ) {
	echo mk_form_open( 'eligibility-form',
		$args['heading'] ?? 'Check Your Eligibility To Get Up To $1800 In Google Ads Credit',
		$args['sub'] ?? 'Fill out the form below to apply the offer to your account.' );
	echo '<div class="mk-form-grid">';
	mk_f_text( 'company1', 'Business name', true );
	mk_f_select( 'inquiry-typed', 'Choose a sign-up offer', array( 'Up to $600 credit', 'Up to $1800 credit', 'Up to $3600 credit' ), true );
	mk_f_select( 'account-type', 'Do you already have a Google Ads account?', array( 'Yes', 'No' ), true );
	mk_f_text( 'website1', 'Provide website URL', true, 'https://' );
	mk_f_text( 'email1', 'Email address', true, '', 'email' );
	mk_f_text( 'username1', 'Full name', true, 'Name' );
	mk_f_text( 'phone1', 'Phone number', false, 'Phone', 'tel' );
	echo '</div>';
	echo mk_form_close( 'Claim Now' );
}

/* ── 2. Google Ads audit request ────────────────────────────────────────── */
function mk_form_audit( $args = array() ) {
	echo mk_form_open( 'audit-form',
		$args['heading'] ?? 'Google Ads Audit Request',
		$args['sub'] ?? 'Tell us about your account and our certified specialists will audit it for you.' );
	echo '<div class="mk-form-grid">';
	mk_f_text( 'aboutbusiness', 'Tell us about your Business / Industry', true );
	mk_f_text( 'audit-company-name', 'Business name', true );
	mk_f_text( 'audit-website', 'Provide website URL', true, 'https://' );
	mk_f_text( 'audit-goals', 'Google Ads Marketing Objective', true, 'E.g. 30 leads/month' );
	mk_f_text( 'audit-services', 'Description of services / products' );
	mk_f_text( 'audit-comp', 'Your top online competitors' );
	mk_f_text( 'audit-selling', 'Your Unique Selling Proposition' );
	mk_f_text( 'audit-current-leads', 'Current number of leads / month', false, '', 'number' );
	mk_f_text( 'audit-desired-leads', 'Desired number of leads / month', false, '', 'number' );
	mk_f_text( 'audit-monthly-ads', 'Your current monthly ad spend', true, '', 'number' );
	mk_f_text( 'audit-account', 'Google Ads account ID', false, 'We will not send an access request without your permission' );
	mk_f_text( 'audit-username', 'Full name', true, 'Name' );
	mk_f_text( 'audit-email', 'Email address', true, 'Email', 'email' );
	mk_f_text( 'audit-phone', 'Phone number', false, 'Phone', 'tel' );
	echo '</div>';
	echo mk_form_close( 'Request My Audit' );
}

/* ── 3. Monthly management cost calculator ──────────────────────────────── */
function mk_form_calc( $args = array() ) { ?>
	<div class="mk-formwrap mk-card mk-card-accent" id="calculate-management-wrap">
		<h3 class="mk-form-title"><?php echo esc_html( $args['heading'] ?? 'Find out your monthly management cost' ); ?></h3>
		<div class="mk-calc-row">
			<input type="text" id="mk-calc-field" inputmode="numeric" placeholder="Enter your monthly Google Ads budget">
			<button type="button" class="mk-btn mk-btn-primary" onclick="mkCalcManagement()">Calculate</button>
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

/* ── 4. Quote / consultation (used across service pages) ────────────────── */
function mk_form_quote( $args = array() ) {
	$goals = $args['goal_options'] ?? array( 'Google Ads / PPC', 'SEO', 'Website Design', 'Social Media', 'Not sure yet' );
	echo mk_form_open( $args['id'] ?? 'quote-form',
		$args['heading'] ?? 'Get Your Free Quote',
		$args['sub'] ?? 'Tell us what you need and a specialist will get back to you within one business day.' );
	echo '<div class="mk-form-grid">';
	mk_f_select( 'inquiry-type', $args['goal_label'] ?? 'Choose your marketing goal', $goals, true );
	mk_f_text( 'website', 'Provide website URL', false, 'Current website' );
	mk_f_text( 'company', 'Business name', true );
	mk_f_text( 'username', 'Full name', true );
	mk_f_text( 'email', 'Email address', true, '', 'email' );
	mk_f_text( 'phone', 'Phone number', false, '', 'tel' );
	echo '</div>';
	mk_f_textarea( 'textarea', 'Additional information', false, 'Message' );
	echo mk_form_close( $args['submit'] ?? 'Get My Quote' );
}

/* ── 5. Contact form ────────────────────────────────────────────────────── */
function mk_form_contact( $args = array() ) {
	echo mk_form_open( 'contact-form', $args['heading'] ?? '', $args['sub'] ?? '' );
	echo '<div class="mk-form-grid">';
	mk_f_text( 'username', 'Full name', true );
	mk_f_text( 'email', 'Email address', true, '', 'email' );
	mk_f_text( 'phone', 'Phone number', false, '', 'tel' );
	mk_f_text( 'company', 'Business name', false );
	echo '</div>';
	mk_f_textarea( 'textarea', 'How can we help?', true, 'Your message' );
	echo mk_form_close( 'Send Message' );
}
