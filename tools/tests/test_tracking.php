<?php
/**
 * The tracking layer's arithmetic.
 *
 * The visitors chart is the most prominent claim the portal makes — "where you
 * were heading without us, versus where you are now" — and a quietly wrong
 * slope on it looks exactly as convincing as a right one. So the fit is pure
 * arithmetic, separated from the database, and tested on numbers whose answer
 * is known.
 *
 * The questions: does a known line come back exactly, does a flat series stay
 * flat, is a projection refused when there is not enough history to fit one,
 * and does a downward trend stop at zero instead of forecasting negative
 * visitors.
 *
 *     php tools/tests/test_tracking.php
 */
define( 'ABSPATH', '/x/' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );

// The pure helpers under test touch none of these; they exist so the file
// parses and the class loads outside WordPress.
function add_action( ...$a ) {}
function wp_next_scheduled( ...$a ) { return true; }
function wp_schedule_event( ...$a ) {}
function get_current_user_id() { return 0; }
function current_time( $f ) { return $f === 'mysql' ? gmdate( 'Y-m-d H:i:s' ) : gmdate( $f ); }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function esc_url_raw( $u ) { return (string) $u; }
function wp_kses_post( $s ) { return (string) $s; }
function wp_json_encode( $d ) { return json_encode( $d ); }
function get_user_meta( ...$a ) { return ''; }
function six_table_exists( $t ) { return false; }

// Every read and write below is meant to bail before touching the database.
// The stub is here so that a path which forgot to bail fails loudly on a
// missing method rather than quietly on a null.
class Six_Test_WPDB {
	public $prefix = 'wp_';
	public $last_error = 'no database in this harness';
	public function __call( $m, $a ) { throw new RuntimeException( "reached \$wpdb->$m()" ); }
}
$GLOBALS['wpdb'] = new Six_Test_WPDB();

require dirname( __DIR__, 2 ) . '/portal/class-tracking.php';

$pass = 0; $fail = 0;
function check( $name, $ok, $detail = '' ) {
	global $pass, $fail;
	if ( $ok ) { $pass++; echo "PASS  $name\n"; }
	else { $fail++; echo "FAIL  $name\n" . ( $detail ? "        $detail\n" : '' ); }
}

/** n daily readings from $from, value = $base + $step * day. */
function series( $from, $n, $base, $step = 0 ) {
	$out = array();
	for ( $i = 0; $i < $n; $i++ ) {
		$out[] = array(
			'date'  => gmdate( 'Y-m-d', strtotime( $from ) + $i * DAY_IN_SECONDS ),
			'value' => $base + $step * $i,
		);
	}
	return $out;
}

function dates_from( $from, $n ) {
	return array_column( series( $from, $n, 0 ), 'date' );
}

// ── A known line comes back exactly ──────────────────────────────────────
// 100 on day 0, rising 2 a day. Day 40 must be 180, not "about" 180.
$before = series( '2026-01-01', 30, 100, 2 );
$points = Six_Tracking::project( $before, dates_from( '2026-01-01', 45 ) );
check( 'a straight line is reproduced at its own points',
	abs( $points['2026-01-01'] - 100 ) < 0.01 && abs( $points['2026-01-10'] - 118 ) < 0.01,
	json_encode( array_slice( $points, 0, 3 ) ) );
// 2026-02-09 is day 39 from 2026-01-01, so 100 + 2*39 = 178. The fit is over
// 30 points and this is evaluated 9 days past the last of them.
check( 'and extended correctly past the fitted range',
	abs( $points['2026-02-09'] - 178 ) < 0.01,
	'day 39 should be 100 + 2*39 = 178. Got ' . ( $points['2026-02-09'] ?? 'nothing' ) );

// ── A flat series projects flat ──────────────────────────────────────────
$flat = Six_Tracking::project( series( '2026-01-01', 30, 500, 0 ), dates_from( '2026-01-01', 60 ) );
check( 'a flat history projects flat', abs( $flat['2026-02-20'] - 500 ) < 0.01,
	'got ' . ( $flat['2026-02-20'] ?? 'nothing' ) );

// ── Not enough history is refused, not guessed ───────────────────────────
$thin = Six_Tracking::project( series( '2026-01-01', 13, 100, 5 ), dates_from( '2026-01-01', 30 ) );
check( '13 readings is refused — one below the minimum', $thin === array(),
	'returned ' . count( $thin ) . ' points' );
$ok14 = Six_Tracking::project( series( '2026-01-01', 14, 100, 5 ), dates_from( '2026-01-01', 30 ) );
check( '14 readings is accepted', count( $ok14 ) === 30 );
check( 'the minimum is stated as a constant, not buried in a condition',
	Six_Tracking::MIN_FIT_POINTS === 14 );

// ── A falling trend stops at zero ────────────────────────────────────────
// 300 falling 10 a day hits zero on day 30 and would be -600 by day 90.
$falling = Six_Tracking::project( series( '2026-01-01', 20, 300, -10 ), dates_from( '2026-01-01', 90 ) );
check( 'a falling trend is clamped at zero rather than forecasting negative visitors',
	min( $falling ) >= 0, 'minimum was ' . min( $falling ) );
check( 'and is still falling before it gets there',
	$falling['2026-01-11'] < $falling['2026-01-02'] );

// ── Degenerate input ─────────────────────────────────────────────────────
$same_day = array();
for ( $i = 0; $i < 20; $i++ ) $same_day[] = array( 'date' => '2026-03-01', 'value' => 10 + $i );
check( 'twenty readings on one day cannot be fitted and are refused',
	Six_Tracking::project( $same_day, dates_from( '2026-03-01', 10 ) ) === array() );
check( 'no dates to evaluate returns nothing rather than an empty line',
	Six_Tracking::project( series( '2026-01-01', 30, 100, 1 ), array() ) === array() );

// ── Noise does not drag the fit off the underlying trend ─────────────────
$noisy = series( '2026-01-01', 60, 1000, 5 );
foreach ( $noisy as $i => &$p ) { $p['value'] += ( $i % 7 === 0 ) ? 180 : -30; }
unset( $p );
$fit = Six_Tracking::project( $noisy, array( '2026-01-01', '2026-03-01' ) );
$per_day = ( $fit['2026-03-01'] - $fit['2026-01-01'] ) / 59;
check( 'a weekly spike does not move the slope far from the real trend',
	abs( $per_day - 5 ) < 1.0, 'slope came out at ' . round( $per_day, 3 ) . ' a day, expected about 5' );

// ── Period labels: the thing the advisor reads on a proposal ─────────────
check( 'a period inside one year reads without repeating the year',
	Six_Tracking::period_label( '2026-10-15', '2026-11-12' ) === '15 Oct – 12 Nov 2026',
	Six_Tracking::period_label( '2026-10-15', '2026-11-12' ) );
check( 'a period spanning a year boundary names both years',
	Six_Tracking::period_label( '2025-12-20', '2026-01-18' ) === '20 Dec 2025 – 18 Jan 2026',
	Six_Tracking::period_label( '2025-12-20', '2026-01-18' ) );
check( 'no period at all is an empty string, not a made-up range',
	Six_Tracking::period_label( '', '' ) === '' );
check( 'one date alone still reads as a date',
	Six_Tracking::period_label( '2026-11-12', '' ) === '12 Nov 2026',
	Six_Tracking::period_label( '2026-11-12', '' ) );

// ── Writes refuse rather than report success when the table is absent ────
// six_table_exists() is stubbed false above and six_create_tables() does not
// exist here, so every write path must report the failure.
$r = Six_Tracking::log_work( 7, array( 'title' => 'Rewrote the bail page' ) );
check( 'a work-log write with no table reports failure', $r['ok'] === false && $r['error'] !== '' );
check( 'and names the table rather than shrugging', strpos( $r['error'], 'six_work_log' ) !== false, $r['error'] );

$r = Six_Tracking::record_daily( 7, array( 'sessions' => 10 ) );
check( 'a daily write with no table reports failure', $r['saved'] === 0 && isset( $r['failed']['_table'] ) );

$r = Six_Tracking::propose_metrics( 7, array( array( 'metric_key' => 'sessions', 'current_value' => '10' ) ) );
check( 'a proposal with no table reports failure', $r['saved'] === 0 && isset( $r['failed']['_table'] ) );

// ── Reads degrade to empty, never to a fatal ─────────────────────────────
check( 'reading the log with no table returns nothing', Six_Tracking::work_log( 7 ) === array() );
check( 'the proof rollup with no table returns nothing', Six_Tracking::proof( 7 ) === array() );
check( 'the daily series with no table returns nothing', Six_Tracking::daily( 7, 'sessions' ) === array() );

echo "\nPassed: $pass  Failed: $fail\n";
exit( $fail ? 1 : 0 );
