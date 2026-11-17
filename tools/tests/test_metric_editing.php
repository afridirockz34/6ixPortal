<?php
/**
 * Deleting and clearing a metric.
 *
 * Three ways a metric could not be removed, all reported from live use:
 *
 *  - Delete in the advisor's live-metrics panel never worked. The button
 *    carried class six-del-metric and data-metric-id while the listener looked
 *    for .six-delete-metric and dataset.id. Nothing reported a failure, so it
 *    read as a delete that had simply not redrawn.
 *  - The handler reported success whatever the database did, and removed only
 *    the detail row — leaving the matching stat card on the customer's
 *    dashboard showing a figure the advisor believed they had just deleted.
 *  - Clearing a metric in the activation wizard did nothing: the save loop
 *    skipped any row whose boxes were empty, so an advisor had no way to take
 *    a card off the dashboard once it was on.
 *
 *     php tools/tests/test_metric_editing.php
 */
define( 'ABSPATH', '/x/' );

$pass = 0; $fail = 0;
function check( $name, $ok, $detail = '' ) {
	global $pass, $fail;
	if ( $ok ) { $pass++; echo "PASS  $name\n"; }
	else { $fail++; echo "FAIL  $name\n" . ( $detail ? "        $detail\n" : '' ); }
}

// ── The markup and its listener must agree ───────────────────────────────
// A class or dataset rename on either side silently disables the button, and
// the UI gives no sign. Checked against the real file.
$adv = file_get_contents( dirname( __DIR__, 2 ) . '/portal/templates/advisor-dashboard.php' );

preg_match( '/querySelectorAll\(\s*[\'"]\.([a-z-]*delete-metric)[\'"]/', $adv, $m );
$listen_class = $m[1] ?? '';
check( 'the delete listener names a class', $listen_class !== '' );
check( 'and the button carries exactly that class',
	strpos( $adv, 'six-btn-ghost ' . $listen_class . '"' ) !== false,
	'listener wants .' . $listen_class );

// The listener reads dataset.id, so the button must be data-id, not
// data-metric-id — which is what it was.
preg_match( '/' . preg_quote( $listen_class, '/' ) . '[^>]*?data-([a-z-]+)=/', $adv, $m2 );
check( 'the button\'s data attribute is the one the listener reads',
	( $m2[1] ?? '' ) === 'id', 'button has data-' . ( $m2[1] ?? '?' ) . ', listener reads dataset.id' );

check( 'the metric card has an id so the row can actually be removed',
	strpos( $adv, 'id="mrow-' ) !== false );
check( 'a refused delete tells the advisor rather than looking like a success',
	strpos( $adv, 'Could not delete it. Nothing was changed.' ) !== false );

// ── The wizard can clear a row and can set a target ──────────────────────
check( 'the wizard has a target column', strpos( $adv, 'k-tgt' ) !== false );
check( 'and a clear button on every catalog row', strpos( $adv, 'k-clr' ) !== false );
check( 'whose effect is stated on screen, not left to a tooltip nobody hovers',
	strpos( $adv, 'an empty row means that card does not appear' ) !== false,
	'an advisor reads "clear" as "reset", not as "remove the card"' );
check( 'and the pulled figures are described as editable',
	strpos( $adv, 'can be edited after it is read' ) !== false );
check( 'and collects the target, not just current and previous',
	preg_match( '/target:\s*row\.querySelector\(\s*[\'"]\.k-tgt/', $adv ) === 1 );
check( 'and the Before / Now / Target headings replaced "Last month"',
	strpos( $adv, '>Target</div>' ) !== false && strpos( $adv, '>Last month</div>' ) === false,
	'the wizard still says "Last month" somewhere' );

// ── The handlers ─────────────────────────────────────────────────────────
$ajax = file_get_contents( dirname( __DIR__, 2 ) . '/portal/ajax-handlers.php' );

$del = substr( $ajax, strpos( $ajax, "wp_ajax_six_delete_metric" ), 2200 );
check( 'delete refuses without an id', strpos( $del, 'Missing metric.' ) !== false );
check( 'delete reports a database failure instead of success',
	strpos( $del, 'The delete failed and nothing was changed.' ) !== false );
check( 'delete clears the matching stat card as well as the detail row',
	strpos( $del, 'six_client_kpis' ) !== false, 'only six_metrics is touched' );
check( 'and says so, so the advisor knows both went',
	strpos( $del, 'card_cleared' ) !== false );
check( 'delete suppresses wpdb errors so the JSON body stays JSON',
	strpos( $del, 'suppress_errors' ) !== false );

// The wizard's save loop: an all-empty row must delete, not skip.
$wiz = substr( $ajax, strpos( $ajax, '$mtbl_k = $wpdb->prefix' ), 3000 );
check( 'the wizard treats an emptied row as a removal',
	strpos( $wiz, "\$value === '' && \$prev === '' && \$target === ''" ) !== false
	&& strpos( $wiz, "\$wpdb->delete( \$ktbl" ) !== false,
	'an emptied row is still skipped rather than deleted' );
check( 'and removes the detail row too, not just the card',
	strpos( $wiz, "\$wpdb->delete( \$mtbl_k" ) !== false );
check( 'the wizard writes the target to six_metrics, since the KPI table has no column for it',
	strpos( $wiz, "'target_value'   => \$target" ) !== false );
check( 'and the target is loaded back when the wizard reopens',
	strpos( $ajax, "\$kpis[ \$k ]['target'] = \$r->target_value" ) !== false,
	'reopening the wizard would show an empty target box and wipe it on save' );
check( 'the wizard knows which metrics improve by falling',
	strpos( $ajax, "\$m['lower_better'] = Six_Dash_Metrics::lower_is_better" ) !== false );

// ── Website maintenance belongs in Billing, not Services ─────────────────
$cust = file_get_contents( dirname( __DIR__, 2 ) . '/portal/templates/customer-dashboard.php' );
check( 'maintenance no longer has a Services nav item',
	strpos( $cust, '>Website Maintenance</span>' ) === false,
	'the sidebar still links to it' );
check( 'but it does appear as a billing line',
	strpos( $cust, '>Website Maintenance</td>' ) !== false );
check( 'and its fee is added to the monthly total',
	strpos( $cust, '$billing_total += $maint_fee' ) !== false );
check( 'a maintenance client with no other services still sees the billing table',
	strpos( $cust, 'if(empty($active_svcs_b) && !$maint_on)' ) !== false );
check( 'and a fee nobody set reads as unset rather than as $0',
	strpos( $cust, 'fee not set' ) !== false );

echo "\nPassed: $pass  Failed: $fail\n";
exit( $fail ? 1 : 0 );
