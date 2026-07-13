<?php
/**
 * Template Name: Portal Page
 * Template Post Type: page
 *
 * MUST live in theme ROOT: /wp-content/themes/6ixClaude/portal-page.php
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// Redirect guests to login
if ( ! is_user_logged_in() ) {
    // Use the onboarding page as login — but only if not already redirecting
    $requested_url = home_url( $_SERVER['REQUEST_URI'] );
    // Prevent redirect loop: if already on get-started, don't redirect again
    $current_path = trim( parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );
    if ( strpos( $current_path, 'get-started' ) === false ) {
        wp_redirect( home_url( '/get-started/' ) );
        exit;
    }
}

$role         = Six_Roles::get_portal_role();
$current_slug = get_post_field( 'post_name', get_the_ID() );

// Fallback: detect from URL if get_the_ID() returns 0
if ( ! $current_slug ) {
    $path         = trim( parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );
    $parts        = explode( '/', $path );
    $current_slug = end( $parts ) ?: 'portal';
    // Strip subfolder prefix (e.g. "6ix-redesign")
    $home_path = trim( parse_url( home_url(), PHP_URL_PATH ), '/' );
    if ( $home_path ) {
        $current_slug = str_replace( $home_path . '/', '', ltrim( $path, '/' ) );
        $current_slug = trim( $current_slug, '/' );
        $parts        = explode( '/', $current_slug );
        $current_slug = $parts[0] ?: 'portal';
    }
}

// Role-based access control
$role_map = array(
    'portal'         => 'six_customer',
    'advisor-portal' => 'six_advisor',
    'sales-portal'   => 'six_sales',
);
if (
    isset( $role_map[ $current_slug ] ) &&
    $role !== $role_map[ $current_slug ] &&
    $role !== 'administrator'
) {
    $redirect_map = array(
        'six_customer' => home_url( '/portal/' ),
        'six_advisor'  => home_url( '/advisor-portal/' ),
        'six_sales'    => home_url( '/sales-portal/' ),
    );
    // Unknown role (e.g. subscriber from an old social login): send to
    // onboarding — the old fallback of /portal/ caused an infinite redirect
    // loop when the requested slug WAS /portal/.
    wp_redirect( $redirect_map[ $role ] ?? home_url( '/get-started/' ) );
    exit;
}

// CSS/JS URLs — direct, no enqueue dependency
$portal_css_url = get_stylesheet_directory_uri() . '/portal/assets/portal.css';
$portal_js_url  = get_stylesheet_directory_uri() . '/portal/assets/portal.js';
$portal_css_ver = filemtime( get_stylesheet_directory() . '/portal/assets/portal.css' ) ?: '1.0';
$portal_js_ver  = file_exists( get_stylesheet_directory() . '/portal/assets/portal.js' )
                  ? filemtime( get_stylesheet_directory() . '/portal/assets/portal.js' )
                  : false;

// Data for JS
$js_data = array(
    'ajax_url'      => admin_url( 'admin-ajax.php' ),
    'rest_url'      => rest_url( 'six/v1/' ),
    'nonce'         => wp_create_nonce( 'six_nonce' ),
    'stripe_key'    => get_option( 'six_stripe_publishable_key' ),
    'user_id'       => get_current_user_id(),
    'user_role'     => $role,
    'user_name'     => wp_get_current_user()->display_name,
    'user_initials' => six_get_initials( wp_get_current_user()->display_name ),
);
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html( get_the_title() ); ?> — <?php bloginfo( 'name' ); ?></title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">

    <!-- Portal CSS — loaded directly, no enqueue dependency -->
    <link rel="stylesheet" href="<?php echo esc_url( $portal_css_url . '?v=' . $portal_css_ver ); ?>">

    <?php
    // Still call wp_head for plugins that need it (SEO, security, etc.)
    // but we suppress Divi's heavy output
    remove_action( 'wp_head', 'et_divi_load_scripts' );
    remove_action( 'wp_head', 'et_load_custom_scripts' );
    wp_head();
    ?>
</head>
<body <?php body_class( 'six-portal-body' ); ?>>

<div id="six-portal-root">
    <?php
    // Internal hub: access restricted to advisors, sales, editors
    if ( $current_slug === 'internal-hub' ) {
        $allowed = ( $role === 'six_advisor' || $role === 'six_sales' || current_user_can('edit_posts') || current_user_can('manage_options') );
        if ( ! $allowed ) { wp_redirect( home_url('/portal/') ); exit; }
    }

    switch ( $current_slug ) {
        case 'advisor-portal':
            $view = SIX_PLUGIN_DIR . 'templates/advisor-dashboard.php';
            break;
        case 'sales-portal':
            $view = SIX_PLUGIN_DIR . 'templates/sales-dashboard.php';
            break;
        case 'internal-hub':
            $view = get_stylesheet_directory() . '/portal/templates/internal-product-hub.php';
            break;
        default:
            $view = SIX_PLUGIN_DIR . 'templates/customer-dashboard.php';
    }

    if ( file_exists( $view ) ) {
        include $view;
    } else {
        echo '<div style="padding:40px;color:#FF6699;font-family:sans-serif">
            <strong>Portal view not found:</strong><br>
            <code>' . esc_html( $view ) . '</code><br><br>
            Make sure the <code>portal/templates/</code> folder exists in your theme directory.
        </div>';
    }
    ?>
</div>

<!-- Portal JS inline data -->
<script>
var sixPortal = <?php echo wp_json_encode( $js_data ); ?>;
</script>

<?php if ( $portal_js_ver ) : ?>
<!-- Portal JS -->
<script src="<?php echo esc_url( $portal_js_url . '?v=' . $portal_js_ver ); ?>"></script>
<?php endif; ?>

<?php wp_footer(); ?>
</body>
</html>

