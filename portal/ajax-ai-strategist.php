<?php
/**
 * AJAX handlers for the advisor AI Strategist workspace.
 * All advisor/admin-gated.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function six_ai_is_advisor() {
    return ( class_exists( 'Six_Roles' ) && Six_Roles::is_advisor() )
        || current_user_can( 'manage_options' )
        || current_user_can( 'six_advisor' );
}

// ── Send a message (runs the agent) ─────────────────────────────────────────
add_action( 'wp_ajax_six_ai_send', function () {
    check_ajax_referer( 'six_nonce', 'nonce' );
    if ( ! six_ai_is_advisor() ) wp_send_json_error( 'Permission denied.' );
    if ( ! class_exists( 'Six_AI_Strategist' ) ) wp_send_json_error( 'AI strategist unavailable.' );

    $client_id = intval( $_POST['client_id'] ?? 0 );
    $thread_id = intval( $_POST['thread_id'] ?? 0 );
    $mode      = sanitize_key( $_POST['mode'] ?? 'chat' );
    $message   = wp_kses_post( wp_unslash( $_POST['message'] ?? '' ) );
    if ( ! $client_id ) wp_send_json_error( 'Missing client.' );
    if ( trim( $message ) === '' ) wp_send_json_error( 'Message is empty.' );

    // Give the agent room to run its tool loop.
    @set_time_limit( 180 );

    $res = Six_AI_Strategist::run( $client_id, $thread_id, get_current_user_id(), $message, $mode );
    if ( empty( $res['success'] ) ) wp_send_json_error( $res['error'] ?? 'The strategist failed.' );
    wp_send_json_success( $res );
} );

// ── List threads for a client ───────────────────────────────────────────────
add_action( 'wp_ajax_six_ai_threads', function () {
    check_ajax_referer( 'six_nonce', 'nonce' );
    if ( ! six_ai_is_advisor() ) wp_send_json_error( 'Permission denied.' );
    global $wpdb;
    $client_id = intval( $_POST['client_id'] ?? 0 );
    if ( ! $client_id ) wp_send_json_error( 'Missing client.' );
    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, title, mode, updated_at FROM {$wpdb->prefix}six_ai_threads
         WHERE client_id=%d ORDER BY updated_at DESC LIMIT 40", $client_id ) );
    wp_send_json_success( array( 'threads' => $rows ?: array() ) );
} );

// ── Load a thread's messages ────────────────────────────────────────────────
add_action( 'wp_ajax_six_ai_thread_load', function () {
    check_ajax_referer( 'six_nonce', 'nonce' );
    if ( ! six_ai_is_advisor() ) wp_send_json_error( 'Permission denied.' );
    global $wpdb;
    $thread_id = intval( $_POST['thread_id'] ?? 0 );
    if ( ! $thread_id ) wp_send_json_error( 'Missing thread.' );
    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, role, content, tools_used, rating FROM {$wpdb->prefix}six_ai_messages
         WHERE thread_id=%d ORDER BY id ASC", $thread_id ) );
    wp_send_json_success( array( 'messages' => $rows ?: array() ) );
} );

// ── Rate a message (feedback capture) ───────────────────────────────────────
add_action( 'wp_ajax_six_ai_rate', function () {
    check_ajax_referer( 'six_nonce', 'nonce' );
    if ( ! six_ai_is_advisor() ) wp_send_json_error( 'Permission denied.' );
    global $wpdb;
    $message_id = intval( $_POST['message_id'] ?? 0 );
    $rating     = intval( $_POST['rating'] ?? 0 ); // 1 up, -1 down, 0 clear
    $rating     = max( -1, min( 1, $rating ) );
    $note       = sanitize_text_field( $_POST['note'] ?? '' );
    if ( ! $message_id ) wp_send_json_error( 'Missing message.' );
    $data = array( 'rating' => $rating );
    if ( $note !== '' || $rating === 0 ) $data['rating_note'] = mb_substr( $note, 0, 500 );
    $wpdb->update( "{$wpdb->prefix}six_ai_messages", $data, array( 'id' => $message_id ) );
    wp_send_json_success( array( 'rating' => $rating ) );
} );

// ── Save an assistant message as a reusable playbook ────────────────────────
add_action( 'wp_ajax_six_ai_save_playbook', function () {
    check_ajax_referer( 'six_nonce', 'nonce' );
    if ( ! six_ai_is_advisor() ) wp_send_json_error( 'Permission denied.' );
    if ( ! class_exists( 'Six_AI_Strategist' ) ) { Six_AI_Strategist::maybe_create_tables(); }
    global $wpdb;
    $message_id = intval( $_POST['message_id'] ?? 0 );
    $title      = sanitize_text_field( $_POST['title'] ?? '' );
    if ( ! $message_id ) wp_send_json_error( 'Missing message.' );

    $msg = $wpdb->get_row( $wpdb->prepare(
        "SELECT m.content, t.client_id, t.mode
         FROM {$wpdb->prefix}six_ai_messages m
         JOIN {$wpdb->prefix}six_ai_threads t ON m.thread_id=t.id
         WHERE m.id=%d", $message_id ) );
    if ( ! $msg ) wp_send_json_error( 'Message not found.' );

    $co = $wpdb->get_row( $wpdb->prepare(
        "SELECT industry, platforms, goal FROM {$wpdb->prefix}six_checkout_progress WHERE user_id=%d", intval( $msg->client_id ) ) );
    if ( ! $title ) $title = trim( mb_substr( wp_strip_all_tags( $msg->content ), 0, 70 ) );

    $wpdb->insert( "{$wpdb->prefix}six_ai_playbooks", array(
        'title'       => $title ?: 'Strategy playbook',
        'industry'    => $co->industry ?? '',
        'service'     => $msg->mode,
        'goal_tags'   => $co->goal ?? '',
        'content'     => wp_kses_post( $msg->content ),
        'created_by'  => get_current_user_id(),
        'created_at'  => current_time( 'mysql' ),
    ) );
    wp_send_json_success( array( 'id' => intval( $wpdb->insert_id ), 'message' => 'Saved to the playbook library.' ) );
} );

// ── Push an assistant output to the customer as a recommendation ────────────
add_action( 'wp_ajax_six_ai_push_reco', function () {
    check_ajax_referer( 'six_nonce', 'nonce' );
    if ( ! six_ai_is_advisor() ) wp_send_json_error( 'Permission denied.' );
    global $wpdb;
    $client_id  = intval( $_POST['client_id'] ?? 0 );
    $message_id = intval( $_POST['message_id'] ?? 0 );
    $title      = sanitize_text_field( $_POST['title'] ?? 'Strategy recommendation' );
    if ( ! $client_id || ! $message_id ) wp_send_json_error( 'Missing data.' );

    $row = $wpdb->get_row( $wpdb->prepare(
        "SELECT m.content, t.mode FROM {$wpdb->prefix}six_ai_messages m
         JOIN {$wpdb->prefix}six_ai_threads t ON m.thread_id=t.id WHERE m.id=%d", $message_id ) );
    if ( ! $row ) wp_send_json_error( 'Message not found.' );
    $content = $row->content;

    // Advisor-vetted → source advisor_ai so it surfaces in the customer's
    // "from your advisor" recommendations.
    $wpdb->insert( $wpdb->prefix . 'six_recommendations', array(
        'client_id'    => $client_id,
        'advisor_id'   => get_current_user_id(),
        'title'        => $title,
        'description'  => wp_kses_post( $content ),
        'action_label' => 'View strategy',
        'action_type'  => 'info',
        'source'       => 'advisor_ai',
        'status'       => 'active',
        'created_at'   => current_time( 'mysql' ),
    ) );
    $reco_id = intval( $wpdb->insert_id );
    if ( class_exists( 'Six_Notifications' ) ) {
        Six_Notifications::create( array(
            'user_id' => $client_id, 'type' => 'recommendation',
            'title'   => 'New strategy from your advisor',
            'message' => $title,
            'action_url' => home_url( '/portal/' ),
        ) );
    }
    // Start tracking the outcome: snapshot the client's current metrics as a
    // baseline so we can measure the lift this strategy drives later.
    $outcome_id = 0;
    if ( class_exists( 'Six_AI_Strategist' ) ) {
        $outcome_id = Six_AI_Strategist::start_outcome( array(
            'client_id'         => $client_id,
            'recommendation_id' => $reco_id,
            'service'           => $row->mode,
            'title'             => $title,
        ) );
    }
    wp_send_json_success( array( 'id' => $reco_id, 'outcome_id' => $outcome_id, 'message' => 'Shared with the customer — outcome tracking started.' ) );
} );

// ── Playbook library: list ──────────────────────────────────────────────────
add_action( 'wp_ajax_six_ai_playbooks_list', function () {
    check_ajax_referer( 'six_nonce', 'nonce' );
    if ( ! six_ai_is_advisor() ) wp_send_json_error( 'Permission denied.' );
    if ( class_exists( 'Six_AI_Strategist' ) ) Six_AI_Strategist::maybe_create_tables();
    global $wpdb;
    $rows = $wpdb->get_results(
        "SELECT id, title, industry, service, goal_tags, content, uses, created_at
         FROM {$wpdb->prefix}six_ai_playbooks ORDER BY uses DESC, created_at DESC LIMIT 200" );
    wp_send_json_success( array( 'playbooks' => $rows ?: array() ) );
} );

// ── Playbook library: update ────────────────────────────────────────────────
add_action( 'wp_ajax_six_ai_playbook_update', function () {
    check_ajax_referer( 'six_nonce', 'nonce' );
    if ( ! six_ai_is_advisor() ) wp_send_json_error( 'Permission denied.' );
    global $wpdb;
    $id = intval( $_POST['id'] ?? 0 );
    if ( ! $id ) wp_send_json_error( 'Missing playbook.' );
    $wpdb->update( "{$wpdb->prefix}six_ai_playbooks", array(
        'title'     => sanitize_text_field( $_POST['title'] ?? '' ),
        'industry'  => sanitize_text_field( $_POST['industry'] ?? '' ),
        'service'   => sanitize_text_field( $_POST['service'] ?? '' ),
        'goal_tags' => sanitize_text_field( $_POST['goal_tags'] ?? '' ),
        'content'   => wp_kses_post( wp_unslash( $_POST['content'] ?? '' ) ),
    ), array( 'id' => $id ) );
    wp_send_json_success( array( 'id' => $id ) );
} );

// ── Playbook library: delete ────────────────────────────────────────────────
add_action( 'wp_ajax_six_ai_playbook_delete', function () {
    check_ajax_referer( 'six_nonce', 'nonce' );
    if ( ! six_ai_is_advisor() ) wp_send_json_error( 'Permission denied.' );
    global $wpdb;
    $id = intval( $_POST['id'] ?? 0 );
    if ( ! $id ) wp_send_json_error( 'Missing playbook.' );
    $wpdb->delete( "{$wpdb->prefix}six_ai_playbooks", array( 'id' => $id ) );
    wp_send_json_success( array( 'id' => $id ) );
} );

// ── Outcomes: list ──────────────────────────────────────────────────────────
add_action( 'wp_ajax_six_ai_outcomes_list', function () {
    check_ajax_referer( 'six_nonce', 'nonce' );
    if ( ! six_ai_is_advisor() ) wp_send_json_error( 'Permission denied.' );
    if ( class_exists( 'Six_AI_Strategist' ) ) Six_AI_Strategist::maybe_create_tables();
    global $wpdb;
    $rows = $wpdb->get_results(
        "SELECT o.id, o.client_id, o.industry, o.service, o.title, o.status,
                o.baseline_at, o.result_at, o.lift_json
         FROM {$wpdb->prefix}six_ai_outcomes o ORDER BY o.created_at DESC LIMIT 100" );
    foreach ( (array) $rows as $r ) {
        $u = get_userdata( intval( $r->client_id ) );
        $r->client_name = $u ? $u->display_name : ( 'Client #' . $r->client_id );
    }
    // Global proven-outcomes summary
    $summary = class_exists( 'Six_AI_Strategist' ) ? Six_AI_Strategist::proven_outcomes( '' ) : array();
    wp_send_json_success( array( 'outcomes' => $rows ?: array(), 'summary' => $summary ) );
} );

// ── Outcomes: measure now ───────────────────────────────────────────────────
add_action( 'wp_ajax_six_ai_measure_outcome', function () {
    check_ajax_referer( 'six_nonce', 'nonce' );
    if ( ! six_ai_is_advisor() ) wp_send_json_error( 'Permission denied.' );
    if ( ! class_exists( 'Six_AI_Strategist' ) ) wp_send_json_error( 'Unavailable.' );
    $id = intval( $_POST['outcome_id'] ?? 0 );
    if ( ! $id ) wp_send_json_error( 'Missing outcome.' );
    @set_time_limit( 120 );
    $res = Six_AI_Strategist::measure_outcome( $id );
    if ( ! empty( $res['error'] ) ) wp_send_json_error( $res['error'] );
    wp_send_json_success( $res );
} );
