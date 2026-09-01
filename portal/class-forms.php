<?php
/**
 * 6ix Portal — Forms system (core).
 *
 * A full replacement for the marketing site's old Ninja-Forms-swappable
 * clones: every lead-capture form is now a `six_form` post (WP Admin →
 * 6ix Portal → Forms) with its fields, labels, step layout, confirmation
 * emails, and success redirect all editable — no plugin, no code change
 * needed to adjust a form. Every submission is logged to a dedicated table
 * (six_form_submissions) regardless of whether the notification email
 * sends, so a lead is never silently lost.
 *
 * File map:
 *   class-forms.php        — this file: CPT, DB table, field renderer, shortcode.
 *   class-forms-admin.php  — meta boxes (field builder, emails, redirect),
 *                             the Submissions list + detail screens.
 *   class-forms-submit.php — the AJAX submit handler + logging + email send.
 *   data-forms-seed.php    — one-time seed of the real forms from the
 *                             original 6ixdevelopers.com, migrated in.
 *
 * A form is looked up by its "key" (a short slug, e.g. "eligibility") from
 * three places: six_forms_render( $key ) called directly by a template,
 * the [six_form key="..."] shortcode (usable anywhere on the site), and
 * the AJAX submit handler (which re-loads the form server-side by the
 * hidden form_key field, so the fields/labels/emails a submission is
 * validated and processed against always come from the current saved
 * definition, never from client-supplied data).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ── Custom post type ─────────────────────────────────────────────────── */
add_action( 'init', function () {
	register_post_type( 'six_form', array(
		'labels' => array(
			'name'          => 'Forms',
			'singular_name' => 'Form',
			'add_new_item'  => 'Add Form',
			'edit_item'     => 'Edit Form',
			'new_item'      => 'New Form',
			'view_item'     => 'View Form',
			'search_items'  => 'Search Forms',
			'menu_name'     => 'Forms',
			'all_items'     => 'All Forms',
		),
		'public'        => false,
		'show_ui'       => true,
		'show_in_menu'  => 'six-portal', // attaches as a submenu of the existing 6ix Portal menu
		'supports'      => array( 'title' ),
		'has_archive'   => false,
		'rewrite'       => false,
	) );
}, 9 );

/* ── DB table: every submission, success or failure ───────────────────── */
function six_forms_create_table() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$charset = $wpdb->get_charset_collate();
	$table   = $wpdb->prefix . 'six_form_submissions';
	dbDelta( "CREATE TABLE IF NOT EXISTS {$table} (
		id bigint(20) NOT NULL AUTO_INCREMENT,
		form_id bigint(20) NOT NULL DEFAULT 0,
		form_key varchar(100) NOT NULL DEFAULT '',
		form_title varchar(255) NOT NULL DEFAULT '',
		data longtext,
		status varchar(20) NOT NULL DEFAULT 'success',
		owner_email_status varchar(20) NOT NULL DEFAULT 'skipped',
		owner_email_error text,
		customer_email_status varchar(20) NOT NULL DEFAULT 'skipped',
		customer_email_error text,
		ip varchar(100) DEFAULT '',
		user_agent varchar(500) DEFAULT '',
		source_url varchar(500) DEFAULT '',
		lead_status varchar(20) NOT NULL DEFAULT 'new',
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY form_key (form_key),
		KEY status (status),
		KEY created_at (created_at)
	) $charset" );
}
// Runs automatically the first time this code is live — not gated behind a
// manual admin visit, since a missing table would silently drop every
// submission until someone noticed. Guarded so it only ever runs once.
add_action( 'wp_loaded', function () {
	if ( get_option( 'six_forms_table_v1' ) ) return;
	update_option( 'six_forms_table_v1', 1 );
	six_forms_create_table();
} );

/* ── Field types the builder + renderer both understand ───────────────── */
function six_forms_field_types() {
	return array(
		'text'               => array( 'label' => 'Text',                  'has_options' => false ),
		'email'              => array( 'label' => 'Email',                 'has_options' => false ),
		'tel'                => array( 'label' => 'Phone',                 'has_options' => false ),
		'number'             => array( 'label' => 'Number',                'has_options' => false ),
		'textarea'           => array( 'label' => 'Paragraph text',        'has_options' => false ),
		'select'             => array( 'label' => 'Dropdown',              'has_options' => true ),
		'select_placeholder' => array( 'label' => 'Dropdown (no blank labelled option, just a placeholder)', 'has_options' => true ),
		'checkbox'           => array( 'label' => 'Single checkbox',       'has_options' => false ),
		'checkbox_group'     => array( 'label' => 'Checkbox group',        'has_options' => true ),
	);
}

/* ── Lookup: by key (string) or post ID (int/numeric string) ──────────── */
function six_forms_get( $key_or_id ) {
	if ( is_numeric( $key_or_id ) ) {
		$post = get_post( (int) $key_or_id );
	} else {
		$posts = get_posts( array(
			'post_type'   => 'six_form',
			'meta_key'    => 'six_form_key',
			'meta_value'  => (string) $key_or_id,
			'numberposts' => 1,
			'post_status' => 'publish',
		) );
		$post = $posts ? $posts[0] : null;
	}
	if ( ! $post || $post->post_type !== 'six_form' || $post->post_status !== 'publish' ) return null;

	$id     = $post->ID;
	$fields = json_decode( (string) get_post_meta( $id, 'six_form_fields_json', true ), true );
	if ( ! is_array( $fields ) ) $fields = array();

	return array(
		'id'                   => $id,
		'key'                  => get_post_meta( $id, 'six_form_key', true ) ?: $post->post_name,
		'title'                => $post->post_title,
		'heading'              => get_post_meta( $id, 'six_form_heading', true ),
		'sub'                  => get_post_meta( $id, 'six_form_sub', true ),
		'submit_label'         => get_post_meta( $id, 'six_form_submit_label', true ) ?: 'Submit',
		'fields'               => $fields,
		'redirect_url'         => get_post_meta( $id, 'six_form_redirect_url', true ),
		'owner_subject'        => get_post_meta( $id, 'six_form_owner_subject', true ),
		'owner_body'           => get_post_meta( $id, 'six_form_owner_body', true ),
		'customer_enabled'     => (bool) get_post_meta( $id, 'six_form_customer_enabled', true ),
		'customer_email_field' => get_post_meta( $id, 'six_form_customer_email_field', true ),
		'customer_subject'     => get_post_meta( $id, 'six_form_customer_subject', true ),
		'customer_body'        => get_post_meta( $id, 'six_form_customer_body', true ),
	);
}

/** A simple server-generated math captcha (mirrors the original site's calVal check — no external CAPTCHA service/plugin needed). */
function six_forms_captcha() {
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

/** Renders one field per its 'type' — the single source of truth the front-end and the (future) live-preview share. */
function six_forms_render_field( $f ) {
	$type  = $f['type'] ?? 'text';
	$key   = $f['key'] ?? '';
	$label = $f['label'] ?? '';
	$req   = ! empty( $f['required'] );
	$ph    = $f['placeholder'] ?? '';
	$opts  = array_filter( (array) ( $f['options'] ?? array() ), fn( $o ) => trim( (string) $o ) !== '' );

	switch ( $type ) {
	case 'textarea':
		printf(
			'<div class="mk-field mk-field-full"><label>%s%s</label><textarea name="%s" rows="4" placeholder="%s"%s></textarea></div>',
			esc_html( $label ), $req ? ' <span class="mk-req">*</span>' : '', esc_attr( $key ), esc_attr( $ph ), $req ? ' required' : ''
		);
		break;
	case 'select':
		echo '<div class="mk-field"><label>' . esc_html( $label ) . ( $req ? ' <span class="mk-req">*</span>' : '' ) . '</label>';
		echo '<select name="' . esc_attr( $key ) . '"' . ( $req ? ' required' : '' ) . '><option value="">Please select…</option>';
		foreach ( $opts as $o ) echo '<option value="' . esc_attr( $o ) . '">' . esc_html( $o ) . '</option>';
		echo '</select></div>';
		break;
	case 'select_placeholder':
		echo '<div class="mk-field mk-field-full"><select name="' . esc_attr( $key ) . '">';
		echo '<option disabled selected value="">' . esc_html( $ph ?: $label ) . '</option>';
		foreach ( $opts as $o ) echo '<option value="' . esc_attr( $o ) . '">' . esc_html( $o ) . '</option>';
		echo '</select></div>';
		break;
	case 'checkbox':
		echo '<div class="mk-field mk-field-full mk-field-check">';
		echo '<label class="mk-check"><input type="checkbox" name="' . esc_attr( $key ) . '" value="1"> ' . esc_html( $label ) . '</label>';
		echo '</div>';
		break;
	case 'checkbox_group':
		echo '<div class="mk-field mk-field-full mk-field-checkgroup">';
		if ( $label ) echo '<label>' . esc_html( $label ) . '</label>';
		echo '<div class="mk-check-grid">';
		foreach ( $opts as $o ) echo '<label class="mk-check"><input type="checkbox" name="' . esc_attr( $key ) . '[]" value="' . esc_attr( $o ) . '"> ' . esc_html( $o ) . '</label>';
		echo '</div></div>';
		break;
	default: // text, email, tel, number
		printf(
			'<div class="mk-field"><label>%s%s</label><input type="%s" name="%s" placeholder="%s"%s></div>',
			esc_html( $label ), $req ? ' <span class="mk-req">*</span>' : '',
			esc_attr( $type ), esc_attr( $key ), esc_attr( $ph ), $req ? ' required' : ''
		);
	}
}

/**
 * Renders a complete form by key or post ID. Fields are grouped by their
 * 'step' number (in first-appearance order) — a form where every field
 * shares one step renders as a normal single-page form; more than one
 * distinct step number makes it a real multi-step form automatically
 * (Previous/Next + per-step validation, handled by marketing.js), exactly
 * matching how the original site's Eligibility/Audit forms behaved.
 */
function six_forms_render( $key_or_id ) {
	$form = six_forms_get( $key_or_id );
	if ( ! $form ) return;
	six_forms_enqueue_assets();

	$steps = array();
	foreach ( $form['fields'] as $f ) {
		$s = isset( $f['step'] ) ? max( 1, intval( $f['step'] ) ) : 1;
		$steps[ $s ][] = $f;
	}
	ksort( $steps );
	$steps    = array_values( $steps );
	$is_multi = count( $steps ) > 1;
	$html_id  = 'six-form-' . sanitize_html_class( $form['key'] ?: 'form' );
	?>
	<div class="mk-formwrap" id="<?php echo esc_attr( $html_id ); ?>-wrap">
		<?php if ( $form['heading'] ) : ?><h3 class="mk-form-title"><?php echo esc_html( $form['heading'] ); ?></h3><?php endif; ?>
		<?php if ( $form['sub'] ) : ?><p class="mk-form-sub"><?php echo esc_html( $form['sub'] ); ?></p><?php endif; ?>
		<form class="mk-form" id="<?php echo esc_attr( $html_id ); ?>" method="post" action="#" novalidate data-mk-ajax>
			<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'six_forms_submit' ) ); ?>">
			<input type="hidden" name="form_key" value="<?php echo esc_attr( $form['key'] ); ?>">
			<input type="hidden" name="source_url" value="<?php echo esc_url( isset( $_SERVER['REQUEST_URI'] ) ? home_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '' ); ?>">
			<?php foreach ( $steps as $i => $fields ) :
				$is_last = ( $i === count( $steps ) - 1 ); ?>
			<section class="mk-form-step<?php echo $i === 0 ? ' mk-form-step-active' : ''; ?>">
				<div class="mk-form-grid">
				<?php foreach ( $fields as $f ) six_forms_render_field( $f ); ?>
				</div>
				<?php if ( $is_last ) : ?>
				<?php echo six_forms_captcha(); ?>
				<div class="mk-form-actions">
					<button type="submit" class="mk-btn mk-btn-primary mk-btn-lg mk-form-submit"><?php echo esc_html( $form['submit_label'] ); ?></button>
					<p class="mk-form-note">By submitting, you agree to be contacted by 6ix Developers about your enquiry.</p>
				</div>
				<?php endif; ?>
			</section>
			<?php endforeach; ?>
			<?php if ( $is_multi ) : ?>
			<div class="mk-form-stepnav">
				<button type="button" class="mk-btn mk-btn-ghost mk-step-prev" disabled>Previous</button>
				<button type="button" class="mk-btn mk-btn-primary mk-step-next">Next</button>
			</div>
			<?php endif; ?>
		</form>
	</div>
	<?php
}

/* ── [six_form key="eligibility"] / [six_form id="123"] ────────────────── */
add_shortcode( 'six_form', function ( $atts ) {
	$atts = shortcode_atts( array( 'key' => '', 'id' => '' ), $atts, 'six_form' );
	ob_start();
	if ( $atts['id'] !== '' ) six_forms_render( (int) $atts['id'] );
	elseif ( $atts['key'] !== '' ) six_forms_render( $atts['key'] );
	return ob_get_clean();
} );

/**
 * The marketing CSS/JS power the form styling + multi-step/AJAX behaviour
 * (marketing/assets/marketing.css, marketing.js), but are normally only
 * enqueued on recognized "marketing" pages (six_mk_is_marketing_page()) —
 * the [six_form] shortcode needs to work on ANY page, so six_forms_render()
 * enqueues them directly. wp_enqueue_* is idempotent, so this is a no-op on
 * a page that already loaded them the normal way.
 */
function six_forms_enqueue_assets() {
	if ( ! defined( 'SIX_MK_DIR' ) || ! defined( 'SIX_MK_URL' ) ) return;
	$css = SIX_MK_DIR . 'assets/marketing.css';
	wp_enqueue_style( 'six-mk', SIX_MK_URL . 'assets/marketing.css', array(), file_exists( $css ) ? filemtime( $css ) : '1' );
	$js = SIX_MK_DIR . 'assets/marketing.js';
	wp_enqueue_script( 'six-mk', SIX_MK_URL . 'assets/marketing.js', array(), file_exists( $js ) ? filemtime( $js ) : '1', true );
	if ( ! wp_script_is( 'six-mk', 'done' ) ) {
		wp_localize_script( 'six-mk', 'sixMkAjax', array( 'url' => admin_url( 'admin-ajax.php' ) ) );
	}
}
