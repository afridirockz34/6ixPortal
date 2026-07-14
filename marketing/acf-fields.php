<?php
/**
 * 6ix Developers — ACF field registration (in code)
 *
 * Registering field groups here (instead of the ACF UI) keeps the site
 * structure version-controlled and deployable through the normal pipeline.
 * Repeater / flexible fields require ACF Pro; text/textarea/image work on the
 * free plugin. Everything degrades gracefully via mk_field() defaults when ACF
 * is absent, so the site renders before any of this is configured.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// ── Global "Site Settings" options page (brand, nav, footer, portal band) ──
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

    // Small builders to keep this readable.
    $text     = fn( $k, $l, $d = '' ) => array( 'key' => $k, 'label' => $l, 'name' => substr( $k, 4 ), 'type' => 'text', 'default_value' => $d );
    $textarea = fn( $k, $l, $d = '' ) => array( 'key' => $k, 'label' => $l, 'name' => substr( $k, 4 ), 'type' => 'textarea', 'rows' => 3, 'default_value' => $d );
    $url      = fn( $k, $l, $d = '' ) => array( 'key' => $k, 'label' => $l, 'name' => substr( $k, 4 ), 'type' => 'text', 'default_value' => $d, 'instructions' => 'Full or relative URL, e.g. /contact-us' );
    $image    = fn( $k, $l ) => array( 'key' => $k, 'label' => $l, 'name' => substr( $k, 4 ), 'type' => 'image', 'return_format' => 'url', 'preview_size' => 'medium' );

    // ── Global settings ──────────────────────────────────────────────────
    acf_add_local_field_group( array(
        'key'      => 'group_six_site',
        'title'    => 'Site Settings (Header, Footer, Portal CTA)',
        'location' => array( array( array( 'param' => 'options_page', 'operator' => '==', 'value' => 'six-site-settings' ) ) ),
        'fields'   => array(
            $text( 'six_brand_name', 'Brand name', '6ix Developers' ),
            $image( 'six_brand_logo', 'Logo image (optional — overrides brand text)' ),
            array( 'key' => 'six_nav_items', 'label' => 'Header menu', 'name' => 'nav_items', 'type' => 'repeater', 'button_label' => 'Add menu item', 'layout' => 'table',
                'sub_fields' => array( $text( 'six_nav_label', 'Label' ), $url( 'six_nav_url', 'URL' ) ) ),
            $text( 'six_header_cta_label', 'Header button label', 'Free consultation' ),
            $url( 'six_header_cta_url', 'Header button URL', '/contact-us' ),

            $textarea( 'six_footer_about', 'Footer intro', 'A full-stack digital marketing agency helping local businesses grow with websites, Google Ads, SEO and social — now with a live marketing dashboard.' ),
            array( 'key' => 'six_footer_cols', 'label' => 'Footer link columns', 'name' => 'footer_cols', 'type' => 'repeater', 'button_label' => 'Add column',
                'sub_fields' => array(
                    $text( 'six_fcol_title', 'Column title' ),
                    array( 'key' => 'six_fcol_links', 'label' => 'Links', 'name' => 'links', 'type' => 'repeater', 'layout' => 'table', 'button_label' => 'Add link',
                        'sub_fields' => array( $text( 'six_flink_label', 'Label' ), $url( 'six_flink_url', 'URL' ) ) ),
                ) ),
            $text( 'six_footer_email', 'Contact email', 'hello@6ixdevelopers.com' ),
            $text( 'six_footer_phone', 'Contact phone' ),
            array( 'key' => 'six_footer_social', 'label' => 'Social links', 'name' => 'footer_social', 'type' => 'repeater', 'layout' => 'table', 'button_label' => 'Add social',
                'sub_fields' => array( $text( 'six_soc_label', 'Network (e.g. Instagram)' ), $url( 'six_soc_url', 'URL' ) ) ),

            // Portal CTA band defaults (used site-wide unless a page overrides)
            $text( 'six_portal_band_eyebrow', 'Portal band — eyebrow', 'Your Marketing OS' ),
            $text( 'six_portal_band_heading', 'Portal band — heading', 'Get to know the marketing side of your business.' ),
            $textarea( 'six_portal_band_text', 'Portal band — text', 'See exactly how you stack up against your competitors, where your leads come from, and what to fix next — in one live dashboard built for your business.' ),
            array( 'key' => 'six_portal_band_features', 'label' => 'Portal band — bullet features', 'name' => 'portal_band_features', 'type' => 'repeater', 'layout' => 'table', 'button_label' => 'Add feature',
                'sub_fields' => array( $text( 'six_pbf_feature', 'Feature' ) ) ),
            $text( 'six_portal_band_cta', 'Portal band — button label', 'Get started free' ),
        ),
    ) );

    // ── Homepage fields (attached to the Home template) ──────────────────
    acf_add_local_field_group( array(
        'key'      => 'group_six_home',
        'title'    => 'Homepage Content',
        'location' => array( array( array( 'param' => 'page_template', 'operator' => '==', 'value' => 'marketing/templates/template-home.php' ) ) ),
        'fields'   => array(
            // Hero
            array( 'key' => 'six_home_hero_tab', 'label' => 'Hero', 'type' => 'tab' ),
            $text( 'six_hero_eyebrow', 'Eyebrow', 'Toronto digital marketing, reimagined' ),
            $text( 'six_hero_heading', 'Heading', 'Discover the difference AI-driven marketing makes.' ),
            $textarea( 'six_hero_sub', 'Subheading', 'We build the websites, campaigns and content that grow local businesses — and give you a live dashboard to see it all working.' ),
            $text( 'six_hero_cta1_label', 'Primary button label', 'Get your free consultation' ),
            $url(  'six_hero_cta1_url', 'Primary button URL', '/contact-us' ),
            $text( 'six_hero_cta2_label', 'Secondary button label', 'Explore the Marketing OS' ),

            // Services
            array( 'key' => 'six_home_svc_tab', 'label' => 'Services', 'type' => 'tab' ),
            $text( 'six_svc_heading', 'Section heading', 'Everything you need to grow, under one roof.' ),
            $textarea( 'six_svc_sub', 'Section subheading', 'A full-stack team across web, paid, search and social — working from the same data.' ),
            array( 'key' => 'six_svc_cards', 'label' => 'Service cards', 'name' => 'svc_cards', 'type' => 'repeater', 'button_label' => 'Add service',
                'sub_fields' => array(
                    array( 'key' => 'six_svc_icon', 'label' => 'Icon', 'name' => 'icon', 'type' => 'select',
                        'choices' => array( 'website' => 'Website', 'ads' => 'Google Ads', 'seo' => 'SEO', 'social' => 'Social', 'spark' => 'AI', 'chart' => 'Analytics' ), 'default_value' => 'spark' ),
                    $text( 'six_svc_title', 'Title' ),
                    $textarea( 'six_svc_text', 'Text' ),
                    $url( 'six_svc_link', 'Learn-more URL' ),
                ) ),

            // Stats / proof
            array( 'key' => 'six_home_stat_tab', 'label' => 'Results', 'type' => 'tab' ),
            $text( 'six_stats_heading', 'Section heading', 'Real results for real local businesses.' ),
            array( 'key' => 'six_stats', 'label' => 'Stat tiles', 'name' => 'stats', 'type' => 'repeater', 'layout' => 'table', 'button_label' => 'Add stat',
                'sub_fields' => array( $text( 'six_stat_num', 'Number (e.g. 3.2x)' ), $text( 'six_stat_lbl', 'Label' ) ) ),

            // Deep dives
            array( 'key' => 'six_home_dd_tab', 'label' => 'Deep-dives', 'type' => 'tab' ),
            array( 'key' => 'six_deepdives', 'label' => 'Service deep-dives', 'name' => 'deepdives', 'type' => 'repeater', 'button_label' => 'Add deep-dive',
                'sub_fields' => array(
                    $text( 'six_dd_eyebrow', 'Eyebrow' ),
                    $text( 'six_dd_title', 'Title' ),
                    $textarea( 'six_dd_text', 'Text' ),
                    $text( 'six_dd_cta_label', 'Button label' ),
                    $url( 'six_dd_cta_url', 'Button URL' ),
                    $image( 'six_dd_image', 'Image (optional)' ),
                ) ),

            // Testimonials
            array( 'key' => 'six_home_tst_tab', 'label' => 'Testimonials', 'type' => 'tab' ),
            $text( 'six_tst_heading', 'Section heading', 'Loved by the businesses we grow.' ),
            array( 'key' => 'six_testimonials', 'label' => 'Testimonials', 'name' => 'testimonials', 'type' => 'repeater', 'button_label' => 'Add testimonial',
                'sub_fields' => array(
                    $textarea( 'six_tst_quote', 'Quote' ),
                    $text( 'six_tst_name', 'Name' ),
                    $text( 'six_tst_role', 'Role / company' ),
                ) ),

            // Final CTA
            array( 'key' => 'six_home_cta_tab', 'label' => 'Final CTA', 'type' => 'tab' ),
            $text( 'six_final_heading', 'Heading', 'Ready to grow with a team that shows its work?' ),
            $textarea( 'six_final_text', 'Text', 'Book a free consultation and see your tailored plan — no commitment.' ),
            $text( 'six_final_cta_label', 'Button label', 'Get free consultation' ),
            $url(  'six_final_cta_url', 'Button URL', '/contact-us' ),
        ),
    ) );
} );
