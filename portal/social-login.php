<?php
/**
 * 6ix Portal — Social Login (Nextend Social Login integration)
 *
 * Makes Google sign-in follow the same rules as the email flow:
 *   - New Google account → six_customer role, onboarding meta, advisor
 *     assigned, Odoo contact/lead created → sent into onboarding
 *   - Existing account → routed by role: advisor/sales/admin to their
 *     portals, completed customers to /portal/, incomplete customers back
 *     into onboarding to resume
 *
 * Uses NSL's documented hooks (nsl_register_roles, nsl_register_new_user,
 * nsl_login, {provider}_login_redirect_url) so it works in BOTH popup and
 * redirect mode — the JS NSLAfterFormLogin handler in onboarding.php only
 * fires in popup mode. All hooks are no-ops when the plugin is inactive.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Ensure a social-login account is portal-ready: customer role, onboarding
 * meta, advisor assignment, Odoo contact/lead. Idempotent; never touches
 * staff or admin accounts. Also repairs accounts that were created as
 * 'subscriber' before this integration existed.
 */
function six_social_prepare_user( $user_id ) {
    static $prepared = array();
    if ( isset( $prepared[ $user_id ] ) ) return;
    $prepared[ $user_id ] = true;

    $user = get_userdata( $user_id );
    if ( ! $user ) return;
    if ( user_can( $user_id, 'manage_options' ) ) return;

    $roles = (array) $user->roles;
    if ( in_array( 'six_advisor', $roles, true ) || in_array( 'six_sales', $roles, true ) ) return;

    if ( ! in_array( 'six_customer', $roles, true ) ) {
        ( new WP_User( $user_id ) )->set_role( 'six_customer' );
    }
    if ( get_user_meta( $user_id, 'six_checkout_step', true ) === '' ) {
        update_user_meta( $user_id, 'six_checkout_step', 1 );
    }
    if ( get_user_meta( $user_id, 'six_checkout_completed', true ) === '' ) {
        update_user_meta( $user_id, 'six_checkout_completed', 0 );
    }
    update_user_meta( $user_id, 'six_last_activity', current_time( 'mysql' ) );

    // Advisor assignment — same round-robin as the email signup flow
    global $wpdb;
    $has_advisor = $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}six_assignments WHERE client_id=%d", $user_id
    ) );
    if ( ! $has_advisor && function_exists( 'six_assign_advisor_round_robin' ) ) {
        six_assign_advisor_round_robin( $user_id );
    }

    // Odoo contact + lead, same as email signups
    if ( class_exists( 'Six_Odoo' ) ) {
        Six_Odoo::create_or_update_contact( $user_id );
        if ( ! get_user_meta( $user_id, 'six_odoo_lead_id', true ) ) {
            Six_Odoo::sync_lead( array(
                'user_id' => $user_id,
                'status'  => 'started',
                'score'   => 20, // social login = higher intent signal
                'step'    => 1,
            ) );
        }
    }
}

// New social registrations get the portal customer role (not 'subscriber')
add_filter( 'nsl_register_roles', function( $roles, $provider = null ) {
    return array( 'six_customer' );
}, 10, 2 );

// Fires once when NSL creates a brand-new account
add_action( 'nsl_register_new_user', function( $user_id, $provider = null ) {
    six_social_prepare_user( $user_id );
}, 10, 2 );

// Fires on every social login — repairs pre-existing broken accounts too
add_action( 'nsl_login', function( $user_id, $provider = null ) {
    six_social_prepare_user( $user_id );
}, 10, 2 );

/**
 * Post-auth destination — same rules as the email login flow, regardless of
 * what the NSL settings say. Runs after NSL has logged the user in.
 */
function six_social_post_login_url( $redirect_url, $provider = null ) {
    $uid = get_current_user_id();
    if ( ! $uid ) return home_url( '/get-started/' );

    six_social_prepare_user( $uid );

    $role = class_exists( 'Six_Roles' ) ? Six_Roles::get_portal_role( $uid ) : '';
    if ( $role === 'six_advisor' )   return home_url( '/advisor-portal/' );
    if ( $role === 'six_sales' )     return home_url( '/sales-portal/' );
    if ( $role === 'administrator' ) return admin_url();

    $done = get_user_meta( $uid, 'six_checkout_completed', true );
    return $done ? home_url( '/portal/' ) : home_url( '/get-started/' );
}
add_filter( 'google_login_redirect_url',    'six_social_post_login_url', 20, 2 );
add_filter( 'google_register_redirect_url', 'six_social_post_login_url', 20, 2 );
