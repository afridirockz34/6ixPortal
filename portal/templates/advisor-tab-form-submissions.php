<?php
/**
 * Advisor dashboard — "Form Submissions" tab.
 * Included from advisor-dashboard.php's tab chain when ?tab=form-submissions.
 * Every website lead form's submissions, filterable by date/status/form,
 * with a full detail view (every field + when/where + email + Odoo status)
 * on ?tab=form-submissions&submission=ID.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$sub_table = $wpdb->prefix . 'six_form_submissions';
$view_submission_id = isset( $_GET['submission'] ) ? intval( $_GET['submission'] ) : 0;

if ( $view_submission_id ) :
	$sub = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$sub_table} WHERE id=%d", $view_submission_id ) );
	if ( ! $sub ) : ?>
	<div class="six-card" style="padding:40px;text-align:center">
		<p>That submission couldn't be found.</p>
		<a class="six-btn six-btn-primary" href="?tab=form-submissions">&larr; Back to Form Submissions</a>
	</div>
	<?php else :
		$fields = json_decode( $sub->data, true );
		if ( ! is_array( $fields ) ) $fields = array();
		if ( isset( $_POST['six_fs_lead_status'] ) && check_admin_referer( 'six_fs_lead_status_' . $sub->id ) ) {
			$new_status = sanitize_key( $_POST['six_fs_lead_status'] );
			$wpdb->update( $sub_table, array( 'lead_status' => $new_status ), array( 'id' => $sub->id ) );
			$sub->lead_status = $new_status;
		}
		$odoo_resync_result = null;
		if ( isset( $_POST['six_fs_resync_odoo'] ) && check_admin_referer( 'six_fs_resync_odoo_' . $sub->id ) && function_exists( 'six_forms_resync_odoo' ) ) {
			$odoo_resync_result = six_forms_resync_odoo( $sub->id );
			$sub = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$sub_table} WHERE id=%d", $sub->id ) );
		}
		if ( isset( $_POST['six_fs_mark_responded'] ) && check_admin_referer( 'six_fs_mark_responded_' . $sub->id ) && function_exists( 'six_lead_mark_responded' ) && six_lead_can_mark_responded() ) {
			six_lead_mark_responded( $sub->id );
			$sub = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$sub_table} WHERE id=%d", $sub->id ) );
		}
		?>
	<div class="six-page-header">
		<div>
			<a href="?tab=form-submissions" style="font-size:12.5px;color:var(--text3);text-decoration:none">&larr; Back to Form Submissions</a>
			<h1 class="six-page-title" style="margin-top:6px"><?php echo esc_html( $sub->form_title ?: $sub->form_key ); ?></h1>
			<p class="six-page-sub"><?php echo esc_html( date_i18n( 'F j, Y \a\t g:i a', strtotime( $sub->created_at ) ) ); ?></p>
		</div>
		<?php echo six_fs_status_badge( $sub->status ); ?>
	</div>

	<div class="six-card" style="padding:24px;margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px">
		<div>
			<h3 style="margin:0 0 6px;font-size:14px">Response</h3>
			<?php echo six_fs_response_badge( $sub ); ?>
		</div>
		<?php if ( $sub->response_status === 'pending' && function_exists( 'six_lead_can_mark_responded' ) && six_lead_can_mark_responded() ) : ?>
		<form method="post">
			<?php wp_nonce_field( 'six_fs_mark_responded_' . $sub->id ); ?>
			<button type="submit" name="six_fs_mark_responded" value="1" class="six-btn six-btn-primary six-btn-sm">Mark Responded</button>
		</form>
		<?php endif; ?>
	</div>

	<div class="six-card" style="padding:24px;margin-bottom:20px">
		<h3 style="margin:0 0 14px;font-size:14px">Submitted information</h3>
		<table class="six-table">
			<tbody>
			<?php foreach ( $fields as $f ) : ?>
			<tr><td style="width:240px;color:var(--text3)"><?php echo esc_html( $f['label'] ?? '' ); ?></td><td><?php echo esc_html( $f['value'] ?? '' ); ?></td></tr>
			<?php endforeach; ?>
			<?php if ( ! $fields ) : ?>
			<tr><td colspan="2" style="color:var(--text3)">No field data was recorded for this submission.</td></tr>
			<?php endif; ?>
			</tbody>
		</table>
	</div>

	<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">
		<div class="six-card" style="padding:24px">
			<h3 style="margin:0 0 14px;font-size:14px">Delivery</h3>
			<table class="six-table">
				<tbody>
				<tr><td style="color:var(--text3)">Overall</td><td><?php echo six_fs_status_badge( $sub->status ); ?></td></tr>
				<tr><td style="color:var(--text3)">Owner email</td><td><?php echo six_fs_status_badge( $sub->owner_email_status ); ?><?php if ( $sub->owner_email_error ) : ?><div style="font-size:11.5px;color:var(--danger,#dc2626);margin-top:4px"><?php echo esc_html( $sub->owner_email_error ); ?></div><?php endif; ?></td></tr>
				<tr><td style="color:var(--text3)">Customer email</td><td><?php echo six_fs_status_badge( $sub->customer_email_status ); ?><?php if ( $sub->customer_email_error ) : ?><div style="font-size:11.5px;color:var(--danger,#dc2626);margin-top:4px"><?php echo esc_html( $sub->customer_email_error ); ?></div><?php endif; ?></td></tr>
				</tbody>
			</table>
		</div>
		<div class="six-card" style="padding:24px">
			<h3 style="margin:0 0 14px;font-size:14px">Where it came from</h3>
			<table class="six-table">
				<tbody>
				<tr><td style="width:110px;color:var(--text3)">Page</td><td style="word-break:break-all"><?php echo esc_html( $sub->source_url ); ?></td></tr>
				<tr><td style="color:var(--text3)">IP address</td><td><?php echo esc_html( $sub->ip ); ?></td></tr>
				<tr><td style="color:var(--text3)">Device</td><td style="word-break:break-all;font-size:11.5px"><?php echo esc_html( $sub->user_agent ); ?></td></tr>
				</tbody>
			</table>
		</div>
	</div>

	<div class="six-card" style="padding:24px;margin-bottom:20px">
		<h3 style="margin:0 0 14px;font-size:14px">Odoo CRM</h3>
		<?php if ( $odoo_resync_result ) : ?>
		<div style="margin-bottom:12px;padding:10px 12px;border-radius:8px;font-size:12.5px;background:<?php echo ( $odoo_resync_result['odoo_sync_status'] ?? '' ) === 'synced' ? 'rgba(27,158,82,.12)' : 'rgba(220,38,38,.1)'; ?>">
			<?php echo ( $odoo_resync_result['odoo_sync_status'] ?? '' ) === 'synced' ? 'Resynced successfully.' : 'Resync failed: ' . esc_html( $odoo_resync_result['odoo_sync_error'] ?? '' ); ?>
		</div>
		<?php endif; ?>

		<?php if ( $sub->odoo_lead_id ) : ?>
		<p style="font-size:13px">Synced to Odoo — a contact and CRM lead were created, and a "Call Within 10 Minutes" task was scheduled.</p>
		<?php if ( get_option( 'six_odoo_url' ) ) : ?>
		<a class="six-btn six-btn-secondary six-btn-sm" target="_blank" rel="noopener" href="<?php echo esc_url( rtrim( get_option( 'six_odoo_url' ), '/' ) . '/odoo/crm/' . intval( $sub->odoo_lead_id ) ); ?>">Open lead in Odoo &rarr;</a>
		<?php endif; ?>
		<?php else : ?>
		<p style="font-size:13px;color:var(--text3)">Not synced to Odoo yet.</p>
		<?php if ( ! empty( $sub->odoo_sync_error ) ) : ?>
		<p style="font-size:12.5px;color:var(--danger,#dc2626);margin-top:6px"><strong>Last error:</strong> <?php echo esc_html( $sub->odoo_sync_error ); ?></p>
		<?php endif; ?>
		<form method="post" style="margin-top:10px">
			<?php wp_nonce_field( 'six_fs_resync_odoo_' . $sub->id ); ?>
			<button type="submit" name="six_fs_resync_odoo" value="1" class="six-btn six-btn-secondary six-btn-sm">Resync to Odoo</button>
		</form>
		<?php endif; ?>
	</div>

	<div class="six-card" style="padding:24px">
		<h3 style="margin:0 0 14px;font-size:14px">Lead status</h3>
		<form method="post" style="display:flex;gap:10px;align-items:center">
			<?php wp_nonce_field( 'six_fs_lead_status_' . $sub->id ); ?>
			<select name="six_fs_lead_status" class="six-input" style="width:auto">
				<?php foreach ( array( 'new' => 'New', 'contacted' => 'Contacted', 'converted' => 'Converted', 'spam' => 'Spam' ) as $val => $label ) : ?>
				<option value="<?php echo esc_attr( $val ); ?>"<?php selected( $sub->lead_status, $val ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<button type="submit" class="six-btn six-btn-primary six-btn-sm">Update</button>
		</form>
	</div>

<?php endif; else :

	// ── List view: filters + table ────────────────────────────────────
	$f_form   = isset( $_GET['form'] )   ? sanitize_text_field( wp_unslash( $_GET['form'] ) )   : '';
	$f_status = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] )                       : '';
	$f_from   = isset( $_GET['from'] )   ? sanitize_text_field( wp_unslash( $_GET['from'] ) )   : '';
	$f_to     = isset( $_GET['to'] )     ? sanitize_text_field( wp_unslash( $_GET['to'] ) )     : '';

	$where  = array( '1=1' );
	$params = array();
	if ( $f_form !== '' )   { $where[] = 'form_title = %s'; $params[] = $f_form; }
	if ( $f_status !== '' ) { $where[] = 'status = %s';     $params[] = $f_status; }
	if ( $f_from !== '' )   { $where[] = 'created_at >= %s'; $params[] = $f_from . ' 00:00:00'; }
	if ( $f_to !== '' )     { $where[] = 'created_at <= %s'; $params[] = $f_to . ' 23:59:59'; }
	$where_sql = implode( ' AND ', $where );

	$sql = "SELECT * FROM {$sub_table} WHERE {$where_sql} ORDER BY created_at DESC LIMIT 100";
	$rows = $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_results( $sql );
	$forms = $wpdb->get_col( "SELECT DISTINCT form_title FROM {$sub_table} WHERE form_title != '' ORDER BY form_title" );
	?>

	<div class="six-page-header">
		<div>
			<h1 class="six-page-title">Form Submissions</h1>
			<p class="six-page-sub">Every lead-form submission on the website — logged whether or not its notification email sent.</p>
		</div>
	</div>

	<div class="six-card" style="padding:16px 20px;margin-bottom:16px">
		<form method="get" style="display:flex;flex-wrap:wrap;gap:10px;align-items:end">
			<input type="hidden" name="tab" value="form-submissions">
			<label style="font-size:11.5px;color:var(--text3)">Form
				<select name="form" class="six-input" style="min-width:180px">
					<option value="">All forms</option>
					<?php foreach ( $forms as $ft ) : ?>
					<option value="<?php echo esc_attr( $ft ); ?>"<?php selected( $f_form, $ft ); ?>><?php echo esc_html( $ft ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label style="font-size:11.5px;color:var(--text3)">Status
				<select name="status" class="six-input">
					<option value="">All statuses</option>
					<?php foreach ( array( 'success' => 'Success', 'partial' => 'Partial', 'failed' => 'Failed', 'blocked' => 'Blocked' ) as $val => $label ) : ?>
					<option value="<?php echo esc_attr( $val ); ?>"<?php selected( $f_status, $val ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label style="font-size:11.5px;color:var(--text3)">From
				<input type="date" name="from" class="six-input" value="<?php echo esc_attr( $f_from ); ?>">
			</label>
			<label style="font-size:11.5px;color:var(--text3)">To
				<input type="date" name="to" class="six-input" value="<?php echo esc_attr( $f_to ); ?>">
			</label>
			<button type="submit" class="six-btn six-btn-primary six-btn-sm">Filter</button>
			<?php if ( $f_form || $f_status || $f_from || $f_to ) : ?>
			<a class="six-btn six-btn-secondary six-btn-sm" href="?tab=form-submissions">Clear</a>
			<?php endif; ?>
		</form>
	</div>

	<div class="six-card" style="overflow-x:auto">
		<table class="six-table">
			<thead><tr><th>Date</th><th>Form</th><th>Submitted by</th><th>Status</th><th>Response</th><th>Lead status</th><th>Odoo</th><th></th></tr></thead>
			<tbody>
			<?php if ( ! $rows ) : ?>
			<tr><td colspan="8" style="text-align:center;color:var(--text3);padding:30px">No submissions match these filters yet.</td></tr>
			<?php endif; ?>
			<?php foreach ( $rows as $r ) :
				$data = json_decode( $r->data, true );
				$preview = six_fs_preview( is_array( $data ) ? $data : array() );
				$url = '?tab=form-submissions&submission=' . intval( $r->id );
				?>
			<tr>
				<td style="white-space:nowrap"><?php echo esc_html( date_i18n( 'M j, g:i a', strtotime( $r->created_at ) ) ); ?></td>
				<td><?php echo esc_html( $r->form_title ?: $r->form_key ); ?></td>
				<td><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $preview ); ?></a></td>
				<td><?php echo six_fs_status_badge( $r->status ); ?></td>
				<td><?php echo six_fs_response_badge( $r ); ?></td>
				<td><?php echo esc_html( ucfirst( $r->lead_status ) ); ?></td>
				<td><?php
				if ( $r->odoo_lead_id ) {
					echo '<span style="color:var(--success,#1b9e52)">Synced</span>';
				} elseif ( ( $r->odoo_sync_status ?? '' ) === 'failed' ) {
					echo '<span style="color:var(--danger,#dc2626)" title="' . esc_attr( $r->odoo_sync_error ?? '' ) . '">Failed</span>';
				} else {
					echo '<span style="color:var(--text3)">—</span>';
				}
			?></td>
				<td><a class="six-btn six-btn-secondary six-btn-sm" href="<?php echo esc_url( $url ); ?>">View</a></td>
			</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>

<?php endif;

function six_fs_status_badge( $status ) {
	$colors = array(
		'success' => '#1b9e52', 'sent' => '#1b9e52',
		'partial' => '#c17b1a', 'blocked' => '#c17b1a',
		'failed'  => '#d93b3b',
		'skipped' => '#888',
	);
	$c = $colors[ $status ] ?? '#888';
	return '<span style="display:inline-block;padding:2px 10px;border-radius:100px;font-size:11.5px;font-weight:700;color:#fff;background:' . esc_attr( $c ) . '">' . esc_html( ucfirst( $status ) ) . '</span>';
}
/** Response-window / recovery-sequence status, for the Lead Automation Flow's "Responds?" step. */
function six_fs_response_badge( $sub ) {
	$status = $sub->response_status ?? 'pending';
	if ( $status === 'responded' ) {
		return '<span style="display:inline-block;padding:2px 10px;border-radius:100px;font-size:11.5px;font-weight:700;color:#fff;background:#1b9e52">Responded</span>';
	}
	if ( $status === 'abandoned' ) {
		$stage = intval( $sub->recovery_stage ?? 0 );
		return '<span style="display:inline-block;padding:2px 10px;border-radius:100px;font-size:11.5px;font-weight:700;color:#fff;background:#c17b1a">Abandoned — recovery ' . $stage . '/4</span>';
	}
	if ( $status === 'nurture' ) {
		return '<span style="display:inline-block;padding:2px 10px;border-radius:100px;font-size:11.5px;font-weight:700;color:#fff;background:#888">Nurture list</span>';
	}
	// 'pending' — still inside the call-reminder window (or the sweep just hasn't run yet).
	$due = ! empty( $sub->response_due_at ) ? strtotime( $sub->response_due_at ) : 0;
	$mins_left = $due ? round( ( $due - current_time( 'timestamp' ) ) / 60 ) : null;
	if ( $mins_left !== null && $mins_left > 0 ) {
		return '<span style="display:inline-block;padding:2px 10px;border-radius:100px;font-size:11.5px;font-weight:700;color:#fff;background:#2f8f8a">' . $mins_left . 'm left to respond</span>';
	}
	return '<span style="display:inline-block;padding:2px 10px;border-radius:100px;font-size:11.5px;font-weight:700;color:#fff;background:#c17b1a">Window passed — pending sweep</span>';
}
function six_fs_preview( $data ) {
	$bits = array();
	foreach ( $data as $f ) {
		$v = trim( (string) ( $f['value'] ?? '' ) );
		if ( $v !== '' && ( is_email( $v ) || preg_match( '/^[A-Za-z ,.\'-]{2,60}$/', $v ) ) ) {
			$bits[] = $v;
			if ( count( $bits ) >= 2 ) break;
		}
	}
	return $bits ? implode( ' — ', $bits ) : '(no preview)';
}
