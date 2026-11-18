<?php
/**
 * The client profile, rearranged.
 *
 * The advisor's client view had grown into seven tabs with the useful things
 * scattered across them: a whole tab for one communication panel, a
 * questionnaire buried under a metrics sub-tab, and a KPI target editor
 * duplicating figures the integrations already pull. An advisor opening a
 * client could not see what to do next.
 *
 * These checks read the template. There is no clicking here — they protect
 * the arrangement itself, because a section that quietly stops being emitted
 * is invisible until somebody opens the page, and in this codebase that has
 * meant an advisor opening it.
 *
 *     php tools/tests/test_advisor_layout.php
 */
define( 'ABSPATH', '/x/' );

$pass = 0; $fail = 0;
function check( $name, $ok, $detail = '' ) {
	global $pass, $fail;
	if ( $ok ) { $pass++; echo "PASS  $name\n"; }
	else { $fail++; echo "FAIL  $name\n" . ( $detail ? "        $detail\n" : '' ); }
}

$adv = file_get_contents( dirname( __DIR__, 2 ) . '/portal/templates/advisor-dashboard.php' );
$ajx = file_get_contents( dirname( __DIR__, 2 ) . '/portal/ajax-handlers.php' );
$dm  = file_get_contents( dirname( __DIR__, 2 ) . '/portal/class-dashboard-metrics.php' );

// ── The tab bar ──────────────────────────────────────────────────────────
check( 'Activity is no longer a tab of its own',
	strpos( $adv, "'activity'     => array(" ) === false,
	'a whole tab for one panel' );
check( 'and its branch is gone with it',
	strpos( $adv, "elseif(\$ctab==='activity')" ) === false );
check( 'the communication history moved onto Overview',
	strpos( $adv, 'Communication History' ) !== false
	&& strpos( $adv, 'Communication History' ) < strpos( $adv, "CTAB: SERVICES" ),
	'it has to be inside the overview branch, which comes first' );

check( 'AI Strategy is just Strategy — the recommendations are the point',
	strpos( $adv, "'strategy'     => array('icon'=>'', 'label'=>'Strategy'" ) !== false );
check( 'and a bookmarked ?ctab=ai link still answers',
	strpos( $adv, "elseif(\$ctab==='strategy' || \$ctab==='ai')" ) !== false,
	'renaming a tab should not break a link somebody saved' );

// ── The KPI target editor is gone ────────────────────────────────────────
check( 'Set Client KPI Targets is gone',
	strpos( $adv, 'Set Client KPI Targets' ) === false,
	'it duplicated figures the integrations already pull' );
check( 'and the Overview KPIs / Live Service Metrics switcher with it',
	strpos( $adv, 'data-mpane' ) === false );

// ── Per-service tabs ─────────────────────────────────────────────────────
check( 'services render as tabs, one per service the client has',
	strpos( $adv, 'six-svctab' ) !== false );
check( 'and only for services they actually have',
	strpos( $adv, "in_array(\$s->status, array('active','pending'), true)" ) !== false,
	'every client used to get all four, three of them saying "Not requested"' );
check( 'a client with none is told where to add one',
	strpos( $adv, 'No services yet' ) !== false );
check( 'the chosen tab is remembered',
	strpos( $adv, 'six_adv_svctab_' ) !== false );
check( 'the "● Active" badge is gone — the tab already says the service exists',
	strpos( $adv, '● Active</span>' ) === false );

// ── Website maintenance sits with the budgets ────────────────────────────
check( 'maintenance moved beside the services and budgets',
	strpos( $adv, 'Services &amp; Budget' ) !== false
	&& strpos( $adv, 'wp-maint' ) > strpos( $adv, 'Services &amp; Budget' ),
	'a recurring charge is not a performance metric' );

// ── The questionnaire on Overview, with the three-day rule ───────────────
check( 'the questionnaire panel is on Overview',
	strpos( $adv, '$cq_vis' ) !== false );
check( 'it honours the three-day clear',
	strpos( $adv, "empty(\$cq_vis) || !empty(\$cq_vis['show'])" ) !== false );
check( 'it reads the frozen scope, not the live service list',
	strpos( $adv, 'six_questionnaire_client_slugs( $view_client_id )' ) !== false );
check( 'and the advisor is told when a later service was never asked about',
	strpos( $adv, 'A service was activated after the questionnaire went out' ) !== false,
	'the one case where re-issuing is right rather than an annoyance' );

// ── The four overview cards ──────────────────────────────────────────────
check( 'the advisor picks which four numbers the client sees',
	strpos( $adv, 'oc-pick' ) !== false );
check( 'the fifth tick is refused at the checkbox, not dropped on save',
	strpos( $adv, 'c.disabled = (!c.checked && n>=4)' ) !== false,
	'dropping it silently would leave them believing they had chosen it' );
check( 'the handler is registered', strpos( $ajx, 'wp_ajax_six_save_overview_cards' ) !== false );
check( 'choosing none means the default order, not an empty dashboard',
	strpos( $ajx, "delete_user_meta( \$client_id, 'six_overview_cards' )" ) !== false );
check( 'at most four are stored', strpos( $ajx, 'if ( count( $keep ) >= 4 ) break;' ) !== false );
check( 'and the dashboard honours the choice',
	strpos( $dm, 'self::chosen_cards( $user_id )' ) !== false );
check( 'falling back to the old split when nobody chose',
	strpos( $dm, 'if ( ! $wanted ) {' ) !== false );

// ── Nothing references a tab that no longer exists ───────────────────────
check( 'no link points at the Activity tab',
	strpos( $adv, 'ctab=activity' ) === false );

echo "\nPassed: $pass  Failed: $fail\n";
exit( $fail ? 1 : 0 );
