<?php
/**
 * Accepting a figure a Claude session proposed.
 *
 * Two things have to hold, and both have burnt this codebase before.
 *
 * **It writes to both tables.** six_client_kpis feeds the customer's four stat
 * cards; six_metrics feeds the service page's detail rows. Filling only one is
 * exactly how an advisor's number used to stop at the advisor's own screen.
 *
 * **A failed write does not resolve the proposal.** A proposal marked accepted
 * whose write failed is the worst of both outcomes: gone from the queue and
 * absent from the dashboard, with nothing on screen saying so.
 *
 *     php tools/tests/test_proposal_accept.php
 */
define( 'ABSPATH', '/x/' );

$pass = 0; $fail = 0;
function check( $name, $ok, $detail = '' ) {
	global $pass, $fail;
	if ( $ok ) { $pass++; echo "PASS  $name\n"; }
	else { $fail++; echo "FAIL  $name\n" . ( $detail ? "        $detail\n" : '' ); }
}

$GLOBALS['SENT'] = null;
$GLOBALS['TABLES_PRESENT'] = true;
$GLOBALS['WRITE_FAILS'] = array();   // table short name => true

function check_ajax_referer( ...$a ) { return true; }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_textarea_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
function esc_url_raw( $u ) { return (string) $u; }
function wp_unslash( $v ) { return $v; }
function current_time( $f ) { return $f === 'mysql' ? '2026-11-17 10:00:00' : gmdate( $f ); }
function current_user_can( $c ) { return true; }
function wp_get_current_user() { return (object) array( 'display_name' => 'Test Advisor' ); }
function get_current_user_id() { return 3; }
function six_table_exists( $t ) { return $GLOBALS['TABLES_PRESENT']; }
function six_kpi_key_allowed( $k ) { return in_array( $k, array( 'organic_visitors', 'seo_avg_position' ), true ); }
function add_action( $hook, $cb ) { $GLOBALS['HOOKS'][ $hook ] = $cb; }

class SixAjaxSuccess extends Exception { public $data; function __construct( $d ) { $this->data = $d; } }
class SixAjaxError   extends Exception { public $data; function __construct( $d ) { $this->data = $d; } }
function wp_send_json_success( $d = null ) { throw new SixAjaxSuccess( $d ); }
function wp_send_json_error( $d = null )   { throw new SixAjaxError( $d ); }

class Six_Roles { public static function is_advisor() { return true; } }

class Six_Test_WPDB {
	public $prefix = 'wp_';
	public $last_error = '';
	public $writes = array();
	public function suppress_errors( $x = true ) {}
	public function prepare( $q, ...$a ) { return vsprintf( str_replace( array( '%d', '%s' ), array( '%d', "'%s'" ), $q ), $a ); }
	public function get_row( $q ) {
		return (object) array(
			'id' => 9, 'client_id' => 195, 'service_slug' => 'seo',
			'metric_key' => $GLOBALS['PROPOSAL_KEY'], 'label' => 'Organic Visitors',
			'unit' => 'visitors per month', 'current_value' => '3390',
			'previous_value' => '3260', 'suggested_target' => '4000',
		);
	}
	public function get_var( $q ) { return 0; }
	protected function fails( $table ) {
		foreach ( $GLOBALS['WRITE_FAILS'] as $frag => $_ ) {
			if ( strpos( $table, $frag ) !== false ) return true;
		}
		return false;
	}
	public function replace( $table, $data ) {
		if ( $this->fails( $table ) ) { $this->last_error = 'replace blew up'; return false; }
		$this->writes[] = array( 'op' => 'replace', 'table' => $table, 'data' => $data );
		return 1;
	}
	public function insert( $table, $data ) {
		if ( $this->fails( $table ) ) { $this->last_error = 'insert blew up'; return false; }
		$this->writes[] = array( 'op' => 'insert', 'table' => $table, 'data' => $data );
		return 1;
	}
	public function update( $table, $data, $where ) {
		if ( $this->fails( $table ) ) { $this->last_error = 'update blew up'; return false; }
		$this->writes[] = array( 'op' => 'update', 'table' => $table, 'data' => $data );
		return 1;
	}
}

class Six_Tracking {
	public static $resolved = array();
	public static function proposals_table() { return 'wp_six_metric_proposals'; }
	public static function resolve_proposal( $id, $status ) {
		self::$resolved[] = array( 'id' => $id, 'status' => $status );
		return true;
	}
}

$GLOBALS['wpdb'] = new Six_Test_WPDB();
$GLOBALS['PROPOSAL_KEY'] = 'organic_visitors';
$GLOBALS['HOOKS'] = array();

// Pull just the handlers under test out of the file, so this does not have to
// stand up the whole plugin.
$src = file_get_contents( dirname( __DIR__, 2 ) . '/portal/ajax-handlers.php' );
$from = strpos( $src, "add_action( 'wp_ajax_six_accept_proposal'" );
$to   = strpos( $src, "add_action( 'wp_ajax_six_get_work_log'" );
if ( $from === false || $to === false ) { echo "FAIL  could not locate the handlers in ajax-handlers.php\n"; exit( 1 ); }
// The slice is already PHP with no opening tag, so it is eval'd directly.
// Prefixing it with a close-tag would drop into HTML mode and print the
// handlers instead of registering them — and writing that close-tag in this
// comment would do it here, because PHP honours it inside comments too.
eval( substr( $src, $from, $to - $from ) );

function run_accept( array $post ) {
	$_POST = $post;
	$GLOBALS['wpdb']->writes = array();
	Six_Tracking::$resolved = array();
	try { call_user_func( $GLOBALS['HOOKS']['wp_ajax_six_accept_proposal'] ); }
	catch ( SixAjaxSuccess $s ) { return array( 'ok' => true,  'data' => $s->data ); }
	catch ( SixAjaxError $e )   { return array( 'ok' => false, 'data' => $e->data ); }
	return array( 'ok' => null, 'data' => 'handler returned without responding' );
}

$good = array( 'proposal_id' => 9, 'client_id' => 195, 'current_value' => '3390',
               'previous_value' => '3260', 'target_value' => '4000', 'label' => 'Organic Visitors' );

// ── The happy path writes to BOTH tables ─────────────────────────────────
$GLOBALS['WRITE_FAILS'] = array();
$r = run_accept( $good );
check( 'accepting succeeds', $r['ok'] === true, json_encode( $r['data'] ) );
$tables = array_column( $GLOBALS['wpdb']->writes, 'table' );
check( 'the stat-card table is written', in_array( 'wp_six_client_kpis', $tables, true ), implode( ', ', $tables ) );
check( 'the service detail table is written too', in_array( 'wp_six_metrics', $tables, true ), implode( ', ', $tables ) );
check( 'and the proposal is resolved as accepted',
	( Six_Tracking::$resolved[0]['status'] ?? '' ) === 'accepted' );

$metric_write = null;
foreach ( $GLOBALS['wpdb']->writes as $w ) if ( $w['table'] === 'wp_six_metrics' ) $metric_write = $w['data'];
check( 'the target reaches the detail row', ( $metric_write['target_value'] ?? '' ) === '4000' );
check( 'and the unit comes from the proposal rather than being left blank',
	( $metric_write['unit'] ?? '' ) === 'visitors per month' );

// ── The advisor's edits win over what was proposed ───────────────────────
$r = run_accept( array_merge( $good, array( 'current_value' => '3400', 'target_value' => '5000', 'label' => 'Organic Sessions' ) ) );
$metric_write = null; $kpi_write = null;
foreach ( $GLOBALS['wpdb']->writes as $w ) {
	if ( $w['table'] === 'wp_six_metrics' ) $metric_write = $w['data'];
	if ( $w['table'] === 'wp_six_client_kpis' ) $kpi_write = $w['data'];
}
check( 'an edited figure is what gets saved', ( $kpi_write['kpi_value'] ?? '' ) === '3400' );
check( 'an edited target is what gets saved', ( $metric_write['target_value'] ?? '' ) === '5000' );
check( 'an edited label is what gets saved', ( $metric_write['label'] ?? '' ) === 'Organic Sessions' );

// ── A failed write must not resolve the proposal ─────────────────────────
$GLOBALS['WRITE_FAILS'] = array( 'six_client_kpis' => true, 'six_metrics' => true );
$r = run_accept( $good );
check( 'when every write fails, the call reports an error', $r['ok'] === false, json_encode( $r['data'] ) );
check( 'and the proposal stays in the queue', Six_Tracking::$resolved === array(),
	json_encode( Six_Tracking::$resolved ) );

// One of two failing is a partial save, reported as such — the figure did land
// somewhere, so leaving it pending would duplicate it on the next accept.
$GLOBALS['WRITE_FAILS'] = array( 'six_client_kpis' => true );
$r = run_accept( $good );
check( 'one of two failing still saves and still resolves', $r['ok'] === true );
check( 'but says so rather than reporting a clean save',
	strpos( (string) ( $r['data']['message'] ?? '' ), 'not everywhere' ) !== false,
	json_encode( $r['data'] ) );
check( 'and names what did not land',
	strpos( (string) ( $r['data']['partial'] ?? '' ), 'blew up' ) !== false, json_encode( $r['data'] ) );

// ── A missing table is reported, never silently skipped ──────────────────
$GLOBALS['WRITE_FAILS'] = array();
$GLOBALS['TABLES_PRESENT'] = false;
$r = run_accept( $good );
check( 'a missing table is an error, not a success', $r['ok'] === false, json_encode( $r['data'] ) );
check( 'and the message says which table and what to do',
	stripos( (string) $r['data'], 'table is missing' ) !== false, (string) $r['data'] );
check( 'and nothing is resolved', Six_Tracking::$resolved === array() );
$GLOBALS['TABLES_PRESENT'] = true;

// ── A metric with no catalog key still reaches the detail rows ───────────
// Not every proposal maps to a stat card. One that does not must still save,
// or an advisor's own metric silently does nothing.
$GLOBALS['PROPOSAL_KEY'] = '';
$r = run_accept( $good );
$tables = array_column( $GLOBALS['wpdb']->writes, 'table' );
check( 'a keyless proposal saves to the detail rows', $r['ok'] === true && in_array( 'wp_six_metrics', $tables, true ) );
check( 'and does not attempt a keyless stat-card write',
	! in_array( 'wp_six_client_kpis', $tables, true ), implode( ', ', $tables ) );
check( 'and is still resolved', ( Six_Tracking::$resolved[0]['status'] ?? '' ) === 'accepted' );

// A key the catalog does not allow is skipped for the cards, not written blind.
$GLOBALS['PROPOSAL_KEY'] = 'something_invented';
$r = run_accept( $good );
$tables = array_column( $GLOBALS['wpdb']->writes, 'table' );
check( 'an unknown metric key is not written to the stat cards',
	! in_array( 'wp_six_client_kpis', $tables, true ), implode( ', ', $tables ) );
$GLOBALS['PROPOSAL_KEY'] = 'organic_visitors';

// ── Refusals ─────────────────────────────────────────────────────────────
$r = run_accept( array( 'proposal_id' => 0, 'client_id' => 195 ) );
check( 'no proposal id is refused', $r['ok'] === false );
$r = run_accept( array_merge( $good, array( 'current_value' => '' ) ) );
check( 'accepting with no figure is refused', $r['ok'] === false );
check( 'and says a figure is required',
	stripos( (string) $r['data'], 'figure is required' ) !== false, (string) $r['data'] );

echo "\nPassed: $pass  Failed: $fail\n";
exit( $fail ? 1 : 0 );
