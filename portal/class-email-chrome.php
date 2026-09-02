<?php
/**
 * Branded HTML email chrome — the shared visual shell every notification
 * email (form submissions + system-generated events) is wrapped in, so
 * every email the site sends reads as one professional, on-brand piece of
 * communication instead of ad-hoc wp_mail() strings.
 *
 * Deliberately table-based, inline-styled HTML (the email-safe subset —
 * no external stylesheet, no flex/grid) since it has to render consistently
 * in Outlook/Gmail/Apple Mail, not just modern browsers.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * wp_mail() wrapper that captures the REAL failure reason instead of just a
 * true/false. wp_mail() itself only tells you it couldn't hand the message
 * to PHPMailer; it stays silent about *why* unless you separately listen
 * for the 'wp_mail_failed' action, which is exactly what this does — a
 * temporary, single-use listener around one send so the caller can log the
 * actual PHPMailer/SMTP exception text (auth failure, rejected recipient,
 * connection refused, …) instead of the generic "wp_mail() returned false".
 *
 * Note this only ever fires when WordPress itself detects the failure. A
 * `true` return still only means "handed off to the mail server" — not
 * that it reached an inbox. A message that's silently spam-filtered or
 * dropped by the receiving server after acceptance looks identical to a
 * real success from here; that has to be diagnosed from the SMTP plugin's
 * own delivery/activity log, not from this return value.
 *
 * @return array array('sent'=>bool, 'error'=>string)
 */
function six_wp_mail( $to, $subject, $body, $headers = array() ) {
	$captured = null;
	$catch = function ( $wp_error ) use ( &$captured ) {
		$captured = is_wp_error( $wp_error ) ? $wp_error->get_error_message() : 'Unknown mail error.';
	};
	add_action( 'wp_mail_failed', $catch );
	$sent = wp_mail( $to, $subject, $body, $headers );
	remove_action( 'wp_mail_failed', $catch );

	return array(
		'sent'  => (bool) $sent,
		'error' => $sent ? '' : ( $captured ?: 'wp_mail() returned false — check the SMTP plugin\'s configuration and its own send/activity log (a "false" with no specific reason usually means the SMTP plugin itself blocked or misrouted it before WordPress could tell).' ),
	);
}

/** Brand palette, matching marketing/assets/marketing.css's --mk-* tokens. */
function six_email_palette() {
	return array(
		'pink'    => '#FF6699',
		'purple'  => '#8781BA',
		'blue'    => '#6ACAFD',
		'navy'    => '#031523',
		'bg'      => '#F1F3F8',
		'card'    => '#FFFFFF',
		'text1'   => '#101826',
		'text2'   => '#3D4757',
		'text3'   => '#627080',
		'border'  => '#E4E8F0',
	);
}

/** Same logo option/fallback header.php uses — the white variant, for the dark header band. */
function six_email_logo_url() {
	if ( function_exists( 'mk_opt' ) ) {
		return mk_opt( 'brand_logo_scrolled', 'https://6ixdevelopers.com/media/logo/new-logo-white.png' );
	}
	return 'https://6ixdevelopers.com/media/logo/new-logo-white.png';
}

/**
 * A brand-gradient CTA button. Table-based so it renders as a solid button
 * in Outlook (which ignores border-radius/box-shadow on <a> but respects it
 * on a <td> background).
 */
function six_email_button( $url, $label ) {
	$p = six_email_palette();
	return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:6px 6px 0 0;display:inline-block">'
		. '<tr><td style="border-radius:8px;background:' . esc_attr( $p['pink'] ) . '">'
		. '<a href="' . esc_url( $url ) . '" style="display:inline-block;padding:12px 22px;font-family:Helvetica,Arial,sans-serif;font-size:13.5px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:8px">' . esc_html( $label ) . ' &rarr;</a>'
		. '</td></tr></table>';
}

/**
 * A clean two-column "label / value" info table — used for submitted form
 * fields and system-event details alike.
 *
 * @param array $rows Assoc array of label => value (values already plain
 *                     text; this function escapes them).
 */
function six_email_info_table( array $rows ) {
	$p = six_email_palette();
	if ( ! $rows ) return '';
	$out = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:14px 0;border-collapse:collapse">';
	foreach ( $rows as $label => $value ) {
		$out .= '<tr>'
			. '<td style="padding:9px 12px;font-family:Helvetica,Arial,sans-serif;font-size:12.5px;color:' . esc_attr( $p['text3'] ) . ';border-bottom:1px solid ' . esc_attr( $p['border'] ) . ';width:38%;vertical-align:top">' . esc_html( $label ) . '</td>'
			. '<td style="padding:9px 12px;font-family:Helvetica,Arial,sans-serif;font-size:13.5px;color:' . esc_attr( $p['text1'] ) . ';border-bottom:1px solid ' . esc_attr( $p['border'] ) . ';vertical-align:top">' . nl2br( esc_html( (string) $value ) ) . '</td>'
			. '</tr>';
	}
	return $out . '</table>';
}

/**
 * Wrap content in the full branded email shell: dark header band with logo,
 * a white content card (heading + body + optional info table + optional
 * CTA buttons), and a plain footer.
 *
 * @param array $args {
 *   @type string $preheader  Hidden preview text shown next to the subject in inbox lists.
 *   @type string $heading    Card heading.
 *   @type string $body_html  Already-safe HTML for the main message (e.g. nl2br(esc_html(...))).
 *   @type array  $info_rows  Optional label=>value pairs rendered as a table below body_html.
 *   @type array  $links      Optional list of ['label'=>string,'url'=>string] rendered as CTA buttons.
 *   @type string $footer_note  Optional extra line under the standard footer.
 * }
 */
function six_email_chrome( array $args ) {
	$p = six_email_palette();
	$preheader   = $args['preheader']   ?? '';
	$heading     = $args['heading']     ?? '';
	$body_html   = $args['body_html']   ?? '';
	$info_rows   = $args['info_rows']   ?? array();
	$links       = $args['links']       ?? array();
	$footer_note = $args['footer_note'] ?? '';

	$buttons = '';
	foreach ( $links as $link ) {
		if ( empty( $link['url'] ) ) continue;
		$buttons .= six_email_button( $link['url'], $link['label'] ?? 'Open' );
	}

	ob_start(); ?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo esc_html( $heading ); ?></title>
</head>
<body style="margin:0;padding:0;background:<?php echo esc_attr( $p['bg'] ); ?>">
<?php if ( $preheader ) : ?>
<div style="display:none;max-height:0;overflow:hidden;opacity:0"><?php echo esc_html( $preheader ); ?></div>
<?php endif; ?>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:<?php echo esc_attr( $p['bg'] ); ?>;padding:32px 16px">
<tr><td align="center">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%">

	<tr><td style="background:linear-gradient(120deg,<?php echo esc_attr($p['navy']); ?>,#0B1E30);border-radius:14px 14px 0 0;padding:26px 30px" align="left">
		<img src="<?php echo esc_url( six_email_logo_url() ); ?>" height="26" alt="6ix Developers" style="height:26px;display:block">
	</td></tr>

	<tr><td style="background:<?php echo esc_attr( $p['card'] ); ?>;padding:32px 30px 8px" align="left">
		<?php if ( $heading ) : ?>
		<h1 style="margin:0 0 14px;font-family:Helvetica,Arial,sans-serif;font-size:20px;font-weight:800;color:<?php echo esc_attr( $p['text1'] ); ?>;line-height:1.3"><?php echo esc_html( $heading ); ?></h1>
		<?php endif; ?>
		<div style="font-family:Helvetica,Arial,sans-serif;font-size:14.5px;color:<?php echo esc_attr( $p['text2'] ); ?>;line-height:1.65"><?php echo $body_html; ?></div>
		<?php echo six_email_info_table( $info_rows ); ?>
		<?php if ( $buttons ) : ?>
		<div style="margin:20px 0 6px"><?php echo $buttons; ?></div>
		<?php endif; ?>
	</td></tr>

	<tr><td style="background:<?php echo esc_attr( $p['card'] ); ?>;border-radius:0 0 14px 14px;padding:22px 30px 30px" align="left">
		<div style="border-top:1px solid <?php echo esc_attr( $p['border'] ); ?>;padding-top:18px;font-family:Helvetica,Arial,sans-serif;font-size:11.5px;color:<?php echo esc_attr( $p['text3'] ); ?>;line-height:1.6">
			6ix Developers — SEO &amp; Google Ads Agency<br>
			<a href="https://6ixdevelopers.com" style="color:<?php echo esc_attr( $p['text3'] ); ?>">6ixdevelopers.com</a>
			<?php if ( $footer_note ) : ?><br><?php echo esc_html( $footer_note ); ?><?php endif; ?>
		</div>
	</td></tr>

</table>
</td></tr>
</table>
</body>
</html>
	<?php
	return ob_get_clean();
}
