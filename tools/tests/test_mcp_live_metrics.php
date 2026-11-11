<?php
/**
 * The MCP connector's live integration reads.
 *
 * The portal exists so that a session doing client work can read the accounts
 * an advisor connected. The thing that has to be right is not the happy path
 * but the three ways a source can fail to give a number — not connected,
 * errored, or connected and genuinely zero. Those are different facts, and
 * collapsing them is how a report ends up asserting "0 sessions" about a
 * business that simply has not linked their analytics.
 *
 *     php tools/tests/test_mcp_live_metrics.php
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
function __( $s ) { return $s; }

// Stand-ins for the integration classes, so this tests the connector's own
// decisions rather than Google's.
class Six_Analytics {
	public static $ga4, $gsc;
	public static function ga4_summary( $p, $d = 30 ) { return self::$ga4; }
	public static $gsc_rows_asked = null;
	public static function gsc_summary( $s, $d = 28, $rows = 15 ) { self::$gsc_rows_asked = $rows; return self::$gsc; }
	public static function pagespeed( $u, $s = 'mobile' ) { return array( 'url' => $u, 'score' => 71 ); }
}
class Six_Google_Ads {
	public static $ret = false; public static $err = 'boom';
	public static function get_campaign_metrics_for_client( $c, $r = '', $f = false ) { return self::$ret; }
	public static function get_last_error() { return self::$err; }
}
class Six_Meta {
	public static $ret;
	public static function client_report( $c, $d = 30 ) { return self::$ret; }
}
class Six_Profile_API {
	public static function writable_fields() { return array(); }
}

require dirname( __DIR__, 2 ) . '/portal/class-mcp-server.php';

$rc = new ReflectionClass( 'Six_MCP_Server' );
$live = $rc->getMethod( 'live_metrics' ); $live->setAccessible( true );
$gq   = $rc->getMethod( 'gsc_queries' );  $gq->setAccessible( true );
$LM = function ( $days = 30, $sources = array() ) use ( $live ) {
	return $live->invoke( null, 195, $days, $sources );
};

$GA4_OK = array(
	'property_id' => '563571892', 'period_days' => 30,
	'totals' => array( 'sessions' => 1840, 'users' => 1402, 'pageviews' => 5210, 'conversions' => 12.0 ),
	'by_channel' => array(
		array( 'channel' => 'Organic Search', 'sessions' => 1120, 'users' => 900, 'conversions' => 8 ),
		array( 'channel' => 'Direct',         'sessions' => 500,  'users' => 400, 'conversions' => 3 ),
		array( 'channel' => 'Paid Search',    'sessions' => 220,  'users' => 102, 'conversions' => 1 ),
	),
);
$GSC_OK = array(
	'site' => 'https://muddyyorkfishing.ca/', 'period' => '2026-10-13 to 2026-11-09',
	'totals' => array( 'clicks' => 310, 'impressions' => 9100, 'avg_ctr' => 3.4, 'avg_position' => 18.2 ),
	'top_queries' => array(
		array( 'query' => 'fly fishing ontario', 'clicks' => 90, 'impressions' => 4000, 'ctr' => 2.2, 'position' => 12.1 ),
		array( 'query' => 'trout rivers',        'clicks' => 40, 'impressions' => 1200, 'ctr' => 3.3, 'position' => 9.4 ),
	),
);

// ── 1. Nothing connected: four distinct "not_connected", no zeros ──────────
$GLOBALS['META'] = array(); $GLOBALS['OPTS'] = array();
$r = $LM();
foreach ( array( 'ga4', 'search_console', 'google_ads', 'meta_ads' ) as $s ) {
	check( "$s reports not_connected rather than a number",
		( $r['sources'][ $s ]['status'] ?? '' ) === 'not_connected', json_encode( $r['sources'][ $s ] ?? null ) );
}
check( 'no source is reported as measured', $r['measured_sources'] === array() );
check( 'the guidance says everything must be labelled an estimate',
	stripos( $r['guidance'], 'must be labelled an estimate' ) !== false, $r['guidance'] );
check( 'and names connecting analytics as itself a finding',
	stripos( $r['guidance'], 'itself a finding' ) !== false, $r['guidance'] );

// ── 2. GA4 connected and answering ─────────────────────────────────────────
$GLOBALS['META']['six_ga4_property_id'] = '563571892';
Six_Analytics::$ga4 = $GA4_OK;
$r = $LM();
check( 'ga4 reports ok', ( $r['sources']['ga4']['status'] ?? '' ) === 'ok' );
check( 'ga4 is flagged measured', ! empty( $r['sources']['ga4']['measured'] ) );
check( 'organic_sessions is the Organic Search channel only',
	$r['sources']['ga4']['organic_sessions'] === 1120,
	'got ' . json_encode( $r['sources']['ga4']['organic_sessions'] ?? null ) . ' — total is 1840' );
check( 'totals.sessions keeps the all-channel figure',
	$r['sources']['ga4']['totals']['sessions'] === 1840 );
check( 'the note warns the two are different',
	stripos( $r['sources']['ga4']['note'], 'Organic Search channel only' ) !== false );
check( 'measured_sources lists ga4 and nothing else', $r['measured_sources'] === array( 'ga4' ) );
check( 'guidance switches to state-as-fact',
	stripos( $r['guidance'], 'state them as fact' ) !== false, $r['guidance'] );
check( 'and still protects Ahrefs for competitors',
	stripos( $r['guidance'], 'competitors, backlinks' ) !== false );
check( 'and forbids mixing measured with estimated series',
	stripos( $r['guidance'], 'Never plot a measured series' ) !== false );

// A property with no organic channel at all must not report 0 as if measured.
Six_Analytics::$ga4 = array( 'property_id' => '1', 'totals' => array( 'sessions' => 10 ), 'by_channel' => array(
	array( 'channel' => 'Direct', 'sessions' => 10 ) ) );
$r = $LM();
check( 'no organic channel yields null, not a confident zero',
	$r['sources']['ga4']['organic_sessions'] === null,
	json_encode( $r['sources']['ga4']['organic_sessions'] ) );
Six_Analytics::$ga4 = $GA4_OK;

// ── 3. A source that errors is not a source that is absent ─────────────────
Six_Analytics::$ga4 = array( 'error' => 'GA4: Google refused access to property 563571892.' );
$r = $LM();
check( 'an erroring ga4 reports error, not not_connected',
	( $r['sources']['ga4']['status'] ?? '' ) === 'error' );
check( 'and carries the reason through',
	stripos( $r['sources']['ga4']['message'], 'refused access' ) !== false );
check( 'an errored source is not counted as measured', $r['measured_sources'] === array() );
Six_Analytics::$ga4 = $GA4_OK;

// ── 4. Search Console in the summary, queries in their own tool ────────────
$GLOBALS['META']['six_gsc_site'] = 'https://muddyyorkfishing.ca/';
Six_Analytics::$gsc = $GSC_OK;
$r = $LM();
check( 'search_console reports ok', ( $r['sources']['search_console']['status'] ?? '' ) === 'ok' );
check( 'its totals survive', ( $r['sources']['search_console']['totals']['clicks'] ?? 0 ) === 310 );
check( 'the query list is NOT duplicated into the summary',
	! isset( $r['sources']['search_console']['top_queries'] ),
	'it belongs to get_search_console_queries; duplicating it bloats every call' );
check( 'and the note points at the dedicated tool',
	stripos( $r['sources']['search_console']['note'], 'get_search_console_queries' ) !== false );

// ── 5. Ads and Meta ────────────────────────────────────────────────────────
$GLOBALS['META']['six_gads_customer_id'] = '123-456-7890';
Six_Google_Ads::$ret = array( 'clicks' => 220, 'impressions' => 8000, 'conversions' => 9 );
$GLOBALS['META']['six_meta_ad_account_id'] = 'act_1';
Six_Meta::$ret = array( 'spend' => 410.5, 'clicks' => 900 );
$r = $LM();
check( 'google_ads reports ok with its figures',
	( $r['sources']['google_ads']['status'] ?? '' ) === 'ok' && $r['sources']['google_ads']['clicks'] === 220 );
check( 'meta_ads reports ok with its figures',
	( $r['sources']['meta_ads']['status'] ?? '' ) === 'ok' && $r['sources']['meta_ads']['spend'] === 410.5 );
check( 'all four now count as measured', count( $r['measured_sources'] ) === 4, json_encode( $r['measured_sources'] ) );

Six_Google_Ads::$ret = false;
$r = $LM();
check( 'ads returning nothing is an error carrying get_last_error',
	( $r['sources']['google_ads']['status'] ?? '' ) === 'error'
	&& stripos( $r['sources']['google_ads']['message'], 'boom' ) !== false,
	json_encode( $r['sources']['google_ads'] ) );
Six_Google_Ads::$ret = array( 'clicks' => 220 );

Six_Meta::$ret = array( 'error' => 'Meta token expired.' );
$r = $LM();
check( 'a Meta error is reported as an error with its reason',
	( $r['sources']['meta_ads']['status'] ?? '' ) === 'error'
	&& stripos( $r['sources']['meta_ads']['message'], 'token expired' ) !== false );
Six_Meta::$ret = array( 'spend' => 410.5 );

// ── 6. The sources filter and day clamping ─────────────────────────────────
$r = $LM( 30, array( 'ga4' ) );
check( 'a sources filter returns only what was asked for',
	array_keys( $r['sources'] ) === array( 'ga4' ), json_encode( array_keys( $r['sources'] ) ) );
$r = $LM( 30, array( 'ga4', 'nonsense' ) );
check( 'an unknown source name is ignored rather than erroring',
	array_keys( $r['sources'] ) === array( 'ga4' ) );
check( 'days clamps above 365', ( $LM( 9999 ) )['period_days'] === 365 );
check( 'days clamps below 1', ( $LM( -5 ) )['period_days'] === 30 );
check( 'zero days falls back to the default', ( $LM( 0 ) )['period_days'] === 30 );

// ── 7. get_search_console_queries ──────────────────────────────────────────
$q = $gq->invoke( null, 195, 28, 25 );
check( 'queries come back when connected', count( $q['queries'] ?? array() ) === 2 );
check( 'and are flagged measured', ! empty( $q['measured'] ) );
check( 'totals come with them', ( $q['totals']['impressions'] ?? 0 ) === 9100 );
check( 'the note names the three findings to look for',
	stripos( $q['note'], 'near-zero CTR' ) !== false && stripos( $q['note'], '11-20' ) !== false );
$q = $gq->invoke( null, 195, 28, 1 );
check( 'limit is honoured', count( $q['queries'] ) === 1 );
$q = $gq->invoke( null, 195, 28, 9999 );
check( 'an absurd limit does not error', isset( $q['queries'] ) );

// The cap that made every caller's limit a lie: gsc_summary asked Google for
// 15 rows no matter what, so a limit of 50 could only ever return 15.
Six_Analytics::$gsc_rows_asked = null;
$gq->invoke( null, 195, 28, 50 );
check( 'the requested limit reaches Google, not a hard-coded 15',
	Six_Analytics::$gsc_rows_asked === 50,
	'asked for ' . var_export( Six_Analytics::$gsc_rows_asked, true ) );
$gq->invoke( null, 195, 28, 100 );
check( 'and the maximum limit does too', Six_Analytics::$gsc_rows_asked === 100 );
$gq->invoke( null, 195, 28, 0 );
check( 'an unset limit asks for the default rather than everything',
	Six_Analytics::$gsc_rows_asked === 25, var_export( Six_Analytics::$gsc_rows_asked, true ) );

unset( $GLOBALS['META']['six_gsc_site'] );
$q = $gq->invoke( null, 195, 28, 25 );
check( 'without Search Console it explains rather than returning empty',
	( $q['error'] ?? '' ) === 'not_connected'
	&& stripos( $q['message'], 'Data Sources tab' ) !== false, json_encode( $q ) );
check( 'and says what the figures must be labelled instead',
	stripos( $q['message'], 'labelled estimates' ) !== false );

$GLOBALS['META']['six_gsc_site'] = 'x';
Six_Analytics::$gsc = array( 'error' => 'Search Console API is not enabled.' );
$q = $gq->invoke( null, 195, 28, 25 );
check( 'a Search Console error is passed through, not swallowed',
	( $q['error'] ?? '' ) === 'search_console_error'
	&& stripos( $q['message'], 'not enabled' ) !== false );

// ── 8. The connector declares the tools it implements ──────────────────────
$src = file_get_contents( dirname( __DIR__, 2 ) . '/portal/class-mcp-server.php' );
foreach ( array( 'get_live_metrics', 'get_search_console_queries', 'get_pagespeed' ) as $t ) {
	check( "$t is declared, dispatched and reachable",
		substr_count( $src, "'$t'" ) >= 2, 'declared in tools() and handled in call_tool()' );
}
check( 'the server instructions explain measured vs estimated',
	stripos( $src, 'MEASURED and may be stated as fact' ) !== false );
check( 'and tell a session to read live data before writing numbers',
	stripos( $src, 'before writing numbers into an audit' ) !== false );

echo "\nPassed: $pass  Failed: $fail\n";
exit( $fail ? 1 : 0 );
