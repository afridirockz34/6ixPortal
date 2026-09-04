<?php
/**
 * 6ix Portal — Forms system: admin UI.
 *
 *   - Meta boxes on the six_form edit screen: Form Settings (key, heading,
 *     copy, submit label, redirect URL, shortcode), a Fields builder
 *     (repeater — add/reorder/remove fields, pick type, mark required, set
 *     which step it belongs to), and Email Notifications (owner + optional
 *     customer confirmation, with merge tags).
 *   - "Form Submissions" screen (WP Admin → 6ix Portal → Form Submissions):
 *     every logged submission, filterable by form/status, with a detail
 *     view showing every submitted field and both emails' send status —
 *     the record that survives even a failed send, per the whole point of
 *     six_forms_log_submission().
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ── Admin assets (field-builder JS/CSS, only on the six_form editor) ──── */
add_action( 'admin_enqueue_scripts', function ( $hook ) {
	$screen = get_current_screen();
	if ( ! $screen || $screen->post_type !== 'six_form' ) return;
	if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) return;
	$css = SIX_PLUGIN_DIR . 'assets/forms-admin.css';
	wp_enqueue_style( 'six-forms-admin', SIX_PLUGIN_URL . 'assets/forms-admin.css', array(), file_exists( $css ) ? filemtime( $css ) : '1' );
	$marketing_admin_css = defined( 'SIX_MK_DIR' ) ? SIX_MK_DIR . 'assets/admin-marketing.css' : '';
	if ( $marketing_admin_css && file_exists( $marketing_admin_css ) ) {
		wp_enqueue_style( 'six-mk-admin', SIX_MK_URL . 'assets/admin-marketing.css', array(), filemtime( $marketing_admin_css ) );
	}
} );

/* ── Meta boxes ──────────────────────────────────────────────────────────── */
// System-generated entries (six_form_is_system=1 — onboarding abandonment,
// budget change, etc.) aren't visitor-facing forms: no shortcode, no field
// builder, no redirect URL. They only need the About box (what fires this
// email, and its merge tags) + the same Email Notifications box every real
// form uses, so their subject/body stay editable in the same familiar place.
add_action( 'add_meta_boxes', function () {
	global $post;
	$is_system = $post && get_post_meta( $post->ID, 'six_form_is_system', true );

	if ( $is_system ) {
		add_meta_box( 'six_form_system_about', 'System-Generated Notification', 'six_forms_mb_system_about', 'six_form', 'normal', 'high' );
	} else {
		add_meta_box( 'six_form_settings', 'Form Settings', 'six_forms_mb_settings', 'six_form', 'normal', 'high' );
		add_meta_box( 'six_form_fields', 'Fields', 'six_forms_mb_fields', 'six_form', 'normal', 'high' );
	}
	add_meta_box( 'six_form_emails', 'Email Notifications', 'six_forms_mb_emails', 'six_form', 'normal', 'default' );
} );

function six_forms_mb_system_about( $post ) {
	wp_nonce_field( 'six_forms_save', 'six_forms_nonce' );
	$key = get_post_meta( $post->ID, 'six_form_key', true ) ?: $post->post_name;
	echo '<p class="six-adm-hint" style="margin-top:0">This is a <strong>system-generated notification</strong> — the app sends it automatically when this event happens (not a form a visitor fills out, so there\'s no shortcode or field builder). Edit its Subject/Body below in the <strong>Email Notifications</strong> box; the admin copy goes to the addresses set under 6ix Portal → Settings → Notifications, and the customer copy (if enabled) goes to whichever customer the event is about.</p>';
	echo '<p class="six-adm-hint"><strong>Event key:</strong> <code>' . esc_html( $key ) . '</code></p>';
	// Preserve the fields the save handler already expects, without exposing
	// a builder UI for them.
	echo '<input type="hidden" name="six_form_fields_json" value="' . esc_attr( wp_json_encode( array() ) ) . '">';
	echo '<input type="hidden" name="six_form_key" value="' . esc_attr( $key ) . '">';
}

function six_forms_field( $key, $label, $val, $hint = '', $ph = '' ) {
	echo '<div class="six-adm-field"><label class="six-adm-field-label">' . esc_html( $label ) . '</label>';
	if ( $hint ) echo '<p class="six-adm-hint" style="margin-top:0">' . esc_html( $hint ) . '</p>';
	echo '<input type="text" name="' . esc_attr( $key ) . '" value="' . esc_attr( $val ) . '" placeholder="' . esc_attr( $ph ) . '"></div>';
}
function six_forms_textarea( $key, $label, $val, $hint = '', $rows = 6 ) {
	echo '<div class="six-adm-field"><label class="six-adm-field-label">' . esc_html( $label ) . '</label>';
	if ( $hint ) echo '<p class="six-adm-hint" style="margin-top:0">' . esc_html( $hint ) . '</p>';
	echo '<textarea name="' . esc_attr( $key ) . '" rows="' . intval( $rows ) . '">' . esc_textarea( $val ) . '</textarea></div>';
}

function six_forms_mb_settings( $post ) {
	wp_nonce_field( 'six_forms_save', 'six_forms_nonce' );
	$g = fn( $k ) => get_post_meta( $post->ID, $k, true );
	$key = $g( 'six_form_key' ) ?: $post->post_name;

	six_forms_field( 'six_form_key', 'Form key', $key,
		'A short, unique, URL-safe slug — how templates and the shortcode refer to this form. Changing it will break any [six_form key="…"] already using the old one.', 'e.g. eligibility' );

	if ( $post->post_status === 'publish' && $key ) {
		echo '<div class="six-adm-field"><label class="six-adm-field-label">Shortcode</label>';
		echo '<p class="six-adm-hint" style="margin-top:0">Paste this anywhere on the site — a page, a post, a widget — to display this exact form there.</p>';
		echo '<input type="text" readonly onclick="this.select()" value=\'[six_form key="' . esc_attr( $key ) . '"]\' style="font-family:monospace;background:#f6f7fb"></div>';
	}

	six_forms_field( 'six_form_heading', 'Heading', $g( 'six_form_heading' ), '', 'e.g. Check Your Eligibility To Get Up To $1800 In Google Ads Credit' );
	six_forms_textarea( 'six_form_sub', 'Subheading', $g( 'six_form_sub' ), '', 3 );
	six_forms_field( 'six_form_submit_label', 'Submit button label', $g( 'six_form_submit_label' ) ?: 'Submit', '', 'e.g. SEND MESSAGE' );
	six_forms_field( 'six_form_redirect_url', 'Success redirect URL', $g( 'six_form_redirect_url' ),
		'Optional. Send the visitor to this URL after a successful submission instead of showing the inline confirmation message — e.g. a "thank you" page.', 'https://…' );
}

function six_forms_mb_fields( $post ) {
	$types = six_forms_field_types();
	$raw   = get_post_meta( $post->ID, 'six_form_fields_json', true );
	$fields = json_decode( (string) $raw, true );
	if ( ! is_array( $fields ) ) $fields = array();
	if ( ! $fields ) $fields = array( array( 'key' => '', 'type' => 'text', 'label' => '', 'required' => false, 'placeholder' => '', 'step' => 1, 'options' => array() ) );
	?>
	<p class="six-adm-hint" style="margin-top:0">Each field's <strong>Step</strong> number groups it into a page of the form. Give every field the same step (e.g. all "1") for a normal single-page form, or use 2, 3, 4… to split it into a multi-step form like the original site's Eligibility/Audit forms — the visitor gets Previous/Next buttons automatically, with each step validated before they can continue.</p>
	<div class="six-forms-builder" id="six-forms-builder">
		<div class="six-forms-rows" id="six-forms-rows">
			<?php foreach ( $fields as $i => $f ) : ?>
			<?php echo six_forms_field_row( $f, $types ); ?>
			<?php endforeach; ?>
		</div>
		<div class="six-forms-row-template" style="display:none" aria-hidden="true">
			<?php echo six_forms_field_row( array( 'key' => '', 'type' => 'text', 'label' => '', 'required' => false, 'placeholder' => '', 'step' => 1, 'options' => array() ), $types ); ?>
		</div>
		<button type="button" class="button six-forms-add-field">+ Add field</button>
		<input type="hidden" name="six_form_fields_json" id="six_form_fields_json" class="six-forms-json" value="<?php echo esc_attr( wp_json_encode( $fields ) ); ?>">
	</div>
	<script>
	(function(){
		var TYPES = <?php echo wp_json_encode( $types ); ?>;
		function syncJSON(){
			var out = [];
			document.querySelectorAll('#six-forms-rows .six-forms-row').forEach(function(row){
				var key = row.querySelector('.sf-key').value.trim();
				if (!key) return;
				var opts = row.querySelector('.sf-options').value.split('\n').map(function(s){ return s.trim(); }).filter(Boolean);
				out.push({
					key: key,
					type: row.querySelector('.sf-type').value,
					label: row.querySelector('.sf-label').value.trim(),
					required: row.querySelector('.sf-required').checked,
					placeholder: row.querySelector('.sf-placeholder').value.trim(),
					step: parseInt(row.querySelector('.sf-step').value, 10) || 1,
					options: opts
				});
			});
			document.getElementById('six_form_fields_json').value = JSON.stringify(out);
		}
		function updateTypeVisibility(row){
			var type = row.querySelector('.sf-type').value;
			var hasOptions = TYPES[type] && TYPES[type].has_options;
			row.querySelector('.sf-options-wrap').style.display = hasOptions ? '' : 'none';
			// A single checkbox uses its Label as the field's own visible
			// text (no separate options), everything else keeps Label as
			// the question/field label.
		}
		function wireRow(row){
			row.querySelectorAll('input, select, textarea').forEach(function(el){
				el.addEventListener('input', syncJSON);
				el.addEventListener('change', function(){ updateTypeVisibility(row); syncJSON(); });
			});
			row.querySelector('.sf-remove').addEventListener('click', function(){ row.remove(); syncJSON(); });
			updateTypeVisibility(row);
		}
		document.querySelectorAll('#six-forms-rows .six-forms-row').forEach(wireRow);
		document.querySelector('.six-forms-add-field').addEventListener('click', function(){
			var tpl = document.querySelector('.six-forms-row-template .six-forms-row');
			var row = tpl.cloneNode(true);
			document.getElementById('six-forms-rows').appendChild(row);
			wireRow(row);
			row.querySelector('.sf-key').focus();
		});
		syncJSON();
		var form = document.getElementById('post');
		if (form) form.addEventListener('submit', syncJSON);
	})();
	</script>
	<?php
}

function six_forms_field_row( $f, $types ) {
	ob_start(); ?>
	<div class="six-forms-row">
		<div class="six-forms-row-grid">
			<label>Field key<input type="text" class="sf-key" value="<?php echo esc_attr( $f['key'] ?? '' ); ?>" placeholder="e.g. company_name"></label>
			<label>Type
				<select class="sf-type">
					<?php foreach ( $types as $tkey => $t ) : ?>
					<option value="<?php echo esc_attr( $tkey ); ?>"<?php selected( $f['type'] ?? 'text', $tkey ); ?>><?php echo esc_html( $t['label'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>Step<input type="number" class="sf-step" min="1" value="<?php echo esc_attr( $f['step'] ?? 1 ); ?>" style="width:70px"></label>
			<label class="six-forms-required"><input type="checkbox" class="sf-required"<?php checked( ! empty( $f['required'] ) ); ?>> Required</label>
		</div>
		<div class="six-forms-row-grid">
			<label>Label / question<input type="text" class="sf-label" value="<?php echo esc_attr( $f['label'] ?? '' ); ?>" placeholder="e.g. Business name"></label>
			<label>Placeholder<input type="text" class="sf-placeholder" value="<?php echo esc_attr( $f['placeholder'] ?? '' ); ?>" placeholder="shown inside an empty field"></label>
		</div>
		<div class="sf-options-wrap">
			<label>Options — one per line<textarea class="sf-options" rows="3" placeholder="Option A&#10;Option B"><?php echo esc_textarea( implode( "\n", (array) ( $f['options'] ?? array() ) ) ); ?></textarea></label>
		</div>
		<button type="button" class="button-link sf-remove">Remove field</button>
	</div>
	<?php return ob_get_clean();
}

function six_forms_mb_emails( $post ) {
	$g = fn( $k ) => get_post_meta( $post->ID, $k, true );
	$is_system = (bool) $g( 'six_form_is_system' );
	?>
	<p class="six-adm-hint" style="margin-top:0">Merge tags: <code>{field_key}</code> for one field's value, <code>{all_fields}</code> for every <?php echo $is_system ? 'piece of event info' : 'submitted field'; ?> as a readable list, <code>{form_title}</code> for this <?php echo $is_system ? "notification's name" : "form's title"; ?>.</p>

	<h4 style="margin:18px 0 6px">To you (the business)</h4>
	<?php
	six_forms_field( 'six_form_owner_subject', 'Subject', $g( 'six_form_owner_subject' ), '', 'e.g. New {form_title} submission' );
	six_forms_textarea( 'six_form_owner_body', 'Body', $g( 'six_form_owner_body' ) ?: '{all_fields}', '', 8 );
	?>
	<h4 style="margin:22px 0 6px">To the customer<?php echo $is_system ? '' : ' (optional confirmation)'; ?></h4>
	<div class="six-adm-field">
		<label><input type="checkbox" name="six_form_customer_enabled" value="1"<?php checked( (bool) $g( 'six_form_customer_enabled' ) ); ?>> Send this email to the customer<?php echo $is_system ? " this event is about" : " who filled out this form"; ?></label>
	</div>
	<?php
	if ( ! $is_system ) {
		six_forms_field( 'six_form_customer_email_field', 'Which field holds their email address', $g( 'six_form_customer_email_field' ),
			'Match this to one of the field keys above (e.g. "email1"). If left blank, the first field the visitor filled in with a valid email address is used.', 'e.g. email1' );
	}
	six_forms_field( 'six_form_customer_subject', 'Subject', $g( 'six_form_customer_subject' ), '', "Thanks for reaching out!" );
	six_forms_textarea( 'six_form_customer_body', 'Body', $g( 'six_form_customer_body' ) ?: "Thanks — we've received your submission and will be in touch shortly.", '', 6 );

	six_forms_textarea( 'six_form_sms_body', 'SMS text (optional)', $g( 'six_form_sms_body' ),
		'Leave blank to skip SMS entirely. Only sent when a phone number is available. Keep it short — this is a text message, not an email.', 3 );
}

/* ── Save ────────────────────────────────────────────────────────────── */
add_action( 'save_post_six_form', function ( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
	if ( ! isset( $_POST['six_forms_nonce'] ) || ! wp_verify_nonce( $_POST['six_forms_nonce'], 'six_forms_save' ) ) return;
	if ( ! current_user_can( 'edit_post', $post_id ) ) return;

	$text_fields = array(
		'six_form_key', 'six_form_heading', 'six_form_submit_label', 'six_form_redirect_url',
		'six_form_owner_subject', 'six_form_customer_email_field', 'six_form_customer_subject',
	);
	foreach ( $text_fields as $k ) {
		if ( isset( $_POST[ $k ] ) ) update_post_meta( $post_id, $k, sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) );
	}
	if ( isset( $_POST['six_form_key'] ) ) {
		$key = sanitize_title( wp_unslash( $_POST['six_form_key'] ) );
		update_post_meta( $post_id, 'six_form_key', $key );
	}
	if ( isset( $_POST['six_form_redirect_url'] ) ) {
		update_post_meta( $post_id, 'six_form_redirect_url', esc_url_raw( wp_unslash( $_POST['six_form_redirect_url'] ) ) );
	}
	foreach ( array( 'six_form_sub', 'six_form_owner_body', 'six_form_customer_body', 'six_form_sms_body' ) as $k ) {
		if ( isset( $_POST[ $k ] ) ) update_post_meta( $post_id, $k, sanitize_textarea_field( wp_unslash( $_POST[ $k ] ) ) );
	}
	update_post_meta( $post_id, 'six_form_customer_enabled', ! empty( $_POST['six_form_customer_enabled'] ) ? 1 : 0 );

	if ( isset( $_POST['six_form_fields_json'] ) ) {
		$decoded = json_decode( wp_unslash( $_POST['six_form_fields_json'] ), true );
		$clean   = array();
		$allowed_types = array_keys( six_forms_field_types() );
		if ( is_array( $decoded ) ) {
			foreach ( $decoded as $row ) {
				if ( ! is_array( $row ) ) continue;
				$key = sanitize_key( str_replace( ' ', '_', $row['key'] ?? '' ) );
				if ( $key === '' ) continue;
				$type = in_array( $row['type'] ?? '', $allowed_types, true ) ? $row['type'] : 'text';
				$clean[] = array(
					'key'         => $key,
					'type'        => $type,
					'label'       => sanitize_text_field( $row['label'] ?? '' ),
					'required'    => ! empty( $row['required'] ),
					'placeholder' => sanitize_text_field( $row['placeholder'] ?? '' ),
					'step'        => max( 1, intval( $row['step'] ?? 1 ) ),
					'options'     => array_values( array_filter( array_map( 'sanitize_text_field', (array) ( $row['options'] ?? array() ) ), fn( $o ) => $o !== '' ) ),
				);
			}
		}
		update_post_meta( $post_id, 'six_form_fields_json', wp_json_encode( $clean ) );
	}
} );

/* ── "Type" column on the Forms list (WP Admin → 6ix Portal → Forms) ────
 * Distinguishes shortcode-based lead forms from system-generated
 * notifications at a glance, per the "just write system generated" request. */
add_filter( 'manage_six_form_posts_columns', function ( $columns ) {
	$new = array();
	foreach ( $columns as $k => $label ) {
		$new[ $k ] = $label;
		if ( $k === 'title' ) $new['six_form_type'] = 'Type';
	}
	return $new;
} );
add_action( 'manage_six_form_posts_custom_column', function ( $column, $post_id ) {
	if ( $column !== 'six_form_type' ) return;
	if ( get_post_meta( $post_id, 'six_form_is_system', true ) ) {
		echo '<span style="display:inline-block;padding:2px 9px;border-radius:100px;font-size:11px;font-weight:700;color:#fff;background:#627080">System Generated</span>';
	} else {
		$key = get_post_meta( $post_id, 'six_form_key', true );
		echo $key ? '<code>[six_form key="' . esc_html( $key ) . '"]</code>' : '—';
	}
}, 10, 2 );

/* ── Submissions list (WP Admin → 6ix Portal → Form Submissions) ───────── */
// Priority 20 so the 'six-portal' parent menu (registered at the default
// priority by six_admin_menu(), portal/admin-settings.php) already exists —
// same fix already documented/applied for the old Ninja Forms settings page.
add_action( 'admin_menu', function () {
	add_submenu_page( 'six-portal', 'Form Submissions', 'Form Submissions', 'manage_options', 'six-portal-submissions', 'six_forms_submissions_page' );
}, 20 );

function six_forms_submissions_page() {
	if ( ! current_user_can( 'manage_options' ) ) return;

	$view_id = isset( $_GET['view'] ) ? (int) $_GET['view'] : 0;
	if ( $view_id ) {
		six_forms_render_submission_detail( $view_id );
		return;
	}

	require_once __DIR__ . '/class-forms-list-table.php';
	$table = new Six_Forms_Submissions_List_Table();
	$table->prepare_items();
	?>
	<div class="wrap">
		<h1>Form Submissions</h1>
		<p>Every lead-form submission on the site — logged here whether or not its notification email actually sent, so nothing is lost to an SMTP problem.</p>
		<form method="get">
			<input type="hidden" name="page" value="six-portal-submissions">
			<?php $table->views(); ?>
			<?php $table->search_box( 'Search', 'six-forms-search' ); ?>
			<?php $table->display(); ?>
		</form>
	</div>
	<?php
}

function six_forms_render_submission_detail( $id ) {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}six_form_submissions WHERE id=%d", $id ) );
	$back = admin_url( 'admin.php?page=six-portal-submissions' );
	if ( ! $row ) {
		echo '<div class="wrap"><h1>Submission not found</h1><p><a href="' . esc_url( $back ) . '">&larr; Back to Form Submissions</a></p></div>';
		return;
	}
	$data = json_decode( $row->data, true );
	if ( ! is_array( $data ) ) $data = array();

	// Allow updating the lead status (new / contacted / converted / spam) —
	// the hook point this leaves for the client dashboard integration.
	if ( isset( $_POST['six_forms_lead_status'] ) && check_admin_referer( 'six_forms_lead_status_' . $id ) ) {
		$wpdb->update( $wpdb->prefix . 'six_form_submissions', array( 'lead_status' => sanitize_key( $_POST['six_forms_lead_status'] ) ), array( 'id' => $id ) );
		$row->lead_status = sanitize_key( $_POST['six_forms_lead_status'] );
		echo '<div class="notice notice-success is-dismissible"><p>Updated.</p></div>';
	}

	if ( isset( $_POST['six_forms_resync_odoo'] ) && check_admin_referer( 'six_forms_resync_odoo_' . $id ) && function_exists( 'six_forms_resync_odoo' ) ) {
		$resync = six_forms_resync_odoo( $id );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}six_form_submissions WHERE id=%d", $id ) );
		if ( ( $resync['odoo_sync_status'] ?? '' ) === 'synced' ) {
			echo '<div class="notice notice-success is-dismissible"><p>Resynced to Odoo — lead #' . intval( $row->odoo_lead_id ) . '.</p></div>';
		} else {
			echo '<div class="notice notice-error is-dismissible"><p>Odoo resync failed: ' . esc_html( $resync['odoo_sync_error'] ?? 'Unknown error.' ) . '</p></div>';
		}
	}
	?>
	<div class="wrap">
		<p><a href="<?php echo esc_url( $back ); ?>">&larr; Back to Form Submissions</a></p>
		<h1><?php echo esc_html( $row->form_title ?: $row->form_key ); ?> — #<?php echo intval( $row->id ); ?></h1>
		<p style="color:#666"><?php echo esc_html( date_i18n( 'F j, Y g:i a', strtotime( $row->created_at ) ) ); ?> · <?php echo esc_html( $row->ip ); ?></p>

		<table class="widefat striped" style="max-width:760px;margin-top:14px">
			<tbody>
			<?php foreach ( $data as $k => $f ) : ?>
			<tr><td style="width:220px"><strong><?php echo esc_html( $f['label'] ?? $k ); ?></strong></td><td><?php echo esc_html( $f['value'] ?? '' ); ?></td></tr>
			<?php endforeach; ?>
			<?php if ( ! $data ) : ?>
			<tr><td colspan="2">No field data was recorded for this submission.</td></tr>
			<?php endif; ?>
			</tbody>
		</table>

		<table class="widefat striped" style="max-width:760px;margin-top:20px">
			<thead><tr><th>Status</th><th>Owner email</th><th>Customer email</th></tr></thead>
			<tbody>
			<tr>
				<td><?php echo six_forms_status_badge( $row->status ); ?></td>
				<td><?php echo six_forms_status_badge( $row->owner_email_status ); ?><?php if ( $row->owner_email_error ) : ?><br><span style="color:#b32d2e;font-size:12px"><?php echo esc_html( $row->owner_email_error ); ?></span><?php endif; ?></td>
				<td><?php echo six_forms_status_badge( $row->customer_email_status ); ?><?php if ( $row->customer_email_error ) : ?><br><span style="color:#b32d2e;font-size:12px"><?php echo esc_html( $row->customer_email_error ); ?></span><?php endif; ?></td>
			</tr>
			</tbody>
		</table>

		<form method="post" style="margin-top:20px">
			<?php wp_nonce_field( 'six_forms_lead_status_' . $id ); ?>
			<label for="six_forms_lead_status"><strong>Lead status</strong></label><br>
			<select name="six_forms_lead_status" id="six_forms_lead_status">
				<?php foreach ( array( 'new' => 'New', 'contacted' => 'Contacted', 'converted' => 'Converted', 'spam' => 'Spam' ) as $val => $label ) : ?>
				<option value="<?php echo esc_attr( $val ); ?>"<?php selected( $row->lead_status, $val ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<button type="submit" class="button button-primary">Update</button>
		</form>

		<h2 style="margin-top:28px">Odoo CRM</h2>
		<?php if ( $row->odoo_lead_id ) : ?>
		<p>Synced — a contact and CRM lead were created, and a follow-up task was scheduled for the owner to reach out within 24 hours.<?php if ( get_option( 'six_odoo_url' ) ) : ?> <a href="<?php echo esc_url( rtrim( get_option( 'six_odoo_url' ), '/' ) . '/odoo/crm/' . intval( $row->odoo_lead_id ) ); ?>" target="_blank" rel="noopener">Open lead in Odoo &rarr;</a><?php endif; ?></p>
		<?php else : ?>
		<p style="color:#666">Not synced to Odoo yet.</p>
		<?php if ( ! empty( $row->odoo_sync_error ) ) : ?>
		<p style="color:#b32d2e;font-size:12px"><strong>Last error:</strong> <?php echo esc_html( $row->odoo_sync_error ); ?></p>
		<?php endif; ?>
		<form method="post">
			<?php wp_nonce_field( 'six_forms_resync_odoo_' . $id ); ?>
			<button type="submit" name="six_forms_resync_odoo" value="1" class="button">Resync to Odoo</button>
		</form>
		<?php endif; ?>

		<p style="margin-top:24px;color:#888;font-size:12px">Source page: <?php echo esc_html( $row->source_url ); ?><br>User agent: <?php echo esc_html( $row->user_agent ); ?></p>
	</div>
	<?php
}

function six_forms_status_badge( $status ) {
	$colors = array(
		'success' => '#1b9e52', 'sent' => '#1b9e52',
		'partial' => '#c17b1a', 'blocked' => '#c17b1a',
		'failed'  => '#d93b3b',
		'skipped' => '#888',
	);
	$c = $colors[ $status ] ?? '#888';
	return '<span style="display:inline-block;padding:2px 10px;border-radius:100px;font-size:12px;font-weight:700;color:#fff;background:' . esc_attr( $c ) . '">' . esc_html( ucfirst( $status ) ) . '</span>';
}
