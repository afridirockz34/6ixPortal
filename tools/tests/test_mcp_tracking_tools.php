<?php
/**
 * The connector's work-log and metric-proposal tools.
 *
 * The property these exist to hold: **a figure Claude reads does not reach the
 * customer's dashboard.** It becomes a proposal, an advisor confirms it, and
 * confirming is what puts it on a stat card. If that ever stops being true,
 * the portal starts showing numbers nobody stands behind, and nothing on the
 * screen distinguishes those from the ones somebody checked.
 *
 * Also: the declarations themselves. These tools are read by a model that has
 * never seen this codebase, so a description that fails to say "log every
 * change" or "this needs a period" is a defect in the same way a wrong return
 * value is.
 *
 *     php tools/tests/test_mcp_tracking_tools.php
 */

$pass = 0; $fail = 0;
function check( $name, $ok, $detail = '' ) {
	global $pass, $fail;
	if ( $ok ) { $pass++; echo "PASS  $name\n"; }
	else { $fail++; echo "FAIL  $name\n" . ( $detail ? "        $detail\n" : '' ); }
}

define( 'ABSPATH', '/x/' );
$GLOBALS['META'] = array();
$GLOBALS['OPTS'] = array();

function get_user_meta( $id, $key, $single = false ) { return $GLOBALS['META'][ $key ] ?? ''; }
function get_option( $k, $d = '' ) { return $GLOBALS['OPTS'][ $k ] ?? $d; }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) $k ) ); }
function add_action() {}
function add_filter() {}
function register_rest_route() {}
function wp_json_encode( $d ) { return json_encode( $d ); }
function esc_html( $s ) { return $s; }
function is_wp_error( $t ) { return $t instanceof WP_Error; }
class WP_Error { public $m; function __construct( $m = '' ) { $this->m = $m; } function get_error_message() { return $this->m; } }
function __( $s ) { return $s; }

class Six_Analytics {
	public static function ga4_summary( $p, $d = 30 ) { return array( 'error' => 'x' ); }
	public static function gsc_summary( $s, $d = 28, $r = 15 ) { return array( 'error' => 'x' ); }
	public static function pagespeed( $u, $s = 'mobile' ) { return array(); }
}
class Six_Profile_API {
	public static function writable_fields() { return array(); }
	/** The harness always holds a write token; the gating itself is checked
	    against the source above, not by faking a refusal here. */
	public static function require_write( $request ) { return true; }
}

/** Records what the connector asked of it, so the dispatch can be checked. */
class Six_Tracking {
	public static $logged = array();
	public static $proposed = array();
	public static $entities = array();
	public static $log_ok = true;

	public static function log_work( $client_id, array $row ) {
		self::$logged[] = array( 'client' => $client_id, 'row' => $row );
		return self::$log_ok
			? array( 'ok' => true, 'id' => 42, 'error' => '' )
			: array( 'ok' => false, 'id' => 0, 'error' => 'the six_work_log table is missing' );
	}
	public static function work_log( $client_id, array $args = array() ) {
		return array( array( 'id' => 1, 'title' => 'Retitled the sexual assault page', 'category' => 'Title Tags' ) );
	}
	public static function proof( $client_id, $service = '' ) {
		return array( array( 'category' => 'Title Tags', 'found' => 8, 'deployed' => 6, 'pending' => 2, 'completion' => 0.75 ) );
	}
	public static function propose_metrics( $client_id, array $rows, $service = '', $origin = 'claude' ) {
		self::$proposed[] = array( 'client' => $client_id, 'rows' => $rows, 'service' => $service, 'origin' => $origin );
		return array( 'saved' => count( $rows ), 'failed' => array() );
	}
	public static function record_entities( $client_id, array $rows, $service = '', $source = '' ) {
		self::$entities[] = array( 'client' => $client_id, 'rows' => $rows, 'source' => $source );
		return array( 'saved' => count( $rows ), 'failed' => array() );
	}
}

require dirname( __DIR__, 2 ) . '/portal/class-mcp-server.php';

$rc = new ReflectionClass( 'Six_MCP_Server' );
$tools_m = $rc->getMethod( 'tools' ); $tools_m->setAccessible( true );
$tools = $tools_m->invoke( null );
$by_name = array();
foreach ( $tools as $t ) $by_name[ $t['name'] ] = $t;

// ── The tools exist and are described for a model that has never seen us ──
foreach ( array( 'log_work', 'list_work_log', 'propose_metrics', 'record_entity_metrics' ) as $n ) {
	check( "$n is declared", isset( $by_name[ $n ] ) );
}

$lw = $by_name['log_work'] ?? array();
check( 'log_work says to log every change, one call per change',
	stripos( $lw['description'] ?? '', 'one call per change' ) !== false );
check( 'and insists on before and after rather than a bare "changed"',
	stripos( $lw['description'] ?? '', 'is not evidence' ) !== false, $lw['description'] ?? '' );
check( 'and says it is not SEO-only',
	stripos( $lw['description'] ?? '', 'Google Ads' ) !== false
	&& stripos( $lw['description'] ?? '', 'website build' ) !== false );
check( 'log_work requires only a client and a title',
	( $lw['inputSchema']['required'] ?? array() ) === array( 'client_id', 'title' ) );
foreach ( array( 'before_value', 'after_value', 'category', 'status', 'verify_how', 'performed_at' ) as $f ) {
	check( "log_work takes $f", isset( $lw['inputSchema']['properties'][ $f ] ) );
}

$pm = $by_name['propose_metrics'] ?? array();
check( 'propose_metrics states plainly that nothing reaches the customer unaccepted',
	stripos( $pm['description'] ?? '', 'until a human accepts it' ) !== false, $pm['description'] ?? '' );
check( 'and tells the session to propose freely rather than self-censor',
	stripos( $pm['description'] ?? '', 'propose freely' ) !== false );
check( 'and requires the period, because a figure without one is not checkable',
	stripos( $pm['description'] ?? '', 'period_start' ) !== false
	&& stripos( $pm['description'] ?? '', 'not checkable' ) !== false );
check( 'and keeps the estimate/measurement distinction alive in the advisor UI',
	stripos( $pm['description'] ?? '', 'estimate' ) !== false );

$re = $by_name['record_entity_metrics'] ?? array();
check( 'record_entity_metrics says to capture a reading BEFORE publishing',
	stripos( $re['description'] ?? '', 'before publishing' ) !== false, $re['description'] ?? '' );
check( 'and explains the portal already syncs Search Console pages itself',
	stripos( $re['description'] ?? '', 'syncs Search Console pages daily' ) !== false );

// ── Every writing tool is behind the write scope ─────────────────────────
$src = file_get_contents( dirname( __DIR__, 2 ) . '/portal/class-mcp-server.php' );
preg_match( "/in_array\(\s*\\\$name,\s*array\(([^)]*)\)/", $src, $m );
$gated = $m[1] ?? '';
foreach ( array( 'log_work', 'propose_metrics', 'record_entity_metrics' ) as $n ) {
	check( "$n needs a write-scoped token", strpos( $gated, "'$n'" ) !== false, $gated );
}
check( 'list_work_log is NOT gated — reading what we did is not a write',
	strpos( $gated, "'list_work_log'" ) === false );

// ── Dispatch ─────────────────────────────────────────────────────────────
$call = $rc->getMethod( 'call_tool' ); $call->setAccessible( true );
function body( $res ) {
	$txt = $res['result']['content'][0]['text'] ?? '{}';
	return json_decode( $txt, true );
}
/** A request object carrying a write token, shaped like the real one. */
class Six_Test_Request {
	public function get_header( $h ) { return $h === 'authorization' ? 'Bearer write-token' : ''; }
	public function get_param( $k ) { return null; }
	public function set_param( $k, $v ) {}
	public function set_header( $k, $v ) {}
	public function set_body( $b ) {}
}

Six_Tracking::$logged = array();
$res = $call->invoke( null, 1, array( 'name' => 'log_work', 'arguments' => array(
	'client_id'    => 195,
	'service_slug' => 'seo',
	'category'     => 'Title Tags',
	'entity'       => 'https://nrlawyers.com/sex-crimes/',
	'title'        => 'Retitled the sexual assault page',
	'before_value' => 'Lawyers For Sexual Crime Charges',
	'after_value'  => 'Sexual Assault Lawyer Toronto | Neuberger & Partners',
) ), new Six_Test_Request() );
$out = body( $res );
check( 'log_work passes the change through to the tracking layer', count( Six_Tracking::$logged ) === 1 );
check( 'and marks the origin as claude, not advisor',
	( Six_Tracking::$logged[0]['row']['origin'] ?? '' ) === 'claude' );
check( 'and carries before_value through unaltered',
	( Six_Tracking::$logged[0]['row']['before_value'] ?? '' ) === 'Lawyers For Sexual Crime Charges' );
check( 'and reports back that it was logged', ! empty( $out['logged'] ) && ( $out['id'] ?? 0 ) === 42 );
check( 'and says where it will show up', stripos( $out['note'] ?? '', 'optimization proof' ) !== false, json_encode( $out ) );

Six_Tracking::$log_ok = false;
$out = body( $call->invoke( null, 1, array( 'name' => 'log_work',
	'arguments' => array( 'client_id' => 195, 'title' => 'x' ) ), new Six_Test_Request() ) );
check( 'a failed write is reported as an error, never as logged',
	( $out['error'] ?? '' ) === 'not_logged' && stripos( $out['message'], 'six_work_log' ) !== false,
	json_encode( $out ) );
Six_Tracking::$log_ok = true;

Six_Tracking::$proposed = array();
$out = body( $call->invoke( null, 1, array( 'name' => 'propose_metrics', 'arguments' => array(
	'client_id'    => 195,
	'service_slug' => 'seo',
	'metrics'      => array(
		array( 'metric_key' => 'organic_visitors', 'current_value' => '3390',
		       'period_start' => '2026-10-15', 'period_end' => '2026-11-12', 'source' => 'GA4' ),
	),
) ), new Six_Test_Request() ) );
check( 'propose_metrics reaches the tracking layer', count( Six_Tracking::$proposed ) === 1 );
check( 'and is stamped as coming from claude',
	( Six_Tracking::$proposed[0]['origin'] ?? '' ) === 'claude' );
check( 'and reports how many were staged', ( $out['proposed'] ?? 0 ) === 1 );
check( 'and says explicitly that the customer does not see it yet',
	stripos( $out['note'] ?? '', 'do not reach the customer' ) !== false, json_encode( $out ) );

$out = body( $call->invoke( null, 1, array( 'name' => 'propose_metrics',
	'arguments' => array( 'client_id' => 195, 'metrics' => array() ) ), new Six_Test_Request() ) );
check( 'proposing nothing is an error with an instruction, not a silent success',
	( $out['error'] ?? '' ) === 'no_metrics' && stripos( $out['message'], 'current_value' ) !== false );

Six_Tracking::$entities = array();
$out = body( $call->invoke( null, 1, array( 'name' => 'record_entity_metrics', 'arguments' => array(
	'client_id' => 195,
	'source'    => 'Ahrefs rank tracker',
	'entities'  => array( array( 'entity' => 'sexual assault lawyer toronto', 'entity_type' => 'keyword', 'position' => 12.4 ) ),
) ), new Six_Test_Request() ) );
check( 'record_entity_metrics reaches the tracking layer', count( Six_Tracking::$entities ) === 1 );
check( 'and keeps the source it was told', Six_Tracking::$entities[0]['source'] === 'Ahrefs rank tracker' );
check( 'and reports the count recorded', ( $out['recorded'] ?? 0 ) === 1 );

$out = body( $call->invoke( null, 1, array( 'name' => 'list_work_log',
	'arguments' => array( 'client_id' => 195 ) ), new Six_Test_Request() ) );
check( 'list_work_log returns the entries and the rollup together',
	isset( $out['entries'][0]['title'] ) && isset( $out['by_category'][0]['completion'] ), json_encode( $out ) );

// ── The server instructions teach the workflow ───────────────────────────
$init = $rc->getMethod( 'initialize' ); $init->setAccessible( true );
$ins  = $init->invoke( null, array() )['instructions'];
check( 'the instructions tell a session to log what it changes',
	stripos( $ins, 'log_work' ) !== false && stripos( $ins, 'did not happen' ) !== false );
check( 'and that figures go to the advisor rather than the customer',
	stripos( $ins, 'never straight to the customer' ) !== false, substr( $ins, -600 ) );
check( 'and that the log is not an SEO-only idea',
	stripos( $ins, 'not only SEO' ) !== false );

echo "\nPassed: $pass  Failed: $fail\n";
exit( $fail ? 1 : 0 );
