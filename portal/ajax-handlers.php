<?php
/**
 * 6ix Portal — ajax-handlers.php (v3)
 * ONLY wp_ajax_ hooks. Zero class definitions — those live in class-missing.php.
 * Upload to: /wp-content/themes/6ixClaude/portal/ajax-handlers.php
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// ─────────────────────────────────────────────────────────────────────────────
// MESSAGING
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'wp_ajax_six_send_message', function() {
    check_ajax_referer( 'six_nonce', 'nonce' );
    $sender   = get_current_user_id();
    $receiver = intval( $_POST['receiver_id'] ?? 0 );
    $message  = sanitize_textarea_field( $_POST['message'] ?? '' );
    if ( ! $receiver || ! $message ) wp_send_json_error( 'Invalid data' );
    $id   = Six_Messaging::send( $sender, $receiver, $message );
    $user = get_userdata( $sender );
    wp_send_json_success( array( 'id' => $id, 'sender_name' => $user->display_name, 'message' => $message, 'time' => date( 'g:i A' ) ) );
} );

add_action( 'wp_ajax_six_get_messages', function() {
    check_ajax_referer( 'six_nonce', 'nonce' );
    $user_id  = get_current_user_id();
    $other_id = intval( $_POST['other_user'] ?? 0 );
    if ( ! $other_id ) wp_send_json_error();
    wp_send_json_success( Six_Messaging::get_conversation( $user_id, $other_id ) );
} );

// ─────────────────────────────────────────────────────────────────────────────
// NOTIFICATIONS
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'wp_ajax_six_mark_notif_read', function() {
    check_ajax_referer( 'six_nonce', 'nonce' );
    Six_Notifications::mark_read( intval( $_POST['notif_id'] ?? 0 ), get_current_user_id() );
    wp_send_json_success();
} );

add_action( 'wp_ajax_six_mark_all_notifications_read', function() {
    check_ajax_referer( 'six_nonce', 'nonce' );
    Six_Notifications::mark_all_read( get_current_user_id() );
    wp_send_json_success();
} );

// Live toast polling — returns the current user's notifications newer than
// since_id (newest first, capped) plus the unread count. The dashboard polls
// this every ~30s while its tab is visible and pops new ones as toasts.
add_action( 'wp_ajax_six_poll_notifications', function() {
    check_ajax_referer( 'six_nonce', 'nonce' );
    $uid = get_current_user_id();
    if ( ! $uid ) wp_send_json_error( 'Not logged in' );
    global $wpdb;
    $t     = $wpdb->prefix . 'six_notifications';
    $since = intval( $_POST['since_id'] ?? 0 );
    $rows  = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, type, title, message, is_read, created_at FROM {$t} WHERE user_id=%d AND id > %d ORDER BY id DESC LIMIT 8",
        $uid, $since ), ARRAY_A );
    $unread = intval( $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$t} WHERE user_id=%d AND is_read=0", $uid ) ) );
    wp_send_json_success( array( 'items' => $rows ?: array(), 'unread' => $unread ) );
} );

// ─────────────────────────────────────────────────────────────────────────────
// PROFILE SAVE
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'wp_ajax_six_save_profile', function() {
    check_ajax_referer( 'six_nonce', 'nonce' );
    $user_id = get_current_user_id();
    global $wpdb;

    // ── Update WP user name ───────────────────────────────────────────────
    $user_data = array( 'ID' => $user_id );
    if ( ! empty( $_POST['first_name'] ) ) $user_data['first_name'] = sanitize_text_field( $_POST['first_name'] );
    if ( ! empty( $_POST['last_name'] ) )  $user_data['last_name']  = sanitize_text_field( $_POST['last_name'] );
    if ( count( $user_data ) > 1 ) {
        $user_data['display_name'] = trim( ( $user_data['first_name'] ?? '' ) . ' ' . ( $user_data['last_name'] ?? '' ) );
        wp_update_user( $user_data );
    }
    if ( isset( $_POST['phone'] ) ) {
        update_user_meta( $user_id, 'billing_phone', sanitize_text_field( $_POST['phone'] ) );
    }

    // ── Update six_checkout_progress with all business + marketing fields ─
    // This is the single source of truth used by AI insights, competitor
    // intelligence, and the scoring engine.
    $table  = $wpdb->prefix . 'six_checkout_progress';
    $fields = array(
        'business_name'   => sanitize_text_field( $_POST['business_name']   ?? '' ),
        'website'         => esc_url_raw(          $_POST['website']         ?? '' ),
        'industry'        => sanitize_text_field( $_POST['industry']        ?? '' ),
        'location'        => sanitize_text_field( $_POST['location']        ?? '' ),
        'employees'       => sanitize_text_field( $_POST['employees']       ?? '' ),
        'monthly_revenue' => sanitize_text_field( $_POST['monthly_revenue'] ?? '' ),
        'goal'            => sanitize_text_field( $_POST['goal']            ?? '' ),
        'challenge'       => sanitize_text_field( $_POST['challenge']       ?? '' ),
        'mktg_budget'     => sanitize_text_field( $_POST['mktg_budget']     ?? '' ),
        'competitors'     => sanitize_text_field( $_POST['competitors']     ?? '' ),
        'updated_at'      => current_time('mysql'),
    );

    $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE user_id=%d", $user_id ) );

    if ( $existing ) {
        $result = $wpdb->update( $table, $fields, array( 'user_id' => $user_id ) );
    } else {
        // Create a new row if checkout was never started (e.g. admin-created user)
        $fields['user_id']    = $user_id;
        $fields['created_at'] = current_time('mysql');
        $result = $wpdb->insert( $table, $fields );
    }

    // If DB error (often missing column), run migration and retry
    if ( $result === false && $wpdb->last_error ) {
        // Force migration to add missing columns
        if ( function_exists('six_onboarding_db_upgrade') ) {
            delete_option('six_onboarding_db_v4'); // reset so it re-runs
            six_onboarding_db_upgrade();
        }
        // Retry — remove any fields that still don't exist
        $live_cols = $wpdb->get_col( "SHOW COLUMNS FROM {$table}", 0 );
        $safe_fields = array_intersect_key( $fields, array_flip( $live_cols ) );
        if ( $existing ) {
            $wpdb->update( $table, $safe_fields, array( 'user_id' => $user_id ) );
        } else {
            $wpdb->insert( $table, $safe_fields );
        }
    }

    // Sync updated contact to Odoo if connected
    if ( class_exists('Six_Odoo') ) {
        Six_Odoo::create_or_update_contact( $user_id );
    }

    wp_send_json_success( array( 'message' => 'Profile saved.' ) );
} );

// NOTE: six_save_checkout_step is handled by six_ajax_save_checkout_step()
// in ajax-onboarding.php. A second registration here used to run on the same
// hook and write conflicting step values — removed.

// ─────────────────────────────────────────────────────────────────────────────
// DATA SOURCES — customer connects analytics/ad accounts so live numbers can
// replace the post-onboarding projection. Stores the (non-secret) account
// identifier and notifies the advisor to complete the access grant.
// ─────────────────────────────────────────────────────────────────────────────
function six_data_source_meta_map() {
    return array(
        'ga4'   => array( 'meta' => 'six_ga4_property_id',     'label' => 'Google Analytics 4' ),
        'gads'  => array( 'meta' => 'six_gads_customer_id',    'label' => 'Google Ads' ),
        'meta'  => array( 'meta' => 'six_meta_ad_account_id',  'label' => 'Meta Ads' ),
        'gbp'   => array( 'meta' => 'six_gbp_location_id',     'label' => 'Google Business Profile' ),
        'gsc'   => array( 'meta' => 'six_gsc_site',            'label' => 'Google Search Console' ),
    );
}

add_action( 'wp_ajax_six_save_data_source', function() {
    check_ajax_referer( 'six_nonce', 'nonce' );
    $user_id = get_current_user_id();
    if ( ! $user_id ) wp_send_json_error( 'Not logged in.' );

    $map    = six_data_source_meta_map();
    $source = sanitize_key( $_POST['source'] ?? '' );
    if ( ! isset( $map[ $source ] ) ) wp_send_json_error( 'Unknown data source.' );

    $value = sanitize_text_field( $_POST['value'] ?? '' );
    if ( $value === '' ) wp_send_json_error( 'Please enter your account ID.' );

    update_user_meta( $user_id, $map[ $source ]['meta'], $value );
    update_user_meta( $user_id, 'six_ds_' . $source . '_at', current_time('mysql') );

    // Notify the assigned advisor to complete the access grant.
    $advisor_id = six_get_client_advisor_id( $user_id );
    if ( $advisor_id && class_exists('Six_Notifications') ) {
        $u = get_userdata( $user_id );
        Six_Notifications::create( array(
            'user_id'    => $advisor_id,
            'type'       => 'data_source_connected',
            'title'      => 'Client connected a data source',
            'message'    => ( $u->display_name ?: $u->user_email ) . ' provided their ' . $map[$source]['label'] . ' ID (' . $value . '). Complete the access grant to pull live data.',
            'action_url' => admin_url('admin.php?page=six-clients'),
        ) );
    }

    // Compute new completeness for the UI.
    $connected = 0;
    foreach ( $map as $s ) { if ( get_user_meta( $user_id, $s['meta'], true ) ) $connected++; }

    wp_send_json_success( array(
        'message'    => 'Connected. Your advisor will finish linking it.',
        'connected'  => $connected,
        'total'      => count( $map ),
        'source'     => $source,
    ) );
} );

// ─────────────────────────────────────────────────────────────────────────────
// SERVICES
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'wp_ajax_six_request_service', function() {
    check_ajax_referer( 'six_nonce', 'nonce' );
    global $wpdb;
    $client_id = get_current_user_id();
    $service   = sanitize_text_field( $_POST['service'] ?? '' );
    if ( ! $service ) wp_send_json_error( 'Invalid service' );
    $names = array(
        'google-ads' => 'Google Ads', 'seo' => 'SEO',
        'social-media' => 'Social Media Marketing', 'brand-dev' => 'Brand Development',
    );
    $existing = $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}six_client_services WHERE client_id=%d AND service_slug=%s", $client_id, $service
    ) );
    if ( $existing ) wp_send_json_error( 'Already requested' );
    $wpdb->insert( $wpdb->prefix . 'six_client_services', array(
        'client_id' => $client_id, 'service_slug' => $service,
        'service_name' => $names[ $service ] ?? ucwords( str_replace( '-', ' ', $service ) ),
        'status' => 'pending', 'budget' => 0,
    ) );
    $client      = get_userdata( $client_id );
    $service_nm  = $names[ $service ] ?? ucwords( str_replace( '-', ' ', $service ) );

    // Prefer that service's dedicated advisor (if one is assigned), else the
    // client's General advisor.
    $role_map   = array( 'google-ads' => 'google-ads', 'seo' => 'seo', 'social-media' => 'smm' );
    $advisor_id = six_get_client_advisor_id( $client_id, $role_map[ $service ] ?? '' );
    if ( $advisor_id ) {
        Six_Notifications::create( array(
            'user_id' => $advisor_id, 'type' => 'service_request',
            'title' => 'New Service Request',
            'message' => $client->display_name . ' requested ' . $service_nm,
        ) );
    }

    if ( function_exists( 'six_send_system_email' ) && $client ) {
        six_send_system_email( 'service_added', array(
            'client_name'  => $client->display_name,
            'client_email' => $client->user_email,
            'service_name' => $service_nm,
            'submitted_at' => current_time( 'F j, Y g:i a' ),
        ), array(
            'customer_email' => $client->user_email,
            'dashboard_url'  => home_url( '/advisor-portal/?tab=clients&client=' . $client_id ),
        ) );
    }

    wp_send_json_success( array( 'status' => 'pending' ) );
} );

// ── Advisor: add service to client ──────────────────────────────────────────
add_action('wp_ajax_six_adv_add_client_service','six_adv_add_client_service');
function six_adv_add_client_service(){
    check_ajax_referer('six_nonce','nonce');
    $is_allowed = (class_exists('Six_Roles') && Six_Roles::is_advisor())
                  || current_user_can('manage_options')
                  || current_user_can('six_advisor');
    if(!$is_allowed) wp_send_json_error('Permission denied');
    global $wpdb;
    $client_id = intval($_POST['client_id']  ?? 0);
    $slug      = sanitize_text_field($_POST['service_slug'] ?? '');
    $budget    = floatval($_POST['budget'] ?? 0);
    if(!$client_id || !$slug) wp_send_json_error('Missing required fields');
    $names = array(
        'google-ads'     => 'Google Ads',
        'seo'            => 'SEO',
        'google-business'=> 'Google Business Profile',
        'website'        => 'Website Development',
    );
    // Check if already exists (any status)
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}six_client_services WHERE client_id=%d AND service_slug=%s",
        $client_id, $slug
    ));
    if($existing){
        // Update budget and set active rather than duplicating
        $wpdb->update(
            "{$wpdb->prefix}six_client_services",
            array('status'=>'active','budget'=>$budget,'advisor_id'=>get_current_user_id()),
            array('id'=>$existing)
        );
        wp_send_json_success(array('message'=>'Service activated','updated'=>true));
    }
    $wpdb->insert("{$wpdb->prefix}six_client_services", array(
        'client_id'    => $client_id,
        'service_slug' => $slug,
        'service_name' => $names[$slug] ?? ucwords(str_replace('-',' ',$slug)),
        'status'       => 'active',
        'budget'       => $budget,
        'advisor_id'   => get_current_user_id(),
    ));
    wp_send_json_success(array('message'=>'Service added','id'=>$wpdb->insert_id));
}


// ── Approve service request ──────────────────────────────────────────────
add_action('wp_ajax_six_approve_service','six_approve_service');
function six_approve_service(){
    // Verify nonce — send descriptive error if it fails
    $nonce_ok = isset($_POST['nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'six_nonce');
    if ( ! $nonce_ok ) {
        wp_send_json_error('Nonce verification failed — please refresh the page and try again');
        return;
    }
    // Allow advisors, admins, and users with six_advisor role
    $uid = get_current_user_id();
    $is_allowed = $uid && (
        ( class_exists('Six_Roles') && Six_Roles::is_advisor() )
        || current_user_can('manage_options')
        || current_user_can('six_advisor')
        || in_array('six_advisor', (array)(wp_get_current_user()->roles ?? []))
        || in_array('administrator', (array)(wp_get_current_user()->roles ?? []))
    );
    if ( ! $is_allowed ) {
        wp_send_json_error('Permission denied — user #'.$uid.' is not an advisor. Role: '.implode(',', wp_get_current_user()->roles ?? []));
        return;
    }
    global $wpdb;
    $svc_id   = intval($_POST['service_id'] ?? $_POST['id'] ?? 0);
    $client_id= intval($_POST['client_id']  ?? 0);
    if(!$svc_id) wp_send_json_error('No service ID');
    $svc = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}six_client_services WHERE id=%d", $svc_id));
    if(!$svc) wp_send_json_error('Service not found');
    if ( ! $client_id ) $client_id = intval( $svc->client_id ?? 0 ); // authoritative fallback — the row always knows its own client
    // Write ONLY columns that exist. The v7 migration adds advisor_id +
    // updated_at to this table for exactly this handler; approved_by/approved_at
    // were never created, so writing them made the whole update fail and the
    // status never changed ("Approve" appeared to do nothing). Guard each
    // optional column by what the live table actually has.
    $cols = $wpdb->get_col( "SHOW COLUMNS FROM {$wpdb->prefix}six_client_services", 0 );
    $data = array( 'status' => 'active' );
    if ( in_array( 'advisor_id', $cols, true ) ) $data['advisor_id'] = get_current_user_id();
    if ( in_array( 'updated_at', $cols, true ) ) $data['updated_at'] = current_time( 'mysql' );
    if ( in_array( 'approved_by', $cols, true ) ) $data['approved_by'] = get_current_user_id();
    if ( in_array( 'approved_at', $cols, true ) ) $data['approved_at'] = current_time( 'mysql' );
    $updated = $wpdb->update( "{$wpdb->prefix}six_client_services", $data, array( 'id' => $svc_id ) );
    if ( $updated === false ) {
        wp_send_json_error('Database error: '.$wpdb->last_error);
        return;
    }
    // Move Odoo lead to Customer stage
    if ($client_id && class_exists('Six_Odoo')) {
        $lead_id = intval(get_user_meta($client_id,'six_odoo_lead_id',true));
        if ($lead_id) Six_Odoo::update_lead_stage($lead_id,'Customer');
    }

    if ( $client_id && function_exists( 'six_send_system_email' ) ) {
        $client = get_userdata( $client_id );
        $activator = wp_get_current_user();
        if ( $client ) {
            six_send_system_email( 'service_activated', array(
                'client_name'  => $client->display_name,
                'client_email' => $client->user_email,
                'service_name' => $svc->service_name,
                'budget'       => number_format( floatval( $svc->budget ), 0 ),
                'activated_by' => $activator ? $activator->display_name : '',
                'activated_at' => current_time( 'F j, Y g:i a' ),
            ), array(
                'customer_email' => $client->user_email,
                'dashboard_url'  => home_url( '/advisor-portal/?tab=clients&client=' . $client_id ),
            ) );
        }
    }

    wp_send_json_success(array('message'=>'Service approved.','service_name'=>$svc->service_name));
}

// ─────────────────────────────────────────────────────────────────────────────
// BUDGET CHANGE REQUESTS
// ─────────────────────────────────────────────────────────────────────────────

// Customer submits budget change request
add_action( 'wp_ajax_six_request_budget_change', function() {
    check_ajax_referer( 'six_nonce', 'nonce' );
    global $wpdb;
    $client_id  = get_current_user_id();
    $service_id = intval( $_POST['service_id'] ?? 0 );
    $new_budget = floatval( $_POST['new_budget'] ?? 0 );
    if ( ! $service_id || $new_budget <= 0 ) wp_send_json_error( 'Invalid data' );

    $service = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}six_client_services WHERE id=%d AND client_id=%d", $service_id, $client_id
    ) );
    if ( ! $service ) wp_send_json_error( 'Service not found' );

    // Store pending request as user meta
    update_user_meta( $client_id, 'six_budget_req_' . $service_id, array(
        'requested_budget' => $new_budget,
        'requested_at'     => current_time( 'mysql' ),
        'status'           => 'pending',
    ) );

    // Notify advisor
    $advisor_id = six_get_client_advisor_id( $client_id );
    if ( $advisor_id ) {
        $client  = get_userdata( $client_id );
        $advisor = get_userdata( $advisor_id );
        Six_Notifications::create( array(
            'user_id'    => $advisor_id,
            'type'       => 'budget_change',
            'title'      => 'Budget Change Request',
            'message'    => $client->display_name . ' is requesting a budget change for ' . $service->service_name . ' to $' . number_format( $new_budget, 0 ) . '/mo.',
            'action_url' => home_url( '/advisor-portal/?tab=approvals' ),
        ) );
        // Email
        $subject = '[6ix Developers] Budget Change Request from ' . $client->display_name;
        $body    = '<p>Hi ' . esc_html( $advisor->first_name ) . ',</p>'
                 . '<p><strong>' . esc_html( $client->display_name ) . '</strong> submitted a budget change request:</p>'
                 . '<ul><li><strong>Service:</strong> ' . esc_html( $service->service_name ) . '</li>'
                 . '<li><strong>Current:</strong> $' . number_format( floatval( $service->budget ), 0 ) . '/mo</li>'
                 . '<li><strong>Requested:</strong> $' . number_format( $new_budget, 0 ) . '/mo</li></ul>'
                 . '<p><a href="' . home_url( '/advisor-portal/?tab=approvals' ) . '">Review in Portal →</a></p>';
        wp_mail( $advisor->user_email, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );
    }

    if ( function_exists( 'six_send_system_email' ) ) {
        $client = $client ?? get_userdata( $client_id );
        six_send_system_email( 'budget_change', array(
            'client_name'      => $client->display_name,
            'client_email'     => $client->user_email,
            'service_name'     => $service->service_name,
            'current_budget'   => number_format( floatval( $service->budget ), 0 ),
            'requested_budget' => number_format( $new_budget, 0 ),
            'submitted_at'     => current_time( 'F j, Y g:i a' ),
        ), array(
            'send_customer' => false, // customer copy of this template is sent on approval instead, once the change is actually live
            'dashboard_url' => home_url( '/advisor-portal/?tab=approvals' ),
        ) );
    }

    wp_send_json_success( array( 'message' => 'Budget change request sent to your advisor for approval.' ) );
} );

// Advisor approves budget change
add_action( 'wp_ajax_six_approve_budget_change', function() {
    check_ajax_referer( 'six_nonce', 'nonce' );
    if ( ! Six_Roles::is_advisor() ) wp_send_json_error( 'Permission denied' );
    global $wpdb;
    $client_id    = intval( $_POST['client_id']  ?? 0 );
    $service_id   = intval( $_POST['service_id'] ?? 0 );
    $final_budget = floatval( $_POST['budget']   ?? 0 );
    if ( ! $client_id || ! $service_id || $final_budget <= 0 ) wp_send_json_error( 'Invalid data' );
    $wpdb->update( $wpdb->prefix . 'six_client_services', array( 'budget' => $final_budget ),
        array( 'id' => $service_id, 'client_id' => $client_id ) );
    delete_user_meta( $client_id, 'six_budget_req_' . $service_id );
    $service = $wpdb->get_row( $wpdb->prepare( "SELECT service_name FROM {$wpdb->prefix}six_client_services WHERE id=%d", $service_id ) );
    Six_Notifications::create( array(
        'user_id' => $client_id, 'type' => 'budget_approved',
        'title'   => 'Budget Updated',
        'message' => ( $service->service_name ?? 'Service' ) . ' budget set to $' . number_format( $final_budget, 0 ) . '/mo.',
    ) );
    // Log to Odoo
    if ( class_exists('Six_Odoo') ) {
        $lead_id = intval( get_user_meta($client_id, 'six_odoo_lead_id', true) );
        if ( $lead_id ) {
            $client_user = get_userdata($client_id);
            $client_name = $client_user ? $client_user->display_name : "Client #{$client_id}";
            $svc_name    = $service->service_name ?? "Service #{$service_id}";
            $advisor_uid = Six_Odoo::get_advisor_odoo_uid_public($client_id);
            Six_Odoo::create_activity(
                $lead_id,
                'Customer update request',
                "Budget change approved\n"
                . "Client: {$client_name}\n"
                . "Service: {$svc_name}\n"
                . "New budget: $" . number_format($final_budget,0) . "/mo\n"
                . "Approved at: " . current_time('mysql'),
                'Todo', 0, $advisor_uid
            );
            Six_Odoo::post_note($lead_id,
                "Budget updated: {$svc_name} to $" . number_format($final_budget,0) . "/mo\n"
                . "Approved at: " . current_time('mysql')
            );
        }
    }

    if ( function_exists( 'six_send_system_email' ) ) {
        $client_user = $client_user ?? get_userdata( $client_id );
        if ( $client_user ) {
            six_send_system_email( 'budget_change', array(
                'client_name' => $client_user->display_name,
                'service_name'=> $service->service_name ?? 'Service',
                'new_budget'  => number_format( $final_budget, 0 ),
            ), array(
                'send_admin'     => false, // admin already got the request-time copy above
                'customer_email' => $client_user->user_email,
                'dashboard_url'  => home_url( '/advisor-portal/?tab=clients&client=' . $client_id ),
            ) );
        }
    }

    wp_send_json_success( array( 'budget' => $final_budget ) );
} );

// Advisor declines budget change
add_action( 'wp_ajax_six_decline_budget_change', function() {
    check_ajax_referer( 'six_nonce', 'nonce' );
    if ( ! Six_Roles::is_advisor() ) wp_send_json_error( 'Permission denied' );
    $client_id  = intval( $_POST['client_id']  ?? 0 );
    $service_id = intval( $_POST['service_id'] ?? 0 );
    delete_user_meta( $client_id, 'six_budget_req_' . $service_id );
    Six_Notifications::create( array(
        'user_id' => $client_id, 'type' => 'budget_declined',
        'title'   => 'Budget Request Declined',
        'message' => 'Your budget change request was reviewed. Contact your advisor to discuss.',
    ) );
    wp_send_json_success();
} );

// ─────────────────────────────────────────────────────────────────────────────
// METRICS — with edit + delete
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'wp_ajax_six_add_metric', function() {
    check_ajax_referer( 'six_nonce', 'nonce' );
    if ( ! Six_Roles::is_advisor() ) wp_send_json_error( 'Permission denied' );
    global $wpdb;
    $client_id = intval( $_POST['client_id'] ?? 0 );
    $label     = sanitize_text_field( $_POST['label']     ?? '' );
    // Accept both param spellings — the dashboard JS sends service_slug /
    // *_value, older callers send service / previous|current|target. A mismatch
    // here previously saved metrics with an empty service_slug and empty values,
    // so they never matched the customer's service page.
    $service   = sanitize_text_field( $_POST['service_slug'] ?? $_POST['service'] ?? '' );
    $metric_id = intval( $_POST['metric_id']              ?? 0 );
    if ( ! $client_id || ! $label ) wp_send_json_error( 'Missing fields' );

    $data = array(
        'previous_value' => sanitize_text_field( $_POST['previous_value'] ?? $_POST['previous'] ?? '' ),
        'current_value'  => sanitize_text_field( $_POST['current_value']  ?? $_POST['current']  ?? '' ),
        'target_value'   => sanitize_text_field( $_POST['target_value']   ?? $_POST['target']   ?? '' ),
        'unit'           => sanitize_text_field( $_POST['unit']     ?? '' ),
        'updated_at'     => current_time( 'mysql' ),
    );

    // Edit existing metric
    if ( $metric_id ) {
        $wpdb->update( $wpdb->prefix . 'six_metrics', $data, array( 'id' => $metric_id, 'client_id' => $client_id ) );
        wp_send_json_success( array( 'id' => $metric_id, 'updated' => true ) );
    }

    // Insert new (upsert by label+service)
    $existing = $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}six_metrics WHERE client_id=%d AND service_slug=%s AND label=%s",
        $client_id, $service, $label
    ) );
    if ( $existing ) {
        $wpdb->update( $wpdb->prefix . 'six_metrics', $data, array( 'id' => $existing ) );
        wp_send_json_success( array( 'id' => $existing, 'updated' => true ) );
    }
    $data['client_id'] = $client_id; $data['service_slug'] = $service; $data['label'] = $label;
    $wpdb->insert( $wpdb->prefix . 'six_metrics', $data );
    wp_send_json_success( array( 'id' => $wpdb->insert_id, 'updated' => false ) );
} );

// Service-activation wizard — read saved setup (config + connected account IDs
// + existing metrics) so the advisor can review/edit before activating.
add_action( 'wp_ajax_six_get_service_setup', function() {
    check_ajax_referer( 'six_nonce', 'nonce' );
    if ( ! Six_Roles::is_advisor() && ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permission denied.' );
    $client_id = intval( $_POST['client_id'] ?? 0 );
    $slug      = sanitize_key( $_POST['service_slug'] ?? '' );
    if ( ! $client_id || ! $slug ) wp_send_json_error( 'Missing client or service.' );
    global $wpdb;
    $config = get_user_meta( $client_id, 'six_svc_config_' . $slug, true );
    if ( ! is_array( $config ) ) $config = array();
    // Pull any already-connected data-source account IDs as sensible defaults.
    $map = function_exists( 'six_data_source_meta_map' ) ? six_data_source_meta_map() : array();
    foreach ( $map as $src => $m ) {
        $v = get_user_meta( $client_id, $m['meta'], true );
        if ( $v !== '' && $v !== false ) $config += array( $m['meta'] => $v );
    }
    $metrics = $wpdb->get_results( $wpdb->prepare(
        "SELECT id,label,previous_value AS previous,current_value AS current,target_value AS target
         FROM {$wpdb->prefix}six_metrics WHERE client_id=%d AND service_slug=%s ORDER BY id", $client_id, $slug ), ARRAY_A );
    wp_send_json_success( array( 'config' => (object) $config, 'metrics' => $metrics ?: array() ) );
} );

// Service-activation wizard — save advisor-entered service info + metrics, and
// optionally activate the service. Account-ID fields are mirrored into the
// canonical data-source meta keys so live data connects automatically.
add_action( 'wp_ajax_six_save_service_setup', function() {
    check_ajax_referer( 'six_nonce', 'nonce' );
    if ( ! Six_Roles::is_advisor() && ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permission denied.' );
    global $wpdb;
    $client_id = intval( $_POST['client_id'] ?? 0 );
    $svc_id    = intval( $_POST['service_id'] ?? 0 );
    $slug      = sanitize_key( $_POST['service_slug'] ?? '' );
    $activate  = ! empty( $_POST['activate'] );
    if ( ! $client_id || ! $slug ) wp_send_json_error( 'Missing client or service.' );

    // Config (arbitrary advisor-entered fields).
    $config_raw = json_decode( wp_unslash( $_POST['config'] ?? '{}' ), true );
    if ( ! is_array( $config_raw ) ) $config_raw = array();
    $config = array();
    foreach ( $config_raw as $k => $v ) { $config[ sanitize_key( $k ) ] = sanitize_textarea_field( (string) $v ); }
    update_user_meta( $client_id, 'six_svc_config_' . $slug, $config );

    // Mirror connected account IDs into the canonical data-source meta keys.
    $map = function_exists( 'six_data_source_meta_map' ) ? six_data_source_meta_map() : array();
    foreach ( $map as $src => $m ) {
        if ( ! empty( $config[ $m['meta'] ] ) ) {
            update_user_meta( $client_id, $m['meta'], $config[ $m['meta'] ] );
            update_user_meta( $client_id, 'six_ds_' . $src . '_at', current_time( 'mysql' ) );
        }
    }

    // Metrics (upsert by label within this service).
    $metrics = json_decode( wp_unslash( $_POST['metrics'] ?? '[]' ), true );
    if ( ! is_array( $metrics ) ) $metrics = array();
    $saved = 0;
    foreach ( $metrics as $mm ) {
        $label = sanitize_text_field( $mm['label'] ?? '' );
        if ( $label === '' ) continue;
        $row = array(
            'previous_value' => sanitize_text_field( $mm['previous'] ?? '' ),
            'current_value'  => sanitize_text_field( $mm['current']  ?? '' ),
            'target_value'   => sanitize_text_field( $mm['target']   ?? '' ),
            'updated_at'     => current_time( 'mysql' ),
        );
        $ex = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}six_metrics WHERE client_id=%d AND service_slug=%s AND label=%s",
            $client_id, $slug, $label ) );
        if ( $ex ) {
            $wpdb->update( "{$wpdb->prefix}six_metrics", $row, array( 'id' => $ex ) );
        } else {
            $row['client_id'] = $client_id; $row['service_slug'] = $slug; $row['label'] = $label;
            $wpdb->insert( "{$wpdb->prefix}six_metrics", $row );
        }
        $saved++;
    }

    // Activate the service if requested.
    $activated = false;
    if ( $activate && $svc_id ) {
        $cols = $wpdb->get_col( "SHOW COLUMNS FROM {$wpdb->prefix}six_client_services", 0 );
        $d = array( 'status' => 'active' );
        if ( in_array( 'advisor_id', $cols, true ) ) $d['advisor_id'] = get_current_user_id();
        if ( in_array( 'updated_at', $cols, true ) ) $d['updated_at'] = current_time( 'mysql' );
        if ( $wpdb->update( "{$wpdb->prefix}six_client_services", $d, array( 'id' => $svc_id ) ) !== false ) {
            $activated = true;
            if ( class_exists( 'Six_Odoo' ) ) {
                $lead = intval( get_user_meta( $client_id, 'six_odoo_lead_id', true ) );
                if ( $lead ) Six_Odoo::update_lead_stage( $lead, 'Customer' );
            }
        }
    }
    wp_send_json_success( array( 'metrics_saved' => $saved, 'activated' => $activated ) );
} );

// AI-suggested metrics for a service (activation step). Grounded in live data
// where connected, else industry benchmarks — returned in prev/current/target
// form for the advisor to review and save.
add_action( 'wp_ajax_six_suggest_metrics', function() {
    check_ajax_referer( 'six_nonce', 'nonce' );
    if ( ! Six_Roles::is_advisor() && ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permission denied.' );
    if ( ! class_exists( 'Six_AI_Strategist' ) ) wp_send_json_error( 'AI Strategist not available.' );
    $client_id = intval( $_POST['client_id'] ?? 0 );
    $slug      = sanitize_key( $_POST['service_slug'] ?? '' );
    if ( ! $client_id || ! $slug ) wp_send_json_error( 'Missing client or service.' );
    @set_time_limit( 90 );
    $r = Six_AI_Strategist::suggest_metrics( $client_id, $slug );
    if ( isset( $r['error'] ) ) wp_send_json_error( $r['error'] );
    wp_send_json_success( $r );
} );

add_action( 'wp_ajax_six_delete_metric', function() {
    check_ajax_referer( 'six_nonce', 'nonce' );
    if ( ! Six_Roles::is_advisor() ) wp_send_json_error( 'Permission denied' );
    global $wpdb;
    $wpdb->delete( $wpdb->prefix . 'six_metrics', array( 'id' => intval( $_POST['metric_id'] ?? 0 ) ) );
    wp_send_json_success();
} );

// ─────────────────────────────────────────────────────────────────────────────
// RECOMMENDATIONS — with edit + delete + customer approve/dismiss
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'wp_ajax_six_add_recommendation', function() {
    check_ajax_referer( 'six_nonce', 'nonce' );
    if ( ! Six_Roles::is_advisor() ) wp_send_json_error( 'Permission denied' );
    global $wpdb;
    $client_id = intval( $_POST['client_id'] ?? 0 );
    $rec_id    = intval( $_POST['rec_id']    ?? 0 );
    if ( ! $client_id ) wp_send_json_error( 'Missing client' );
    $data = array(
        'title'        => sanitize_text_field( $_POST['title']        ?? '' ),
        'description'  => sanitize_textarea_field( $_POST['description']  ?? '' ),
        'action_label' => sanitize_text_field( $_POST['action_label'] ?? '' ),
        'action_type'  => 'info',
    );
    if ( $rec_id ) {
        $wpdb->update( $wpdb->prefix . 'six_recommendations', $data,
            array( 'id' => $rec_id, 'advisor_id' => get_current_user_id() ) );
        wp_send_json_success( array( 'id' => $rec_id, 'updated' => true ) );
    }
    $data['client_id'] = $client_id; $data['advisor_id'] = get_current_user_id(); $data['status'] = 'active';
    $wpdb->insert( $wpdb->prefix . 'six_recommendations', $data );
    Six_Notifications::create( array(
        'user_id' => $client_id, 'type' => 'recommendation',
        'title'   => 'New Recommendation from your Advisor',
        'message' => sanitize_text_field( $_POST['title'] ?? '' ),
    ) );
    wp_send_json_success( array( 'id' => $wpdb->insert_id, 'updated' => false ) );
} );

add_action( 'wp_ajax_six_delete_recommendation', function() {
    check_ajax_referer( 'six_nonce', 'nonce' );
    if ( ! Six_Roles::is_advisor() ) wp_send_json_error( 'Permission denied' );
    global $wpdb;
    $wpdb->delete( $wpdb->prefix . 'six_recommendations',
        array( 'id' => intval( $_POST['rec_id'] ?? 0 ), 'advisor_id' => get_current_user_id() ) );
    wp_send_json_success();
} );

// Customer approves recommendation
add_action( 'wp_ajax_six_approve_recommendation', function() {
    check_ajax_referer( 'six_nonce', 'nonce' );
    global $wpdb;
    $client_id = get_current_user_id();
    $rec_id    = intval( $_POST['rec_id'] ?? 0 );
    $rec       = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}six_recommendations WHERE id=%d AND client_id=%d", $rec_id, $client_id
    ) );
    if ( ! $rec ) wp_send_json_error( 'Not found' );
    $wpdb->update( $wpdb->prefix . 'six_recommendations', array( 'status' => 'approved' ), array( 'id' => $rec_id ) );
    $client = get_userdata( $client_id );
    Six_Notifications::create( array(
        'user_id' => $rec->advisor_id, 'type' => 'recommendation_approved',
        'title'   => 'Recommendation Approved',
        'message' => $client->display_name . ' approved: "' . $rec->title . '"',
    ) );
    wp_send_json_success();
} );

// Customer dismisses recommendation
add_action( 'wp_ajax_six_dismiss_recommendation', function() {
    check_ajax_referer( 'six_nonce', 'nonce' );
    global $wpdb;
    $wpdb->update( $wpdb->prefix . 'six_recommendations',
        array( 'status' => 'dismissed' ),
        array( 'id' => intval( $_POST['rec_id'] ?? 0 ), 'client_id' => get_current_user_id() )
    );
    wp_send_json_success();
} );

// ─────────────────────────────────────────────────────────────────────────────
// REPORTS — file upload OR url
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'wp_ajax_six_upload_report', function() {
    check_ajax_referer( 'six_nonce', 'nonce' );
    if ( ! Six_Roles::is_advisor() ) wp_send_json_error( 'Permission denied' );
    global $wpdb;
    $client_id = intval( $_POST['client_id'] ?? 0 );
    $title     = sanitize_text_field( $_POST['title']  ?? '' );
    if ( ! $client_id || ! $title ) wp_send_json_error( 'Missing fields' );

    $file_url  = '';
    $file_size = '';

    if ( ! empty( $_FILES['report_file']['name'] ) ) {
        if ( ! function_exists( 'wp_handle_upload' ) ) require_once ABSPATH . 'wp-admin/includes/file.php';
        $upload = wp_handle_upload( $_FILES['report_file'], array( 'test_form' => false ) );
        if ( isset( $upload['error'] ) ) wp_send_json_error( $upload['error'] );
        $file_url  = $upload['url'];
        $file_size = size_format( filesize( $upload['file'] ) );
    } elseif ( ! empty( $_POST['url'] ) ) {
        $file_url = esc_url_raw( $_POST['url'] );
    }

    if ( ! $file_url ) wp_send_json_error( 'No file or URL provided' );

    $wpdb->insert( $wpdb->prefix . 'six_reports', array(
        'client_id'  => $client_id,
        'advisor_id' => get_current_user_id(),
        'title'      => $title,
        'type'       => 'monthly',
        'file_url'   => $file_url,
        'file_size'  => $file_size,
        'period'     => sanitize_text_field( $_POST['period'] ?? '' ),
    ) );
    Six_Notifications::create( array(
        'user_id'    => $client_id, 'type' => 'report_uploaded',
        'title'      => 'New Report Available',
        'message'    => $title . ' has been uploaded.',
        'action_url' => home_url( '/portal/?tab=reports' ),
    ) );
    wp_send_json_success( array( 'id' => $wpdb->insert_id, 'url' => $file_url ) );
} );

// ─────────────────────────────────────────────────────────────────────────────
// BOOKING
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'wp_ajax_six_book_meeting', function() {
    check_ajax_referer( 'six_nonce', 'nonce' );
    $client_id = get_current_user_id();
    if ( ! $client_id ) wp_send_json_error( 'Not logged in.' );
    if ( ! class_exists( 'Six_Appointments' ) ) wp_send_json_error( 'Booking unavailable.' );

    // Unified path: persists the appointment, creates a Google Calendar event +
    // Meet link (when the advisor has connected their calendar), and emails BOTH
    // the customer and the advisor.
    $result = Six_Appointments::create( array(
        'client_id' => $client_id,
        'start'     => sanitize_text_field( $_POST['start'] ?? '' ),
        'duration'  => intval( $_POST['duration'] ?? 30 ),
        'notes'     => sanitize_textarea_field( $_POST['notes'] ?? '' ),
        'source'    => 'booking',
    ) );
    if ( ! empty( $result['success'] ) ) wp_send_json_success( $result );
    wp_send_json_error( $result['error'] ?? 'Could not book meeting.' );
} );

// Advisor books a meeting WITH a client from the client profile. Same unified
// path as the customer booking (persists, creates the Google Calendar event +
// Meet link, emails both parties) — just with the client and advisor supplied.
add_action( 'wp_ajax_six_adv_book_meeting', function() {
    check_ajax_referer( 'six_nonce', 'nonce' );
    if ( ! Six_Roles::is_advisor() && ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permission denied.' );
    if ( ! class_exists( 'Six_Appointments' ) ) wp_send_json_error( 'Booking unavailable.' );
    $client_id = intval( $_POST['client_id'] ?? 0 );
    $start     = sanitize_text_field( $_POST['start'] ?? '' );
    if ( ! $client_id ) wp_send_json_error( 'Missing client.' );
    if ( ! $start )     wp_send_json_error( 'Please pick a time.' );
    $result = Six_Appointments::create( array(
        'client_id'  => $client_id,
        'advisor_id' => get_current_user_id(),
        'start'      => $start,
        'duration'   => intval( $_POST['duration'] ?? 30 ),
        'notes'      => sanitize_textarea_field( $_POST['notes'] ?? '' ),
        'source'     => 'advisor',
    ) );
    if ( ! empty( $result['success'] ) ) wp_send_json_success( $result );
    wp_send_json_error( $result['error'] ?? 'Could not book meeting.' );
} );

// ─────────────────────────────────────────────────────────────────────────────
// GOOGLE ADS — MCC / Manager Account
// ─────────────────────────────────────────────────────────────────────────────

// Admin: save global MCC credentials (done once)
add_action( 'wp_ajax_six_save_mcc_credentials', function() {
    check_ajax_referer( 'six_nonce', 'nonce' );
    if ( ! Six_Roles::is_advisor() ) wp_send_json_error( 'Permission denied' );
    $mask = str_repeat( '•', 12 );
    $fields = array(
        'six_gads_developer_token', 'six_gads_manager_id',
        'six_gads_client_id', 'six_gads_client_secret', 'six_gads_refresh_token',
    );
    foreach ( $fields as $f ) {
        if ( isset( $_POST[ $f ] ) && $_POST[ $f ] !== $mask && $_POST[ $f ] !== '' ) {
            update_option( $f, sanitize_text_field( $_POST[ $f ] ) );
        }
    }
    // Clear cached token to force fresh fetch
    delete_option( 'six_gads_access_token' );
    delete_option( 'six_gads_token_expires' );

    $token = Six_Google_Ads::get_mcc_access_token( true );
    if ( $token ) {
        wp_send_json_success( array( 'message' => 'MCC credentials saved and connection verified ✓' ) );
    } else {
        wp_send_json_error( 'Credentials saved but token verification failed: ' . Six_Google_Ads::get_last_error() );
    }
} );

// Advisor: save Customer ID for a specific client
add_action( 'wp_ajax_six_save_client_gads', function() {
    check_ajax_referer( 'six_nonce', 'nonce' );
    if ( ! Six_Roles::is_advisor() ) wp_send_json_error( 'Permission denied' );
    global $wpdb;
    $client_id   = intval( $_POST['client_id'] ?? 0 );
    $customer_id = sanitize_text_field( $_POST['six_gads_customer_id'] ?? '' );
    if ( ! $client_id ) wp_send_json_error( 'Invalid client' );
    if ( ! current_user_can( 'manage_options' ) ) {
        $ok = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}six_assignments WHERE client_id=%d AND advisor_id=%d",
            $client_id, get_current_user_id()
        ) );
        if ( ! $ok ) wp_send_json_error( 'Not your client' );
    }
    $clean = preg_replace( '/[^0-9]/', '', $customer_id );
    update_user_meta( $client_id, 'six_gads_customer_id',         $clean );
    update_user_meta( $client_id, 'six_gads_customer_id_display', $customer_id );
    wp_send_json_success( array( 'message' => 'Customer ID saved.' ) );
} );

// Advisor: manually trigger sync for one client.
// Accepts an optional date_range (THIS_MONTH default, LAST_MONTH, ALL_TIME,
// rolling windows, or "YYYY-MM-DD:YYYY-MM-DD") and a force flag that bypasses
// the 6-hour cache — used by the advisor's "Refresh now" button and the
// date-range selector on the client profile.
add_action( 'wp_ajax_six_sync_client_gads', function() {
    check_ajax_referer( 'six_nonce', 'nonce' );
    if ( ! Six_Roles::is_advisor() ) wp_send_json_error( 'Permission denied' );
    $client_id   = intval( $_POST['client_id'] ?? 0 );
    $customer_id = get_user_meta( $client_id, 'six_gads_customer_id', true );
    if ( ! $customer_id ) wp_send_json_error( 'No Google Ads Customer ID set for this client.' );

    $range = sanitize_text_field( $_POST['date_range'] ?? 'THIS_MONTH' );
    // Whitelist tokens; allow the "YYYY-MM-DD:YYYY-MM-DD" custom form through.
    $allowed = array( 'THIS_MONTH', 'LAST_MONTH', 'ALL_TIME', 'LAST_7_DAYS', 'LAST_14_DAYS', 'LAST_30_DAYS', 'YESTERDAY', 'TODAY' );
    if ( ! in_array( strtoupper( $range ), $allowed, true )
         && ! preg_match( '/^\d{4}-\d{2}-\d{2}:\d{4}-\d{2}-\d{2}$/', $range ) ) {
        $range = 'THIS_MONTH';
    }
    $force = ! empty( $_POST['force'] );

    $metrics = Six_Google_Ads::get_campaign_metrics_for_client( $client_id, $range, $force );
    if ( $metrics !== false ) {
        wp_send_json_success( array(
            'metrics'    => $metrics,
            'date_range' => strtoupper( $range ),
            'empty'      => empty( $metrics['campaigns'] ),
        ) );
    } else {
        wp_send_json_error( Six_Google_Ads::get_last_error() ?: 'Sync failed.' );
    }
} );

// Advisor: toggle "Auto-from-source" for a service's metrics. When enabled for
// a live-source service (Google Ads today), immediately pull the current month
// and persist it to six_metrics so the customer dashboard reflects live data.
add_action( 'wp_ajax_six_toggle_metric_auto', function() {
    check_ajax_referer( 'six_nonce', 'nonce' );
    if ( ! Six_Roles::is_advisor() ) wp_send_json_error( 'Permission denied' );
    global $wpdb;
    $client_id = intval( $_POST['client_id'] ?? 0 );
    $slug      = sanitize_key( $_POST['service_slug'] ?? '' );
    $enabled   = ! empty( $_POST['enabled'] );
    if ( ! $client_id || ! $slug ) wp_send_json_error( 'Invalid request' );

    // Ownership check for non-admin advisors
    if ( ! current_user_can( 'manage_options' ) ) {
        $ok = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}six_assignments WHERE client_id=%d AND advisor_id=%d",
            $client_id, get_current_user_id()
        ) );
        if ( ! $ok ) wp_send_json_error( 'Not your client' );
    }

    // Which slugs can auto-pull from a live source
    $live_sources = array( 'google-ads' => 'Google Ads' );

    if ( ! $enabled ) {
        delete_user_meta( $client_id, 'six_metric_auto_' . $slug );
        wp_send_json_success( array( 'message' => 'Manual entry', 'enabled' => false ) );
    }

    update_user_meta( $client_id, 'six_metric_auto_' . $slug, '1' );

    // Enabling: pull live data right now for supported sources.
    if ( $slug === 'google-ads' && class_exists( 'Six_Google_Ads' ) ) {
        $m = Six_Google_Ads::get_campaign_metrics_for_client( $client_id, 'THIS_MONTH', true );
        if ( $m === false ) {
            // Keep the preference on, but tell the advisor why nothing pulled.
            wp_send_json_success( array(
                'message' => 'On — but no data yet: ' . ( Six_Google_Ads::get_last_error() ?: 'check the Customer ID.' ),
                'enabled' => true,
            ) );
        }
        wp_send_json_success( array(
            'message' => 'On — Google Ads metrics pulled ✓',
            'enabled' => true,
            'metrics' => $m,
        ) );
    }

    wp_send_json_success( array( 'message' => 'On', 'enabled' => true ) );
} );

// ─────────────────────────────────────────────────────────────────────────────
// STRIPE
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'wp_ajax_six_stripe_setup', function() {
    check_ajax_referer( 'six_nonce', 'nonce' );
    if ( ! class_exists( 'Six_Stripe' ) ) wp_send_json_error( 'Stripe not configured' );
    $pk = get_option( 'six_stripe_publishable_key', '' );
    if ( ! $pk ) wp_send_json_error( 'Stripe publishable key not set. Ask your advisor to finish setup.' );
    $secret = Six_Stripe::create_setup_intent( get_current_user_id() );
    if ( $secret ) wp_send_json_success( array( 'client_secret' => $secret, 'pk' => $pk ) );
    else wp_send_json_error( 'Could not start card setup (check the Stripe secret key).' );
} );

// Customer saves their confirmed card (after Stripe.js confirmCardSetup).
add_action( 'wp_ajax_six_save_card', function() {
    check_ajax_referer( 'six_nonce', 'nonce' );
    if ( ! class_exists( 'Six_Stripe' ) ) wp_send_json_error( 'Stripe not configured' );
    $pm = sanitize_text_field( wp_unslash( $_POST['payment_method_id'] ?? '' ) );
    if ( ! $pm ) wp_send_json_error( 'Missing card.' );
    Six_Stripe::save_payment_method( get_current_user_id(), $pm );
    $d    = Six_Stripe::get_payment_method_details( $pm );
    $card = ( is_array( $d ) && ! empty( $d['card'] ) ) ? $d['card'] : array();
    wp_send_json_success( array( 'brand' => ucfirst( $card['brand'] ?? 'Card' ), 'last4' => $card['last4'] ?? '••••' ) );
} );

// Advisor starts a card setup FOR a client (e.g. after a phone consultation).
add_action( 'wp_ajax_six_admin_stripe_setup', function() {
    check_ajax_referer( 'six_nonce', 'nonce' );
    if ( ! Six_Roles::is_advisor() && ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permission denied.' );
    if ( ! class_exists( 'Six_Stripe' ) ) wp_send_json_error( 'Stripe not configured' );
    $client_id = intval( $_POST['client_id'] ?? 0 );
    if ( ! $client_id ) wp_send_json_error( 'No client specified.' );
    $pk = get_option( 'six_stripe_publishable_key', '' );
    if ( ! $pk ) wp_send_json_error( 'Stripe publishable key not set in 6ix Portal → Integrations.' );
    $secret = Six_Stripe::create_setup_intent( $client_id );
    if ( ! $secret ) wp_send_json_error( 'Could not start card setup (check the Stripe secret key).' );
    wp_send_json_success( array( 'client_secret' => $secret, 'pk' => $pk ) );
} );

// Advisor saves the confirmed card to the client's account.
add_action( 'wp_ajax_six_admin_save_card', function() {
    check_ajax_referer( 'six_nonce', 'nonce' );
    if ( ! Six_Roles::is_advisor() && ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permission denied.' );
    if ( ! class_exists( 'Six_Stripe' ) ) wp_send_json_error( 'Stripe not configured' );
    $client_id = intval( $_POST['client_id'] ?? 0 );
    $pm        = sanitize_text_field( wp_unslash( $_POST['payment_method_id'] ?? '' ) );
    if ( ! $client_id || ! $pm ) wp_send_json_error( 'Missing data.' );
    Six_Stripe::save_payment_method( $client_id, $pm );
    $d    = Six_Stripe::get_payment_method_details( $pm );
    $card = ( is_array( $d ) && ! empty( $d['card'] ) ) ? $d['card'] : array();
    wp_send_json_success( array( 'brand' => ucfirst( $card['brand'] ?? 'Card' ), 'last4' => $card['last4'] ?? '••••' ) );
} );

// ─────────────────────────────────────────────────────────────────────────────
// DASHBOARD DATA
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'wp_ajax_six_get_dashboard', function() {
    check_ajax_referer( 'six_nonce', 'nonce' );
    $user_id = get_current_user_id();
    global $wpdb;
    $services        = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}six_client_services WHERE client_id=%d", $user_id ) );
    $metrics         = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}six_metrics WHERE client_id=%d ORDER BY updated_at DESC", $user_id ) );
    $recommendations = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}six_recommendations WHERE client_id=%d AND status='active' ORDER BY created_at DESC", $user_id ) );
    $notifications   = Six_Notifications::get_for_user( $user_id, 10 );
    $unread_msgs     = Six_Messaging::get_unread_count( $user_id );
    wp_send_json_success( compact( 'services', 'metrics', 'recommendations', 'notifications', 'unread_msgs' ) );
} );

// ─────────────────────────────────────────────────────────────────────────────
// AI INSIGHT — proxy Anthropic API calls server-side with 24hr caching
// Caching means: if same user visits the same tab twice in 24hrs → 0 API calls
// Manual refresh forces a new call and resets the cache
// ─────────────────────────────────────────────────────────────────────────────
add_action( 'wp_ajax_six_ai_insight', 'six_ajax_ai_insight' );
function six_ajax_ai_insight() {
    check_ajax_referer( 'six_nonce', 'nonce' );

    $user_id  = get_current_user_id();
    $prompt   = sanitize_textarea_field( wp_unslash( $_POST['prompt'] ?? '' ) );
    $cache_id = sanitize_key( $_POST['cache_key'] ?? '' );
    if ( ! $prompt ) wp_send_json_error( 'No prompt provided.' );

    // ── Check cache first (saves API call entirely) ───────────────────────
    $transient_key = 'six_ai_' . $user_id . '_' . md5( $prompt );
    $cached = get_transient( $transient_key );
    if ( $cached !== false ) {
        wp_send_json_success( array( 'text' => $cached, 'cached' => true ) );
    }

    // ── Validate API key ──────────────────────────────────────────────────
    $api_key = get_option( 'six_anthropic_api_key', '' );
    if ( ! $api_key ) {
        wp_send_json_error( 'AI not configured. Add your Anthropic API key in WP Admin → 6ix Portal → Integrations.' );
    }

    // ── Call Anthropic ────────────────────────────────────────────────────
    $response = wp_remote_post( 'https://api.anthropic.com/v1/messages', array(
        'timeout' => 45,
        'headers' => array(
            'Content-Type'      => 'application/json',
            'x-api-key'         => $api_key,
            'anthropic-version' => '2023-06-01',
        ),
        'body' => wp_json_encode( array(
            'model'      => 'claude-haiku-4-5-20251001', // cheapest + fastest model
            'max_tokens' => 350,                          // kept low to minimise cost
            'messages'   => array(
                array( 'role' => 'user', 'content' => $prompt )
            ),
        ) ),
    ) );

    if ( is_wp_error( $response ) ) {
        wp_send_json_error( 'Connection error: ' . $response->get_error_message() );
    }

    $code = wp_remote_retrieve_response_code( $response );
    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( $code === 429 ) {
        wp_send_json_error( 'Rate limited — please wait 30 seconds and try again.' );
    }
    if ( $code === 400 && strpos( $body['error']['message'] ?? '', 'credit' ) !== false ) {
        wp_send_json_error( 'Insufficient credits. Please add credits at console.anthropic.com → Billing.' );
    }
    if ( $code !== 200 ) {
        $err = $body['error']['message'] ?? "API error ({$code})";
        wp_send_json_error( $err );
    }

    $text = $body['content'][0]['text'] ?? '';
    if ( ! $text ) wp_send_json_error( 'Empty response from AI.' );

    // ── Cache for 24 hours — no API cost on repeat visits ────────────────
    set_transient( $transient_key, $text, 24 * HOUR_IN_SECONDS );

    wp_send_json_success( array( 'text' => $text, 'cached' => false ) );
}

// Check if any cached insight exists for this user/tab (called on page load)
add_action( 'wp_ajax_six_ai_check_cache', 'six_ajax_ai_check_cache' );
function six_ajax_ai_check_cache() {
    check_ajax_referer( 'six_nonce', 'nonce' );
    $user_id = get_current_user_id();
    // Check for any cached insight for this user (transient keys start with six_ai_{user_id}_)
    global $wpdb;
    $like    = $wpdb->esc_like( '_transient_six_ai_' . $user_id . '_' ) . '%';
    $exists  = $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT 1", $like
    ) );
    wp_send_json_success( array( 'has_cache' => (int)$exists > 0 ) );
}

// ─────────────────────────────────────────────────────────────────────────────
// OPPORTUNITY ENGINE — client requests an AI strategy
// Saves to six_recommendations, notifies advisor, creates Odoo task
// ─────────────────────────────────────────────────────────────────────────────
add_action( 'wp_ajax_six_request_opportunity', 'six_ajax_request_opportunity' );
function six_ajax_request_opportunity() {
    check_ajax_referer( 'six_nonce', 'nonce' );
    global $wpdb;

    $client_id   = get_current_user_id();
    $type        = sanitize_key( $_POST['type'] ?? '' );
    $title       = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
    $description = sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) );

    if ( ! $type || ! $title ) wp_send_json_error( 'Missing fields.' );

    // Get advisor
    $advisor_id = six_get_client_advisor_id( $client_id );
    if ( ! $advisor_id ) $advisor_id = 1; // fallback to admin

    $source = 'ai_' . $type;

    // Dismiss any previous record of same type so only one active at a time
    $wpdb->update(
        $wpdb->prefix . 'six_recommendations',
        array( 'status' => 'dismissed' ),
        array( 'client_id' => $client_id, 'source' => $source )
    );

    // Insert new opportunity
    $wpdb->insert( $wpdb->prefix . 'six_recommendations', array(
        'client_id'    => $client_id,
        'advisor_id'   => $advisor_id,
        'title'        => $title,
        'description'  => $description,
        'action_label' => 'Approve Strategy',
        'action_type'  => 'ai_opportunity',
        'status'       => 'active',
        'source'       => $source,
        'created_at'   => current_time( 'mysql' ),
    ) );
    $rec_id = $wpdb->insert_id;

    // Notify advisor
    $client     = get_userdata( $client_id );
    $client_name = $client ? $client->display_name : 'A client';
    if ( class_exists( 'Six_Notifications' ) ) {
        Six_Notifications::create( array(
            'user_id' => $advisor_id,
            'type'    => 'opportunity_requested',
            'title'   => 'Strategy Request: ' . $title,
            'message' => $client_name . ' requested the "' . $title . '" strategy. Review and approve in the Approvals tab.',
        ) );
    }

    // Create Odoo task if connected
    if ( class_exists( 'Six_Odoo' ) && function_exists( 'six_odoo_create_onboarding_task' ) ) {
        $checkout = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}six_checkout_progress WHERE user_id=%d", $client_id
        ) );
        six_odoo_create_onboarding_task( $client_id, $client->user_email ?? '', 'opportunity', array(
            'title'       => 'Strategy Requested: ' . $title,
            'description' => $client_name . ' requested: ' . $title . "\n\n" . substr( $description, 0, 500 ),
        ) );
    }

    wp_send_json_success( array( 'rec_id' => $rec_id, 'message' => 'Strategy request sent to your advisor.' ) );
}

// ─────────────────────────────────────────────────────────────────────────────
// APPROVE OPPORTUNITY — advisor approves from their portal
// Extended version of six_approve_recommendation that also creates Odoo task
// ─────────────────────────────────────────────────────────────────────────────
add_action( 'wp_ajax_six_approve_opportunity', 'six_ajax_approve_opportunity' );
function six_ajax_approve_opportunity() {
    check_ajax_referer( 'six_nonce', 'nonce' );
    if ( ! Six_Roles::is_advisor() ) wp_send_json_error( 'Permission denied' );
    global $wpdb;

    $rec_id     = intval( $_POST['rec_id'] ?? 0 );
    $advisor_id = get_current_user_id();

    $rec = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}six_recommendations WHERE id=%d AND advisor_id=%d", $rec_id, $advisor_id
    ) );
    if ( ! $rec ) wp_send_json_error( 'Not found.' );

    $wpdb->update( $wpdb->prefix . 'six_recommendations',
        array( 'status' => 'approved' ), array( 'id' => $rec_id ) );

    // Notify client
    if ( class_exists( 'Six_Notifications' ) ) {
        $advisor    = get_userdata( $advisor_id );
        Six_Notifications::create( array(
            'user_id' => $rec->client_id,
            'type'    => 'opportunity_approved',
            'title'   => 'Strategy Approved: ' . $rec->title,
            'message' => ( $advisor ? $advisor->display_name : 'Your advisor' ) . ' has approved your "' . $rec->title . '" strategy and will begin implementing it.',
        ) );
    }

    // Create Odoo task for implementation
    if ( class_exists( 'Six_Odoo' ) && function_exists( 'six_odoo_create_onboarding_task' ) ) {
        $client = get_userdata( $rec->client_id );
        six_odoo_create_onboarding_task( $rec->client_id, $client->user_email ?? '', 'opportunity', array(
            'title'       => 'Implement Strategy: ' . $rec->title,
            'description' => 'Strategy approved for ' . ( $client ? $client->display_name : 'client' ) . ".\n\n" . substr( $rec->description, 0, 500 ),
        ) );
    }

    wp_send_json_success( array( 'message' => 'Strategy approved.' ) );
}

// ─────────────────────────────────────────────────────────────────────────────
// ADVISOR PUSH SUGGESTION — advisor generates and sends AI rec to client
// Client sees it in their portal and can Approve or Dismiss
// ─────────────────────────────────────────────────────────────────────────────
add_action( 'wp_ajax_six_advisor_push_suggestion', 'six_ajax_advisor_push_suggestion' );
function six_ajax_advisor_push_suggestion() {
    check_ajax_referer( 'six_nonce', 'nonce' );
    if ( ! Six_Roles::is_advisor() ) wp_send_json_error( 'Permission denied.' );

    global $wpdb;
    $advisor_id  = get_current_user_id();
    $client_id   = intval( $_POST['client_id']   ?? 0 );
    $type        = sanitize_key( $_POST['type']        ?? 'advisor_ai' );
    $title       = sanitize_text_field( wp_unslash( $_POST['title']       ?? '' ) );
    $description = sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) );

    if ( ! $client_id || ! $title || ! $description ) {
        wp_send_json_error( 'Missing required fields.' );
    }

    // Verify advisor owns this client
    $assigned = $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}six_assignments WHERE client_id=%d AND advisor_id=%d",
        $client_id, $advisor_id
    ) );
    if ( ! $assigned ) wp_send_json_error( 'Not your client.' );

    // Insert recommendation — status active so client can see + act on it
    $wpdb->insert( $wpdb->prefix . 'six_recommendations', array(
        'client_id'    => $client_id,
        'advisor_id'   => $advisor_id,
        'title'        => $title,
        'description'  => $description,
        'action_label' => 'Approve Strategy',
        'action_type'  => 'ai_opportunity',
        'status'       => 'active',
        'source'       => 'advisor_' . $type,
        'created_at'   => current_time( 'mysql' ),
    ) );
    $rec_id = $wpdb->insert_id;

    // Notify client
    $advisor     = get_userdata( $advisor_id );
    $advisor_name = $advisor ? $advisor->display_name : 'Your advisor';
    if ( class_exists( 'Six_Notifications' ) ) {
        Six_Notifications::create( array(
            'user_id' => $client_id,
            'type'    => 'advisor_suggestion',
            'title'   => 'New Strategy from ' . $advisor_name . ': ' . $title,
            'message' => $advisor_name . ' sent you a new recommendation. Visit your Growth tab to review and approve it.',
        ) );
    }

    wp_send_json_success( array( 'rec_id' => $rec_id ) );
}

// ─────────────────────────────────────────────────────────────────────────────
// CLIENT APPROVE/DISMISS advisor-pushed suggestion
// Extended handler — covers both ai_ and advisor_ sourced recommendations
// ─────────────────────────────────────────────────────────────────────────────
add_action( 'wp_ajax_six_client_respond_suggestion', 'six_ajax_client_respond_suggestion' );
function six_ajax_client_respond_suggestion() {
    check_ajax_referer( 'six_nonce', 'nonce' );
    global $wpdb;
    $client_id = get_current_user_id();
    $rec_id    = intval( $_POST['rec_id']  ?? 0 );
    $action    = sanitize_key( $_POST['response'] ?? '' ); // 'approve' or 'dismiss'

    $rec = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}six_recommendations WHERE id=%d AND client_id=%d",
        $rec_id, $client_id
    ) );
    if ( ! $rec ) wp_send_json_error( 'Not found.' );

    $new_status = $action === 'approve' ? 'approved' : 'dismissed';
    $wpdb->update( $wpdb->prefix . 'six_recommendations', array( 'status' => $new_status ), array( 'id' => $rec_id ) );

    // Notify advisor
    $client = get_userdata( $client_id );
    $client_name = $client ? $client->display_name : 'Client';
    if ( class_exists( 'Six_Notifications' ) ) {
        Six_Notifications::create( array(
            'user_id' => $rec->advisor_id,
            'type'    => 'suggestion_' . $action . 'd',
            'title'   => $client_name . ' ' . ( $action === 'approve' ? 'approved' : 'dismissed' ) . ': ' . $rec->title,
            'message' => $client_name . ' has ' . ( $action === 'approve' ? 'approved and wants to move forward with' : 'dismissed' ) . ' the suggestion: "' . $rec->title . '".',
        ) );
    }

    // If approved, create Odoo task
    if ( $action === 'approve' && class_exists( 'Six_Odoo' ) && function_exists( 'six_odoo_create_onboarding_task' ) ) {
        six_odoo_create_onboarding_task( $client_id, $client->user_email ?? '', 'opportunity', array(
            'title'       => 'Client Approved: ' . $rec->title,
            'description' => $client_name . ' approved advisor suggestion.\n\n' . substr( $rec->description, 0, 500 ),
        ) );
    }

    wp_send_json_success( array( 'status' => $new_status ) );
}

// ─────────────────────────────────────────────────────────────────────────────
// CACHE OVERVIEW AI — saves roadmap + action plan to 72hr transient
// ─────────────────────────────────────────────────────────────────────────────
add_action( 'wp_ajax_six_cache_overview_ai', function() {
    check_ajax_referer( 'six_nonce', 'nonce' );
    $user_id  = get_current_user_id();
    $roadmap  = sanitize_textarea_field( wp_unslash( $_POST['roadmap']      ?? '' ) );
    $action   = sanitize_textarea_field( wp_unslash( $_POST['action_plan']  ?? '' ) );
    if ( $roadmap || $action ) {
        set_transient( 'six_overview_ai_' . $user_id, array(
            'roadmap' => $roadmap,
            'action'  => $action,
        ), 72 * HOUR_IN_SECONDS );
    }
    wp_send_json_success();
} );

// ─────────────────────────────────────────────────────────────────────────────
// MARK NOTIFICATIONS READ
// ─────────────────────────────────────────────────────────────────────────────
add_action( 'wp_ajax_six_mark_notifications_read', function() {
    check_ajax_referer( 'six_nonce', 'nonce' );
    if ( class_exists('Six_Notifications') ) {
        Six_Notifications::mark_all_read( get_current_user_id() );
    }
    wp_send_json_success();
} );

// ─────────────────────────────────────────────────────────────────────────────
// GET AVAILABLE CALENDAR SLOTS
// Returns 30-min slots 8am-6:30pm filtered by existing calendar events
// ─────────────────────────────────────────────────────────────────────────────
add_action( 'wp_ajax_six_get_available_slots', function() {
    check_ajax_referer( 'six_nonce', 'nonce' );
    $advisor_id = intval( $_POST['advisor_id'] ?? 0 );
    $date       = sanitize_text_field( $_POST['date'] ?? '' );
    if ( ! $advisor_id || ! $date ) wp_send_json_error( 'Missing params.' );

    $slots    = array();
    $busy     = array();
    $tz       = 'America/Toronto';

    // Try to get busy times from Google Calendar
    if ( class_exists('Six_Google_Calendar') ) {
        $token = Six_Google_Calendar::get_access_token( $advisor_id );
        if ( $token ) {
            $day_start = $date . 'T00:00:00-05:00';
            $day_end   = $date . 'T23:59:00-05:00';
            $resp = wp_remote_post( 'https://www.googleapis.com/calendar/v3/freeBusy', array(
                'timeout' => 15,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                ),
                'body' => wp_json_encode( array(
                    'timeMin' => $day_start,
                    'timeMax' => $day_end,
                    'items'   => array( array( 'id' => 'primary' ) ),
                ) ),
            ) );
            if ( ! is_wp_error( $resp ) ) {
                $data = json_decode( wp_remote_retrieve_body( $resp ), true );
                $busy = $data['calendars']['primary']['busy'] ?? array();
            }
        }
    }

    // Generate 30-min slots from 8:00 to 18:30
    $slot_start = strtotime( $date . ' 08:00:00' );
    $slot_end   = strtotime( $date . ' 18:30:00' );
    $buffer_secs= 15 * 60;

    for ( $t = $slot_start; $t <= $slot_end; $t += 30 * 60 ) {
        $t_end   = $t + 30 * 60;
        $is_busy = false;
        // Check if slot overlaps with any busy period (including 15-min buffer)
        foreach ( $busy as $b ) {
            $b_start = strtotime( $b['start'] ) - $buffer_secs;
            $b_end   = strtotime( $b['end'] )   + $buffer_secs;
            if ( $t < $b_end && $t_end > $b_start ) {
                $is_busy = true;
                break;
            }
        }
        // Don't show past slots
        if ( $t < time() + 3600 ) $is_busy = true;

        $slots[] = array(
            'time'   => gmdate( 'Y-m-d\TH:i:sP', $t ),
            'label'  => date_i18n( 'g:i A', $t ),
            'booked' => $is_busy,
        );
    }

    wp_send_json_success( array( 'slots' => $slots ) );
} );

// ─────────────────────────────────────────────────────────────────────────────
// TEST GA4 CONNECTION
// ─────────────────────────────────────────────────────────────────────────────
add_action( 'wp_ajax_six_test_ga4', function() {
    if ( ! check_ajax_referer( 'six_test_ga4', '_ajax_nonce', false ) ) wp_send_json_error('Nonce failed');
    if ( ! current_user_can('manage_options') ) wp_send_json_error('Permission denied');
    $property_id = sanitize_text_field($_POST['property_id'] ?? get_option('six_ga4_property_id',''));
    $json_key    = get_option('six_ga4_service_account_json','');
    if ( ! $property_id ) wp_send_json_error('No Property ID set.');
    if ( ! $json_key )    wp_send_json_error('No Service Account JSON set.');
    $key = json_decode($json_key, true);
    if ( ! $key || empty($key['client_email']) || empty($key['private_key']) ) {
        wp_send_json_error('Invalid Service Account JSON format.');
    }
    // Try to get a Google OAuth token
    $now = time();
    $header  = base64_encode(json_encode(['alg'=>'RS256','typ'=>'JWT']));
    $payload = base64_encode(json_encode([
        'iss'   => $key['client_email'],
        'scope' => 'https://www.googleapis.com/auth/analytics.readonly',
        'aud'   => 'https://oauth2.googleapis.com/token',
        'exp'   => $now + 3600,
        'iat'   => $now,
    ]));
    $sig_input = $header . '.' . $payload;
    $private_key = openssl_pkey_get_private($key['private_key']);
    if ( ! $private_key ) wp_send_json_error('Could not load private key from JSON.');
    openssl_sign($sig_input, $sig, $private_key, OPENSSL_ALGO_SHA256);
    $jwt = $sig_input . '.' . base64_encode($sig);
    $tok_resp = wp_remote_post('https://oauth2.googleapis.com/token', [
        'body' => ['grant_type'=>'urn:ietf:params:oauth:grant-type:jwt-bearer','assertion'=>$jwt],
    ]);
    if ( is_wp_error($tok_resp) ) wp_send_json_error('Network error: '.$tok_resp->get_error_message());
    $tok = json_decode(wp_remote_retrieve_body($tok_resp), true);
    if ( empty($tok['access_token']) ) wp_send_json_error('Auth failed: '.(json_encode($tok)));
    // Quick test call to GA4 Data API
    $test = wp_remote_post("https://analyticsdata.googleapis.com/v1beta/properties/{$property_id}:runReport", [
        'headers' => ['Authorization'=>'Bearer '.$tok['access_token'],'Content-Type'=>'application/json'],
        'body'    => json_encode(['dateRanges'=>[['startDate'=>'7daysAgo','endDate'=>'today']],'metrics'=>[['name'=>'sessions']]]),
    ]);
    if ( is_wp_error($test) ) wp_send_json_error('GA4 API error: '.$test->get_error_message());
    $res = json_decode(wp_remote_retrieve_body($test), true);
    if ( isset($res['error']) ) wp_send_json_error('GA4 error: '.$res['error']['message']);
    $sessions = $res['rows'][0]['metricValues'][0]['value'] ?? '0';
    wp_send_json_success("Connected! Property {$property_id} · {$sessions} sessions last 7 days.");
});

// ─────────────────────────────────────────────────────────────────────────────
// TEST META ADS CONNECTION
// ─────────────────────────────────────────────────────────────────────────────
add_action( 'wp_ajax_six_test_meta', function() {
    if ( ! check_ajax_referer( 'six_test_meta', '_ajax_nonce', false ) ) wp_send_json_error('Nonce failed');
    if ( ! current_user_can('manage_options') ) wp_send_json_error('Permission denied');
    $token   = get_option('six_meta_access_token','');
    $raw_acct = get_option('six_meta_ad_account_id','');
    if ( ! $token )    wp_send_json_error('No access token set.');
    if ( ! $raw_acct ) wp_send_json_error('No Ad Account ID set.');
    // An ad account must be addressed as act_<digits>. A bare numeric ID is read
    // by Graph as a generic object and fails with "does not exist / missing
    // permissions". Normalise it (also strips act_ dupes and separators).
    $account_id = class_exists('Six_Meta') ? Six_Meta::acct( $raw_acct ) : ( 'act_' . preg_replace('/[^0-9]/','',$raw_acct) );
    if ( ! $account_id || $account_id === 'act_' ) wp_send_json_error('Ad Account ID has no digits. Use the numeric ID from Ads Manager (e.g. act_1234567890 or 1234567890).');
    $gver = class_exists('Six_Meta') ? Six_Meta::API_VERSION : 'v23.0';
    $resp = wp_remote_get("https://graph.facebook.com/{$gver}/{$account_id}?fields=name,account_status,currency&access_token=".rawurlencode($token));
    if ( is_wp_error($resp) ) wp_send_json_error('Network error: '.$resp->get_error_message());
    $data = json_decode(wp_remote_retrieve_body($resp), true);
    if ( isset($data['error']) ) {
        $m = $data['error']['message'] ?? 'error';
        if ( stripos($m,'does not exist')!==false || stripos($m,'missing permission')!==false ) {
            $m .= ' — check that this is the Ad Account ID (not the Business or App ID), that the account is added to your Business Manager, and that the System User token has the ads_read permission and access to this account.';
        }
        wp_send_json_error('Meta error ('.$account_id.'): '.$m);
    }
    $name = $data['name'] ?? $account_id;
    wp_send_json_success("Connected! Account: {$name} ({$account_id}) · Status: ".(($data['account_status']??0)==1?'Active':'Inactive'));
});

// ─────────────────────────────────────────────────────────────────────────────
// ADVISOR: SAVE CLIENT PROFILE (advisor editing client data)
// ─────────────────────────────────────────────────────────────────────────────
add_action( 'wp_ajax_six_adv_save_client_profile', function() {
    check_ajax_referer( 'six_nonce', 'nonce' );
    // Previous check had a precedence bug ((!meta)==='advisor' is always
    // false) and never denied anyone — any logged-in user could edit any
    // client's profile.
    if ( ! Six_Roles::is_advisor() && ! current_user_can('manage_options') ) {
        wp_send_json_error('Permission denied.');
    }
    global $wpdb;
    $client_id = intval( $_POST['client_id'] ?? 0 );
    if ( ! $client_id ) wp_send_json_error('No client.');

    // Update WP user name/phone
    $user_data = array('ID'=>$client_id);
    if(!empty($_POST['first_name'])) $user_data['first_name']  = sanitize_text_field($_POST['first_name']);
    if(!empty($_POST['last_name']))  $user_data['last_name']   = sanitize_text_field($_POST['last_name']);
    if(!empty($_POST['first_name'])||!empty($_POST['last_name'])) {
        $user_data['display_name'] = trim(($user_data['first_name']??'').' '.($user_data['last_name']??''));
        wp_update_user($user_data);
    }
    if(isset($_POST['phone'])) update_user_meta($client_id,'billing_phone',sanitize_text_field($_POST['phone']));

    // Update checkout progress
    $table  = $wpdb->prefix.'six_checkout_progress';
    $fields = array(
        'business_name' => sanitize_text_field($_POST['business_name']??''),
        'website'       => esc_url_raw($_POST['website']??''),
        'industry'      => sanitize_text_field($_POST['industry']??''),
        'location'      => sanitize_text_field($_POST['location']??''),
        'goal'          => sanitize_text_field($_POST['goal']??''),
        'challenge'     => sanitize_text_field($_POST['challenge']??''),
        'mktg_budget'   => sanitize_text_field($_POST['mktg_budget']??''),
        'competitors'   => sanitize_text_field($_POST['competitors']??''),
        'updated_at'    => current_time('mysql'),
    );
    $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE user_id=%d",$client_id));
    if($existing) $wpdb->update($table,$fields,array('user_id'=>$client_id));
    else { $fields['user_id']=$client_id; $fields['created_at']=current_time('mysql'); $wpdb->insert($table,$fields); }

    // Notify client that advisor updated their profile
    if(class_exists('Six_Notifications')) {
        Six_Notifications::create(array(
            'user_id' => $client_id,
            'type'    => 'profile',
            'title'   => 'Profile Updated',
            'message' => 'Your advisor updated your business profile.',
        ));
    }
    if(class_exists('Six_Odoo')) Six_Odoo::create_or_update_contact($client_id);
    wp_send_json_success();
});

// ─────────────────────────────────────────────────────────────────────────────
// ADVISOR: SET SERVICE BUDGET DIRECTLY
// ─────────────────────────────────────────────────────────────────────────────
add_action( 'wp_ajax_six_adv_set_budget', function() {
    check_ajax_referer( 'six_nonce', 'nonce' );
    if ( ! Six_Roles::is_advisor() && ! current_user_can('manage_options') ) {
        wp_send_json_error('Permission denied.');
    }
    global $wpdb;
    $svc_id    = intval($_POST['service_id']??0);
    $client_id = intval($_POST['client_id']??0);
    $budget    = floatval($_POST['budget']??0);
    if(!$svc_id||!$client_id) wp_send_json_error('Missing params.');
    $wpdb->update("{$wpdb->prefix}six_client_services",
        array('budget'=>$budget,'updated_at'=>current_time('mysql')),
        array('id'=>$svc_id,'client_id'=>$client_id));
    // Notify client
    if(class_exists('Six_Notifications')) {
        Six_Notifications::create(array(
            'user_id' => $client_id,
            'type'    => 'billing',
            'title'   => 'Budget Updated',
            'message' => 'Your advisor updated your service budget to $'.number_format($budget,0).'/mo.',
        ));
    }
    wp_send_json_success();
});

// ─────────────────────────────────────────────────────────────────────────────
// SAVE SINGLE CLIENT DATA SOURCE KEY (GA4 property ID etc)
// ─────────────────────────────────────────────────────────────────────────────
add_action( 'wp_ajax_six_save_client_datasource', function() {
    check_ajax_referer( 'six_nonce', 'nonce' );
    if ( ! Six_Roles::is_advisor() && ! current_user_can('manage_options') ) {
        wp_send_json_error('Permission denied.');
    }
    $client_id = intval($_POST['client_id']??0);
    $key       = sanitize_key($_POST['key']??'');
    $value     = sanitize_text_field( wp_unslash( $_POST['value']??'' ) );
    $allowed   = array(
        'six_ga4_property_id','six_meta_pixel_id','six_gbp_location_id','six_gsc_site',
        'six_client_domain','six_target_location',
    );
    if(!$client_id||!in_array($key,$allowed)) wp_send_json_error('Not allowed.');
    // Normalise a domain to a bare host so the SEO tools accept it.
    if ( $key === 'six_client_domain' && $value !== '' ) {
        $value = preg_replace('#^https?://#i','',$value);
        $value = preg_replace('#^www\.#i','',$value);
        $value = rtrim( preg_replace('#/.*$#','',$value), '/' );
    }
    update_user_meta($client_id,$key,$value);
    wp_send_json_success();
});

// ─────────────────────────────────────────────────────────────────────────────
// TEST A CLIENT DATA SOURCE — live fetch so the advisor sees it actually works
// ─────────────────────────────────────────────────────────────────────────────
add_action( 'wp_ajax_six_test_client_datasource', function() {
    check_ajax_referer( 'six_nonce', 'nonce' );
    if ( ! Six_Roles::is_advisor() && ! current_user_can('manage_options') ) {
        wp_send_json_error('Permission denied.');
    }
    $client_id = intval($_POST['client_id']??0);
    $source    = sanitize_key($_POST['source']??'');
    if(!$client_id) wp_send_json_error('No client.');

    if ( $source === 'ga4' ) {
        if ( ! class_exists('Six_Analytics') ) wp_send_json_error('Analytics integration unavailable.');
        $prop = get_user_meta($client_id,'six_ga4_property_id',true) ?: get_option('six_ga4_property_id','');
        if ( ! $prop ) wp_send_json_error('No GA4 property ID saved for this client.');
        $r = Six_Analytics::ga4_summary( $prop, 30 );
        if ( isset($r['error']) ) wp_send_json_error( $r['error'] );
        $s = intval($r['sessions'] ?? $r['totals']['sessions'] ?? 0);
        wp_send_json_success( array('message'=>'GA4 connected — '.number_format($s).' sessions in the last 30 days.') );
    }
    if ( $source === 'gsc' ) {
        if ( ! class_exists('Six_Analytics') ) wp_send_json_error('Analytics integration unavailable.');
        $site = get_user_meta($client_id,'six_gsc_site',true);
        if ( ! $site ) wp_send_json_error('No Search Console site saved for this client.');
        $r = Six_Analytics::gsc_summary( $site, 28 );
        if ( isset($r['error']) ) wp_send_json_error( $r['error'] );
        $c = intval($r['clicks'] ?? $r['totals']['clicks'] ?? 0);
        wp_send_json_success( array('message'=>'Search Console connected — '.number_format($c).' clicks in the last 28 days.') );
    }
    if ( $source === 'gads' ) {
        if ( ! class_exists('Six_Google_Ads') ) wp_send_json_error('Google Ads integration unavailable.');
        $m = Six_Google_Ads::get_campaign_metrics_for_client( $client_id, 'THIS_MONTH' );
        if ( $m === false ) wp_send_json_error( Six_Google_Ads::get_last_error() ?: 'Google Ads data unavailable.' );
        if ( empty($m) ) wp_send_json_success( array('message'=>'Google Ads connected — no active-campaign data yet this month.') );
        wp_send_json_success( array('message'=>'Google Ads connected — live campaign metrics retrieved.') );
    }
    if ( $source === 'meta' ) {
        if ( ! class_exists('Six_Meta') ) wp_send_json_error('Meta integration unavailable.');
        if ( ! Six_Meta::configured() ) wp_send_json_error('No Meta System User token set. Add it in 6ix Portal → Integrations.');
        $acct = get_user_meta($client_id,'six_meta_ad_account_id',true);
        if ( ! $acct ) wp_send_json_error('No Meta ad account ID saved for this client.');
        $r = Six_Meta::account_insights( $acct, 30 );
        if ( isset($r['error']) ) wp_send_json_error( $r['error'] );
        $t = $r['totals'] ?? array();
        wp_send_json_success( array('message'=>'Meta Ads connected — $'.number_format($t['spend']??0,2).' spend, '.number_format($t['leads']??0).' leads in the last 30 days.') );
    }
    if ( $source === 'dataforseo' ) {
        if ( ! class_exists('Six_AI_Strategist') || ! class_exists('Six_DataForSEO') ) wp_send_json_error('DataForSEO integration unavailable.');
        $domain = Six_AI_Strategist::client_domain( $client_id );
        if ( ! $domain ) wp_send_json_error('No website/domain saved for this client.');
        $r = Six_DataForSEO::ranked_keywords( $domain, Six_AI_Strategist::target_location($client_id), 1 );
        if ( isset($r['error']) ) wp_send_json_error( $r['error'] );
        wp_send_json_success( array('message'=>'DataForSEO reachable for '.$domain.'.') );
    }
    wp_send_json_error('Unknown source.');
});

// ─────────────────────────────────────────────────────────────────────────────
// COMPETITOR ANALYSIS — DataForSEO competitor domains, one run per month,
// persisted so the table stays populated between visits.
// ─────────────────────────────────────────────────────────────────────────────
add_action( 'wp_ajax_six_competitor_analysis', function() {
    check_ajax_referer( 'six_nonce', 'nonce' );
    $uid = get_current_user_id();
    if ( ! $uid ) wp_send_json_error( 'Not logged in' );

    $window = 30 * DAY_IN_SECONDS;
    $last   = intval( get_user_meta( $uid, 'six_comp_analysis_at', true ) );
    $saved  = get_user_meta( $uid, 'six_comp_analysis_data', true );

    // Once a month: return the saved snapshot instead of spending another run.
    if ( $last && ( time() - $last ) < $window && ! empty( $saved ) ) {
        wp_send_json_success( array( 'locked' => true, 'ran_at' => $last, 'next_at' => $last + $window, 'data' => $saved ) );
    }
    if ( ! class_exists( 'Six_DataForSEO' ) ) wp_send_json_error( 'Competitor data source is not configured.' );

    $domain = class_exists( 'Six_AI_Strategist' ) ? Six_AI_Strategist::client_domain( $uid ) : '';
    if ( ! $domain ) {
        global $wpdb;
        $w = $wpdb->get_var( $wpdb->prepare( "SELECT website FROM {$wpdb->prefix}six_checkout_progress WHERE user_id=%d", $uid ) );
        $domain = $w ? preg_replace( '/^www\./', '', parse_url( $w, PHP_URL_HOST ) ?: $w ) : '';
    }
    if ( ! $domain ) wp_send_json_error( 'Add your website URL in your profile first, then run the analysis.' );

    $loc = class_exists( 'Six_AI_Strategist' ) ? Six_AI_Strategist::target_location( $uid ) : '';
    $res     = Six_DataForSEO::competitors( $domain, $loc, 12 );
    $api_err = $res['error'] ?? '';   // an empty set is common for small sites — fall through

    $rows = array();
    foreach ( (array) ( $res['competitors'] ?? array() ) as $c ) {
        $d = $c['domain'] ?? '';
        if ( ! $d || $d === $domain ) continue;
        $visits   = intval( $c['organic_traffic'] );
        $keywords = intval( $c['organic_keywords'] );
        $shared   = intval( $c['common_keywords'] );
        if     ( $shared >= 50 && $visits >= 1000 ) $insight = 'Direct rival — high keyword overlap and strong traffic';
        elseif ( $shared >= 50 )                    $insight = 'Direct competitor — competes for many of your keywords';
        elseif ( $visits >= 5000 )                  $insight = 'Market leader — a large footprint to learn from';
        elseif ( $shared > 0 )                      $insight = 'Partial overlap — competes on some terms';
        else                                        $insight = 'Adjacent player';
        $rows[] = array( 'domain' => $d, 'visits' => $visits, 'keywords' => $keywords, 'shared' => $shared, 'insight' => $insight );
        if ( count( $rows ) >= 10 ) break;
    }

    // Fallbacks — competitors() finds nothing when the client's own site has a
    // thin ranking footprint (new/small sites). Use the competitors they named
    // at onboarding, else discover who ranks for their top keyword.
    if ( empty( $rows ) ) {
        global $wpdb;
        $ck = $wpdb->get_row( $wpdb->prepare(
            "SELECT competitors, seo_keywords, ads_keywords FROM {$wpdb->prefix}six_checkout_progress WHERE user_id=%d", $uid ) );

        $row_from_domain = function ( $dom, $insight ) use ( $loc, $domain ) {
            $dom = preg_replace( '/^https?:\/\//', '', trim( (string) $dom ) );
            $dom = preg_replace( '/^www\./', '', $dom );
            $dom = preg_replace( '#/.*$#', '', $dom );
            if ( ! $dom || strpos( $dom, '.' ) === false || $dom === $domain ) return null;
            $rk = Six_DataForSEO::ranked_keywords( $dom, $loc, 100 );
            if ( isset( $rk['error'] ) || empty( $rk['ranked_keywords'] ) ) return null;
            $kws = $rk['ranked_keywords'];
            $vol = 0; foreach ( $kws as $k ) $vol += intval( $k['volume'] ?? 0 );
            return array( 'domain' => $dom, 'visits' => (int) round( $vol * 0.20 ), 'keywords' => count( $kws ), 'shared' => 0, 'insight' => $insight );
        };

        // A) Competitors the customer named during onboarding.
        foreach ( array_filter( array_map( 'trim', explode( ',', $ck->competitors ?? '' ) ) ) as $nd ) {
            $r = $row_from_domain( $nd, 'Competitor you named — live keyword footprint' );
            if ( $r ) { $rows[] = $r; if ( count( $rows ) >= 8 ) break; }
        }

        // B) Still nothing? Whoever ranks page-1 for their top keyword.
        if ( empty( $rows ) ) {
            $kw = trim( strtok( (string) ( $ck->seo_keywords ?: $ck->ads_keywords ?: '' ), ',' ) );
            if ( $kw ) {
                $serp = Six_DataForSEO::serp( $kw, $loc, 10 );
                $seen = array();
                foreach ( (array) ( $serp['results'] ?? array() ) as $it ) {
                    $du = preg_replace( '/^www\./', '', (string) ( $it['domain'] ?? '' ) );
                    if ( ! $du || $du === $domain || isset( $seen[ $du ] ) ) continue;
                    $seen[ $du ] = 1;
                    $r = $row_from_domain( $du, 'Ranks page 1 for “' . $kw . '”' );
                    if ( $r ) { $rows[] = $r; if ( count( $rows ) >= 6 ) break; }
                }
            }
        }
    }

    if ( empty( $rows ) ) {
        wp_send_json_error( $api_err
            ? 'Competitor data source error: ' . $api_err
            : 'No competitor data yet for ' . $domain . '. Add a few competitor URLs or target keywords in the profile, then run again.' );
    }

    $data = array( 'domain' => $domain, 'rows' => $rows, 'ran_at' => time() );
    update_user_meta( $uid, 'six_comp_analysis_data', $data );
    update_user_meta( $uid, 'six_comp_analysis_at', time() );
    wp_send_json_success( array( 'locked' => false, 'ran_at' => time(), 'next_at' => time() + $window, 'data' => $data ) );
} );

// ─────────────────────────────────────────────────────────────────────────────
// SAVE MULTIPLE META IDS PER CLIENT
// ─────────────────────────────────────────────────────────────────────────────
add_action( 'wp_ajax_six_save_client_datasources', function() {
    check_ajax_referer( 'six_nonce', 'nonce' );
    if ( ! Six_Roles::is_advisor() && ! current_user_can('manage_options') ) {
        wp_send_json_error('Permission denied.');
    }
    $client_id = intval($_POST['client_id']??0);
    if(!$client_id) wp_send_json_error('No client.');
    $allowed = array('six_meta_business_id','six_meta_ad_account_id','six_meta_pixel_id');
    foreach($allowed as $key){
        if(isset($_POST[$key])) update_user_meta($client_id,$key,sanitize_text_field($_POST[$key]));
    }
    wp_send_json_success();
});

// ─────────────────────────────────────────────────────────────────────────────
// DELETE A CUSTOMER — purge from the portal + database, keep Odoo untouched
// ─────────────────────────────────────────────────────────────────────────────
add_action( 'wp_ajax_six_delete_client', function() {
    check_ajax_referer( 'six_nonce', 'nonce' );
    if ( ! Six_Roles::is_advisor() && ! current_user_can('manage_options') ) {
        wp_send_json_error( array( 'message' => 'Permission denied.' ) );
    }
    $client_id = intval( $_POST['client_id'] ?? 0 );
    if ( ! $client_id )                          wp_send_json_error( array( 'message' => 'No customer specified.' ) );
    if ( $client_id === get_current_user_id() )  wp_send_json_error( array( 'message' => 'You cannot delete your own account.' ) );

    $user = get_userdata( $client_id );
    if ( ! $user )                               wp_send_json_error( array( 'message' => 'Customer not found (already deleted?).' ) );

    // Safety: only customer accounts may be deleted here — never an advisor,
    // sales user or administrator.
    if ( class_exists( 'Six_Roles' ) && ! Six_Roles::is_customer( $client_id ) ) {
        wp_send_json_error( array( 'message' => 'Only customer accounts can be deleted from here.' ) );
    }
    // Confirmation must match the account name (defence in depth alongside the UI).
    $confirm  = trim( sanitize_text_field( wp_unslash( $_POST['confirm_name'] ?? '' ) ) );
    if ( $confirm === '' || strcasecmp( $confirm, trim( $user->display_name ) ) !== 0 ) {
        wp_send_json_error( array( 'message' => 'Confirmation name did not match.' ) );
    }

    global $wpdb;
    $p = $wpdb->prefix;

    // AI chat messages hang off the client's threads — delete them first.
    $wpdb->query( $wpdb->prepare(
        "DELETE FROM {$p}six_ai_messages WHERE thread_id IN ( SELECT id FROM {$p}six_ai_threads WHERE client_id = %d )",
        $client_id
    ) );
    // Tables keyed directly by the client. six_ai_playbooks is agency-wide and is
    // intentionally NOT touched.
    $by_client = array(
        'six_ai_threads', 'six_ai_outcomes', 'six_appointments', 'six_assignments',
        'six_client_services', 'six_client_kpis', 'six_metrics', 'six_recommendations',
        'six_reports',
    );
    foreach ( $by_client as $t ) {
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$p}{$t} WHERE client_id = %d", $client_id ) );
    }
    // Tables keyed by user_id.
    foreach ( array( 'six_checkout_progress', 'six_notifications' ) as $t ) {
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$p}{$t} WHERE user_id = %d", $client_id ) );
    }
    // Direct messages in either direction.
    $wpdb->query( $wpdb->prepare(
        "DELETE FROM {$p}six_messages WHERE sender_id = %d OR receiver_id = %d", $client_id, $client_id
    ) );

    // Finally remove the WordPress user itself — this also drops ALL of their
    // usermeta (data-source IDs, budget requests, onboarding meta, etc.).
    require_once ABSPATH . 'wp-admin/includes/user.php';
    $deleted = wp_delete_user( $client_id );
    if ( ! $deleted ) {
        wp_send_json_error( array( 'message' => 'Portal data was removed, but the user account could not be fully deleted. Contact an administrator.' ) );
    }

    wp_send_json_success( array(
        'message'  => 'Customer removed from the portal. Their Odoo record was kept.',
        'redirect' => home_url( '/advisor-portal/?tab=clients' ),
    ) );
} );

// ─────────────────────────────────────────────────────────────────────────────
// SYNC CLIENT TO ODOO ON DEMAND
// ─────────────────────────────────────────────────────────────────────────────
add_action( 'wp_ajax_six_sync_odoo_client', function() {
    check_ajax_referer( 'six_nonce', 'nonce' );
    if ( ! Six_Roles::is_advisor() && ! current_user_can('manage_options') ) {
        wp_send_json_error('Permission denied.');
    }
    $client_id = intval($_POST['client_id']??0);
    if(!$client_id) wp_send_json_error('No client.');
    if(class_exists('Six_Odoo')) {
        $result = Six_Odoo::create_or_update_contact($client_id);
        wp_send_json_success(array('partner_id'=>$result));
    }
    wp_send_json_error('Odoo not configured.');
});

// ─────────────────────────────────────────────────────────────────────────────
// ADVISOR EDIT RECOMMENDATION
// ─────────────────────────────────────────────────────────────────────────────
add_action( 'wp_ajax_six_adv_edit_rec', function() {
    check_ajax_referer( 'six_nonce', 'nonce' );
    if ( ! Six_Roles::is_advisor() && ! current_user_can('manage_options') ) {
        wp_send_json_error('Permission denied.');
    }
    global $wpdb;
    $rec_id = intval($_POST['rec_id']??0);
    if(!$rec_id) wp_send_json_error('No rec.');
    $wpdb->update("{$wpdb->prefix}six_recommendations", array(
        'title'       => sanitize_text_field($_POST['title']??''),
        'description' => sanitize_textarea_field($_POST['description']??''),
    ), array('id'=>$rec_id));
    wp_send_json_success();
});

// ═════════════════════════════════════════════════════════════════════════════
// INTERNAL PRODUCT HUB — Shared issue tracker
//
// Storage: WordPress site option 'six_hub_issues' (JSON array).
// This means every team member hitting the page reads the SAME data from the DB.
//
// Nonce:   'six_hub_nonce' — generated fresh per page load in internal-product-hub.php
//          Falls back to 'six_nonce' for backward compatibility.
//
// Access:  Advisors, Sales reps, and anyone with edit_posts capability.
//          All write operations are sanitized before storage.
//
// Actions: six_hub_get_issues    — read all issues
//          six_hub_save_issues   — write full issues array (append/update in JS)
//          six_hub_delete_issue  — delete single issue by ID
//          six_hub_update_status — update status of single issue by ID
// ═════════════════════════════════════════════════════════════════════════════

function six_hub_check_access() {
    // Any logged-in WordPress user may read/write the hub.
    // The page itself is already protected by WordPress login.
    return is_user_logged_in();
}

// ── Helper: verify hub nonce (accepts both hub-specific and legacy nonce) ──
function six_hub_verify_nonce() {
    $nonce = sanitize_text_field( $_POST['nonce'] ?? '' );
    // Accept the hub-specific nonce (generated by internal-process-page.php)
    if ( wp_verify_nonce( $nonce, 'six_hub_nonce' ) ) return true;
    // Also accept the main portal nonce for backward compatibility
    if ( wp_verify_nonce( $nonce, 'six_nonce' ) ) return true;
    // If both fail, return a clear JSON error (not WordPress's die())
    wp_send_json_error( array(
        'message' => 'Session expired. Please refresh the page and try again.',
        'code'    => 'invalid_nonce',
    ), 403 );
}

// ── Helper: sanitize one issue from POST data ──────────────────────────────
function six_hub_sanitize_issue( $issue ) {
    return array(
        'id'       => intval( $issue['id'] ?? time() ),
        'type'     => in_array( $issue['type']??'', array('bug','suggestion') ) ? $issue['type'] : 'bug',
        'status'   => in_array( $issue['status']??'', array('open','in-progress','fixed') ) ? $issue['status'] : 'open',
        'title'    => sanitize_text_field( $issue['title']    ?? '' ),
        'section'  => sanitize_text_field( $issue['section']  ?? '' ),
        'desc'     => sanitize_textarea_field( $issue['desc'] ?? '' ),
        'steps'    => sanitize_textarea_field( $issue['steps'] ?? '' ),
        'severity' => in_array( $issue['severity']??'', array('low','medium','high') ) ? $issue['severity'] : 'medium',
        'reporter' => sanitize_text_field( $issue['reporter'] ?? '' ),
        'created'  => sanitize_text_field( $issue['created']  ?? '' ),
    );
}

// ── READ all issues ────────────────────────────────────────────────────────
add_action( 'wp_ajax_six_hub_get_issues', function() {
    six_hub_verify_nonce();
    if ( ! six_hub_check_access() ) wp_send_json_error( 'Please log in to view issues.', 403 );
    $raw     = get_option( 'six_hub_issues', '[]' );
    $decoded = json_decode( $raw, true );
    wp_send_json_success( is_array($decoded) ? $decoded : array() );
});
// Not-logged-in users get a clear message
add_action( 'wp_ajax_nopriv_six_hub_get_issues', function() {
    wp_send_json_error( array('message'=>'Please log in to view issues.', 'code'=>'not_logged_in'), 403 );
});

// ── WRITE full issues array ────────────────────────────────────────────────
add_action( 'wp_ajax_six_hub_save_issues', function() {
    six_hub_verify_nonce();
    if ( ! six_hub_check_access() ) wp_send_json_error( 'Please log in to save issues.', 403 );

    $raw    = stripslashes( $_POST['issues'] ?? '[]' );
    $issues = json_decode( $raw, true );
    if ( ! is_array( $issues ) ) wp_send_json_error( 'Invalid data format.' );

    $clean = array_map( 'six_hub_sanitize_issue', $issues );
    update_option( 'six_hub_issues', wp_json_encode( $clean ), false );
    wp_send_json_success( array( 'count' => count($clean), 'saved' => true ) );
});
add_action( 'wp_ajax_nopriv_six_hub_save_issues', function() {
    wp_send_json_error( array('message'=>'Please log in to save issues.', 'code'=>'not_logged_in'), 403 );
});

// ── DELETE single issue by ID ──────────────────────────────────────────────
add_action( 'wp_ajax_six_hub_delete_issue', function() {
    six_hub_verify_nonce();
    if ( ! six_hub_check_access() ) wp_send_json_error( 'Access denied.', 403 );

    $id      = intval( $_POST['issue_id'] ?? 0 );
    $raw     = get_option( 'six_hub_issues', '[]' );
    $issues  = json_decode( $raw, true );
    if ( ! is_array($issues) ) wp_send_json_error('No issues.');

    $filtered = array_values( array_filter( $issues, fn($i) => intval($i['id'] ?? 0) !== $id ) );
    update_option( 'six_hub_issues', wp_json_encode($filtered), false );
    wp_send_json_success( array( 'deleted' => $id, 'remaining' => count($filtered) ) );
});

// ── UPDATE status of single issue ─────────────────────────────────────────
add_action( 'wp_ajax_six_hub_update_status', function() {
    six_hub_verify_nonce();
    if ( ! six_hub_check_access() ) wp_send_json_error( 'Access denied.', 403 );

    $id     = intval( $_POST['issue_id'] ?? 0 );
    $status = sanitize_key( $_POST['status'] ?? '' );
    if ( ! in_array( $status, array('open','in-progress','fixed'), true ) ) wp_send_json_error('Invalid status.');

    $raw    = get_option( 'six_hub_issues', '[]' );
    $issues = json_decode( $raw, true );
    if ( ! is_array($issues) ) wp_send_json_error('No issues.');

    $updated = false;
    foreach ( $issues as &$issue ) {
        if ( intval($issue['id'] ?? 0) === $id ) {
            $issue['status'] = $status;
            $updated = true;
            break;
        }
    }
    unset($issue);

    if ( ! $updated ) wp_send_json_error('Issue not found.');
    update_option( 'six_hub_issues', wp_json_encode($issues), false );
    wp_send_json_success( array( 'id' => $id, 'status' => $status ) );
});

// =============================================================================
// KPI MANAGEMENT — advisor-editable fields, customer dashboard
// Table: six_client_kpis (client_id, kpi_key, kpi_value, kpi_prev, updated_at)
// =============================================================================

// ── One-time table creation (?six_kpi_setup=1) ────────────────────────────
add_action('admin_init', function() {
    if ( ! current_user_can('manage_options') || empty($_GET['six_kpi_setup']) ) return;
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}six_client_kpis (
        id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        client_id   BIGINT UNSIGNED NOT NULL,
        kpi_key     VARCHAR(60) NOT NULL,
        kpi_value   VARCHAR(255) NOT NULL DEFAULT '',
        kpi_prev    VARCHAR(255) NOT NULL DEFAULT '',
        updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY client_kpi (client_id, kpi_key),
        KEY idx_client (client_id)
    ) {$charset};";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
    echo '<p style="font-family:monospace;padding:20px;color:#56D364">six_client_kpis table ready.</p>';
    exit;
});

// ── Advisor saves KPI values ──────────────────────────────────────────────
add_action('wp_ajax_six_save_client_kpi', function() {
    check_ajax_referer('six_nonce','nonce');
    if ( ! Six_Roles::is_advisor() && ! current_user_can('manage_options') ) {
        wp_send_json_error('Permission denied'); return;
    }
    global $wpdb;
    $client_id = intval($_POST['client_id'] ?? 0);
    $kpi_key   = sanitize_key($_POST['kpi_key'] ?? '');
    $kpi_value = sanitize_text_field($_POST['kpi_value'] ?? '');

    $allowed_keys = array('new_customers','sales_revenue','total_visitors','roi_projection','roi_growth_pct');
    if ( ! $client_id || ! in_array($kpi_key, $allowed_keys, true) ) {
        wp_send_json_error('Invalid data'); return;
    }

    // Fetch current value to store as prev
    $current = $wpdb->get_var($wpdb->prepare(
        "SELECT kpi_value FROM {$wpdb->prefix}six_client_kpis WHERE client_id=%d AND kpi_key=%s",
        $client_id, $kpi_key
    ));

    $wpdb->replace("{$wpdb->prefix}six_client_kpis", array(
        'client_id'  => $client_id,
        'kpi_key'    => $kpi_key,
        'kpi_value'  => $kpi_value,
        'kpi_prev'   => $current ?: '',
        'updated_at' => current_time('mysql'),
    ));

    // Log to Odoo if lead exists
    if ( class_exists('Six_Odoo') ) {
        $lead_id = intval(get_user_meta($client_id,'six_odoo_lead_id',true));
        if ($lead_id) {
            $labels = array(
                'new_customers'  => 'New Customers',
                'sales_revenue'  => 'Sales Revenue',
                'total_visitors' => 'Total Visitors',
                'roi_projection' => 'ROI Projection',
                'roi_growth_pct' => 'Growth %',
            );
            Six_Odoo::post_note($lead_id,
                "Advisor updated KPI: " . ($labels[$kpi_key] ?? $kpi_key) . "\n" .
                "Previous: " . ($current ?: 'none') . "\n" .
                "New value: {$kpi_value}\n" .
                "Updated at: " . current_time('mysql')
            );
        }
    }

    wp_send_json_success(array('saved' => true, 'prev' => $current ?: ''));
});

// ── Get all KPIs for a client (advisor dashboard) ─────────────────────────
add_action('wp_ajax_six_get_client_kpis', function() {
    check_ajax_referer('six_nonce','nonce');
    if ( ! Six_Roles::is_advisor() && ! current_user_can('manage_options') ) {
        wp_send_json_error('Permission denied'); return;
    }
    global $wpdb;
    $client_id = intval($_POST['client_id'] ?? 0);
    if (!$client_id) { wp_send_json_error('Missing client_id'); return; }
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT kpi_key, kpi_value, kpi_prev, updated_at FROM {$wpdb->prefix}six_client_kpis WHERE client_id=%d",
        $client_id
    ));
    $data = array();
    foreach ($rows as $r) $data[$r->kpi_key] = array('value'=>$r->kpi_value,'prev'=>$r->kpi_prev,'updated'=>$r->updated_at);
    wp_send_json_success($data);
});

// =============================================================================
// ADVISOR: COMPLETE ONBOARDING FOR CUSTOMER
// =============================================================================
add_action('wp_ajax_six_advisor_complete_onboarding', 'six_advisor_complete_onboarding');

function six_advisor_complete_onboarding() {
    check_ajax_referer('six_nonce', 'nonce');
    // Previous check (!manage_options && !class_exists) was always false —
    // it never denied anyone.
    if ( ! Six_Roles::is_advisor() && ! current_user_can('manage_options') ) {
        wp_send_json_error('Permission denied'); return;
    }

    $client_id     = intval( $_POST['client_id']      ?? 0 );
    $services      = sanitize_text_field( $_POST['services']        ?? '' );
    $budget        = intval( $_POST['budget']          ?? 0 );
    $payment_note  = sanitize_text_field( $_POST['payment_method']  ?? '' );
    $notes         = sanitize_textarea_field( $_POST['notes']       ?? '' );
    $send_login    = ! empty( $_POST['send_login'] ) && $_POST['send_login'] !== '0';

    if ( ! $client_id ) { wp_send_json_error('No client ID'); return; }
    $client = get_userdata( $client_id );
    if ( ! $client ) { wp_send_json_error('Client account not found'); return; }

    global $wpdb;

    // 1. Update checkout_progress + completion flags (mirror the customer flow)
    $wpdb->update(
        $wpdb->prefix . 'six_checkout_progress',
        array(
            'platforms'   => $services,
            'mktg_budget' => $budget,
            'step'        => 5,
            'score'       => 100,
            'completed'   => 1,
            'updated_at'  => current_time('mysql'),
        ),
        array('user_id' => $client_id)
    );
    update_user_meta($client_id, 'six_checkout_completed', 1);
    update_user_meta($client_id, 'six_checkout_step', 5);
    update_user_meta($client_id, 'six_checkout_score', 100);

    // 2. Create the selected service records so they appear in both dashboards.
    $svc_names = array(
        'google-ads'      => 'Google Ads',
        'seo'             => 'SEO',
        'social-media'    => 'Social Media Marketing',
        'brand-dev'       => 'Brand Development',
        'website'         => 'Website Development',
        'google-business' => 'Google Business Profile',
    );
    $svc_slugs = array_filter( array_map( 'trim', explode( ',', $services ) ) );
    $per_svc   = count($svc_slugs) ? intval( round( $budget / count($svc_slugs) ) ) : 0;
    foreach ( $svc_slugs as $slug ) {
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}six_client_services WHERE client_id=%d AND service_slug=%s",
            $client_id, $slug ) );
        if ( $exists ) {
            // Activate an existing (pending) row.
            $wpdb->update( "{$wpdb->prefix}six_client_services",
                array( 'status' => 'active', 'budget' => $per_svc ),
                array( 'id' => $exists ) );
        } else {
            $wpdb->insert( "{$wpdb->prefix}six_client_services", array(
                'client_id'    => $client_id,
                'service_slug' => $slug,
                'service_name' => $svc_names[$slug] ?? ucwords( str_replace('-',' ',$slug) ),
                'status'       => 'active',
                'budget'       => $per_svc,
            ) );
        }
    }

    // 3. Generate fresh login credentials and email them to the customer.
    //    Call requesters often never set a password, so we set a new one and
    //    send it, guaranteeing they can log in.
    $new_password = '';
    if ( $send_login ) {
        $new_password = wp_generate_password( 12, false );
        wp_set_password( $new_password, $client_id ); // note: also clears sessions
        $login_url = home_url( '/portal/' );
        $svc_list  = array();
        foreach ( $svc_slugs as $slug ) $svc_list[] = $svc_names[$slug] ?? $slug;
        wp_mail(
            $client->user_email,
            'Your 6ix Developers account is ready',
            '<div style="font-family:Helvetica,Arial,sans-serif;max-width:560px;margin:0 auto;color:#1a1a2e">'
            . '<h2 style="color:#0f1428">Welcome to 6ix Developers</h2>'
            . '<p>Hi ' . esc_html( $client->first_name ?: $client->display_name ) . ',</p>'
            . '<p>Your account has been set up and your onboarding is complete. You can now log in to your client dashboard to track your growth, message your advisor, and manage your services.</p>'
            . '<table style="font-size:14px;line-height:1.9;margin:10px 0;background:#f6f8fc;border-radius:8px;padding:6px 14px">'
            . '<tr><td style="color:#666;padding-right:14px">Email</td><td><strong>' . esc_html( $client->user_email ) . '</strong></td></tr>'
            . '<tr><td style="color:#666;padding-right:14px">Password</td><td><strong>' . esc_html( $new_password ) . '</strong></td></tr>'
            . '</table>'
            . ( $svc_list ? '<p style="font-size:13px;color:#555">Services: ' . esc_html( implode( ', ', $svc_list ) ) . '</p>' : '' )
            . '<p style="margin:18px 0"><a href="' . esc_url( $login_url ) . '" style="display:inline-block;background:#FF6699;color:#fff;text-decoration:none;font-weight:700;padding:12px 22px;border-radius:8px">Log in to your dashboard</a></p>'
            . '<p style="font-size:12px;color:#888">For your security, change your password after your first login from Profile settings.</p>'
            . '<p style="font-size:12px;color:#999">— 6ix Developers</p></div>',
            array( 'Content-Type: text/html; charset=UTF-8' )
        );
        delete_user_meta( $client_id, 'six_temp_password' );
    }

    // 4. Move Odoo lead to Onboarding Submitted (out of Call Requested).
    if ( class_exists('Six_Odoo') ) {
        Six_Odoo::on_onboarding_completed(
            $client_id,
            $services,
            $budget,
            $payment_note && $payment_note !== '0'
        );

        $lead_id = intval(get_user_meta($client_id, 'six_odoo_lead_id', true));
        if ($lead_id) {
            $advisor  = get_userdata(get_current_user_id());
            $adv_name = $advisor ? $advisor->display_name : 'Advisor';
            Six_Odoo::post_note(
                $lead_id,
                "Onboarding completed by advisor ({$adv_name}).\n"
                . ( $payment_note ? "Payment: {$payment_note}\n" : '' )
                . ( $send_login ? "Login credentials emailed to customer.\n" : '' )
                . ( $notes ? "Notes: {$notes}" : '' )
            );
        }
    }

    // 5. Notify the customer in-portal too.
    if ( class_exists('Six_Notifications') ) {
        Six_Notifications::create( array(
            'user_id'    => $client_id,
            'type'       => 'onboarding_completed',
            'title'      => 'Your account is ready',
            'message'    => 'Your onboarding is complete and your services are active. Welcome aboard!',
            'action_url' => home_url('/portal/'),
        ) );
    }

    error_log("6ix Advisor: completed onboarding for client={$client_id} by advisor=" . get_current_user_id() . " login_sent=" . ($send_login?'yes':'no'));
    wp_send_json_success(array(
        'message'      => 'Onboarding completed.' . ( $send_login ? ' Login details emailed to the customer.' : '' ),
        'login_sent'   => $send_login,
        'password'     => $send_login ? $new_password : '', // shown to advisor as a fallback
        'client_email' => $client->user_email,
    ));
}


// ── Update service budget (advisor) ─────────────────────────────────────
add_action('wp_ajax_six_adv_update_service_budget','six_adv_update_service_budget');
function six_adv_update_service_budget(){
    check_ajax_referer('six_nonce','nonce');
    // Allow advisors and admins
    $is_allowed = ( class_exists('Six_Roles') && Six_Roles::is_advisor() )
                  || current_user_can('manage_options')
                  || current_user_can('six_advisor');
    if ( ! $is_allowed ) wp_send_json_error('Permission denied — not an advisor');
    global $wpdb;
    $client_id  = intval($_POST['client_id']??0);
    $service_id = intval($_POST['service_id']??0);
    $budget     = intval($_POST['budget']??0);
    if(!$client_id||!$service_id) wp_send_json_error('Invalid data');
    $wpdb->update("{$wpdb->prefix}six_client_services",array('budget'=>$budget),array('id'=>$service_id,'client_id'=>$client_id));
    // Update total budget in checkout
    $total = $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(budget),0) FROM {$wpdb->prefix}six_client_services WHERE client_id=%d AND status='active'",$client_id));
    $wpdb->update("{$wpdb->prefix}six_checkout_progress",array('mktg_budget'=>intval($total)),array('user_id'=>$client_id));
    wp_send_json_success();
}

// End of six-portal AJAX handlers

// ── Default advisor ──────────────────────────────────────────────────────
add_action('wp_ajax_six_get_default_advisor',        'six_get_default_advisor');
add_action('wp_ajax_nopriv_six_get_default_advisor', 'six_get_default_advisor');
function six_get_default_advisor(){
    $email   = get_option('six_default_advisor_email','musab@6ixdevelopers.com');
    $advisor = get_user_by('email',$email);
    if(!$advisor) $advisor = get_users(array('role'=>'six_advisor','number'=>1))[0]??null;
    if(!$advisor) wp_send_json_error('No advisor');
    wp_send_json_success(array(
        'first_name'  => $advisor->first_name,
        'last_name'   => $advisor->last_name,
        'email'       => $advisor->user_email,
        'title'       => get_user_meta($advisor->ID,'six_title',true)?:'Account Manager',
        'avatar_url'  => get_avatar_url($advisor->ID,array('size'=>80)),
        'specialties' => get_user_meta($advisor->ID,'six_specialties',true)?:'',
    ));
}
