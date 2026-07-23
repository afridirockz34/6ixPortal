<?php
/**
 * 6ixClaude — functions.php
 * Portal: Customer | Advisor | Sales
 * Integrations: Odoo CRM | Stripe | Google Ads | Google Calendar
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ── STANDALONE THEME MIGRATION ───────────────────────────────────────────────
// This theme used to be a Divi child. Once style.css stops declaring a parent,
// WordPress still has the old template option cached — re-activate once so
// get_template() points at this theme instead of Divi.
add_action( 'init', function () {
    if ( get_option( 'six_theme_standalone_v1' ) ) return;
    $stylesheet = get_option( 'stylesheet' );
    if ( $stylesheet && get_option( 'template' ) !== $stylesheet && ! wp_get_theme( $stylesheet )->parent() ) {
        switch_theme( $stylesheet );
    }
    update_option( 'six_theme_standalone_v1', 1 );
}, 1 );

// ── CONSTANTS ───────────────────────────────────────────────────────────────
define( 'SIX_PORTAL_VERSION', '1.0.0' );
define( 'SIX_PLUGIN_DIR',     get_stylesheet_directory() . '/portal/' );
define( 'SIX_PLUGIN_URL',     get_stylesheet_directory_uri() . '/portal/' );

// ── LOAD PORTAL MODULES ─────────────────────────────────────────────────────
require_once SIX_PLUGIN_DIR . 'class-missing.php';
require_once SIX_PLUGIN_DIR . 'class-icons.php';      // SVG icon library
require_once SIX_PLUGIN_DIR . 'class-questionnaire.php';
require_once SIX_PLUGIN_DIR . 'ajax-onboarding.php';

$optional_files = array(
    'class-odoo.php',          // must load before growth engine
    'class-estimate-engine.php', // Estimate Engine — real data growth plans
    'class-growth-engine.php', // Growth Engine
    'class-stripe.php',
    'class-google-ads-calendar.php',
    'class-appointments.php',   // unified calls/meetings store (after calendar + odoo)
    'ajax-handlers.php',
    'admin-settings.php',
    'social-login.php',        // Nextend Social Login (Google) integration
);
// ── MARKETING SITE (public website redesign) ────────────────────────────────
$marketing_loader = get_stylesheet_directory() . '/marketing/marketing.php';
if ( file_exists( $marketing_loader ) ) require_once $marketing_loader;
foreach ( $optional_files as $file ) {
    $path = SIX_PLUGIN_DIR . $file;
    if ( file_exists( $path ) ) {
        require_once $path;
    }
}

// ── ENQUEUE ASSETS ──────────────────────────────────────────────────────────
add_action( 'wp_enqueue_scripts', 'six_enqueue_assets' );
function six_enqueue_assets() {
    if ( ! is_user_logged_in() ) return;

    $portal_slugs = array( 'portal', 'advisor-portal', 'sales-portal', 'get-started', 'internal-hub' );
    $current_slug = get_post_field( 'post_name', get_the_ID() );
    if ( ! in_array( $current_slug, $portal_slugs, true ) ) return;

    // Fonts — Syne + DM Sans + Inter (for number displays)
    wp_enqueue_style(
        'six-fonts',
        'https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&family=Inter:wght@400;500;600;700;800;900&display=swap',
        array(),
        null
    );

    // Portal CSS
    $css_path = SIX_PLUGIN_DIR . 'assets/portal.css';
    if ( file_exists( $css_path ) ) {
        wp_enqueue_style(
            'six-portal',
            SIX_PLUGIN_URL . 'assets/portal.css',
            array(),
            filemtime( $css_path )  // bust cache on every file change
        );
    }

    // Portal JS
    wp_enqueue_script( 'chartjs', 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js', array(), '4.4.1', true );
    $js_path = SIX_PLUGIN_DIR . 'assets/portal.js';
    if ( file_exists( $js_path ) ) {
        wp_enqueue_script(
            'six-portal',
            SIX_PLUGIN_URL . 'assets/portal.js',
            array( 'jquery' ),
            SIX_PORTAL_VERSION,
            true
        );
    }

    wp_localize_script( 'six-portal', 'sixPortal', array(
        'ajax_url'      => admin_url( 'admin-ajax.php' ),
        'rest_url'      => rest_url( 'six/v1/' ),
        'nonce'         => wp_create_nonce( 'six_nonce' ),
        'stripe_key'    => get_option( 'six_stripe_publishable_key' ),
        'user_id'       => get_current_user_id(),
        'user_role'     => class_exists( 'Six_Roles' ) ? Six_Roles::get_portal_role() : '',
        'user_name'     => wp_get_current_user()->display_name,
        'user_initials' => six_get_initials( wp_get_current_user()->display_name ),
    ) );
}

// ── HELPERS ─────────────────────────────────────────────────────────────────
function six_get_initials( $name ) {
    $parts    = explode( ' ', trim( $name ) );
    $initials = '';
    foreach ( array_slice( $parts, 0, 2 ) as $p ) {
        $initials .= strtoupper( mb_substr( $p, 0, 1 ) );
    }
    return $initials ?: 'U';
}

// ── CUSTOM ROLES ─────────────────────────────────────────────────────────────
add_action( 'init', 'six_register_roles' );
function six_register_roles() {
    if ( ! get_role( 'six_customer' ) ) {
        add_role( 'six_customer', 'Portal Customer', array(
            'read'         => true,
            'six_view_own' => true,
        ) );
    }
    if ( ! get_role( 'six_advisor' ) ) {
        add_role( 'six_advisor', 'Portal Advisor', array(
            'read'                 => true,
            'six_manage_clients'   => true,
            'six_upload_reports'   => true,
            'six_approve_services' => true,
        ) );
    }
    if ( ! get_role( 'six_sales' ) ) {
        add_role( 'six_sales', 'Sales Representative', array(
            'read'           => true,
            'six_view_leads' => true,
        ) );
    }
}

// ── DATABASE TABLES ──────────────────────────────────────────────────────────
function six_create_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $tables = array(
        "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}six_checkout_progress (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            score int(3) DEFAULT 0,
            step varchar(50) DEFAULT 'account_created',
            business_name varchar(255) DEFAULT '',
            industry varchar(100) DEFAULT '',
            monthly_revenue varchar(50) DEFAULT '',
            services_selected text,
            budget decimal(10,2) DEFAULT 0,
            contract_signed tinyint(1) DEFAULT 0,
            card_saved tinyint(1) DEFAULT 0,
            odoo_lead_id varchar(100) DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id)
        ) $charset",

        "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}six_assignments (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            client_id bigint(20) NOT NULL,
            advisor_id bigint(20) NOT NULL,
            assigned_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY client_id (client_id)
        ) $charset",

        "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}six_client_services (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            client_id bigint(20) NOT NULL,
            service_slug varchar(50) NOT NULL,
            service_name varchar(100) NOT NULL,
            status varchar(20) DEFAULT 'pending',
            budget decimal(10,2) DEFAULT 0,
            approved_at datetime,
            approved_by bigint(20),
            odoo_project_id varchar(100) DEFAULT '',
            PRIMARY KEY (id),
            UNIQUE KEY client_service (client_id, service_slug)
        ) $charset",

        "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}six_metrics (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            client_id bigint(20) NOT NULL,
            service_slug varchar(50) DEFAULT '',
            label varchar(100) NOT NULL,
            previous_value varchar(100) DEFAULT '',
            current_value varchar(100) DEFAULT '',
            target_value varchar(100) DEFAULT '',
            unit varchar(20) DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY client_service (client_id, service_slug)
        ) $charset",

        "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}six_messages (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            sender_id bigint(20) NOT NULL,
            receiver_id bigint(20) NOT NULL,
            message text NOT NULL,
            is_read tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY thread (sender_id, receiver_id)
        ) $charset",

        "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}six_notifications (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            type varchar(50) DEFAULT '',
            title varchar(255) DEFAULT '',
            message text,
            is_read tinyint(1) DEFAULT 0,
            action_url varchar(500) DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id)
        ) $charset",

        "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}six_recommendations (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            client_id bigint(20) NOT NULL,
            advisor_id bigint(20) NOT NULL,
            title varchar(255) DEFAULT '',
            description text,
            action_label varchar(100) DEFAULT '',
            action_type varchar(50) DEFAULT '',
            action_value text,
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset",

        "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}six_reports (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            client_id bigint(20) NOT NULL,
            advisor_id bigint(20) NOT NULL,
            title varchar(255) DEFAULT '',
            type varchar(50) DEFAULT '',
            file_url varchar(500) DEFAULT '',
            file_size varchar(20) DEFAULT '',
            period varchar(50) DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY client_id (client_id)
        ) $charset",
    );

    foreach ( $tables as $sql ) {
        dbDelta( $sql );
    }
}

// ── PORTAL TEMPLATE ──────────────────────────────────────────────────────────
add_filter( 'template_include', 'six_portal_template' );
function six_portal_template( $template ) {
    $portal_slugs = array( 'portal', 'advisor-portal', 'sales-portal', 'get-started', 'internal-hub' );
    if ( is_page( $portal_slugs ) ) {
        $custom = get_stylesheet_directory() . '/portal-page.php';
        if ( file_exists( $custom ) ) return $custom;
    }
    return $template;
}

// ── HIDE ADMIN BAR ────────────────────────────────────────────────────────────
add_action( 'after_setup_theme', function() {
    $role = class_exists( 'Six_Roles' ) ? Six_Roles::get_portal_role() : '';
    if ( in_array( $role, array( 'six_customer', 'six_advisor', 'six_sales' ), true ) ) {
        show_admin_bar( false );
    }
} );

// ── LOGIN REDIRECTS ───────────────────────────────────────────────────────────
add_action( 'template_redirect', 'six_serve_onboarding_page', 0 );
function six_serve_onboarding_page() {
    if ( ! is_page( 'get-started' ) ) return;

    if ( is_user_logged_in() ) {
        $uid  = get_current_user_id();
        $role = class_exists('Six_Roles') ? Six_Roles::get_portal_role($uid) : '';

        // Logged in but no portal role (e.g. social login created the account
        // as 'subscriber' before the NSL hooks ran) — repair it to a customer
        // so onboarding and the portal routers work.
        if ( $role === 'guest' && ! current_user_can('manage_options') && function_exists('six_social_prepare_user') ) {
            six_social_prepare_user( $uid );
            $role = Six_Roles::get_portal_role( $uid );
        }

        if ( $role === 'six_advisor' )   { wp_redirect( home_url('/advisor-portal/') ); exit; }
        if ( $role === 'six_sales' )     { wp_redirect( home_url('/sales-portal/') ); exit; }
        if ( $role === 'administrator' ) { wp_redirect( admin_url() ); exit; }
        if ( $role === 'six_customer' ) {
            $done = get_user_meta($uid, 'six_checkout_completed', true);
            if ( $done ) { 
                // Prevent redirect loop with portal-page.php
                $ref = sanitize_url( $_SERVER['HTTP_REFERER'] ?? '' );
                if ( strpos($ref, home_url('/portal/')) === false ) {
                    wp_redirect( home_url('/portal/') ); exit;
                }
            }
        }
    }

    $user_id     = is_user_logged_in() ? get_current_user_id() : 0;
    $resume_step = 0;
    if ( $user_id ) {
        global $wpdb;
        $row         = $wpdb->get_row( $wpdb->prepare("SELECT step FROM {$wpdb->prefix}six_checkout_progress WHERE user_id=%d", $user_id) );
        $resume_step = $row ? intval($row->step) : 1;
    }
    $js_data = array(
        'ajax_url'      => admin_url('admin-ajax.php'),
        'nonce'         => wp_create_nonce('six_nonce'),
        'stripe_key'    => get_option('six_stripe_publishable_key', ''),
        'user_id'       => $user_id,
        'email'         => $user_id ? wp_get_current_user()->user_email : '',
        'resume_step'   => $resume_step,
        // Email passed from the marketing "Client Login" for an unknown address —
        // prefill it and jump straight into the flow.
        'prefill_email' => ( ! $user_id && ! empty($_GET['email']) ) ? sanitize_email( wp_unslash($_GET['email']) ) : '',
    );

    $css_url = get_stylesheet_directory_uri() . '/portal/assets/portal.css';
    $css_ver = @filemtime( get_stylesheet_directory() . '/portal/assets/portal.css' ) ?: '1';

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
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?php echo esc_url($css_url . '?v=' . $css_ver); ?>">
<script src="https://js.stripe.com/v3/"></script>
</head>
<body>
<script>var sixPortal = <?php echo wp_json_encode($js_data); ?>;</script>
<?php
    $tpl = get_stylesheet_directory() . '/portal/templates/onboarding.php';
    if ( file_exists($tpl) ) include $tpl;
    else echo '<div style="padding:60px;font-family:sans-serif;color:red">Template not found: ' . esc_html($tpl) . '</div>';
?>
</body>
</html>
<?php
    exit;
}

add_filter( 'login_url', 'six_custom_login_url', 10, 3 );
function six_custom_login_url( $login_url, $redirect, $force_reauth ) {
    if ( is_admin() ) return $login_url;
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if ( strpos($uri, 'get-started') !== false ) return $login_url;
    if ( $redirect && strpos($redirect, 'get-started') !== false ) return $login_url;
    $url = home_url('/get-started/');
    if ( $redirect ) $url = add_query_arg('redirect_to', urlencode($redirect), $url);
    return $url;
}

remove_filter( 'login_redirect', 'six_login_redirect', 10 );
add_filter( 'login_redirect', 'six_portal_login_redirect', 20, 3 );
function six_portal_login_redirect( $redirect_to, $requested, $user ) {
    if ( is_wp_error($user) || ! class_exists('Six_Roles') ) return $redirect_to;
    $role = Six_Roles::get_portal_role($user->ID);
    if ( $role === 'six_advisor' )  return home_url('/advisor-portal/');
    if ( $role === 'six_sales' )    return home_url('/sales-portal/');
    if ( $role === 'six_customer' ) {
        $done = get_user_meta($user->ID, 'six_checkout_completed', true);
        return $done ? home_url('/portal/') : home_url('/get-started/');
    }
    return $redirect_to;
}

// ── SCORE CALCULATORS ─────────────────────────────────────────────────────────
function six_calculate_checkout_score( $user_id ) {
    global $wpdb;
    $row = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}six_checkout_progress WHERE user_id = %d", $user_id
    ) );
    if ( ! $row ) return 0;
    $score = 0;
    if ( $row->user_id )           $score += 10;
    if ( $row->business_name )     $score += 20;
    if ( $row->services_selected ) $score += 20;
    if ( $row->budget )            $score += 20;
    if ( $row->contract_signed )   $score += 20;
    if ( $row->card_saved )        $score += 10;
    $wpdb->update( "{$wpdb->prefix}six_checkout_progress",
        array( 'score' => $score, 'updated_at' => current_time('mysql') ),
        array( 'user_id' => $user_id )
    );
    if ( class_exists('Six_Odoo') ) Six_Odoo::sync_lead( $user_id, array('checkout_score' => $score) );
    return $score;
}

function six_calculate_health_score( $client_id ) {
    return class_exists('Six_Health_Score') ? Six_Health_Score::calculate($client_id) : 0;
}

function six_calculate_readiness_score( $data ) {
    $score = 0;
    if ( ! empty( $data['monthly_revenue'] ) )    $score += 20;
    if ( ! empty( $data['industry'] ) )            $score += 10;
    if ( ! empty( $data['current_traffic'] ) )     $score += 15;
    if ( ! empty( $data['marketing_channels'] ) )  $score += 20;
    if ( ! empty( $data['goals'] ) )               $score += 15;
    $rev = intval( preg_replace('/[^0-9]/', '', $data['monthly_revenue'] ?? '') );
    if ( $rev > 50000 )  $score += 10;
    if ( $rev > 100000 ) $score += 10;
    return min(100, $score);
}

// ── TRACK ACTIVITY ────────────────────────────────────────────────────────────
add_action( 'wp', function() {
    if ( is_user_logged_in() ) {
        $slugs = array('portal', 'advisor-portal', 'sales-portal');
        if ( in_array( get_post_field('post_name', get_the_ID()), $slugs, true ) ) {
            update_user_meta( get_current_user_id(), 'six_last_activity', current_time('mysql') );
        }
    }
} );

// ── AJAX: get advisor for user ─────────────────────────────────────────────────
if ( ! function_exists('six_ajax_get_advisor_for_user') ) {
    add_action( 'wp_ajax_six_get_advisor_for_user',        'six_ajax_get_advisor_for_user' );
    add_action( 'wp_ajax_nopriv_six_get_advisor_for_user', 'six_ajax_get_advisor_for_user' );
    function six_ajax_get_advisor_for_user() {
        check_ajax_referer('six_nonce', 'nonce');
        $uid = intval($_POST['user_id'] ?? 0);
        if ( ! $uid ) { wp_send_json_success(null); return; }
        global $wpdb;
        $aid = $wpdb->get_var($wpdb->prepare(
            "SELECT advisor_id FROM {$wpdb->prefix}six_assignments WHERE client_id=%d", $uid
        ));
        if ( ! $aid ) { wp_send_json_success(null); return; }
        $adv = get_userdata($aid);
        $ini = function_exists('six_get_initials') ? six_get_initials($adv->display_name) : strtoupper(substr($adv->display_name,0,2));
        wp_send_json_success(array(
            'id'        => $aid,
            'name'      => $adv->display_name,
            'email'     => $adv->user_email,
            'initials'  => $ini,
            'role'      => 'Account Manager · 6ix Developers',
            'expertise' => array('Google Ads', 'SEO', 'Growth Strategy'),
        ));
    }
}

// ── AUTO-RUN DB MIGRATIONS ────────────────────────────────────────────────────
// Delegates to six_onboarding_db_upgrade() (ajax-onboarding.php) — the single
// source of truth for schema migrations. The previous inline v6 copy here was
// a subset that marked v6 done without adding the schedule_call_* columns,
// which broke six_schedule_onboarding_call on sites where it ran first.
add_action('init', function() {
    if ( ! is_user_logged_in() ) return;
    if ( get_option('six_onboarding_db_v7') ) return;
    if ( function_exists('six_onboarding_db_upgrade') ) {
        six_onboarding_db_upgrade();
    }
}, 20);
