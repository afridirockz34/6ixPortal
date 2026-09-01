<?php
/**
 * 6ix Portal — Forms system: the "Form Submissions" list table.
 * Native WP_List_Table so sorting, pagination, and the wp-admin look come
 * for free — matches how every other WP list screen already behaves.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! class_exists( 'WP_List_Table' ) ) require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';

class Six_Forms_Submissions_List_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct( array( 'singular' => 'submission', 'plural' => 'submissions', 'ajax' => false ) );
	}

	public function get_columns() {
		return array(
			'created_at'  => 'Date',
			'form_title'  => 'Form',
			'preview'     => 'Submitted by',
			'status'      => 'Status',
			'lead_status' => 'Lead status',
		);
	}

	protected function get_sortable_columns() {
		return array( 'created_at' => array( 'created_at', true ), 'form_title' => array( 'form_title', false ) );
	}

	protected function extra_tablenav( $which ) {
		if ( $which !== 'top' ) return;
		global $wpdb;
		$forms = $wpdb->get_col( "SELECT DISTINCT form_title FROM {$wpdb->prefix}six_form_submissions ORDER BY form_title" );
		$cur_form   = isset( $_GET['form'] ) ? sanitize_text_field( wp_unslash( $_GET['form'] ) ) : '';
		$cur_status = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
		?>
		<div class="alignleft actions">
			<select name="form">
				<option value="">All forms</option>
				<?php foreach ( $forms as $f ) : ?>
				<option value="<?php echo esc_attr( $f ); ?>"<?php selected( $cur_form, $f ); ?>><?php echo esc_html( $f ); ?></option>
				<?php endforeach; ?>
			</select>
			<select name="status">
				<option value="">All statuses</option>
				<?php foreach ( array( 'success', 'partial', 'failed', 'blocked' ) as $s ) : ?>
				<option value="<?php echo esc_attr( $s ); ?>"<?php selected( $cur_status, $s ); ?>><?php echo esc_html( ucfirst( $s ) ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php submit_button( 'Filter', '', 'filter_action', false ); ?>
		</div>
		<?php
	}

	public function views() {
		global $wpdb;
		$table = $wpdb->prefix . 'six_form_submissions';
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		$new   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE lead_status='new'" );
		$base  = admin_url( 'admin.php?page=six-portal-submissions' );
		$cur   = empty( $_GET['lead_status'] );
		printf(
			'<ul class="subsubsub"><li><a href="%s"%s>All (%d)</a> | </li><li><a href="%s"%s>New leads (%d)</a></li></ul>',
			esc_url( $base ), $cur ? ' class="current"' : '', $total,
			esc_url( add_query_arg( 'lead_status', 'new', $base ) ), ( ! $cur && ( $_GET['lead_status'] ?? '' ) === 'new' ) ? ' class="current"' : '', $new
		);
	}

	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
		case 'created_at':  return esc_html( date_i18n( 'M j, Y g:i a', strtotime( $item->created_at ) ) );
		case 'form_title':  return esc_html( $item->form_title ?: $item->form_key );
		case 'status':      return six_forms_status_badge( $item->status );
		case 'lead_status': return esc_html( ucfirst( $item->lead_status ) );
		default:            return '';
		}
	}

	public function column_preview( $item ) {
		$data = json_decode( $item->data, true );
		$bits = array();
		if ( is_array( $data ) ) {
			foreach ( $data as $f ) {
				$v = $f['value'] ?? '';
				if ( $v !== '' && ( is_email( $v ) || preg_match( '/^[A-Za-z ,.\'-]{2,60}$/', $v ) ) ) {
					$bits[] = $v;
					if ( count( $bits ) >= 2 ) break;
				}
			}
		}
		$preview = $bits ? implode( ' — ', $bits ) : '(no preview available)';
		$view_url = add_query_arg( array( 'page' => 'six-portal-submissions', 'view' => $item->id ), admin_url( 'admin.php' ) );
		return '<a href="' . esc_url( $view_url ) . '"><strong>' . esc_html( $preview ) . '</strong></a>'
			. '<div class="row-actions"><span><a href="' . esc_url( $view_url ) . '">View</a></span></div>';
	}

	public function prepare_items() {
		global $wpdb;
		$table = $wpdb->prefix . 'six_form_submissions';
		$per_page = 20;
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

		$where  = array( '1=1' );
		$params = array();
		if ( ! empty( $_GET['form'] ) ) { $where[] = 'form_title = %s'; $params[] = sanitize_text_field( wp_unslash( $_GET['form'] ) ); }
		if ( ! empty( $_GET['status'] ) ) { $where[] = 'status = %s'; $params[] = sanitize_key( $_GET['status'] ); }
		if ( ! empty( $_GET['lead_status'] ) ) { $where[] = 'lead_status = %s'; $params[] = sanitize_key( $_GET['lead_status'] ); }
		if ( ! empty( $_GET['s'] ) ) { $where[] = 'data LIKE %s'; $params[] = '%' . $wpdb->esc_like( sanitize_text_field( wp_unslash( $_GET['s'] ) ) ) . '%'; }
		$where_sql = implode( ' AND ', $where );

		$orderby = in_array( $_GET['orderby'] ?? '', array( 'created_at', 'form_title' ), true ) ? $_GET['orderby'] : 'created_at';
		$order   = strtoupper( $_GET['order'] ?? '' ) === 'ASC' ? 'ASC' : 'DESC';

		$total_items = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}", $params ) ) : $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}" ) );

		$current_page = $this->get_pagenum();
		$offset = ( $current_page - 1 ) * $per_page;

		$sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$query_params = array_merge( $params, array( $per_page, $offset ) );
		$this->items = $wpdb->get_results( $wpdb->prepare( $sql, $query_params ) );

		$this->set_pagination_args( array( 'total_items' => $total_items, 'per_page' => $per_page, 'total_pages' => ceil( $total_items / $per_page ) ) );
	}
}
