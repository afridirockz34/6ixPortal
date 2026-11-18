<?php
/**
 * How long the engagement is, and where that decision is recorded.
 *
 * Two plans: twelve months and six. The six-month one is not the twelve-month
 * one with the second half deleted — the monthly rate is lower too, so the
 * per-month volumes drop as well as the total, and two extras are year-only.
 *
 * The decision is made once, by the advisor, when they set the service up.
 * Everything downstream reads it: the strategy is built for it, the task
 * board spans it, the content set is sized by it. A session that cannot see
 * it would build the wrong plan and nobody would notice until month seven.
 *
 *     php tools/tests/test_engagement_plans.php
 */
define( 'ABSPATH', '/x/' );

$pass = 0; $fail = 0;
function check( $name, $ok, $detail = '' ) {
	global $pass, $fail;
	if ( $ok ) { $pass++; echo "PASS  $name\n"; }
	else { $fail++; echo "FAIL  $name\n" . ( $detail ? "        $detail\n" : '' ); }
}

$adv  = file_get_contents( dirname( __DIR__, 2 ) . '/portal/templates/advisor-dashboard.php' );
$api  = file_get_contents( dirname( __DIR__, 2 ) . '/portal/class-profile-api.php' );
$mcp  = file_get_contents( dirname( __DIR__, 2 ) . '/portal/class-mcp-server.php' );
$skl  = dirname( __DIR__, 2 ) . '/.claude/skills/';
$plans = file_get_contents( $skl . 'seo-strategy-builder/references/engagement-plans.md' );

// ── The advisor chooses it at setup ──────────────────────────────────────
check( 'the activation wizard asks how long the plan is',
	strpos( $adv, 'wiz-term' ) !== false );
check( 'with twelve and six as the two options',
	strpos( $adv, "value=\"12\"" ) !== false && strpos( $adv, "value=\"6\"" ) !== false );
check( 'twelve is preselected, because over-delivering is the safer error',
	strpos( $adv, "(W.config.plan_term||'12')==='12'?'checked':''" ) !== false );
check( 'and the choice is collected with the rest of step one',
	strpos( $adv, 'W.config.plan_term = term.value' ) !== false,
	'a radio nobody reads back is a control that does nothing' );
check( 'the wizard says why it matters, rather than reading as an admin field',
	strpos( $adv, 'Decides what the strategy document commits to' ) !== false );

// ── A session can read it ────────────────────────────────────────────────
check( 'the profile exposes a term per service',
	strpos( $api, "'plan_terms'" ) !== false );
check( 'anything that is not 6 reads as 12',
	strpos( $api, "\$term === '6' ? 6 : 12" ) !== false,
	'a stray value must not silently halve what a client was promised' );
check( 'and the comment says why the default is the long plan',
	strpos( $api, 'over-delivering is the safer error' ) !== false );

// ── The task tool spans the right term ───────────────────────────────────
check( 'create_tasks is told the term varies',
	strpos( $mcp, 'commercial.plan_terms' ) !== false );
check( 'and that every task needs a category',
	strpos( $mcp, 'Put a category on every task' ) !== false,
	'an uncategorised row lands under Other, where nobody looks' );

// ── The two plans are written down once ──────────────────────────────────
check( 'the six-month plan keeps the whole foundation',
	strpos( $plans, 'still gets the **whole** of the foundation' ) !== false,
	'halving the foundation would be a worse version of the same thing, not less of it' );
check( 'and the extras are named',
	strpos( $plans, 'Competitor watch' ) !== false
	&& strpos( $plans, 'Quarterly content refresh' ) !== false );
check( 'with the rule that an extra must not decide whether they rank',
	strpos( $plans, 'load-bearing for results is not a premium' ) !== false );
check( 'the twelve-row table is specified',
	strpos( $plans, 'Twelve rows, one per month' ) !== false );
check( 'counts rather than names, because twelve rows answer how much and when',
	strpos( $plans, '**Counts, not names.**' ) !== false );
check( 'and the last three months are called out as real work',
	strpos( $plans, 'are not padding' ) !== false,
	'a plan that front-loads its deliverables is noticed in month ten' );
check( 'no price appears in any plan document',
	strpos( $plans, '**A price.**' ) !== false );
check( 'the hundred topics stay a hundred on both plans',
	strpos( file_get_contents( $skl . 'seo-content-production/references/producing-a-page-set.md' ),
	        'stay a hundred on both plans' ) !== false,
	'the topic set is the research; how many get written is the engagement' );

// ── The audit ends with both ─────────────────────────────────────────────
$tpl = file_get_contents( $skl . 'seo-audit-report/references/report-template.md' );
check( 'every audit ends with the same two options',
	strpos( $tpl, '### `plans` — the same two options in every audit' ) !== false );
check( 'and never with a price',
	strpos( $tpl, 'No price in this block, ever' ) !== false );
check( 'the six-month column is a different scope, not a worse version',
	strpos( $tpl, 'not a worse version of the same thing' ) !== false );

echo "\nPassed: $pass  Failed: $fail\n";
exit( $fail ? 1 : 0 );
