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
require_once SIX_MK_DIR . 'ninja-forms.php'; // Ninja Forms auto-provisioning + admin controls
require_once SIX_MK_DIR . 'pages.php';      // service page content (keyed by slug)
require_once SIX_MK_DIR . 'cpt.php';        // Client Success + Testimonials (no plugins)
require_once SIX_MK_DIR . 'cpt-casestudy.php'; // Case Studies (brochure-style stories)
require_once SIX_MK_DIR . 'home-fields.php';   // Homepage Content editor (no ACF required)
require_once SIX_MK_DIR . 'setup.php';      // one-time page + front-page + seed setup
require_once SIX_MK_DIR . 'acf-fields.php'; // optional: only active if ACF is installed

/**
 * Register our marketing templates as real, selectable options in the
 * native WordPress "Template" dropdown (Page Attributes, in every editor).
 *
 * WordPress auto-discovers page templates by scanning the theme for
 * `Template Name:` headers, but only 1 level of subdirectories deep from
 * the theme root. Our templates live 2 levels deep (marketing/templates/),
 * so they were NEVER auto-discovered — meaning the dropdown always showed
 * "Default Template" as selected for these pages (never our actual
 * template), and saving the page via the normal editor SILENTLY reset
 * _wp_page_template to empty, since WordPress always writes back exactly
 * what the dropdown showed. That falls through to the theme's default page
 * rendering (raw post_content — including any old content left over from
 * before this redesign), which is why the page appears to "lose everything".
 *
 * This filter fixes it going forward. See setup.php for the one-time repair
 * that restores any page already broken by this.
 */
add_filter( 'theme_page_templates', function ( $templates ) {
    return array_merge( $templates, six_mk_managed_page_templates() );
} );

/** slug => [ template path, human label ] for every page this codebase manages. */
function six_mk_managed_page_templates_by_slug() {
    return array(
        'about-us'                              => array( 'marketing/templates/template-about.php', '6ix — About' ),
        'contact-us'                            => array( 'marketing/templates/template-contact.php', '6ix — Contact' ),
        'website-design-agency-toronto'         => array( 'marketing/templates/template-service.php', '6ix — Service Page' ),
        'ppc-google-ads-management-toronto'     => array( 'marketing/templates/template-service.php', '6ix — Service Page' ),
        'seo-agency-toronto'                    => array( 'marketing/templates/template-service.php', '6ix — Service Page' ),
        'social-media-marketing-agency-toronto' => array( 'marketing/templates/template-service.php', '6ix — Service Page' ),
        'ppc-agency-toronto'                    => array( 'marketing/templates/template-service.php', '6ix — Service Page' ),
        'digital-marketing-agency-toronto'      => array( 'marketing/templates/template-service.php', '6ix — Service Page' ),
        'case-studies'                          => array( 'marketing/templates/template-case-studies.php', '6ix — Case Studies' ),
    );
}

/** template path => human label, for the theme_page_templates dropdown (includes Home). */
function six_mk_managed_page_templates() {
    $out = array( 'marketing/templates/template-home.php' => '6ix — Home' );
    foreach ( six_mk_managed_page_templates_by_slug() as $entry ) {
        $out[ $entry[0] ] = $entry[1];
    }
    return $out;
}

/**
 * A page is a "marketing page" when it uses one of our marketing templates.
 * We check the assigned page template file path.
 */
function six_mk_is_marketing_page() {
    // Single Case Study views are rendered by a marketing template too, so they
    // need the same fonts + design system even though they aren't Pages.
    if ( is_singular( 'six_case_study' ) ) return true;
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

/**
 * Ninja Forms restyle — enqueued late (priority 30, vs. 20 above and
 * Ninja Forms' own default of 10) so it loads after both marketing.css and
 * Ninja Forms' own front-end CSS, and depends on Ninja Forms' main
 * stylesheet handle when present so the load order is guaranteed rather
 * than left to enqueue timing. Every rule in the file is also !important,
 * as a second guarantee regardless of source order.
 */
add_action( 'wp_enqueue_scripts', function () {
    if ( ! six_mk_is_marketing_page() ) return;
    if ( ! class_exists( 'Ninja_Forms' ) ) return;
    $css  = SIX_MK_DIR . 'assets/ninja-forms-theme.css';
    $deps = wp_style_is( 'nf-display', 'registered' ) ? array( 'nf-display' ) : array();
    wp_enqueue_style( 'six-mk-nf-theme', SIX_MK_URL . 'assets/ninja-forms-theme.css', $deps, file_exists( $css ) ? filemtime( $css ) : '1' );
}, 30 );

// On marketing pages, strip Divi's front-end shell so our template owns the page.
add_action( 'wp', function () {
    if ( ! six_mk_is_marketing_page() ) return;
    remove_action( 'wp_head', 'et_divi_load_scripts' );
    remove_action( 'wp_head', 'et_load_custom_scripts' );
    add_filter( 'body_class', function ( $c ) { $c[] = 'six-mk-body'; return $c; } );
} );
