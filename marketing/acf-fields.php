<?php
/**
 * 6ix Developers — ACF field registration (in code)
 *
 * Field groups are registered here (not in the ACF UI) so the site structure
 * is version-controlled and deploys through the pipeline. Repeaters require
 * ACF Pro. Every field has a code-side default (see the templates/partials),
 * so pages render complete before anything is filled in.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'acf/init', function () {
    if ( ! function_exists( 'acf_add_options_page' ) ) return;
    acf_add_options_page( array(
        'page_title' => '6ix Site Settings',
        'menu_title' => '6ix Site',
        'menu_slug'  => 'six-site-settings',
        'capability' => 'manage_options',
        'icon_url'   => 'dashicons-admin-site-alt3',
        'position'   => 3,
    ) );
} );

add_action( 'acf/init', function () {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) return;

    $text     = fn( $k, $l, $d = '' ) => array( 'key' => $k, 'label' => $l, 'name' => substr( $k, 4 ), 'type' => 'text', 'default_value' => $d );
    $textarea = fn( $k, $l, $d = '', $r = 3 ) => array( 'key' => $k, 'label' => $l, 'name' => substr( $k, 4 ), 'type' => 'textarea', 'rows' => $r, 'default_value' => $d );
    $url      = fn( $k, $l, $d = '' ) => array( 'key' => $k, 'label' => $l, 'name' => substr( $k, 4 ), 'type' => 'text', 'default_value' => $d, 'instructions' => 'Full or relative URL, e.g. /contact-us' );
    $image    = fn( $k, $l ) => array( 'key' => $k, 'label' => $l, 'name' => substr( $k, 4 ), 'type' => 'image', 'return_format' => 'url', 'preview_size' => 'medium' );
    $tab      = fn( $k, $l ) => array( 'key' => $k, 'label' => $l, 'type' => 'tab' );

    // ── Global: header / footer / portal band ────────────────────────────
    acf_add_local_field_group( array(
        'key'      => 'group_six_site',
        'title'    => 'Site Settings (Header, Footer, Portal CTA)',
        'location' => array( array( array( 'param' => 'options_page', 'operator' => '==', 'value' => 'six-site-settings' ) ) ),
        'fields'   => array(
            $tab( 'six_site_header_tab', 'Header' ),
            $text( 'six_brand_name', 'Brand name', '6ix Developers' ),
            $image( 'six_brand_logo', 'Logo image' ),
            $image( 'six_brand_flag', 'Flag / secondary badge (optional)' ),
            $text( 'six_header_phone', 'Phone (display)', '888-808-7265' ),
            $text( 'six_header_phone_tel', 'Phone (dial digits)', '18888087265' ),
            array( 'key' => 'six_nav_services', 'label' => 'Services dropdown items', 'name' => 'nav_services', 'type' => 'repeater', 'layout' => 'table', 'button_label' => 'Add service link',
                'sub_fields' => array( $text( 'six_navs_label', 'Label' ), $url( 'six_navs_url', 'URL' ) ) ),
            $text( 'six_header_cta_label', 'Header button label', 'Contact us' ),
            $url( 'six_header_cta_url', 'Header button URL', '/contact-us' ),

            $tab( 'six_site_footer_tab', 'Footer' ),
            $text( 'six_footer_address', 'Address', '1550 South Gateway Rd. Mississauga, Ontario, Canada' ),
            $url( 'six_footer_map_url', 'Address link (Google Maps)', 'https://g.page/6ixdevelopers?share' ),
            $text( 'six_footer_email', 'Email', 'help@6ixdevelopers.com' ),
            $text( 'six_footer_tollfree', 'Toll-free phone', '888-808-7265' ),
            $text( 'six_footer_toronto', 'Toronto phone', '(416) 306-3443' ),
            array( 'key' => 'six_footer_links', 'label' => 'Footer links', 'name' => 'footer_links', 'type' => 'repeater', 'layout' => 'table', 'button_label' => 'Add link',
                'sub_fields' => array( $text( 'six_fl_label', 'Label' ), $url( 'six_fl_url', 'URL' ) ) ),
            array( 'key' => 'six_footer_social', 'label' => 'Social links', 'name' => 'footer_social', 'type' => 'repeater', 'layout' => 'table', 'button_label' => 'Add social',
                'sub_fields' => array( $text( 'six_soc_label', 'Network' ), $url( 'six_soc_url', 'URL' ) ) ),
            array( 'key' => 'six_footer_legal', 'label' => 'Legal links', 'name' => 'footer_legal', 'type' => 'repeater', 'layout' => 'table', 'button_label' => 'Add legal link',
                'sub_fields' => array( $text( 'six_leg_label', 'Label' ), $url( 'six_leg_url', 'URL' ) ) ),
            $image( 'six_footer_partner_badge', 'Google Partner badge' ),
            $url( 'six_footer_partner_url', 'Google Partner link', 'https://www.google.com/partners/agency?id=8013163615' ),
            $text( 'six_footer_established', 'Established text', 'Est. 2012' ),

            $tab( 'six_site_portal_tab', 'Portal CTA band' ),
            $text( 'six_portal_band_eyebrow', 'Eyebrow', 'Your Marketing OS' ),
            $text( 'six_portal_band_heading', 'Heading', 'Get to know the marketing side of your business.' ),
            $textarea( 'six_portal_band_text', 'Text', 'See exactly how you stack up against your competitors, where your leads come from, and what to fix next — in one live dashboard built for your business.' ),
            array( 'key' => 'six_portal_band_features', 'label' => 'Bullet features', 'name' => 'portal_band_features', 'type' => 'repeater', 'layout' => 'table', 'button_label' => 'Add feature',
                'sub_fields' => array( $text( 'six_pbf_feature', 'Feature' ) ) ),
            $text( 'six_portal_band_cta', 'Button label', 'Get started free' ),
        ),
    ) );

    // ── Homepage ─────────────────────────────────────────────────────────
    // Intentionally NOT registered here. The Home page is edited through a
    // plain-postmeta meta box instead (marketing/home-fields.php,
    // "Homepage Content"). An ACF field group used to be registered for the
    // same page with the same box title and the same field names
    // (deepdives, svc_cards, client_success, testimonials, client_logos,
    // …) — a second, unsynced editing surface fighting the first one for
    // the exact same data. Whenever ACF was active, its (often stale or
    // blank) values silently overrode correctly-saved edits from the real
    // editor, which is why changes — e.g. images in "We Can Help Your
    // Business With" — appeared to save but never showed up on the front
    // end. Do not re-add a `group_six_home` field group; extend
    // home-fields.php instead so there is exactly one editing surface for
    // the homepage.
} );
