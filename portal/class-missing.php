<?php
/**
 * 6ix Portal — class-missing.php
 * Path: /portal/class-missing.php
 *
 * Contains: Six_Roles, Six_Notifications, Six_Messaging,
 *           Six_Checkout, Six_Health_Score, portal shortcodes
 *
 * NOTE: Six_Notifications, Six_Messaging, Six_Checkout were previously
 * duplicated inside ajax-handlers.php — they now live ONLY here.
 * The ajax-handlers.php file has been cleaned to remove those duplicates.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// ─────────────────────────────────────────────────────────────────────────────
// Six_Roles
// ─────────────────────────────────────────────────────────────────────────────
if ( ! class_exists( 'Six_Roles' ) ) :
class Six_Roles {

    public static function get_portal_role( $user_id = null ) {
        $user = $user_id ? get_userdata( $user_id ) : wp_get_current_user();
        if ( ! $user || ! $user->ID ) return 'guest';
        $priority = array( 'administrator', 'six_advisor', 'six_sales', 'six_customer' );
        foreach ( $priority as $role ) {
            if ( in_array( $role, (array) $user->roles, true ) ) return $role;
        }
        return 'guest';
    }

    public static function is_advisor( $user_id = null ) {
        return in_array( self::get_portal_role( $user_id ), array( 'six_advisor', 'administrator' ), true );
    }

    public static function is_customer( $user_id = null ) {
        return self::get_portal_role( $user_id ) === 'six_customer';
    }

    public static function is_sales( $user_id = null ) {
        return in_array( self::get_portal_role( $user_id ), array( 'six_sales', 'administrator' ), true );
    }
}
endif;

// ─────────────────────────────────────────────────────────────────────────────
// Six_Notifications
// ─────────────────────────────────────────────────────────────────────────────
if ( ! class_exists( 'Six_Notifications' ) ) :
class Six_Notifications {

    public static function create( $args ) {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'six_notifications',
            array(
                'user_id'    => intval( $args['user_id'] ),
                'type'       => sanitize_text_field( $args['type']       ?? '' ),
                'title'      => sanitize_text_field( $args['title']      ?? '' ),
                'message'    => sanitize_textarea_field( $args['message'] ?? '' ),
                'action_url' => esc_url_raw( $args['action_url']         ?? '' ),
                'is_read'    => 0,
            )
        );

        // Email for high-priority types
        $email_types = array( 'service_request', 'budget_change', 'meeting_booked', 'service_approved' );
        if ( in_array( $args['type'] ?? '', $email_types, true ) ) {
            self::send_email( $args['user_id'], $args['title'] ?? '', $args['message'] ?? '' );
        }

        return $wpdb->insert_id;
    }

    public static function get_for_user( $user_id, $limit = 20 ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}six_notifications
             WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
            $user_id, $limit
        ) );
    }

    public static function get_unread_count( $user_id ) {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}six_notifications
             WHERE user_id = %d AND is_read = 0",
            $user_id
        ) );
    }

    public static function mark_read( $notification_id, $user_id ) {
        global $wpdb;
        return $wpdb->update(
            $wpdb->prefix . 'six_notifications',
            array( 'is_read' => 1 ),
            array( 'id' => intval( $notification_id ), 'user_id' => intval( $user_id ) )
        );
    }

    public static function mark_all_read( $user_id ) {
        global $wpdb;
        return $wpdb->update(
            $wpdb->prefix . 'six_notifications',
            array( 'is_read' => 1 ),
            array( 'user_id' => intval( $user_id ) )
        );
    }

    private static function send_email( $user_id, $title, $message ) {
        $user = get_userdata( $user_id );
        if ( ! $user ) return;
        wp_mail(
            $user->user_email,
            '[6ix Developers] ' . $title,
            $message,
            array( 'Content-Type: text/plain; charset=UTF-8' )
        );
    }
}
endif;

// ─────────────────────────────────────────────────────────────────────────────
// Six_Messaging
// ─────────────────────────────────────────────────────────────────────────────
if ( ! class_exists( 'Six_Messaging' ) ) :
class Six_Messaging {

    public static function send( $sender_id, $receiver_id, $message ) {
        global $wpdb;

        $inserted = $wpdb->insert(
            $wpdb->prefix . 'six_messages',
            array(
                'sender_id'   => intval( $sender_id ),
                'receiver_id' => intval( $receiver_id ),
                'message'     => sanitize_textarea_field( $message ),
                'is_read'     => 0,
            )
        );

        if ( $inserted ) {
            $sender = get_userdata( $sender_id );
            Six_Notifications::create( array(
                'user_id' => $receiver_id,
                'type'    => 'new_message',
                'title'   => 'New Message from ' . $sender->display_name,
                'message' => wp_trim_words( $message, 15 ),
            ) );
            return $wpdb->insert_id;
        }
        return false;
    }

    public static function get_conversation( $user_a, $user_b ) {
        global $wpdb;
        $msgs = $wpdb->get_results( $wpdb->prepare(
            "SELECT m.*, u.display_name AS sender_name
             FROM {$wpdb->prefix}six_messages m
             LEFT JOIN {$wpdb->prefix}users u ON m.sender_id = u.ID
             WHERE ( m.sender_id = %d AND m.receiver_id = %d )
                OR ( m.sender_id = %d AND m.receiver_id = %d )
             ORDER BY m.created_at ASC",
            $user_a, $user_b, $user_b, $user_a
        ) );

        // Mark as read
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$wpdb->prefix}six_messages
             SET is_read = 1
             WHERE receiver_id = %d AND sender_id = %d AND is_read = 0",
            $user_a, $user_b
        ) );

        return $msgs;
    }

    public static function get_unread_count( $user_id ) {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}six_messages
             WHERE receiver_id = %d AND is_read = 0",
            $user_id
        ) );
    }
}
endif;

// ─────────────────────────────────────────────────────────────────────────────
// Six_Checkout
// ─────────────────────────────────────────────────────────────────────────────
if ( ! class_exists( 'Six_Checkout' ) ) :
class Six_Checkout {

    public static function save_step( $user_id, $step, $data = array() ) {
        global $wpdb;
        $table  = $wpdb->prefix . 'six_checkout_progress';
        $update = array( 'updated_at' => current_time( 'mysql' ) );

        switch ( $step ) {
            case 1:
                $update['step'] = 'account_created';
                break;
            case 2:
                $update['step']            = 'business_info';
                $update['business_name']   = sanitize_text_field( $data['business_name']   ?? '' );
                $update['industry']        = sanitize_text_field( $data['industry']        ?? '' );
                $update['monthly_revenue'] = sanitize_text_field( $data['monthly_revenue'] ?? '' );
                break;
            case 3:
                $update['step']              = 'services_selected';
                $update['services_selected'] = wp_json_encode(
                    array_map( 'sanitize_text_field', (array) ( $data['services'] ?? array() ) )
                );
                break;
            case 4:
                $update['step']   = 'budget_confirmed';
                $update['budget'] = floatval( $data['budget'] ?? 0 );
                break;
            case 6:
                $update['step']            = 'contract_signed';
                $update['contract_signed'] = 1;
                break;
            case 7:
                $update['step']      = 'card_saved';
                $update['card_saved'] = 1;
                break;
        }

        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$table} WHERE user_id = %d", $user_id
        ) );

        if ( $exists ) {
            $wpdb->update( $table, $update, array( 'user_id' => $user_id ) );
        } else {
            $update['user_id'] = $user_id;
            $wpdb->insert( $table, $update );
        }

        return six_calculate_checkout_score( $user_id );
    }

    public static function get_progress( $user_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}six_checkout_progress WHERE user_id = %d",
            $user_id
        ) );
    }
}
endif;

// ─────────────────────────────────────────────────────────────────────────────
// Six_Health_Score
// ─────────────────────────────────────────────────────────────────────────────
if ( ! class_exists( 'Six_Health_Score' ) ) :
class Six_Health_Score {

    public static function calculate( $client_id ) {
        global $wpdb;
        $score = 0;

        // Active services (30 pts)
        $active = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}six_client_services
             WHERE client_id = %d AND status = 'active'", $client_id
        ) );
        $score += min( 30, $active * 15 );

        // Recent metrics (25 pts)
        $recent_metrics = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}six_metrics
             WHERE client_id = %d AND updated_at > DATE_SUB(NOW(), INTERVAL 7 DAY)",
            $client_id
        ) );
        if ( $recent_metrics > 0 ) $score += 25;

        // Message activity (20 pts)
        $msgs = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}six_messages
             WHERE sender_id = %d AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)",
            $client_id
        ) );
        $score += min( 20, $msgs * 5 );

        // Profile completeness (15 pts)
        $checkout = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}six_checkout_progress WHERE user_id = %d", $client_id
        ) );
        if ( $checkout ) {
            if ( $checkout->business_name ) $score += 5;
            if ( $checkout->industry )      $score += 5;
            if ( $checkout->budget )        $score += 5;
        }

        // Recent login (10 pts)
        $last = get_user_meta( $client_id, 'six_last_activity', true );
        if ( $last && ( time() - strtotime( $last ) ) < 7 * DAY_IN_SECONDS ) {
            $score += 10;
        }

        $final = min( 100, $score );
        update_user_meta( $client_id, 'six_health_score', $final );
        return $final;
    }

    public static function get_label( $score ) {
        if ( $score >= 75 ) return 'Healthy';
        if ( $score >= 50 ) return 'Moderate';
        return 'At Risk';
    }

    public static function get_color( $score ) {
        if ( $score >= 75 ) return '#56D364';
        if ( $score >= 50 ) return '#E3B341';
        return '#FF6B6B';
    }
}
endif;

// ─────────────────────────────────────────────────────────────────────────────
// Shortcodes
// ─────────────────────────────────────────────────────────────────────────────

// [six_portal_link text="Go to Portal"] — smart redirect based on role
add_shortcode( 'six_portal_link', function( $atts ) {
    $atts = shortcode_atts( array( 'text' => 'Go to Portal' ), $atts );
    if ( ! is_user_logged_in() ) {
        return '<a href="' . esc_url( wp_login_url( home_url( '/portal/' ) ) ) . '">' . esc_html( $atts['text'] ) . '</a>';
    }
    $role = Six_Roles::get_portal_role();
    $map  = array(
        'six_advisor'   => '/advisor-portal/',
        'six_sales'     => '/sales-portal/',
        'administrator' => '/advisor-portal/',
    );
    return '<a href="' . esc_url( home_url( $map[ $role ] ?? '/portal/' ) ) . '">' . esc_html( $atts['text'] ) . '</a>';
} );

// [six_login_form] — simple login redirect shortcode
add_shortcode( 'six_login_form', function() {
    if ( is_user_logged_in() ) {
        $role = Six_Roles::get_portal_role();
        $map  = array( 'six_advisor' => '/advisor-portal/', 'six_sales' => '/sales-portal/', 'administrator' => '/advisor-portal/' );
        $url  = home_url( $map[ $role ] ?? '/portal/' );
        return '<p>You are logged in. <a href="' . esc_url( $url ) . '">Go to your portal →</a></p>';
    }
    return wp_login_form( array( 'echo' => false ) );
} );
