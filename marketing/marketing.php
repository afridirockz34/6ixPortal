<?php
/**
 * 6ix Developers — Marketing Site loader
 *
 * Loads the redesigned public website: an AI-gradient design system whose
 * every section is editable from the WordPress dashboard via ACF fields
 * (registered in code — see acf-fields.php — so they are version-controlled
 * and deploy through the normal pipeline).
 *
 * Templates live in marketing/templates/*.php and declare a `Template Name:`
 * so they can be assigned to a Page in the WP editor.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'SIX_MK_DIR', get_stylesheet_directory() . '/marketing/' );
define( 'SIX_MK_URL', get_stylesheet_directory_uri() . '/marketing/' );

require_once SIX_MK_DIR . 'helpers.php';
require_once SIX_MK_DIR . 'forms.php';      // lead-capture forms (Ninja-Forms swappable)
require_once SIX_MK_DIR . 'pages.php';      // service page content (keyed by slug)
require_once SIX_MK_DIR . 'cpt.php';        // Client Success + Testimonials (no plugins)
require_once SIX_MK_DIR . 'setup.php';      // one-time page + front-page + seed setup
require_once SIX_MK_DIR . 'acf-fields.php'; // optional: only active if ACF is installed

/**
 * A page is a "marketing page" when it uses one of our marketing templates.
 * We check the assigned page template file path.
 */
function six_mk_is_marketing_page() {
    if ( ! is_page() ) return false;
    $tpl = get_page_template_slug( get_queried_object_id() );
    return $tpl && strpos( $tpl, 'marketing/templates/' ) === 0;
}

/**
 * Enqueue fonts + the marketing design system only on marketing pages, and
 * suppress Divi's heavy output there so our design is clean.
 */
add_action( 'wp_enqueue_scripts', function () {
    if ( ! six_mk_is_marketing_page() ) return;

    wp_enqueue_style(
        'six-mk-fonts',
        'https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600;700;800&family=Mulish:wght@300;400;500;600;700;800&family=Inter:wght@400;500;600;700;800;900&display=swap',
        array(), null
    );
    $css = SIX_MK_DIR . 'assets/marketing.css';
    wp_enqueue_style( 'six-mk', SIX_MK_URL . 'assets/marketing.css', array(), file_exists( $css ) ? filemtime( $css ) : '1' );

    $js = SIX_MK_DIR . 'assets/marketing.js';
    wp_enqueue_script( 'six-mk', SIX_MK_URL . 'assets/marketing.js', array(), file_exists( $js ) ? filemtime( $js ) : '1', true );
}, 20 );

// On marketing pages, strip Divi's front-end shell so our template owns the page.
add_action( 'wp', function () {
    if ( ! six_mk_is_marketing_page() ) return;
    remove_action( 'wp_head', 'et_divi_load_scripts' );
    remove_action( 'wp_head', 'et_load_custom_scripts' );
    add_filter( 'body_class', function ( $c ) { $c[] = 'six-mk-body'; return $c; } );
} );
