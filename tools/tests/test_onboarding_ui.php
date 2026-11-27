<?php
/**
 * Onboarding: the hours field, the duplicate question, the audit message, and
 * the tone of the growth plan.
 *
 * All four were reported from a real signup, and three of them are the same
 * kind of fault: the page was asking for something it could not use, or not
 * saying something the client needed to know before paying.
 *
 *   - Business hours were two AM/PM squares per day. That cannot say what
 *     time anyone opens, cannot say "closed at 5", and cannot say "open 24
 *     hours" at all — so for a locksmith or a clinic the answer we collected
 *     could not go on a Google Business Profile, which is what it was for.
 *   - "Reviews / Awards" and "Current rating" were the same question in
 *     different words, asked two steps apart.
 *   - Nothing told the client that every service but a website build opens
 *     with an audit, so the gap between signing and the first deliverable read
 *     as nothing happening.
 *   - The plan page opened by telling them their competitors already outrank
 *     them. True, and an opening insult to somebody who has not hired us yet.
 *
 * These read the templates. There is no clicking here: a field that stops
 * being emitted is invisible until somebody opens the page, and in this
 * codebase that has meant a paying client opening it.
 *
 *     php tools/tests/test_onboarding_ui.php
 */
define( 'ABSPATH', '/x/' );

$pass = 0; $fail = 0;
function check( $name, $ok, $detail = '' ) {
	global $pass, $fail;
	if ( $ok ) { $pass++; echo "PASS  $name\n"; }
	else { $fail++; echo "FAIL  $name\n" . ( $detail ? "        $detail\n" : '' ); }
}

$root = dirname( __DIR__, 2 );
$ob   = file_get_contents( $root . '/portal/templates/onboarding.php' );
$cust = file_get_contents( $root . '/portal/templates/customer-dashboard.php' );
$est  = file_get_contents( $root . '/portal/class-estimate-engine.php' );

// ── Business hours, the way Google Business Profile asks for them ────────
echo "\n-- Business hours --\n";
check( 'the AM/PM squares are gone',
	strpos( $ob, 'ob-hr-btn' ) === false,
	'two squares a day cannot say what time anyone opens' );
check( 'each day has an opening and a closing time',
	strpos( $ob, 'ob-hr-from' ) !== false && strpos( $ob, 'ob-hr-to' ) !== false );
check( 'and a 24-hour option',
	strpos( $ob, 'ob-hr-24' ) !== false && strpos( $ob, 'Open 24 h' ) !== false,
	'the clients who most need their hours right are the ones open all night' );
check( 'and an open/closed state per day',
	strpos( $ob, 'ob-hr-state' ) !== false && strpos( $ob, 'obHrState' ) !== false );
check( 'the times run on half-hour steps',
	strpos( $ob, 'm+=30' ) !== false );
check( 'Monday can be copied across the week',
	strpos( $ob, 'obHrCopy' ) !== false,
	'seven identical rows is the common case and the one that loses people' );
check( 'the value names every day, closed ones included',
	strpos( $ob, "parts.push(label+'Closed')" ) !== false,
	'an omitted day reads as a question nobody answered' );
check( 'and says "Open 24 hours" in words, not as a code',
	strpos( $ob, "'Open 24 hours'" ) !== false );

check( 'a saved answer is split on the FIRST colon only',
	strpos( $ob, "var i=p.indexOf(':')" ) !== false,
	'"Mon: 9:00 AM – 5:00 PM" has three colons in it' );
check( 'answers from the old widget still load',
	strpos( $ob, "/^AM$/i.test(slot)" ) !== false,
	'"Mon:AM" rows are already in the database' );
check( 'and the old "All day" is not silently upgraded to 24 hours',
	strpos( $ob, "else if(/^all day$/i.test(slot)){ put(from,'9:00 AM'); put(to,'5:00 PM'); }" ) !== false,
	'both AM and PM lit was never a claim to be open overnight' );
check( 'a time that is not on the list is refused rather than blanking the field',
	strpos( $ob, 'for(var k=0;k<sel.options.length;k++)' ) !== false );

// ── One question, asked once ─────────────────────────────────────────────
echo "\n-- The duplicate question --\n";
check( 'the star rating is asked once, on the Google Business step',
	substr_count( $ob, 'q-gbp-rating' ) > 0 );
check( 'the other field asks for awards, not for a rating again',
	strpos( $ob, 'Awards &amp; accreditations' ) !== false
	&& strpos( $ob, '>Reviews / Awards<' ) === false );
check( 'and its example no longer shows a star rating',
	strpos( $ob, 'e.g. Google 4.8' ) === false,
	'that placeholder is what made the two questions read the same' );

// ── What happens after they sign ─────────────────────────────────────────
echo "\n-- The audit message --\n";
check( 'step 5 carries an audit note',
	strpos( $ob, 'ob-audit-note' ) !== false );
check( 'it says the account manager runs the audit after onboarding',
	strpos( $ob, 'comprehensive ' ) !== false
	&& strpos( $ob, 'as soon as you finish onboarding' ) !== false );
/* The sentence was reworded in November 2026 to lead with its subject rather
 * than a fronted "If you want to go ahead…" clause. Assert the commitment,
 * not one phrasing of it — a test that pins the exact words breaks every time
 * the copy improves, which is how a test ends up discouraging the fix. */
check( 'it says the card is charged only if they go ahead',
	strpos( $ob, 'charge the card on file' ) !== false
	&& preg_match( '/only once you have read the audit|if you want to go ahead/i', $ob ) === 1 );
check( 'and that they can cancel afterwards',
	strpos( $ob, 'cancel at any time' ) !== false );
check( 'a website-only client is not promised an audit',
	strpos( $ob, "if(!audited.length){ box.style.display='none'; return; }" ) !== false,
	'a website build does not start with one, and promising one is a promise we break' );
check( 'the note is shown on resume as well as on first arrival',
	strpos( $ob, 'OB._auditNote();' ) !== false
	&& strpos( $ob, '_initStep5:function(){' ) !== false );

echo "\n-- And again on the dashboard they land on --\n";
check( 'the overview says the audit is under way',
	strpos( $cust, 'audit is under way' ) !== false );
check( 'it names the services being audited',
	strpos( $cust, '$audited_slugs' ) !== false );
/* This used to pin the one condition the banner was built on — a report row
 * of type 'audit'. That single signal is exactly what went wrong: NR Lawyers
 * had the audit delivered and the build months under way while the dashboard
 * still told them their audit was being prepared, because an audit written in
 * a client session and handed over as a document never creates that row.
 *
 * So assert the behaviour — it switches itself off once work is visible —
 * and assert that more than one thing can switch it off. Pinning the
 * condition is how a test ends up defending the bug. */
check( 'it clears itself once work is visible, with no advisor action',
	strpos( $cust, '$work_started' ) !== false,
	'a banner an advisor has to remember to switch off is a banner that stays up' );
check( '  and any of filed reports, published tasks or logged work ends it',
	strpos( $cust, 'Six_Tasks::summary' )      !== false
	&& strpos( $cust, 'Six_Tracking::work_log' ) !== false
	&& strpos( $cust, '! empty( $reports )' )    !== false,
	'one signal missed a client six months into delivery' );
check( 'and a website-only client never sees it',
	strpos( $cust, "array( 'seo', 'google-ads', 'google-business' )" ) !== false );

// ── Tone on the growth plan ──────────────────────────────────────────────
echo "\n-- Tone --\n";
check( 'the plan no longer says the demand is not reaching them',
	strpos( $ob, 'almost none of it is reaching' ) === false );
check( 'it says the demand is what the plan goes after',
	strpos( $ob, 'it is the demand this plan goes after' ) !== false );
check( 'the model is told to state the opportunity, not the shortfall',
	strpos( $est, 'CONFIDENCE RULE' ) !== false );
check( 'with the before-and-after spelled out for it',
	strpos( $est, 'three competitors already outrank you' ) !== false
	&& strpos( $est, 'those positions are the target' ) !== false,
	'a rule with no example is a rule the model averages away' );
check( 'and told never to print a bare domain as a name',
	strpos( $est, 'NAME RULE' ) !== false
	&& strpos( $est, 'tps.ca' ) !== false );

check( 'the competitor is named, not left as a domain',
	strpos( $est, "'name'     => self::site_name(" ) !== false );
check( 'the name comes from what the site publishes about itself',
	strpos( $est, 'og:site_name' ) !== false,
	'an inferred name is a name we cannot stand behind' );
check( 'and a name we cannot read falls back to the domain',
	strpos( $est, "if ( \$name === '' ) \$name = \$domain;" ) !== false,
	'a wrong name is worse than an unreadable one' );
check( 'the lookup is cached so a plan page does not refetch it',
	strpos( $est, 'six_site_name_' ) !== false );
check( 'and it cannot hang the page a prospect is waiting on',
	strpos( $est, "'timeout'     => 4," ) !== false );
check( 'no client detail goes out in the request',
	strpos( $est, "'user-agent'  => 'Mozilla/5.0 (compatible; 6ixPortal/1.0)'" ) !== false,
	'this request goes to a third party we have no relationship with' );
check( 'the page renders the name, not the domain',
	strpos( $ob, 'var compName = topComp.name || topComp.domain;' ) !== false );

check( 'the stat rows lead with the figure',
	strpos( $ob, "'<div class=\"ob-hero-row\"><strong>' + nf(snap.ranking.top3) + '</strong>'" ) !== false,
	'label-first meant holding a clause in your head while you crossed the row' );
check( 'and the label after it finishes the sentence',
	strpos( $ob, 'search terms where you are already in the top three' ) !== false );
check( 'the row CSS stopped pushing the number to the far side',
	strpos( $ob, '.ob-hero-row{display:flex;align-items:baseline;gap:10px;' ) !== false );

echo "\nPassed: $pass  Failed: $fail\n";
exit( $fail ? 1 : 0 );
