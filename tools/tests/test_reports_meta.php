<?php
/**
 * A report that says what it is.
 *
 * Reported from live use: "Right now in reports, we don't know what the
 * report is for." The row was a filename and a date. Nothing said which
 * service it covered, what period it measured, or what was inside — so a
 * client opening the page before a call could not tell which of five files
 * to read, and neither could the advisor who uploaded them.
 *
 * The columns this needed did not exist, which is its own trap: dbDelta does
 * NOT alter a table whose statement says CREATE TABLE IF NOT EXISTS, so
 * adding them to that string would have done nothing on any live site.
 *
 *     php tools/tests/test_reports_meta.php
 */
define( 'ABSPATH', '/x/' );

$pass = 0; $fail = 0;
function check( $name, $ok, $detail = '' ) {
	global $pass, $fail;
	if ( $ok ) { $pass++; echo "PASS  $name\n"; }
	else { $fail++; echo "FAIL  $name\n" . ( $detail ? "        $detail\n" : '' ); }
}

$fn   = file_get_contents( dirname( __DIR__, 2 ) . '/functions.php' );
$ajax = file_get_contents( dirname( __DIR__, 2 ) . '/portal/ajax-handlers.php' );
$adv  = file_get_contents( dirname( __DIR__, 2 ) . '/portal/templates/advisor-dashboard.php' );
$cust = file_get_contents( dirname( __DIR__, 2 ) . '/portal/templates/customer-dashboard.php' );

// ── The columns are added the only way that works ────────────────────────
check( 'the new columns are added explicitly, not by editing the CREATE statement',
	strpos( $fn, 'function six_reports_ensure_columns' ) !== false,
	'dbDelta does not ALTER a CREATE TABLE IF NOT EXISTS — its regex captures IF' );
check( 'and the comment says why, so nobody tries dbDelta again',
	strpos( $fn, 'its table-name regex captures `IF`' ) !== false );
foreach ( array( 'service_slug', 'summary', 'period_start', 'period_end' ) as $col ) {
	check( "column $col is wanted", strpos( $fn, "'$col'" ) !== false );
}
check( 'the check is gated on a signature, so it costs nothing per request',
	strpos( $fn, 'six_reports_cols_sig' ) !== false );
check( 'a failed ALTER is logged and the signature is not recorded',
	strpos( $fn, "if ( ! \$out['failed'] ) update_option( 'six_reports_cols_sig'" ) !== false,
	'recording it would mean never retrying' );
check( 'it runs from six_create_tables, which the version bump reaches',
	strpos( $fn, 'six_reports_ensure_columns();' ) !== false );
check( 'and the schema version moved, or none of it runs',
	strpos( $fn, "SIX_DB_VERSION = '2026.11.18.1'" ) !== false );

// ── The upload collects them ─────────────────────────────────────────────
foreach ( array( 'rpt-kind', 'rpt-service', 'rpt-from', 'rpt-to', 'rpt-summary' ) as $field ) {
	check( "the upload form has $field", strpos( $adv, $field ) !== false );
}
check( 'and the submit sends them',
	strpos( $adv, "['kind','service','from','to','summary']" ) !== false );

// ── The handler stores them without risking the whole row ────────────────
$up = substr( $ajax, strpos( $ajax, 'wp_ajax_six_upload_report' ), 4000 );
check( 'the kind is a controlled list, so the page can group rather than sort by typing',
	strpos( $up, "array( 'audit', 'strategy', 'monthly', 'implementation', 'other' )" ) !== false );
check( 'an unknown kind falls back rather than being stored',
	strpos( $up, "if ( ! in_array( \$kind, \$kinds, true ) ) \$kind = 'monthly';" ) !== false );
check( 'a column that is not there yet is left out of the insert',
	strpos( $up, 'SHOW COLUMNS FROM' ) !== false,
	'strict mode fails the whole statement on an unknown column, losing the report' );
check( 'and the failure is reported rather than reported as success',
	strpos( $up, 'could not be filed' ) !== false );
check( 'the insert is error-suppressed so the JSON body stays JSON',
	strpos( $up, 'suppress_errors' ) !== false );

// ── The client can tell what it is ───────────────────────────────────────
check( 'each row is labelled with what kind of report it is',
	strpos( $cust, '$rep_kinds' ) !== false );
check( 'with a plain-words line for every kind',
	strpos( $cust, 'What is wrong with the site today' ) !== false
	&& strpos( $cust, 'What moved last month' ) !== false );
check( 'the service it covers is named',
	strpos( $cust, '$rep_svc' ) !== false );
check( 'the period it covers is shown',
	strpos( $cust, 'Covers <?php echo esc_html($rp)' ) !== false );
check( 'and a report filed before these fields existed still renders',
	strpos( $cust, "!empty(\$rep->summary) ? \$rep->summary : \$rk[1]" ) !== false,
	'an older row has no summary and must fall back to the kind description' );

echo "\nPassed: $pass  Failed: $fail\n";
exit( $fail ? 1 : 0 );
