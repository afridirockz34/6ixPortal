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

	public static $from = '2026-10-01';
	public static $ndays = 40;

	public static function graph_series( $uid, $key = '', $days = 180 ) {
		if ( ! self::$has ) return array( 'has' => false, 'note' => 'none' );
		list( $dates, $vals ) = six_fake_days( self::$ndays, self::$from );
		// A held level, which is what Six_Tracking::hold_level() now returns.
		$proj = array_fill( 0, count( $vals ), 214.0 );
		return array(
			'has'       => true,
			'metric'    => 'gsc_clicks',
			'dates'     => $dates,
			'actual'    => $vals,
			'projected' => self::$projected ? $proj : array(),
			'start'     => self::$start,
			'fitted_on' => 20,
			'level'     => 214.0,
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

// ── The comparison line is the level, and stays the level ───────────────
// It used to be shifted up to meet the measured figure on the split day, so
// the two lines left from exactly the same point. That was right for a
// fitted slope and wrong for a held level: sliding the level to match one
// day's reading stops it being the level. A small step at the split is the
// truth — "you averaged 214 a day, and on the day we began you were at 230".
$v5 = Six_Dash_Metrics::visitor_series( 7, $CTX );
$after_split = array_values( array_filter( array_column( $v5['points'], 'before' ),
	function ( $b ) { return $b !== null; } ) );
check( 'the comparison line is flat across the whole window',
	count( array_unique( $after_split ) ) === 1,
	json_encode( array_slice( $after_split, 0, 5 ) ) );
check( 'and it is the level itself, not the level shifted to meet the measured line',
	(int) $after_split[0] === 214,
	'drew ' . $after_split[0] . ' against a held level of 214' );
check( 'the level reaches the chart so the caption can state it',
	(int) ( $v5['hold_level'] ?? 0 ) === 214, var_export( $v5['hold_level'] ?? null, true ) );

// The caption names a tool, and names it from the metric that won rather
// than from a source column that can disagree with it.
check( 'the tool is named from the winning metric',
	( $v5['measured_tool'] ?? '' ) === 'Google Search Console',
	$v5['measured_tool'] ?? 'none' );
$tpl = file_get_contents( dirname( __DIR__, 2 ) . '/portal/templates/customer-dashboard.php' );
$dm  = file_get_contents( dirname( __DIR__, 2 ) . '/portal/class-dashboard-metrics.php' );
check( 'and the caption uses it rather than guessing from the source column',
	strpos( $tpl, "VIS.measured_tool || 'Google Search Console'" ) !== false
	&& strpos( $tpl, "measured_from === 'ga4' ? 'Google Analytics'" ) === false );
check( 'the caption says the level is held, not projected',
	strpos( $tpl, 'held flat' ) !== false
	&& strpos( $tpl, 'nobody can show what an untouched site would have done' ) !== false,
	'a client who asks how the dashed line was worked out gets an answer' );
check( 'and the anchor that moved it is gone',
	strpos( file_get_contents( dirname( __DIR__, 2 ) . '/portal/class-dashboard-metrics.php' ),
		'$anchor = (float)' ) === false );

/* ── The two figures that disagreed ──────────────────────────────────────
 *
 * Reported from live use: the card read "3,745 organic sessions · last 30
 * days" in the top right, and directly under it "October 2026 · 114
 * visitors". Same metric, same card, thirty times apart.
 *
 * 114 was the MEAN of the handful of October days that happened to fall
 * inside the 30-day display window. last_calendar_month() was handed that
 * window rather than the full series, found five October days in it, and
 * decided from `count($vals) > 6` that five readings could not be a daily
 * series — so it averaged them and labelled the result as the month. */
echo "\n-- Last month against last 30 days --\n";
Six_Tracking::$start     = '2026-10-21';
Six_Tracking::$projected = true;
Six_Tracking::$from      = '2026-10-01';
Six_Tracking::$ndays     = 40;              // 1 Oct – 9 Nov: October complete
$vm = Six_Dash_Metrics::visitor_series( 7, $CTX );
$lm = $vm['last_month'] ?? null;
check( 'last month is reported when the month is actually held',
	is_array( $lm ) && ( $lm['label'] ?? '' ) === 'October 2026',
	'got ' . var_export( $lm, true ) );
check( '  and it is the month TOTAL, not the mean of a few days',
	is_array( $lm ) && (int) $lm['value'] > 5000,
	'got ' . ( is_array( $lm ) ? $lm['value'] : 'null' )
	. ' — a daily mean would land near 250, which is the bug this guards' );
check( '  so it is the same kind of number as the 30-day figure',
	is_array( $lm ) && ( $vm['current_mo'] ?? 0 ) > 0
	&& $lm['value'] / max( 1, $vm['current_mo'] ) > 0.4
	&& $lm['value'] / max( 1, $vm['current_mo'] ) < 2.5,
	'month=' . ( is_array( $lm ) ? $lm['value'] : 'null' ) . ' 30d=' . ( $vm['current_mo'] ?? 0 )
	. ' — two totals of the same metric cannot be an order of magnitude apart' );

/* And the other half: a month we only partly hold is not reported at all.
 * A partial month summed is a total that is quietly too low, which is worse
 * than an absent figure, because the client puts it beside this month's and
 * reads a fall that did not happen. */
Six_Tracking::$from  = '2026-10-24';        // only the tail of October
Six_Tracking::$ndays = 32;
$vp = Six_Dash_Metrics::visitor_series( 7, $CTX );
check( 'a month we only partly hold is left out rather than under-reported',
	( $vp['last_month'] ?? null ) === null,
	'got ' . var_export( $vp['last_month'] ?? null, true ) );

/* ── The hover read the wrong point ──────────────────────────────────────
 *
 * X(i) places a point at pad.l + (i/(n-1)) * spanW, where spanW is 80% of
 * the plot width — the slack on the right is for the end marker and its
 * label. The hover inverted it against the FULL width, so every lookup
 * landed at 0.8 of the index under the cursor: hover over week 6, read
 * week 4 or 5. Reported exactly that way. */
echo "\n-- Hovering reads the point under the cursor --\n";
check( 'the span the forward mapping uses travels with the geometry',
	strpos( $tpl, 'spanW:spanW' ) !== false,
	'the inverse cannot be right unless it can see the same number' );
check( '  and the inverse divides by that span, not the full plot width',
	strpos( $tpl, 'GEO.pad.l) / GEO.spanW' ) !== false
	&& strpos( $tpl, '/(GEO.W-GEO.pad.l-GEO.pad.r))*(GEO.n-1)' ) === false,
	'this is the 0.8 offset' );
check( 'the tooltip names the real date rather than "Week N"',
	strpos( $tpl, "'Week ' + (i+1)" ) === false
	&& strpos( $tpl, 'toLocaleDateString' ) !== false,
	'the axis has carried real dates since the series went daily' );
check( 'no "without us" figure is shown on days before we started',
	strpos( $tpl, "ttbRow.style.display = hasB ? 'flex' : 'none'" ) !== false,
	'fmt(null) printed 0, which reads as a measured zero' );
check( 'and the legend does not key a line that was not drawn',
	strpos( $tpl, 'No &ldquo;without us&rdquo; line' ) !== false
	&& strpos( $tpl, 'blockerShort' ) !== false,
	'a key for a missing line reads as a broken chart' );

/* ── The window is adjustable ────────────────────────────────────────────
 *
 * The chart was fixed at the last 30 days with no control, so a client
 * could not tell a quiet fortnight from a trend. The daily series is
 * already fetched over 180 days, so the longer windows cost nothing. */
echo "\n-- Choosing how far back to look --\n";
Six_Tracking::$from  = '2026-06-01';
Six_Tracking::$ndays = 180;
$w30 = Six_Dash_Metrics::visitor_series( 7, $CTX + array( 'window' => 30 ) );
$w90 = Six_Dash_Metrics::visitor_series( 7, array( 'window' => 90 ) + $CTX );
check( 'the default window is 30 days', count( $w30['points'] ?? array() ) === 30,
	count( $w30['points'] ?? array() ) . ' points' );
check( 'asking for 90 draws 90', count( $w90['points'] ?? array() ) === 90,
	count( $w90['points'] ?? array() ) . ' points' );
check( 'the headline stays the last 30 days whatever the chart shows',
	( $w30['current_mo'] ?? 0 ) === ( $w90['current_mo'] ?? -1 ),
	'30d=' . ( $w30['current_mo'] ?? 0 ) . ' 90d=' . ( $w90['current_mo'] ?? 0 )
	. ' — "last 30 days" has to keep meaning the same thing, or it cannot be '
	. 'compared against last month' );
check( 'and a silly window is clamped rather than obeyed',
	count( Six_Dash_Metrics::visitor_series( 7, array( 'window' => 99999 ) + $CTX )['points'] ) <= 180 );

check( 'the picker is rendered from the ranges the server allows',
	strpos( $tpl, 'rangeBtns' ) !== false && strpos( $tpl, "searchParams.set('range'" ) !== false,
	'one implementation of the window, on the server' );

/* ── GA4 had the answers all along ───────────────────────────────────────
 *
 * by_channel has been in ga4_summary() since it was written, and the
 * conversions total was hiding the event breakdown. Neither reached the
 * overview, so it could say how many people came and never where from or
 * whether any of them got in touch. */
echo "\n-- Where visitors came from, and what they did --\n";
$an = file_get_contents( dirname( __DIR__, 2 ) . '/portal/class-analytics.php' );
check( 'GA4 events are read by name, not just totalled',
	strpos( $an, "'name' => 'eventName'" ) !== false
	&& strpos( $an, 'function ga4_events' ) !== false );
check( 'automatic events are not counted as engagement',
	strpos( $an, "'session_start', 'first_visit', 'page_view'" ) !== false,
	'every property would otherwise look busy' );
check( 'an untracked call event reports null, never a measured zero',
	strpos( $an, 'null, not 0' ) !== false
	&& strpos( $dm, "'calls' => $calls" ) !== false,
	'"nobody called" and "we are not tracking calls" are different claims' );
check( 'and the client is told which of the two it is',
	strpos( $tpl, 'not yet tracking' ) !== false,
	'a blank where a number belongs reads as zero' );
check( 'the figures are cached so a page load does not wait on GA4 twice',
	strpos( $dm, 'six_engage_' ) !== false && strpos( $dm, 'set_transient' ) !== false );

echo "\nPassed: $pass  Failed: $fail\n";
exit( $fail ? 1 : 0 );
