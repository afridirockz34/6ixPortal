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
    acf_add_local_field_group( array(
        'key'      => 'group_six_home',
        'title'    => 'Homepage Content',
        'location' => array( array( array( 'param' => 'page_template', 'operator' => '==', 'value' => 'marketing/templates/template-home.php' ) ) ),
        'fields'   => array(
            $tab( 'six_home_hero_tab', 'Hero' ),
            $text( 'six_hero_heading', 'Heading', 'Discover The Difference' ),
            $text( 'six_hero_subheading', 'Subheading', '6ix Developers can make' ),
            $text( 'six_hero_lead', 'Lead line', 'Elevate your marketing through industry-leading:' ),
            $textarea( 'six_hero_typing_words', 'Rotating words (comma-separated)', 'PPC Management, Search Engine Marketing, Paid Social Media Advertising, Website Page Speed Optimization, Email Marketing', 2 ),
            $text( 'six_hero_cta1_label', 'Primary button label', 'Get your free consultation' ),
            $url(  'six_hero_cta1_url', 'Primary button URL', '/contact-us' ),
            $text( 'six_hero_cta2_label', 'Secondary (portal) button label', 'Get to know your marketing' ),

            $tab( 'six_home_svc_tab', 'Services' ),
            $text( 'six_svc_heading', 'Section heading', 'How 6ix Developers Can Help Your Business' ),
            $textarea( 'six_svc_intro', 'Intro paragraph', '', 5 ),
            array( 'key' => 'six_svc_cards', 'label' => 'Service cards', 'name' => 'svc_cards', 'type' => 'repeater', 'button_label' => 'Add service',
                'sub_fields' => array(
                    $image( 'six_svcc_image', 'Icon image' ),
                    $text( 'six_svcc_title', 'Title' ),
                    $textarea( 'six_svcc_text', 'Text' ),
                    $url( 'six_svcc_link', 'Learn-more URL' ),
                ) ),

            $tab( 'six_home_cs_tab', 'Client Success' ),
            $text( 'six_cs_heading', 'Section heading', 'Client Success' ),
            $text( 'six_cs_eyebrow', 'Eyebrow', 'We are Diverse & Experienced' ),
            array( 'key' => 'six_client_success', 'label' => 'Success slides (add / remove freely)', 'name' => 'client_success', 'type' => 'repeater', 'button_label' => 'Add slide',
                'sub_fields' => array(
                    $text( 'six_cs_title', 'Client / industry' ),
                    $text( 'six_cs_period', 'Period (e.g. 2024, Q3 - Q4)' ),
                    $text( 'six_cs_conv', 'Conversion rate (e.g. 16.50%)' ),
                    $text( 'six_cs_ctr', 'Click-through rate' ),
                    $text( 'six_cs_cpl', 'Cost per lead (e.g. $125.70)' ),
                ) ),

            $tab( 'six_home_commit_tab', 'Commitment' ),
            $text( 'six_commit_heading', 'Heading', 'Our Commitment To Helping Other Businesses' ),
            $textarea( 'six_commit_p1', 'Paragraph 1' ),
            $textarea( 'six_commit_p2', 'Paragraph 2' ),
            $text( 'six_commit_q', 'Question line', 'Could your business benefit from our services?' ),
            $text( 'six_commit_cta1', 'Button 1 label', 'Get free consultation' ),
            $text( 'six_commit_cta2', 'Button 2 label', 'Find out more about us' ),

            $tab( 'six_home_dd_tab', 'We Can Help With' ),
            $text( 'six_dd_heading', 'Section heading', 'We Can Help Your Business With' ),
            array( 'key' => 'six_deepdives', 'label' => 'Service deep-dives', 'name' => 'deepdives', 'type' => 'repeater', 'button_label' => 'Add deep-dive',
                'sub_fields' => array(
                    $text( 'six_dd_eyebrow', 'Eyebrow' ),
                    $text( 'six_dd_title', 'Title' ),
                    $textarea( 'six_dd_text', 'Text', '', 5 ),
                    $text( 'six_dd_cta_label', 'Button label', 'Learn More' ),
                    $url( 'six_dd_cta_url', 'Button URL' ),
                    $image( 'six_dd_image', 'Image' ),
                ) ),

            $tab( 'six_home_tst_tab', 'Trust & Testimonials' ),
            $text( 'six_tst_heading', 'Section heading', 'Businesses Who Trust Us' ),
            array( 'key' => 'six_client_logos', 'label' => 'Client logos', 'name' => 'client_logos', 'type' => 'repeater', 'layout' => 'table', 'button_label' => 'Add logo',
                'sub_fields' => array( $image( 'six_cl_image', 'Logo' ) ) ),
            array( 'key' => 'six_testimonials', 'label' => 'Testimonials (add / remove freely)', 'name' => 'testimonials', 'type' => 'repeater', 'button_label' => 'Add testimonial',
                'sub_fields' => array(
                    $textarea( 'six_tst_quote', 'Quote', '', 4 ),
                    $text( 'six_tst_name', 'Name' ),
                    $text( 'six_tst_role', 'Role / company (optional)' ),
                ) ),

            $tab( 'six_home_blog_tab', 'Blog' ),
            $text( 'six_blog_heading', 'Section heading', 'From the Blog' ),
            array( 'key' => 'six_blog_count', 'label' => 'Posts to show', 'name' => 'blog_count', 'type' => 'number', 'default_value' => 3, 'min' => 0, 'max' => 6,
                   'instructions' => 'Latest published posts. Section hides automatically when there are no posts (or set 0).' ),

            $tab( 'six_home_cta_tab', 'Final CTA' ),
            $text( 'six_final_heading', 'Heading', 'Ready to find out what sets 6ix Developers apart?' ),
            $text( 'six_final_cta_label', 'Button label', 'Get free consultation now' ),
            $url(  'six_final_cta_url', 'Button URL', '/contact-us' ),
        ),
    ) );
} );
