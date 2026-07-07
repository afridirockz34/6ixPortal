<?php
/**
 * ADD THIS BLOCK TO functions.php — replaces all previous versions
 * ─────────────────────────────────────────────────────────────────────────
 * Delete any previous functions named:
 *   six_intercept_wp_login, six_maybe_skip_auth_redirect,
 *   six_allow_public_onboarding, six_allow_get_started_public,
 *   six_custom_login_url, six_make_get_started_public,
 *   six_serve_onboarding_page, six_post_login_redirect,
 *   six_portal_login_redirect, six_login_redirect, six_ajax_get_advisor_for_user
 * ─────────────────────────────────────────────────────────────────────────
 */

// ── Serve /get-started/ before any plugin can redirect ────────────────────
add_action( 'template_redirect', 'six_serve_onboarding_page', 0 );
function six_serve_onboarding_page() {
    if ( ! is_page( 'get-started' ) ) return;

    // ── ROLE-BASED REDIRECT (fix bug: advisors/sales were landing here) ──
    if ( is_user_logged_in() ) {
        $uid  = get_current_user_id();
        $role = class_exists('Six_Roles') ? Six_Roles::get_portal_role($uid) : '';

        // Advisors always go to advisor portal
        if ( $role === 'six_advisor' ) {
            wp_redirect( home_url('/advisor-portal/') ); exit;
        }
        // Sales always go to sales portal
        if ( $role === 'six_sales' ) {
            wp_redirect( home_url('/sales-portal/') ); exit;
        }
        // Admins go to wp-admin
        if ( current_user_can('manage_options') && $role === '' ) {
            wp_redirect( admin_url() ); exit;
        }
        // Customers who finished checkout → dashboard
        if ( $role === 'six_customer' ) {
            $done = get_user_meta($uid, 'six_checkout_completed', true);
            if ( $done ) { wp_redirect( home_url('/portal/') ); exit; }
            // else: fall through and show onboarding so they can resume
        }
    }

    // Build JS data
    $user_id     = is_user_logged_in() ? get_current_user_id() : 0;
    $resume_step = 0;
    if ( $user_id ) {
        global $wpdb;
        $row         = $wpdb->get_row( $wpdb->prepare("SELECT step FROM {$wpdb->prefix}six_checkout_progress WHERE user_id=%d", $user_id) );
        $resume_step = $row ? intval($row->step) : 1;
    }
    $js_data = array(
        'ajax_url'    => admin_url('admin-ajax.php'),
        'nonce'       => wp_create_nonce('six_nonce'),
        'stripe_key'  => get_option('six_stripe_publishable_key', ''),
        'user_id'     => $user_id,
        'email'       => $user_id ? wp_get_current_user()->user_email : '',
        'resume_step' => $resume_step,
    );

    $css_url = get_stylesheet_directory_uri() . '/portal/assets/portal.css';
    $css_ver = @filemtime(get_stylesheet_directory() . '/portal/assets/portal.css') ?: '1';

    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Get Started — <?php bloginfo('name'); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?php echo esc_url($css_url . '?v=' . $css_ver); ?>">
<script src="https://js.stripe.com/v3/"></script>
</head>
<body>
<script>var sixPortal = <?php echo wp_json_encode($js_data); ?>;</script>
<?php
    $tpl = get_stylesheet_directory() . '/portal/templates/onboarding.php';
    if ( file_exists($tpl) ) { include $tpl; }
    else { echo '<div style="padding:60px;font-family:sans-serif;color:red">Template not found: ' . esc_html($tpl) . '</div>'; }
?>
</body>
</html>
<?php
    exit;
}

// ── wp_login_url() → /get-started/ (with loop guard) ─────────────────────
add_filter( 'login_url', 'six_custom_login_url', 10, 3 );
function six_custom_login_url( $login_url, $redirect, $force_reauth ) {
    if ( is_admin() ) return $login_url;
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    // Loop guard: already on get-started or redirecting to it
    if ( strpos($uri, 'get-started') !== false ) return $login_url;
    if ( $redirect && strpos($redirect, 'get-started') !== false ) return $login_url;
    $url = home_url('/get-started/');
    if ( $redirect ) $url = add_query_arg('redirect_to', urlencode($redirect), $url);
    return $url;
}

// ── Post-WP-login redirect ────────────────────────────────────────────────
remove_filter( 'login_redirect', 'six_login_redirect', 10 );
add_filter( 'login_redirect', 'six_portal_login_redirect', 20, 3 );
function six_portal_login_redirect( $redirect_to, $requested, $user ) {
    if ( is_wp_error($user) || ! class_exists('Six_Roles') ) return $redirect_to;
    $role = Six_Roles::get_portal_role($user->ID);
    if ( $role === 'six_advisor' ) return home_url('/advisor-portal/');
    if ( $role === 'six_sales' )   return home_url('/sales-portal/');
    if ( $role === 'six_customer' ) {
        $done = get_user_meta($user->ID, 'six_checkout_completed', true);
        return $done ? home_url('/portal/') : home_url('/get-started/');
    }
    return $redirect_to;
}

// ── AJAX: get advisor for user ────────────────────────────────────────────
if ( ! function_exists('six_ajax_get_advisor_for_user') ) {
    add_action( 'wp_ajax_six_get_advisor_for_user',        'six_ajax_get_advisor_for_user' );
    add_action( 'wp_ajax_nopriv_six_get_advisor_for_user', 'six_ajax_get_advisor_for_user' );
    function six_ajax_get_advisor_for_user() {
        check_ajax_referer('six_nonce', 'nonce');
        $uid = intval($_POST['user_id'] ?? 0);
        if ( ! $uid ) { wp_send_json_success(null); return; }
        global $wpdb;
        $aid = $wpdb->get_var($wpdb->prepare("SELECT advisor_id FROM {$wpdb->prefix}six_assignments WHERE client_id=%d", $uid));
        if ( ! $aid ) { wp_send_json_success(null); return; }
        $adv = get_userdata($aid);
        $ini = function_exists('six_get_initials') ? six_get_initials($adv->display_name) : strtoupper(substr($adv->display_name,0,2));
        wp_send_json_success(array(
            'id'       => $aid, 'name' => $adv->display_name, 'email' => $adv->user_email,
            'initials' => $ini, 'role' => 'Account Manager · 6ix Developers',
            'expertise'=> array('Google Ads','SEO','Growth Strategy'),
        ));
    }
}
