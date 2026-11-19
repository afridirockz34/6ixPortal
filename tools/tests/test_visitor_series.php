<?php
/**
 * The overview chart draws from whatever data exists.
 *
 * Reported from live use, after a release that removed the benchmark
 * fallbacks: "The overview graph is not loading now even though I have
 * backfilled the numbers, synced the numbers, selected the day we started."
 *
 * All three of those were the right things to do. The bug was that
 * visitor_series() gave up before it looked: an early return fired when no
 * connector reported a visitor count, and the daily series — the table the
 * backfill had just filled — was read three lines further down. Removing the
 * benchmark that used to keep `$current` non-zero exposed it.
 *
 * Backfilled readings are the best data we have. Nothing is decided before
 * they are looked at.
 *
 *     php tools/tests/test_visitor_series.php
 */
define( 'ABSPATH', '/x/' );
define( 'DAY_IN_SECONDS', 86400 );

$pass = 0; $fail = 0;
function check( $name, $ok, $detail = '' ) {
	global $pass, $fail;
	if ( $ok ) { $pass++; echo "PASS  $name\n"; }
	else { $fail++; echo "FAIL  $name\n" . ( $detail ? "        $detail\n" : '' ); }
}

// Nothing connected, nothing in any option: the exact state of a client
// whose numbers came from a backfill rather than from a live connector.
function get_option( $k, $d = '' ) { return $d; }
function get_user_meta( ...$a ) { return ''; }
function add_action( ...$a ) {} function add_filter( ...$a ) {}
function apply_filters( $tag, $v ) { return $v; }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) $k ) ); }
function wp_json_encode( $d ) { return json_encode( $d ); }
function date_i18n( $f, $t ) { return gmdate( $f, $t ); }
function current_time( $t ) { return $t === 'timestamp' ? time() : gmdate( 'Y-m-d H:i:s' ); }

/** 40 days of readings, the last 30 of which are the window. */
function six_fake_days( $n = 40, $start = '2026-10-01' ) {
	$dates = array(); $vals = array();
	for ( $i = 0; $i < $n; $i++ ) {
		$dates[] = gmdate( 'Y-m-d', strtotime( $start . ' +' . $i . ' days' ) );
		$vals[]  = 200 + $i * 4 + ( $i % 7 === 6 ? -40 : 0 );
	}
	return array( $dates, $vals );
}

class Six_Tracking {
	public static $has = true;
	public static $projected = true;
	public static $start = '2026-10-21';

	public static function graph_series( $uid, $key = '', $days = 180 ) {
		if ( ! self::$has ) return array( 'has' => false, 'note' => 'none' );
		list( $dates, $vals ) = six_fake_days();
		$proj = array();
		foreach ( $vals as $i => $v ) $proj[] = 200 + $i * 1.2;
		return array(
			'has'       => true,
			'metric'    => 'gsc_clicks',
			'dates'     => $dates,
			'actual'    => $vals,
			'projected' => self::$projected ? $proj : array(),
			'start'     => self::$start,
			'fitted_on' => 20,
			'source'    => 'gsc',
			'blocker'   => self::$projected ? '' : 'too_few_before',
			'note'      => '',
		);
	}
	public static function engagement_start( $uid ) { return self::$start; }
}

require dirname( __DIR__, 2 ) . '/portal/class-dashboard-metrics.php';

$CTX = array( 'slugs' => array( 'seo' ), 'budgets' => array( 'seo' => 1500 ), 'kpi' => array(), 'cpc' => 0 );

// ── The regression ───────────────────────────────────────────────────────
$v = Six_Dash_Metrics::visitor_series( 7, $CTX );
check( 'a backfilled client gets a chart even though no connector reports',
	! empty( $v['has'] ),
	'has=' . var_export( $v['has'], true ) . ' note=' . ( $v['note'] ?? '' )
	. ' — this is the bug: backfill, sync and a start date all set, and no chart' );
check( 'and it is the measured daily series, not a modelled one',
	( $v['source'] ?? '' ) === 'measured' && ! empty( $v['daily'] ) );
check( 'showing thirty days', count( $v['points'] ?? array() ) === 30,
	count( $v['points'] ?? array() ) . ' points' );
check( 'with a headline figure summed from the real readings',
	( $v['current_mo'] ?? 0 ) > 0 );

// ── The split, and the second line ───────────────────────────────────────
check( 'the split lands on the day work began',
	( $v['split_on'] ?? '' ) === '2026-10-21', $v['split_on'] ?? 'none' );
check( 'the "without us" line is drawn', ( $v['projected_from'] ?? '' ) === 'fit' );
$before = array_column( $v['points'], 'before' );
check( 'and only from the split onwards',
	$before[0] === null && $before[ count( $before ) - 1 ] !== null,
	'first=' . var_export( $before[0], true ) . ' last=' . var_export( end( $before ), true ) );

// No start date recorded: one line, and the chart says which date is missing.
Six_Tracking::$start = '';
$v2 = Six_Dash_Metrics::visitor_series( 7, $CTX );
check( 'with no start date there is still a chart', ! empty( $v2['has'] ) );
check( 'but only one line', ( $v2['projected_from'] ?? '' ) === 'none' );
check( 'and the blocker names the missing date', ( $v2['blocker'] ?? '' ) === 'no_start',
	$v2['blocker'] ?? 'none' );
Six_Tracking::$start = '2026-10-21';

// Not enough pre-engagement history: one line, and a different reason.
Six_Tracking::$projected = false;
$v3 = Six_Dash_Metrics::visitor_series( 7, $CTX );
check( 'too little history before the start still draws the measured line',
	! empty( $v3['has'] ) && ( $v3['projected_from'] ?? '' ) === 'none' );
check( 'with its own reason, not the start-date one',
	( $v3['blocker'] ?? '' ) === 'too_few_before', $v3['blocker'] ?? 'none' );
Six_Tracking::$projected = true;

// ── Genuinely nothing: say so, do not invent ─────────────────────────────
Six_Tracking::$has = false;
$v4 = Six_Dash_Metrics::visitor_series( 7, $CTX );
check( 'a client with no readings at all gets no chart', empty( $v4['has'] ) );
check( 'and is told what to connect',
	strpos( $v4['note'] ?? '', 'Search Console' ) !== false, $v4['note'] ?? '' );
Six_Tracking::$has = true;

// ── The ordering that caused it, asserted directly ───────────────────────
$src = file_get_contents( dirname( __DIR__, 2 ) . '/portal/class-dashboard-metrics.php' );
$fn  = substr( $src, strpos( $src, 'public static function visitor_series' ) );
$read_daily = strpos( $fn, 'Six_Tracking::graph_series' );
$give_up    = strpos( $fn, 'Nothing is reporting visitors yet' );
check( 'the daily series is read before the function gives up',
	$read_daily !== false && $give_up !== false && $read_daily < $give_up,
	'the backfilled table was read three lines after the early return that skipped it' );

echo "\nPassed: $pass  Failed: $fail\n";
exit( $fail ? 1 : 0 );
