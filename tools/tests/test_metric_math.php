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


/* ─────────────────────────────────────────────────────────────────────────
 * The target graphs that replaced the progress bars
 *
 * A bar could say 62% and nothing else: not whether the number is moving,
 * not how fast, not whether it has moved at all since the work started. The
 * graph answers those, which means it can also lie about them in ways a bar
 * could not — by drawing a smooth line through days nobody measured.
 * ───────────────────────────────────────────────────────────────────────── */

// Two readings is two readings. It must draw as two dots, flagged sparse,
// never as a month of daily values interpolated between them.
$s = Six_Dash_Metrics::target_series( 1, 'Organic Visitors', 420, 1000, 300 );
check( 'two known figures produce a series', ! empty( $s['has'] ) );
check( 'and it is marked sparse rather than drawn as a measured line',
	$s['sparse'] === true && $s['measured'] === false );
check( 'with exactly the two points we actually have', count( $s['points'] ) === 2,
	count( $s['points'] ) . ' points' );
check( 'labelled as what they are',
	$s['points'][0]['label'] === 'Before' && $s['points'][1]['label'] === 'Now' );
check( 'and the chart is told to say so', strpos( $s['note'], 'Two readings' ) !== false );
check( 'with the longer explanation kept for the tooltip, so the footer stays on one line',
	strpos( $s['note_long'], 'fills in' ) !== false );

// The geometry the brief asked for: the target high on the plot, the line
// in the middle band.
check( 'the target sits at 85% of the plot height',
	abs( $s['target_y'] - 0.85 ) < 0.001, 'target_y = ' . $s['target_y'] );
$now_y = $s['points'][1]['y'];
check( 'a metric 42% of the way to its target draws in the 30-50% band',
	$now_y > 0.30 && $now_y < 0.50, 'y = ' . $now_y );
check( 'nothing is ever drawn above 95% of the plot',
	Six_Dash_Metrics::target_series( 1, 'Organic Visitors', 99999, 1000 )['points'][0]['y'] <= 0.95 );

// A metric that improves by falling still has to climb the chart, or the
// picture says the opposite of the number.
$s = Six_Dash_Metrics::target_series( 1, 'Average Position', 11.3, 6, 18 );
check( 'a lower-is-better metric is recognised', $s['lower'] === true );
check( 'its worst reading sits at the bottom of the plot',
	$s['points'][0]['y'] < $s['points'][1]['y'],
	'18 drew at ' . $s['points'][0]['y'] . ', 11.3 at ' . $s['points'][1]['y'] );
check( 'and its target still sits at 85%', abs( $s['target_y'] - 0.85 ) < 0.001,
	'target_y = ' . $s['target_y'] );

// Nothing measured is not zero.
$s = Six_Dash_Metrics::target_series( 1, 'Organic Visitors', '', 1000 );
check( 'a metric with nothing measured draws no graph', empty( $s['has'] ) );
check( 'and says why', strpos( $s['reason'], 'nothing measured' ) !== false );

// No target is not a target of zero.
$s = Six_Dash_Metrics::target_series( 1, 'Organic Visitors', 420, '' );
check( 'a metric with no target still draws its line', ! empty( $s['has'] ) );
check( 'but has no target marker to draw', $s['target_y'] === null );
check( 'and no percentage', $s['pct'] === null );

// Target met: the point sits on the circle, not past the top of the plot.
$s = Six_Dash_Metrics::target_series( 1, 'Organic Visitors', 1000, 1000, 300 );
check( 'a metric at its target reads as met', $s['met'] === true && $s['pct'] === 100 );
check( 'and draws level with the target marker',
	abs( $s['points'][1]['y'] - $s['target_y'] ) < 0.001 );

// ── The seven-day mean ───────────────────────────────────────────────────
$flat = Six_Dash_Metrics::weekly_mean( array_fill( 0, 10, 100.0 ) );
check( 'a flat series stays flat under smoothing',
	$flat[0] === 100.0 && $flat[9] === 100.0 );
check( 'the smoothed series is the same length as the input', count( $flat ) === 10 );

$spike = Six_Dash_Metrics::weekly_mean( array( 0, 0, 0, 0, 0, 0, 70.0 ) );
check( 'a one-day spike is flattened, not carried at full height',
	abs( $spike[6] - 10.0 ) < 0.001, 'got ' . $spike[6] );
check( 'and the first point averages only what exists before it',
	$spike[0] === 0.0 );

$rising = Six_Dash_Metrics::weekly_mean( array( 1, 2, 3, 4, 5, 6, 7, 8 ) );
check( 'the mean trails rather than leading, so it never uses a future day',
	$rising[7] < 8.0 && $rising[7] > 4.0, 'got ' . $rising[7] );

// ── Where the line splits ────────────────────────────────────────────────
$pts = array();
for ( $i = 0; $i < 30; $i++ ) {
	$pts[] = array( 'date' => gmdate( 'Y-m-d', strtotime( '2026-10-01 +' . $i . ' days' ) ) );
}
check( 'a start date inside the window splits there',
	Six_Dash_Metrics::split_index( $pts, '2026-10-11' ) === 10,
	var_export( Six_Dash_Metrics::split_index( $pts, '2026-10-11' ), true ) );
check( 'a start date before the window splits at the first point, so both lines run the full width',
	Six_Dash_Metrics::split_index( $pts, '2026-05-01' ) === 0 );
check( 'a start date after the window produces no split at all',
	Six_Dash_Metrics::split_index( $pts, '2026-12-01' ) === null );
check( 'no start date produces no split, rather than splitting at day zero',
	Six_Dash_Metrics::split_index( $pts, '' ) === null,
	'a client with no recorded start would get a "without us" line out of nothing' );
check( 'a start date on the last day still splits',
	Six_Dash_Metrics::split_index( $pts, '2026-10-30' ) === 29 );
check( 'a timestamp works as well as a date',
	Six_Dash_Metrics::split_index( $pts, '2026-10-11 14:32:00' ) === 10 );
check( 'an empty window splits nowhere', Six_Dash_Metrics::split_index( array(), '2026-10-11' ) === null );


/* ─────────────────────────────────────────────────────────────────────────
 * What the dashboard actually renders
 *
 * These read the template. A class rename on either side of a listener, or a
 * section that quietly stops being emitted, is invisible until somebody opens
 * the page — which in this codebase has meant a client opening it.
 * ───────────────────────────────────────────────────────────────────────── */
$cust = file_get_contents( dirname( __DIR__, 2 ) . '/portal/templates/customer-dashboard.php' );

// ── The progress bars are gone, and graphs replaced them ─────────────────
check( 'the per-metric progress bars are gone',
	strpos( $cust, 'transition:width 1s ease' ) === false,
	'a progress bar is still being rendered' );
check( 'target graphs replaced them', strpos( $cust, 'six-target-grid' ) !== false );
check( 'two to a row on a desktop',
	strpos( $cust, '.six-target-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr))' ) !== false );
check( 'and one on a phone, at a width a phone actually is',
	strpos( $cust, '@media(max-width:620px){.six-target-grid{grid-template-columns:1fr}}' ) !== false );
check( 'each plot carries its series as data',
	strpos( $cust, 'data-graph="<?php echo esc_attr(wp_json_encode($tser))' ) !== false );
check( 'the target is drawn as a glowing circle',
	strpos( $cust, "radialGradient" ) !== false && strpos( $cust, "uid + 'g'" ) !== false );
check( 'the graph is drawn at the box\'s real size, not a stretched viewBox',
	strpos( $cust, 'box.clientWidth' ) !== false
	&& strpos( $cust, "preserveAspectRatio: 'none'" ) === false,
	'a fixed viewBox stretched every axis label sideways' );
check( 'and it redraws when the theme changes',
	strpos( $cust, "attributeFilter: ['data-theme']" ) !== false );

// ── Performance / Reporting ──────────────────────────────────────────────
check( 'the two halves are a full-width 50/50 control',
	strpos( $cust, '.six-svc-switch{display:grid;grid-template-columns:1fr 1fr' ) !== false );
check( 'with a Performance half', strpos( $cust, 'data-pane="performance"' ) !== false );
check( 'and a Reporting half', strpos( $cust, 'data-pane="reporting"' ) !== false );
check( 'both panes exist in the markup',
	strpos( $cust, 'id="six-pane-performance"' ) !== false
	&& strpos( $cust, 'id="six-pane-reporting"' ) !== false );
check( 'the graphs are under Performance',
	strpos( $cust, 'id="six-pane-performance"' ) < strpos( $cust, 'six-target-grid">' ) );
check( 'the optimization proof is under Reporting',
	strpos( $cust, 'id="six-pane-reporting"' ) < strpos( $cust, '>Optimization proof<' ) );
check( 'the client\'s task list is under Reporting too',
	strpos( $cust, '<?php echo $plan_html; $plan_html = \'\'; ?>' ) !== false );
check( 'and a client with no metrics still sees their plan',
	strpos( $cust, "if ( ! empty( \$plan_html ) ) { echo \$plan_html;" ) !== false,
	'no metrics means no Reporting pane to hold it' );

// ── Performance Trend is gone ────────────────────────────────────────────
check( 'the Performance Trend chart is no longer rendered',
	strpos( $cust, "id=\"svc-perf-chart\"" ) === false,
	'the bar chart is still there' );

// ── The overview chart ───────────────────────────────────────────────────
check( 'the overview splits at the day the service was switched on',
	strpos( $cust, 'VIS.split_at' ) !== false );
check( 'and marks that day on the plot', strpos( $cust, "'we started'" ) !== false );
check( 'the "without us" line starts at the split, not at the left edge',
	strpos( $cust, 'for (var bi = Math.max(0, split); bi < n; bi++)' ) !== false,
	'the grey line would describe days before we did anything' );
/* Headroom above the line, so a chart whose line touches the frame does not
 * read as a number with nowhere left to go. Kept as a range rather than an
 * exact factor: the value is a judgement about how the plot looks, and a
 * test that pins it turns every visual tweak into a test failure. */
check( 'the line leaves headroom above it rather than touching the frame',
	preg_match( '/var maxY = peak \/ 0\.(8[0-9]|9[0-2])\b/', $cust ) === 1,
	'a line against the top of the plot reads as maxed out' );
/* Horizontally it used to stop at 80%, reserving the right fifth for "the
 * end of the line's marker and its label". No end label is ever drawn, so a
 * fifth of the chart was blank — reported as the graph looking incomplete
 * beside Search Console, which runs its line the full width. */
check( 'the line runs the full width of the plot',
	preg_match( '/var spanW = \(W - pad\.l - pad\.r\) \* 0\.9[5-9]\d*/', $cust ) === 1,
	'a blank right-hand fifth is why the chart read as unfinished' );
check( 'the caption says how many days are shown',
	strpos( $cust, 'VIS.window_days' ) !== false );
check( 'and that the line is smoothed, so a client is not told a weekend was a crash',
	strpos( $cust, 'smoothed over seven' ) !== false );
check( 'the overview redraws on a theme change instead of keeping its light greys',
	strpos( $cust, 'window.sixRedrawVisits = build' ) !== false
	&& strpos( $cust, 'window.sixRedrawVisits()' ) !== false );

// ── Competitor analysis includes the client ──────────────────────────────
check( 'the client appears on their own competitor table',
	strpos( $cust, '>You</span>' ) !== false );
check( 'with their position out of a named total',
	strpos( $cust, 'by estimated organic visits' ) !== false,
	'"3rd" with no denominator is not a fact' );
check( 'their own row uses the same estimate as every other row',
	strpos( $cust, 'estimated on the same basis' ) !== false
	|| strpos( file_get_contents( dirname( __DIR__, 2 ) . '/portal/ajax-handlers.php' ),
	           'estimated on the same basis' ) !== false );
check( 'and their measured figure is shown separately, named as measured',
	strpos( $cust, 'Your own analytics measured' ) !== false,
	'a measured number in a column of estimates is the comparison a client can catch us making' );
check( 'the empty-state colspan matches the new column count',
	strpos( $cust, 'colspan="6"' ) !== false );

// ── The CSS a service page needs is actually on a service page ───────────
//
// The target graphs and the Performance/Reporting switch were first written
// into the Overview tab's own <style> block, which is inside
// `if($active_tab==='overview')`. On a service page — the only page that uses
// any of it — none of it loaded: the graphs stacked one per row and the
// switch rendered as two lines of unstyled text. Nothing failed; it just
// looked broken.
$style_blocks = array();
$off = 0;
while ( ( $s = strpos( $cust, '<style', $off ) ) !== false ) {
	$e = strpos( $cust, '</style>', $s );
	$style_blocks[] = array( 'start' => $s, 'end' => $e );
	$off = $e === false ? strlen( $cust ) : $e;
}
function six_in_tab_branch( $hay, $pos ) {
	$head = substr( $hay, 0, $pos );
	return preg_match_all( "/<\?php\s*if\s*\(\s*\\$active_tab/", $head ) > 0;
}
foreach ( array( '.six-target-grid{', '.six-svc-switch{', '.six-target-card{' ) as $rule ) {
	$at = strpos( $cust, $rule );
	check( "the rule $rule exists", $at !== false );
	if ( $at === false ) continue;
	$inside = null;
	foreach ( $style_blocks as $b ) {
		if ( $at > $b['start'] && $b['end'] !== false && $at < $b['end'] ) { $inside = $b; break; }
	}
	check( "$rule is in a <style> block", $inside !== null );
	if ( $inside === null ) continue;
	check( "and that block is not inside one tab's branch",
		! six_in_tab_branch( $cust, $inside['start'] ),
		'a service page would render this unstyled' );
}

// ── Zero is a measurement ────────────────────────────────────────────────
check( 'a measured zero renders as zero, not as an em dash',
	Six_Dash_Metrics::format( 0, array() ) === '0',
	'got ' . var_export( Six_Dash_Metrics::format( 0, array() ), true )
	. ' — a client with no conversions this month was told we had not measured' );
check( 'and an advisor who types 0 sees it stay',
	Six_Dash_Metrics::format( '0', array() ) === '0' );
check( 'zero with a unit keeps its prefix and suffix',
	Six_Dash_Metrics::format( 0, array( 'prefix' => '$' ) ) === '$0' );
check( 'an empty value is still an em dash', Six_Dash_Metrics::format( '', array() ) === '—' );
check( 'and so is null', Six_Dash_Metrics::format( null, array() ) === '—' );
check( 'a negative figure is shown rather than hidden',
	Six_Dash_Metrics::format( -4, array() ) === '-4',
	'it is either real or a data problem somebody needs to see' );

// ── No benchmarks anywhere a client reads a number ───────────────────────
$dm = file_get_contents( dirname( __DIR__, 2 ) . '/portal/class-dashboard-metrics.php' );
check( 'the benchmark estimator is gone from the dashboard metrics',
	strpos( $dm, 'function estimates(' ) === false,
	'a modelled figure and a measured one looked identical on the card' );
check( 'and nothing calls it', strpos( $dm, 'self::estimates(' ) === false );
check( 'a card with no measurement and no advisor figure has no value',
	strpos( $dm, "\$src = 'none';" ) !== false );
check( 'and says what to connect instead of showing a benchmark',
	strpos( $dm, "'Connect ' . ( self::connectors()" ) !== false );
check( 'the invented 35% baseline is gone',
	strpos( $dm, '$current * 0.35' ) === false,
	'every "+N vs before" on the dashboard was computed against it' );
check( 'the modelled twelve-week ramp is gone',
	strpos( $dm, 'exp( -0.7 * ( $w - 6 ) )' ) === false,
	'a sigmoid curve was drawn in the same ink as a measurement' );
check( 'and a client with one reading is told so rather than shown a curve',
	strpos( $dm, 'Only one reading so far' ) !== false );
check( 'the customer dashboard no longer captions anything a benchmark estimate',
	strpos( $cust, 'Benchmark estimate based on your onboarding' ) === false );

// ── The advisor picks the overview cards ─────────────────────────────────
check( 'the advisor\'s chosen cards are read per client',
	strpos( $dm, "function chosen_cards(" ) !== false );
check( 'the picker has a list of every metric to choose from',
	strpos( $dm, "function card_choices(" ) !== false );
check( 'a choice is stored as service:key, so the same key under two services stays distinct',
	strpos( $dm, "\$slug . ':' . \$key" ) !== false );
check( 'at most four are honoured', strpos( $dm, 'array_slice( $out, 0, 4 )' ) !== false );

// ── Website maintenance stays out of the service nav ─────────────────────
check( 'maintenance still has no Services nav item',
	strpos( $cust, '>Website Maintenance</span>' ) === false );

echo "\nPassed: $pass  Failed: $fail\n";
exit( $fail ? 1 : 0 );
