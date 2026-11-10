<?php
/**
 * Have the SEO skills drifted away from the portal code they quote?
 *
 *     php tools/check_skill_drift.php
 *
 * The skills name the portal's metric keys and profile stage values directly,
 * because that is what makes them usable in a client session. The cost is that
 * a rename in the code and not in the skill is a *silent* failure: a projection
 * saved against a key nothing reads stores happily and is never compared again,
 * and a stage the API no longer emits sends a session down a branch that can
 * never be reached. Neither throws. Both are caught here.
 *
 * Run it after any change to the metric catalogue, the profile API's stages, or
 * the skills' performance-tracking reference. Exits non-zero on drift, so it
 * can gate a commit.
 */

$root = dirname( __DIR__ );

define( 'ABSPATH', '/x/' );
require $root . '/portal/class-dashboard-metrics.php';

$fail = 0;
$doc  = file_get_contents(
	$root . '/.claude/skills/seo-strategy-builder/references/performance-tracking.md' );
if ( $doc === false ) {
	fwrite( STDERR, "Cannot read performance-tracking.md — has the skill moved?\n" );
	exit( 2 );
}

// ── 1. Every metric key the code captures is documented, and vice versa ──────
$code = array();
foreach ( Six_Dash_Metrics::catalog() as $svc ) {
	$code = array_merge( $code, array_keys( $svc['metrics'] ) );
}
sort( $code );

preg_match_all( '/^\| `([a-z_0-9]+)` \| /m', $doc, $m );
$documented = $m[1];
sort( $documented );

$missing = array_diff( $code, $documented );
$extra   = array_diff( $documented, $code );
printf( "metric keys — in code: %d, documented: %d\n", count( $code ), count( $documented ) );
if ( $missing ) {
	echo '  NOT DOCUMENTED: ' . implode( ', ', $missing ) . "\n";
	$fail++;
}
if ( $extra ) {
	echo '  DOCUMENTED BUT GONE: ' . implode( ', ', $extra ) . "\n";
	$fail++;
}
if ( ! $missing && ! $extra ) {
	echo "  in step\n";
}

// ── 2. "Better when" matches lower_is_better() ───────────────────────────────
// Get this wrong and a monthly report congratulates a client on a number that
// got worse — the single most expensive kind of mistake in this whole set.
foreach ( $code as $k ) {
	if ( ! preg_match( '/^\| `' . preg_quote( $k, '/' ) . '` \| [^|]*\| \*?\*?(lower|higher)/m', $doc, $mm ) ) {
		continue;
	}
	if ( ( $mm[1] === 'lower' ) !== Six_Dash_Metrics::lower_is_better( $k ) ) {
		echo "  WRONG DIRECTION for {$k}: doc says {$mm[1]}\n";
		$fail++;
	}
}

// ── 3. Every stage the skills branch on is one the API can emit ──────────────
$api = file_get_contents( $root . '/portal/class-profile-api.php' );
preg_match_all(
	"/'(audit_intake_incomplete|ready_for_audit|awaiting_strategy_answers|ready_for_strategy|[a-z_]*strategy[a-z_]*)'/",
	$api, $sm );
$stages = array_values( array_unique(
	array_filter( $sm[1], function ( $s ) { return strpos( $s, '_' ) !== false; } ) ) );
echo "\nstage values the API emits: " . implode( ', ', $stages ) . "\n";

$files = glob( $root . '/.claude/skills/seo-*/{SKILL.md,references/*.md}', GLOB_BRACE );
foreach ( $files as $f ) {
	preg_match_all(
		'/`(audit_intake_incomplete|ready_for_audit|awaiting_strategy_answers|ready_for_strategy|strategy_answers_unsubmitted)`/',
		file_get_contents( $f ), $fm );
	foreach ( array_unique( $fm[1] ) as $s ) {
		if ( ! in_array( $s, $stages, true ) ) {
			echo '  ' . basename( $f ) . " names a stage the API never returns: {$s}\n";
			$fail++;
		}
	}
}

echo $fail ? "\n{$fail} problem(s).\n" : "\nSkills are in step with the code.\n";
exit( $fail ? 1 : 0 );
