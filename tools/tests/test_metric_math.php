<?php
/**
 * Progress bars and trend arrows.
 *
 * The dashboard computed `current ÷ target` for every metric, which is
 * backwards for the ones that improve by falling. An average position of 11.3
 * against a target of 6 rendered as a full green bar at 100%, and so did a
 * page load of 1,200ms against 400. Both say "done" about work that has not
 * started, which is the single most misleading thing a client dashboard can do.
 *
 * The same inversion ran through the trend arrows: every fall was red, so an
 * average position improving from 18 to 11 was painted as a loss.
 *
 *     php tools/tests/test_metric_math.php
 */
define( 'ABSPATH', '/x/' );
function add_action() {}
function add_filter() {}
function get_user_meta( ...$a ) { return ''; }
function get_option( $k, $d = '' ) { return $d; }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) $k ) ); }
function wp_json_encode( $d ) { return json_encode( $d ); }

require dirname( __DIR__, 2 ) . '/portal/class-dashboard-metrics.php';

$pass = 0; $fail = 0;
function check( $name, $ok, $detail = '' ) {
	global $pass, $fail;
	if ( $ok ) { $pass++; echo "PASS  $name\n"; }
	else { $fail++; echo "FAIL  $name\n" . ( $detail ? "        $detail\n" : '' ); }
}

// ── The bug from the screenshot ──────────────────────────────────────────
$p = Six_Dash_Metrics::progress( 11.3, 6, true );
check( 'avg position 11.3 against a target of 6 is NOT complete',
	$p['pct'] < 100 && ! $p['met'], json_encode( $p ) );
check( 'and reads as roughly half way, not nearly none',
	$p['pct'] > 40 && $p['pct'] < 60, 'got ' . $p['pct'] . '%' );

$p = Six_Dash_Metrics::progress( 1200, 400, true );
check( 'page load 1200ms against a target of 400ms is not complete either',
	$p['pct'] < 100 && ! $p['met'], json_encode( $p ) );

// ── Targets actually met ─────────────────────────────────────────────────
check( 'a lower-is-better metric at its target is 100%',
	Six_Dash_Metrics::progress( 6, 6, true )['met'] === true );
check( 'and past its target is still 100%, not 150%',
	Six_Dash_Metrics::progress( 3, 6, true )['pct'] === 100 );
check( 'a higher-is-better metric at its target is 100%',
	Six_Dash_Metrics::progress( 4000, 4000 )['met'] === true );
check( 'and past it is capped at 100%',
	Six_Dash_Metrics::progress( 9000, 4000 )['pct'] === 100 );

// ── Short of the target, nothing reaches 100 ─────────────────────────────
check( 'organic visitors 3390 of 4000 is 85%',
	Six_Dash_Metrics::progress( 3390, 4000 )['pct'] === 85,
	json_encode( Six_Dash_Metrics::progress( 3390, 4000 ) ) );
check( 'a hair short of the target never rounds up to 100',
	Six_Dash_Metrics::progress( 3999, 4000 )['pct'] === 99 );
check( 'and a hair short on a falling metric does not either',
	Six_Dash_Metrics::progress( 6.01, 6, true )['pct'] === 99 );

// ── A starting point gives the honest reading ────────────────────────────
// Began at 18, aiming for 6, now at 12: half the distance travelled.
$p = Six_Dash_Metrics::progress( 12, 6, true, 18 );
check( 'with a start, progress is the distance travelled',
	$p['pct'] === 50, json_encode( $p ) );
check( 'and says so, so the figure can be argued with',
	strpos( $p['reason'], 'starting point' ) !== false );

$p = Six_Dash_Metrics::progress( 2000, 4000, false, 1000 );
check( 'the same for a rising metric: 1000 → 4000, now 2000, is a third',
	$p['pct'] === 33, json_encode( $p ) );

// A start on the wrong side of the target is not usable, and the ratio is
// used instead rather than producing a negative or an over-100 bar.
$p = Six_Dash_Metrics::progress( 12, 6, true, 4 );
check( 'a start already past the target falls back to the ratio',
	$p['pct'] > 0 && $p['pct'] < 100 && strpos( $p['reason'], 'ratio' ) !== false,
	json_encode( $p ) );

// ── No target is not zero progress ───────────────────────────────────────
foreach ( array( '', null, '–', 'n/a' ) as $empty ) {
	$p = Six_Dash_Metrics::progress( 2, $empty, true );
	check( 'a target of ' . var_export( $empty, true ) . ' gives null, not 0%',
		$p['pct'] === null, json_encode( $p ) );
}
check( 'and says why', Six_Dash_Metrics::progress( 2, '', true )['reason'] === 'no target set' );
check( 'nothing measured yet is also null rather than 0%',
	Six_Dash_Metrics::progress( '', 400, true )['pct'] === null );

// ── Trend arrows point the right way ─────────────────────────────────────
$c = Six_Dash_Metrics::change( 11, 18, true );
check( 'average position falling from 18 to 11 is an improvement',
	$c['good'] === true && $c['arrow'] === '↓', json_encode( $c ) );
$c = Six_Dash_Metrics::change( 18, 11, true );
check( 'and rising from 11 to 18 is not',
	$c['good'] === false && $c['arrow'] === '↑', json_encode( $c ) );
$c = Six_Dash_Metrics::change( 3390, 3260 );
check( 'visitors rising is an improvement', $c['good'] === true && $c['pct'] === 4 );
$c = Six_Dash_Metrics::change( 3260, 3390 );
check( 'and visitors falling is not', $c['good'] === false );
check( 'no previous figure means no arrow rather than a fake 0%',
	Six_Dash_Metrics::change( 100, '' )['pct'] === null );
check( 'a previous of zero cannot produce a percentage change',
	Six_Dash_Metrics::change( 100, 0 )['pct'] === null );
check( 'a move too small to mean anything is neither good nor bad',
	Six_Dash_Metrics::change( 1000, 1001 )['good'] === null );

// ── Direction is known for metrics the advisor typed by label ────────────
foreach ( array( 'Avg Position', 'On-page Issues', 'Page Load (ms)', 'Avg Cost / Click' ) as $label ) {
	check( "'$label' is known to improve by falling",
		Six_Dash_Metrics::lower_is_better( $label ) === true );
}
foreach ( array( 'Organic Visitors', 'Keywords on Page 1', 'Ad Clicks', 'Map Views' ) as $label ) {
	check( "'$label' is known to improve by rising",
		Six_Dash_Metrics::lower_is_better( $label ) === false );
}
check( 'a label nobody has seen before defaults to rising',
	Six_Dash_Metrics::lower_is_better( 'Widgets Produced' ) === false );
check( 'but one that plainly counts a problem is caught by its wording',
	Six_Dash_Metrics::lower_is_better( 'Crawl Errors' ) === true );

// ── The two health metrics can now hold a target at all ──────────────────
$editable = Six_Dash_Metrics::editable_metrics( array( 'seo' ), false );
check( 'onpage_issues is an editable metric, so it can be given a target',
	isset( $editable['onpage_issues'] ), implode( ', ', array_keys( $editable ) ) );
check( 'page_load_ms too', isset( $editable['page_load_ms'] ) );

// They must not have displaced a stat card: cards() fills its slots from the
// first keys of the service, and visitors has to stay in the top row.
$cat = Six_Dash_Metrics::catalog();
$first_four = array_slice( array_keys( $cat['seo']['metrics'] ), 0, 4 );
check( 'and neither displaced a stat card from the top row',
	! in_array( 'onpage_issues', $first_four, true ) && ! in_array( 'page_load_ms', $first_four, true ),
	implode( ', ', $first_four ) );
check( 'organic visitors is still the first SEO card',
	$first_four[0] === 'organic_visitors' );

// ── number() tells a missing figure from a zero ──────────────────────────
check( 'an empty string is no figure, not zero', Six_Dash_Metrics::number( '' ) === null );
check( 'an em dash is no figure', Six_Dash_Metrics::number( '–' ) === null );
check( 'a real zero is zero', Six_Dash_Metrics::number( '0' ) === 0.0 );
check( 'a formatted figure is read', Six_Dash_Metrics::number( '3,390' ) === 3390.0 );
check( 'and a currency figure is read', Six_Dash_Metrics::number( '$1,240.50' ) === 1240.50 );

echo "\nPassed: $pass  Failed: $fail\n";
exit( $fail ? 1 : 0 );
