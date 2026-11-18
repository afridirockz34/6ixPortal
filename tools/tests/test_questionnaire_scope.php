<?php
/**
 * The questionnaire's scope, and when it goes away.
 *
 * Two things were reported from live use, and they are the same bug seen from
 * two angles:
 *
 *   "the questions are showing up after some time"
 *   "even after filling it fully, after some time it shows a few more
 *    questions, sometimes already answered and empty"
 *
 * The question set was built from whatever services were active *at the
 * moment the page rendered*. An advisor activating a second service three
 * weeks later silently added that service's questions to a form the client
 * had already finished — so a questionnaire that read 100% on Monday was
 * asking four new questions on Friday, and `only_if_svc` could introduce a
 * question whose answer had never been stored under that key, which is why
 * some of them came back blank.
 *
 * A questionnaire is a thing you hand somebody. Its contents do not change
 * while they are filling it in. The scope is frozen when it is issued, and
 * re-issuing is the deliberate act that adds a new service's questions.
 *
 * The third rule: a finished questionnaire clears itself after three days,
 * rather than staying at the top of the client's dashboard for ever.
 *
 *     php tools/tests/test_questionnaire_scope.php
 */
define( 'ABSPATH', '/x/' );
define( 'DAY_IN_SECONDS', 86400 );

$pass = 0; $fail = 0;
function check( $name, $ok, $detail = '' ) {
	global $pass, $fail;
	if ( $ok ) { $pass++; echo "PASS  $name\n"; }
	else { $fail++; echo "FAIL  $name\n" . ( $detail ? "        $detail\n" : '' ); }
}

// ── A very small slice of WordPress ──────────────────────────────────────
$GLOBALS['META'] = array();
$GLOBALS['NOW']  = strtotime( '2026-11-18 10:00:00' );

function get_user_meta( $id, $key, $single = false ) {
	return $GLOBALS['META'][ (int) $id ][ $key ] ?? '';
}
function update_user_meta( $id, $key, $val ) {
	$GLOBALS['META'][ (int) $id ][ $key ] = $val; return true;
}
function delete_user_meta( $id, $key ) {
	unset( $GLOBALS['META'][ (int) $id ][ $key ] ); return true;
}
function current_time( $type ) {
	return $type === 'timestamp' ? $GLOBALS['NOW'] : gmdate( 'Y-m-d H:i:s', $GLOBALS['NOW'] );
}
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_textarea_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s ); }
function wp_json_encode( $d ) { return json_encode( $d ); }
function get_userdata( $id ) { return null; }
function add_action( ...$a ) {}
function add_filter( ...$a ) {}
function apply_filters( $tag, $v ) { return $v; }
function do_action( ...$a ) {}
function wp_mail( ...$a ) { return true; }
function home_url( $p = '' ) { return 'https://example.test' . $p; }
function wp_generate_password( $n = 12, $s = true ) { return str_repeat( 'x', $n ); }
function get_option( $k, $d = '' ) { return $d; }
function update_option( $k, $v ) { return true; }

class Six_Test_QWPDB {
	public $prefix = 'wp_';
	public $slugs = array( 'seo' );
	public function prepare( $q, ...$a ) { return $q; }
	public function get_col( $q, $i = 0 ) { return $this->slugs; }
	public function get_var( $q ) { return null; }
	public function get_results( $q ) { return array(); }
	public function get_row( $q ) { return null; }
}
$GLOBALS['wpdb'] = new Six_Test_QWPDB();

require dirname( __DIR__, 2 ) . '/portal/data-seo-questionnaire.php';

$CLIENT = 42;
function reset_client() {
	global $CLIENT;
	$GLOBALS['META'][ $CLIENT ] = array();
	$GLOBALS['wpdb']->slugs = array( 'seo' );
}

// ── The scope is frozen when the questionnaire is issued ─────────────────
reset_client();
check( 'with nothing issued, the live service list is used',
	six_questionnaire_client_slugs( $CLIENT ) === array( 'seo' ) );

six_issue_strategy_questionnaire( $CLIENT, false );
check( 'issuing records the scope',
	get_user_meta( $CLIENT, SIX_Q_SCOPE_META, true ) === array( 'seo' ),
	var_export( get_user_meta( $CLIENT, SIX_Q_SCOPE_META, true ), true ) );

// The whole bug, reproduced: a second service goes live afterwards.
$GLOBALS['wpdb']->slugs = array( 'seo', 'google-business' );
check( 'activating another service does NOT change what they are asked',
	six_questionnaire_client_slugs( $CLIENT ) === array( 'seo' ),
	'got ' . implode( ',', six_questionnaire_client_slugs( $CLIENT ) )
	. ' — a finished questionnaire would sprout new questions' );
check( 'but the advisor is told the new service was never asked about',
	six_questionnaire_scope_gap( $CLIENT ) === array( 'google-business' ),
	var_export( six_questionnaire_scope_gap( $CLIENT ), true ) );

// Re-issuing is the deliberate act that widens it.
six_issue_strategy_questionnaire( $CLIENT, false );
check( 're-issuing re-freezes the scope to what is live now',
	six_questionnaire_client_slugs( $CLIENT ) === array( 'seo', 'google-business' ),
	implode( ',', six_questionnaire_client_slugs( $CLIENT ) ) );
check( 'and the gap closes', six_questionnaire_scope_gap( $CLIENT ) === array() );

// Re-issuing must not reset the date it was first issued.
reset_client();
six_issue_strategy_questionnaire( $CLIENT, false );
$first_issued = six_strategy_questionnaire_issued_at( $CLIENT );
$GLOBALS['NOW'] += 5 * DAY_IN_SECONDS;
six_issue_strategy_questionnaire( $CLIENT, false );
check( 're-issuing keeps the original issue date',
	six_strategy_questionnaire_issued_at( $CLIENT ) === $first_issued );
$GLOBALS['NOW'] -= 5 * DAY_IN_SECONDS;

// Withdrawing clears all three, or the next issue inherits a stale scope.
six_withdraw_strategy_questionnaire( $CLIENT );
check( 'withdrawing clears the issue date', ! six_strategy_questionnaire_issued( $CLIENT ) );
check( 'and the frozen scope with it',
	get_user_meta( $CLIENT, SIX_Q_SCOPE_META, true ) === '',
	'the next issue would inherit a scope from the wrong engagement' );

// A corrupted or half-written scope falls back rather than asking nothing.
reset_client();
update_user_meta( $CLIENT, SIX_Q_SCOPE_META, 'not-an-array' );
check( 'a scope that is not a list falls back to the live services',
	six_questionnaire_client_slugs( $CLIENT ) === array( 'seo' ) );
update_user_meta( $CLIENT, SIX_Q_SCOPE_META, array() );
check( 'and so does an empty one, rather than asking nothing at all',
	six_questionnaire_client_slugs( $CLIENT ) === array( 'seo' ),
	'an empty scope would render a questionnaire with no questions' );

// ── It clears itself three days after being handed in ────────────────────
reset_client();
six_issue_strategy_questionnaire( $CLIENT, false );

$v = six_questionnaire_visible( $CLIENT );
check( 'an unsubmitted questionnaire is shown', $v['show'] === true );
check( 'and says why it is still there', $v['reason'] === 'not submitted' );

update_user_meta( $CLIENT, SIX_Q_SUBMITTED_META, current_time( 'mysql' ) );
$v = six_questionnaire_visible( $CLIENT );
check( 'the day it is submitted it is still shown, so they can check what they said',
	$v['show'] === true );
check( 'with the date it will clear', $v['clears_on'] === gmdate( 'Y-m-d', $GLOBALS['NOW'] + 3 * DAY_IN_SECONDS ) );

$GLOBALS['NOW'] += 2 * DAY_IN_SECONDS;
check( 'two days later it is still there', six_questionnaire_visible( $CLIENT )['show'] === true );

$GLOBALS['NOW'] += 1 * DAY_IN_SECONDS + 60;
$v = six_questionnaire_visible( $CLIENT );
check( 'three days later it is gone', $v['show'] === false );
check( 'and the reason is that it is finished, not that it broke',
	$v['reason'] === 'completed' );

check( 'three days is stated once, as a constant', SIX_Q_CLEAR_DAYS === 3 );

// An unreadable submission date keeps it on screen. Hiding a questionnaire
// because a timestamp would not parse is the wrong way to fail.
reset_client();
six_issue_strategy_questionnaire( $CLIENT, false );
update_user_meta( $CLIENT, SIX_Q_SUBMITTED_META, 'not a date' );
check( 'an unreadable submission date leaves it visible',
	six_questionnaire_visible( $CLIENT )['show'] === true );

// ── The templates use the frozen scope, not the live list ────────────────
$cust = file_get_contents( dirname( __DIR__, 2 ) . '/portal/templates/customer-dashboard.php' );
check( 'the sidebar badge is built from the frozen scope',
	strpos( $cust, "six_seo_questionnaire_progress(\$user_id, \$q_scope, 'strategy')" ) !== false );
check( 'and the form itself from the same scope',
	strpos( $cust, "six_seo_questionnaire_for_client( \$q_scope, 'strategy' )" ) !== false,
	'a badge counting questions the form never renders is worse than no badge' );
check( 'the sidebar link honours the three-day clear',
	strpos( $cust, 'six_questionnaire_visible' ) !== false );

echo "\nPassed: $pass  Failed: $fail\n";
exit( $fail ? 1 : 0 );
