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

// ─────────────────────────────────────────────────────────────────────────────
// PROFILE SAVE
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'wp_ajax_six_save_profile', function() {
    check_ajax_referer( 'six_nonce', 'nonce' );
    $user_id   = get_current_user_id();
    $user_data = array( 'ID' => $user_id );
    if ( ! empty( $_POST['first_name'] ) ) $user_data['first_name'] = sanitize_text_field( $_POST['first_name'] );
    if ( ! empty( $_POST['last_name'] ) )  $user_data['last_name']  = sanitize_text_field( $_POST['last_name'] );
    if ( count( $user_data ) > 1 ) {
        $user_data['display_name'] = trim( ( $user_data['first_name'] ?? '' ) . ' ' . ( $user_data['last_name'] ?? '' ) );
        wp_update_user( $user_data );
    }
    if ( isset( $_POST['phone'] ) ) update_user_meta( $user_id, 'billing_phone', sanitize_text_field( $_POST['phone'] ) );
    if ( isset( $_POST['business_name'] ) ) {
        Six_Checkout::save_step( $user_id, 2, array(
            'business_name'   => sanitize_text_field( $_POST['business_name']   ?? '' ),
            'industry'        => sanitize_text_field( $_POST['industry']        ?? '' ),
            'monthly_revenue' => sanitize_text_field( $_POST['monthly_revenue'] ?? '' ),
        ) );
    }
    wp_send_json_success( array( 'message' => 'Profile saved.' ) );
} );

// ─────────────────────────────────────────────────────────────────────────────
// CHECKOUT STEP
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'wp_ajax_six_save_checkout_step', function() {
    check_ajax_referer( 'six_nonce', 'nonce' );
    $user_id = get_current_user_id();
    $step    = intval( $_POST['step'] ?? 0 );
    $data    = is_array( $_POST['data'] ?? null ) ? $_POST['data'] : array();
    if ( ! $step ) wp_send_json_error( 'Invalid step' );
    $score = Six_Checkout::save_step( $user_id, $step, $data );
    wp_send_json_success( array( 'score' => $score, 'step' => $step ) );
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
    $advisor_id = $wpdb->get_var( $wpdb->prepare(
        "SELECT advisor_id FROM {$wpdb->prefix}six_assignments WHERE client_id=%d", $client_id
    ) );
    if ( $advisor_id ) {
        $client = get_userdata( $client_id );
        Six_Notifications::create( array(
            'user_id' => $advisor_id, 'type' => 'service_request',
            'title' => 'New Service Request',
            'message' => $client->display_name . ' requested ' . ( $names[ $service ] ?? $service ),
        ) );
    }
    wp_send_json_success( array( 'status' => 'pending' ) );
} );

add_action( 'wp_ajax_six_approve_service', function() {
    check_ajax_referer( 'six_nonce', 'nonce' );
    if ( ! Six_Roles::is_advisor() ) wp_send_json_error( 'Permission denied' );
    global $wpdb;
    $service_id = intval( $_POST['service_id'] ?? 0 );
    $wpdb->update( $wpdb->prefix . 'six_client_services', array(
        'status' => 'active', 'approved_at' => current_time( 'mysql' ), 'approved_by' => get_current_user_id(),
    ), array( 'id' => $service_id ) );
    $s = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}six_client_services WHERE id=%d", $service_id ) );
    if ( $s ) Six_Notifications::create( array(
        'user_id' => $s->client_id, 'type' => 'service_approved',
        'title' => $s->service_name . ' — Now Active!',
        'message' => 'Your ' . $s->service_name . ' campaign is now live.',
    ) );
    wp_send_json_success( array( 'status' => 'active' ) );
} );

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
    $advisor_id = $wpdb->get_var( $wpdb->prepare(
        "SELECT advisor_id FROM {$wpdb->prefix}six_assignments WHERE client_id=%d", $client_id
    ) );
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
    $service   = sanitize_text_field( $_POST['service']   ?? '' );
    $metric_id = intval( $_POST['metric_id']              ?? 0 );
    if ( ! $client_id || ! $label ) wp_send_json_error( 'Missing fields' );

    $data = array(
        'previous_value' => sanitize_text_field( $_POST['previous'] ?? '' ),
        'current_value'  => sanitize_text_field( $_POST['current']  ?? '' ),
        'target_value'   => sanitize_text_field( $_POST['target']   ?? '' ),
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
    global $wpdb;
    $client_id  = get_current_user_id();
    $advisor_id = intval( $wpdb->get_var( $wpdb->prepare(
        "SELECT advisor_id FROM {$wpdb->prefix}six_assignments WHERE client_id=%d", $client_id
    ) ) );
    if ( ! $advisor_id ) wp_send_json_error( 'No advisor assigned' );
    $result = Six_Google_Calendar::book_meeting( array(
        'client_id'  => $client_id, 'advisor_id' => $advisor_id,
        'start'      => sanitize_text_field( $_POST['start']    ?? '' ),
        'duration'   => intval( $_POST['duration']              ?? 30 ),
        'notes'      => sanitize_textarea_field( $_POST['notes'] ?? '' ),
    ) );
    if ( $result ) wp_send_json_success( $result );
    else wp_send_json_error( 'Could not book meeting' );
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

// Advisor: manually trigger sync for one client
add_action( 'wp_ajax_six_sync_client_gads', function() {
    check_ajax_referer( 'six_nonce', 'nonce' );
    if ( ! Six_Roles::is_advisor() ) wp_send_json_error( 'Permission denied' );
    $client_id   = intval( $_POST['client_id'] ?? 0 );
    $customer_id = get_user_meta( $client_id, 'six_gads_customer_id', true );
    if ( ! $customer_id ) wp_send_json_error( 'No Google Ads Customer ID set for this client.' );
    $metrics = Six_Google_Ads::get_campaign_metrics_for_client( $client_id );
    if ( $metrics ) {
        wp_send_json_success( array( 'metrics' => $metrics ) );
    } else {
        wp_send_json_error( Six_Google_Ads::get_last_error() ?: 'Sync failed.' );
    }
} );

// ─────────────────────────────────────────────────────────────────────────────
// STRIPE
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'wp_ajax_six_stripe_setup', function() {
    check_ajax_referer( 'six_nonce', 'nonce' );
    if ( ! class_exists( 'Six_Stripe' ) ) wp_send_json_error( 'Stripe not configured' );
    $secret = Six_Stripe::create_setup_intent( get_current_user_id() );
    if ( $secret ) wp_send_json_success( array( 'client_secret' => $secret ) );
    else wp_send_json_error( 'Could not create setup intent' );
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
