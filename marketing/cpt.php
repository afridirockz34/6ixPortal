<?php
/**
 * 6ix Developers — Custom Post Types for editable, repeatable content.
 *
 * No plugins required. "Client Success" and "Testimonials" become normal
 * post lists in wp-admin: add, edit, delete, reorder (by date/menu order),
 * each with its own image (featured image) and a few native meta fields.
 * Templates fall back to sensible defaults when no posts exist yet.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// ── Register the post types ─────────────────────────────────────────────
add_action( 'init', function () {

    register_post_type( 'six_success', array(
        'labels' => array(
            'name'          => 'Client Success',
            'singular_name' => 'Success Story',
            'add_new_item'  => 'Add Success Story',
            'edit_item'     => 'Edit Success Story',
            'menu_name'     => 'Client Success',
        ),
        'public'        => false,
        'show_ui'       => true,
        'show_in_menu'  => true,
        'menu_icon'     => 'dashicons-chart-line',
        'menu_position' => 26,
        'supports'      => array( 'title', 'thumbnail', 'page-attributes' ),
        'has_archive'   => false,
        'rewrite'       => false,
    ) );

    register_post_type( 'six_testimonial', array(
        'labels' => array(
            'name'          => 'Testimonials',
            'singular_name' => 'Testimonial',
            'add_new_item'  => 'Add Testimonial',
            'edit_item'     => 'Edit Testimonial',
            'menu_name'     => 'Testimonials',
        ),
        'public'        => false,
        'show_ui'       => true,
        'show_in_menu'  => true,
        'menu_icon'     => 'dashicons-format-quote',
        'menu_position' => 27,
        'supports'      => array( 'title', 'editor', 'thumbnail', 'page-attributes' ),
        'has_archive'   => false,
        'rewrite'       => false,
    ) );
} );

// ── Success meta box (period, conversion, CTR, cost/lead) ───────────────
add_action( 'add_meta_boxes', function () {
    add_meta_box( 'six_success_meta', 'Success Metrics', function ( $post ) {
        wp_nonce_field( 'six_success_meta', 'six_success_nonce' );
        $f = fn( $k ) => esc_attr( get_post_meta( $post->ID, $k, true ) );
        $row = function ( $k, $label, $ph ) use ( $f ) {
            echo '<p><label style="display:block;font-weight:600;margin-bottom:4px">' . esc_html( $label ) . '</label>';
            echo '<input type="text" name="' . esc_attr( $k ) . '" value="' . $f( $k ) . '" placeholder="' . esc_attr( $ph ) . '" style="width:100%"></p>';
        };
        echo '<p style="color:#666">The title is the client / industry name. Set the client photo as the Featured Image.</p>';
        $row( 'six_cs_period', 'Period',              '2024, Q3 - Q4' );
        $row( 'six_cs_conv',   'Conversion Rate',     '16.50%' );
        $row( 'six_cs_ctr',    'Click Through Rate',  '6.80%' );
        $row( 'six_cs_cpl',    'Cost Per Lead',       '$125.70' );
    }, 'six_success', 'normal', 'high' );

    add_meta_box( 'six_testimonial_meta', 'Testimonial Details', function ( $post ) {
        wp_nonce_field( 'six_testimonial_meta', 'six_testimonial_nonce' );
        $role = esc_attr( get_post_meta( $post->ID, 'six_tst_role', true ) );
        echo '<p style="color:#666">The title is the person\'s name. Write the quote in the main editor. Optionally set a photo as Featured Image.</p>';
        echo '<p><label style="display:block;font-weight:600;margin-bottom:4px">Role / Company (optional)</label>';
        echo '<input type="text" name="six_tst_role" value="' . $role . '" style="width:100%"></p>';
    }, 'six_testimonial', 'normal', 'high' );
} );

// ── Save meta ───────────────────────────────────────────────────────────
add_action( 'save_post', function ( $post_id ) {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    if ( isset( $_POST['six_success_nonce'] ) && wp_verify_nonce( $_POST['six_success_nonce'], 'six_success_meta' ) ) {
        foreach ( array( 'six_cs_period', 'six_cs_conv', 'six_cs_ctr', 'six_cs_cpl' ) as $k ) {
            if ( isset( $_POST[ $k ] ) ) update_post_meta( $post_id, $k, sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) );
        }
    }
    if ( isset( $_POST['six_testimonial_nonce'] ) && wp_verify_nonce( $_POST['six_testimonial_nonce'], 'six_testimonial_meta' ) ) {
        if ( isset( $_POST['six_tst_role'] ) ) update_post_meta( $post_id, 'six_tst_role', sanitize_text_field( wp_unslash( $_POST['six_tst_role'] ) ) );
    }
} );

/**
 * Fetch Client Success items from the CPT; fall back to $default when none
 * have been created yet, so the homepage is never empty.
 */
function mk_success_items( $default = array() ) {
    $posts = get_posts( array( 'post_type' => 'six_success', 'numberposts' => 30, 'orderby' => 'menu_order date', 'order' => 'ASC', 'post_status' => 'publish' ) );
    if ( ! $posts ) return $default;
    $out = array();
    foreach ( $posts as $p ) {
        $out[] = array(
            'title'  => get_the_title( $p ),
            'period' => get_post_meta( $p->ID, 'six_cs_period', true ),
            'conv'   => get_post_meta( $p->ID, 'six_cs_conv', true ),
            'ctr'    => get_post_meta( $p->ID, 'six_cs_ctr', true ),
            'cpl'    => get_post_meta( $p->ID, 'six_cs_cpl', true ),
            'image'  => get_the_post_thumbnail_url( $p, 'medium' ) ?: '',
        );
    }
    return $out;
}

/** Fetch Testimonials from the CPT; fall back to $default. */
function mk_testimonial_items( $default = array() ) {
    $posts = get_posts( array( 'post_type' => 'six_testimonial', 'numberposts' => 30, 'orderby' => 'menu_order date', 'order' => 'ASC', 'post_status' => 'publish' ) );
    if ( ! $posts ) return $default;
    $out = array();
    foreach ( $posts as $p ) {
        $out[] = array(
            'quote' => wp_strip_all_tags( $p->post_content ),
            'name'  => get_the_title( $p ),
            'role'  => get_post_meta( $p->ID, 'six_tst_role', true ),
            'image' => get_the_post_thumbnail_url( $p, 'thumbnail' ) ?: '',
        );
    }
    return $out;
}
