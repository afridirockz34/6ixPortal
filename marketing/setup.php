<?php
/**
 * 6ix Developers — one-time marketing-site setup (code-driven).
 *
 * Runs once on the /6ix-redesign install after this code deploys, so no
 * dashboard clicks or REST access are needed:
 *   1. Creates a "Home" page using the 6ix — Home template and makes it the
 *      static front page.
 *   2. Seeds the Client Success + Testimonials post types with the current
 *      content so they can be edited/deleted in wp-admin (only if empty).
 *
 * Guarded by an option so it never repeats. Bump the version constant to
 * re-run a specific step in future.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_loaded', function () {
    if ( get_option( 'six_mk_setup_v1' ) ) return;
    update_option( 'six_mk_setup_v1', 1 ); // set first so a fatal can't loop

    // ── 1. Home page + front page ────────────────────────────────────────
    $tpl  = 'marketing/templates/template-home.php';
    $home = get_page_by_path( 'home' );
    if ( ! $home ) {
        // Reuse an existing front page if one is already set, else create.
        $front_id = (int) get_option( 'page_on_front' );
        $home_id  = $front_id ?: wp_insert_post( array(
            'post_title'   => 'Home',
            'post_name'    => 'home',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => '',
        ) );
    } else {
        $home_id = $home->ID;
    }
    if ( $home_id && ! is_wp_error( $home_id ) ) {
        update_post_meta( $home_id, '_wp_page_template', $tpl );
        update_option( 'show_on_front', 'page' );
        update_option( 'page_on_front', $home_id );
    }

    // ── 2. Seed Client Success (only if none exist) ──────────────────────
    if ( ! get_posts( array( 'post_type' => 'six_success', 'numberposts' => 1, 'fields' => 'ids', 'post_status' => 'any' ) ) ) {
        $success = array(
            array( 'Criminal Law Firm',               '2024, Q3 - Q4', '16.50%', '6.80%',  '$125.70' ),
            array( 'Family Law Firm',                 '2024, Q3 - Q4', '19.10%', '7.40%',  '$104.84' ),
            array( 'Employment Law Firm',             '2024, Q3 - Q4', '22.10%', '6.30%',  '$61.21' ),
            array( 'Mortgage Agency',                 '2024, Q3 - Q4', '18.80%', '24.10%', '$19.64' ),
            array( 'Custom Apparel Printing Company', '2024, Q3 - Q4', '8.70%',  '8.30%',  '$35.76' ),
            array( 'Auto Mechanic Shop',              '2024, Q3 - Q4', '16.20%', '10.30%', '$25.84' ),
            array( 'Restaurant',                      '2024, Q3 - Q4', '9.04%',  '22.04%', '$9.95' ),
        );
        foreach ( $success as $i => $s ) {
            $id = wp_insert_post( array( 'post_title' => $s[0], 'post_type' => 'six_success', 'post_status' => 'publish', 'menu_order' => $i ) );
            if ( $id && ! is_wp_error( $id ) ) {
                update_post_meta( $id, 'six_cs_period', $s[1] );
                update_post_meta( $id, 'six_cs_conv',   $s[2] );
                update_post_meta( $id, 'six_cs_ctr',    $s[3] );
                update_post_meta( $id, 'six_cs_cpl',    $s[4] );
            }
        }
    }

    // ── 3. Seed Testimonials (only if none exist) ────────────────────────
    if ( ! get_posts( array( 'post_type' => 'six_testimonial', 'numberposts' => 1, 'fields' => 'ids', 'post_status' => 'any' ) ) ) {
        $tst = array(
            array( 'Annie C.',      'I am very thankful to 6ix Developers for their services. I am super happy with my website and Google Ads. Coming from a bad experience, they made me feel comfortable and kept me in the loop with the whole progress of the website. Also I would like to thank Musab for suggesting and building a business plan for me and setting my business up with Google Ads. Much appreciated.' ),
            array( 'Elidrissia H.', 'I will definitely recommend this company to everybody who wants a professional and perfect website for their business. I am so impressed with their work, and my website came out perfect.' ),
            array( 'Barnard S.',    '6ix Developers did a great job of meeting our needs and helping us design the site we wanted. They were able to implement all of our requests, and contributed great ideas. Thanks a lot. Most recommended web developers.' ),
            array( 'Momi K.',       "6ix Developers has handled our SEO for over five years now, and have been a key partner in our growth. We were a startup when we first started working together, and they respected our smaller budget and worked to get us the best return on investment. Now that we're established, we know that we are in good hands as we market our company in a very competitive online environment. 5 stars for 6ix Developers." ),
        );
        foreach ( $tst as $i => $t ) {
            wp_insert_post( array( 'post_title' => $t[0], 'post_content' => $t[1], 'post_type' => 'six_testimonial', 'post_status' => 'publish', 'menu_order' => $i ) );
        }
    }
}, 20 );
