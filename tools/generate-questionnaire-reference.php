<?php
/**
 * Regenerates the questionnaire field reference the SEO skills use to map a
 * returned questionnaire document onto the portal's question keys.
 *
 * Run from the repo root after changing portal/data-seo-questionnaire.php:
 *
 *   php tools/generate-questionnaire-reference.php
 *
 * It writes .claude/skills/seo-strategy-builder/references/questionnaire-fields.md.
 * Generated rather than hand-written so a question added to the schema can't
 * silently go missing from what the skills know how to map.
 */

define( 'ABSPATH', __DIR__ );   // the schema file guards on this
require __DIR__ . '/../portal/data-seo-questionnaire.php';

$schema = six_seo_questionnaire_schema();

$out  = "# Questionnaire fields\n\n";
$out .= "**Generated file — do not edit by hand.** Regenerate with\n";
$out .= "`php tools/generate-questionnaire-reference.php` in the 6ixPortal repo after\n";
$out .= "changing `portal/data-seo-questionnaire.php`.\n\n";
$out .= "This is the canonical list of questions the 6ix Developers portal stores, and\n";
$out .= "the key each answer is saved under. Use it when a client returns the\n";
$out .= "questionnaire as a document (email, PDF, Word) and you want to map their\n";
$out .= "answers onto the portal with `update_client_profile` — match on the question\n";
$out .= "text, write using the key.\n\n";
$out .= "A question marked **(from signup)** is already captured during onboarding, so\n";
$out .= "it will usually be filled already; writing it overwrites what they told us at\n";
$out .= "signup, so only do that if their document is genuinely more current.\n\n";
$out .= "Questions marked **(optional)** don't count toward questionnaire completion.\n\n";

foreach ( $schema as $skey => $section ) {
	$out .= "## " . $section['title'] . "  \n";
	$out .= "`" . $skey . "`\n\n";
	if ( ! empty( $section['intro'] ) ) $out .= "_" . $section['intro'] . "_\n\n";

	$out .= "| Key | Question | Type | Notes |\n|---|---|---|---|\n";
	foreach ( $section['fields'] as $f ) {
		$notes = array();
		if ( ! empty( $f['maps_to'] ) )     $notes[] = 'from signup';
		if ( ! empty( $f['optional'] ) )    $notes[] = 'optional';
		if ( ! empty( $f['skip_if_svc'] ) ) $notes[] = 'not asked if they bought ' . implode( '/', (array) $f['skip_if_svc'] );
		if ( ! empty( $f['options'] ) )     $notes[] = 'one of: ' . implode( ' / ', (array) $f['options'] );

		$out .= sprintf(
			"| `%s` | %s | %s | %s |\n",
			$f['key'],
			str_replace( '|', '\\|', $f['label'] ),
			$f['type'],
			$notes ? str_replace( '|', '\\|', implode( '; ', $notes ) ) : ''
		);
	}
	$out .= "\n";
}

$counts = array(
	'total'    => count( six_seo_questionnaire_fields() ),
	'new'      => count( six_seo_questionnaire_columns() ),
);
$out .= "---\n\n";
$out .= sprintf(
	"%d questions in total; %d have their own storage and the rest reuse a field\nonboarding already captured.\n",
	$counts['total'], $counts['new']
);

$target = __DIR__ . '/../.claude/skills/seo-strategy-builder/references/questionnaire-fields.md';
file_put_contents( $target, $out );
echo "Wrote " . realpath( $target ) . " (" . $counts['total'] . " questions)\n";

// ── Blank questionnaire, for clients who'd rather fill in a document ──────
// Same schema, so what we email out can never drift from what the portal asks.
// The contact section is skipped: we already hold those details, and asking
// again just gives us a second version to reconcile.
$blank  = "# SEO Questionnaire\n\n";
$blank .= "Everything we need to audit your site and build your strategy.\n\n";
$blank .= "Fill in whatever you can and send it back — you don't have to answer\n";
$blank .= "everything, and anything you'd rather talk through, just say so and we'll\n";
$blank .= "cover it on the call. If you'd prefer to fill this in online instead, it's\n";
$blank .= "in your 6ix Developers portal under **Questionnaire**, where it saves as you go.\n\n";
$blank .= "---\n\n";

foreach ( $schema as $skey => $section ) {
	if ( $skey === 'contact' ) continue;
	$blank .= "## " . $section['title'] . "\n\n";
	if ( ! empty( $section['intro'] ) ) $blank .= "_" . $section['intro'] . "_\n\n";

	foreach ( $section['fields'] as $f ) {
		$suffix = ! empty( $f['optional'] ) ? ' _(optional)_' : '';
		$blank .= "**" . $f['label'] . "**" . $suffix . "\n\n";
		if ( ! empty( $f['help'] ) ) $blank .= "_" . $f['help'] . "_\n\n";
		if ( ! empty( $f['options'] ) ) {
			foreach ( (array) $f['options'] as $opt ) $blank .= "- [ ] " . $opt . "\n";
			$blank .= "\n";
		} else {
			$blank .= "> \n\n";
		}
	}
	$blank .= "---\n\n";
}
$blank .= "Thank you for taking the time to complete this. We'll be in touch shortly to\nconfirm next steps.\n";

$blank_target = __DIR__ . '/../.claude/skills/seo-strategy-builder/assets/blank-questionnaire.md';
file_put_contents( $blank_target, $blank );
echo "Wrote " . realpath( $blank_target ) . "\n";
