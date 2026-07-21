<?php
/**
 * Template Name: Onboarding / Get Started
 * Template Post Type: page
 *
 * MUST live in theme root: /wp-content/themes/6ixClaude/onboarding-page.php
 * Page slug: get-started
 *
 * IMPORTANT: This page must be PUBLIC — no login required.
 * It IS the login/signup page. WordPress must NOT redirect visitors away from it.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// If logged in AND checkout completed → send to dashboard, don't show onboarding
if ( is_user_logged_in() ) {
    $uid       = get_current_user_id();
    $completed = get_user_meta( $uid, 'six_checkout_completed', true );
    if ( $completed ) {
        $role = Six_Roles::get_portal_role( $uid );
        $dest = array(
            'six_advisor' => home_url( '/advisor-portal/' ),
            'six_sales'   => home_url( '/sales-portal/' ),
        );
        wp_redirect( $dest[ $role ] ?? home_url( '/portal/' ) );
        exit;
    }
    // Logged in but not completed → fall through and show onboarding (resume flow)
}

// Build JS data — safe for both guests and logged-in users
$user_id       = is_user_logged_in() ? get_current_user_id() : 0;
$stripe_key    = get_option( 'six_stripe_publishable_key', '' );
$resume_step   = 0;
if ( $user_id ) {
    global $wpdb;
    $progress  = $wpdb->get_row( $wpdb->prepare( "SELECT step, score FROM {$wpdb->prefix}six_checkout_progress WHERE user_id=%d", $user_id ) );
    $resume_step = $progress ? intval( $progress->step ?? 1 ) : 1;
}

$js_data = array(
    'ajax_url'      => admin_url( 'admin-ajax.php' ),
    'nonce'         => wp_create_nonce( 'six_nonce' ),
    'stripe_key'    => $stripe_key,
    'user_id'       => $user_id,
    'email'         => $user_id ? wp_get_current_user()->user_email : '',
    'resume_step'   => $resume_step,
    // Email passed from the marketing "Client Login" for an unknown address.
    'prefill_email' => ( ! $user_id && ! empty($_GET['email']) ) ? sanitize_email( wp_unslash($_GET['email']) ) : '',
);

$css_url = get_stylesheet_directory_uri() . '/portal/assets/portal.css';
$css_ver = @filemtime( get_stylesheet_directory() . '/portal/assets/portal.css' ) ?: '1';
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Get Started — <?php bloginfo( 'name' ); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?php echo esc_url( $css_url . '?v=' . $css_ver ); ?>">
<script src="https://js.stripe.com/v3/"></script>
<?php wp_head(); ?>
</head>
<body>
<script>var sixPortal = <?php echo wp_json_encode( $js_data ); ?>;</script>
<?php
$template = get_stylesheet_directory() . '/portal/templates/onboarding.php';
if ( file_exists( $template ) ) {
    include $template;
} else {
    echo '<div style="padding:60px;color:red;font-family:sans-serif">
        <strong>Onboarding template not found.</strong><br>
        Expected: <code>' . esc_html( $template ) . '</code>
    </div>';
}
?>
<?php wp_footer(); ?>
</body>
</html>
