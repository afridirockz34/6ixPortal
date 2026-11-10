<?php
/**
 * The "Pull metrics" reader.
 *
 * The thing this has to get right is precedence. GA4 is the client's own
 * analytics, Ahrefs is an estimate of visits from the keywords it happens to
 * track, and the two are different claims about the same word. If an estimate
 * can ever overwrite a measurement — or be presented without saying it is an
 * estimate — the advisor approves a number the client can disprove.
 *
 * Rather than boot WordPress, this re-implements the precedence rules from
 * ajax-handlers.php's six_suggest_metrics in a few lines and checks those, then
 * asserts the real handler still contains the clauses that enforce them. The
 * second half is what catches somebody quietly deleting the guard.
 *
 *     php tools/tests/test_pull_metrics.php
 */

$pass = 0; $fail = 0;
function check( $name, $ok, $detail = '' ) {
	global $pass, $fail;
	if ( $ok ) { $pass++; echo "PASS  $name\n"; }
	else { $fail++; echo "FAIL  $name\n" . ( $detail ? "        $detail\n" : '' ); }
}

// ── The precedence rule, in isolation ───────────────────────────────────────
/** Mirrors the handler: GA4 and GSC rows first, estimates only for gaps. */
function pull( $ga4, $ahrefs ) {
	$rows = array(); $ga4_organic = null; $live = false;
	if ( $ga4 ) {
		$live = true;
		$rows[] = array( 'key' => 'total_visitors', 'current' => (string) $ga4['sessions'], 'source' => 'GA4 — measured' );
		if ( isset( $ga4['organic'] ) ) {
			$ga4_organic = $ga4['organic'];
			$rows[] = array( 'key' => 'organic_visitors', 'current' => (string) $ga4['organic'], 'source' => 'GA4 — measured, organic search channel' );
		}
	}
	if ( $ahrefs ) {
		$live = true;
		if ( $ga4_organic === null ) {
			$rows[] = array( 'key' => 'organic_visitors', 'current' => (string) $ahrefs['organic_traffic'], 'source' => 'Ahrefs ESTIMATE — not their analytics.' );
		}
		$rows[] = array( 'key' => 'keywords_tracked', 'current' => (string) $ahrefs['organic_keywords'], 'source' => 'Ahrefs — total ranked' );
	}
	// De-duplicate on key, first wins.
	$seen = array(); $out = array();
	foreach ( $rows as $r ) {
		if ( ! empty( $r['key'] ) && isset( $seen[ $r['key'] ] ) ) continue;
		if ( ! empty( $r['key'] ) ) $seen[ $r['key'] ] = true;
		$out[] = $r;
	}
	return array( 'metrics' => $out, 'has_live' => $live );
}

function row_for( $res, $key ) {
	foreach ( $res['metrics'] as $r ) if ( ( $r['key'] ?? '' ) === $key ) return $r;
	return null;
}

$GA4    = array( 'sessions' => 1840, 'organic' => 1120 );
$AHREFS = array( 'organic_traffic' => 430, 'organic_keywords' => 212 );

// 1. Both connected: GA4 wins organic, and the estimate is nowhere.
$r = pull( $GA4, $AHREFS );
$org = row_for( $r, 'organic_visitors' );
check( 'with GA4 connected, organic comes from GA4', $org && $org['current'] === '1120',
	'got ' . json_encode( $org ) );
check( 'the Ahrefs estimate is not also listed',
	count( array_filter( $r['metrics'], function ( $x ) { return ( $x['key'] ?? '' ) === 'organic_visitors'; } ) ) === 1 );
check( 'the Ahrefs number appears nowhere as organic',
	strpos( json_encode( $org ), '430' ) === false, json_encode( $org ) );
check( 'GA4 still gives total visitors', row_for( $r, 'total_visitors' )['current'] === '1840' );
check( 'Ahrefs still contributes what GA4 cannot', row_for( $r, 'keywords_tracked' )['current'] === '212' );

// 2. No GA4: the estimate is offered, but labelled.
$r = pull( null, $AHREFS );
$org = row_for( $r, 'organic_visitors' );
check( 'without GA4, Ahrefs fills organic', $org && $org['current'] === '430' );
check( 'and says ESTIMATE in the source', stripos( $org['source'], 'estimate' ) !== false, $org['source'] );
check( 'and says it is not their analytics', stripos( $org['source'], 'not their analytics' ) !== false, $org['source'] );
check( 'no total visitors without GA4', row_for( $r, 'total_visitors' ) === null,
	'total_visitors is sessions; only GA4 can measure it' );

// 3. GA4 connected but reporting no organic channel: fall back, still labelled.
$r = pull( array( 'sessions' => 90 ), $AHREFS );
$org = row_for( $r, 'organic_visitors' );
check( 'GA4 with no organic channel falls back to the estimate', $org && $org['current'] === '430' );
check( 'and the fallback is still labelled an estimate', stripos( $org['source'], 'estimate' ) !== false );

// 4. Nothing connected.
$r = pull( null, null );
check( 'nothing connected yields no rows', $r['metrics'] === array() );
check( 'and has_live is false', $r['has_live'] === false );

// ── The real handler still carries the guards ───────────────────────────────
$src = file_get_contents( dirname( __DIR__, 2 ) . '/portal/ajax-handlers.php' );

check( 'handler reads GA4 for every service, not just SEO',
	preg_match( '/GA4 first, for every service/', $src ) === 1 );
check( 'handler guards the Ahrefs organic row behind $ga4_organic',
	preg_match( '/if \( \$ga4_organic === null \) \{/', $src ) === 1,
	'without this guard an estimate is offered beside a measurement' );
check( 'the Ahrefs organic row still calls itself an estimate',
	preg_match( '/Ahrefs ESTIMATE — not their analytics/', $src ) === 1 );
check( 'handler de-duplicates on key, keeping the first',
	preg_match( '/if \( \$k && isset\( \$seen\[ \$k \] \) \) continue;/', $src ) === 1 );
check( 'handler returns has_live', preg_match( "/'has_live' *=> *\\\$live/", $src ) === 1 );
check( 'the previous period is 61 days, not 60',
	preg_match( '/ga4_summary\( \$prop, 61 \)/', $src ) === 1,
	'60 compares 30 prior days against 31 current ones, which flatters growth' );
// Scoped to this handler only. Elsewhere the customer dashboard legitimately
// shows a clearly-labelled industry benchmark when an advisor leaves a figure
// blank; that is a different feature and not what is being removed here.
$handler = '';
if ( preg_match( "/wp_ajax_six_suggest_metrics.*?\n\} \);/s", $src, $hm ) ) $handler = $hm[0];
check( 'the suggest_metrics handler was located for scoped checks', $handler !== '' );
check( 'no benchmark or AI language inside the metrics reader',
	$handler !== '' && ! preg_match( '/benchmark|suggest(ed)? (by|with) ai/i', $handler ),
	'this reader measures; it must not imply it invents' );

// ── And the UI describes what the handler actually does ─────────────────────
$tpl = file_get_contents( dirname( __DIR__, 2 ) . '/portal/templates/advisor-dashboard.php' );
check( 'the button says Pull metrics', strpos( $tpl, '↓ Pull metrics' ) !== false );
check( 'the AI wording is gone everywhere, including the error path',
	strpos( $tpl, 'Suggest metrics with AI' ) === false,
	'the catch() branch used to restore the old label on a network error' );
// Again scoped: the pull-metrics panel must not promise benchmarks it cannot
// return. The customer dashboard's own labelled benchmark fallback is separate.
$panel = '';
if ( preg_match( "/querySelectorAll\('\.six-suggest-metrics'\).*?^\}\);/ms", $tpl, $pm ) ) $panel = $pm[0];
check( 'the pull-metrics panel was located for scoped checks', $panel !== '' );
check( 'no benchmark promise in the pull-metrics panel',
	$panel !== '' && stripos( $panel, 'benchmark' ) === false,
	'it promised numbers the handler never returns' );
check( 'the panel names the accounts it read',
	strpos( $tpl, 'Read just now from' ) !== false );

// ── The profile API tells a client session which it may claim ───────────────
$api = file_get_contents( dirname( __DIR__, 2 ) . '/portal/class-profile-api.php' );
check( 'profile exposes integrations', strpos( $api, "'integrations'     => self::integrations" ) !== false );
check( 'and a traffic_source verdict', strpos( $api, "'traffic_source'" ) !== false );
check( 'ga4_measured is one of the two values', strpos( $api, 'ga4_measured' ) !== false );
check( 'ahrefs_estimate_only is the other', strpos( $api, 'ahrefs_estimate_only' ) !== false );

echo "\nPassed: $pass  Failed: $fail\n";
exit( $fail ? 1 : 0 );
