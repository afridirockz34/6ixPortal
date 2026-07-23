<?php
/**
 * Six_Appointments — unified calls / meetings store.
 *
 * Single source of truth for every scheduled call and call request, whether it
 * originates from onboarding ("Request a call") or the customer dashboard
 * ("Book a meeting"). Each appointment:
 *   1. is persisted in wp_six_appointments (so the advisor dashboard can show it
 *      even when Google Calendar is not connected),
 *   2. gets a Google Calendar event + Google Meet link when the assigned advisor
 *      has connected their calendar,
 *   3. emails BOTH the customer and the advisor (with the Meet link + an .ics
 *      invite when a concrete time exists),
 *   4. notifies the advisor in-portal and syncs to Odoo.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Six_Appointments {

    const DB_VERSION = 1;

    /** Create / migrate the table. Cheap no-op once created. */
    public static function maybe_create_table() {
        if ( (int) get_option( 'six_appointments_db_v', 0 ) >= self::DB_VERSION ) return;
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        dbDelta( "CREATE TABLE {$wpdb->prefix}six_appointments (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            client_id bigint(20) NOT NULL,
            advisor_id bigint(20) DEFAULT 0,
            title varchar(255) DEFAULT '',
            start_datetime datetime NULL,
            time_window varchar(40) DEFAULT '',
            duration int(6) DEFAULT 30,
            notes text,
            source varchar(30) DEFAULT 'booking',
            status varchar(20) DEFAULT 'scheduled',
            meet_link varchar(500) DEFAULT '',
            gcal_event_id varchar(255) DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY client_id (client_id),
            KEY advisor_id (advisor_id),
            KEY start_datetime (start_datetime)
        ) $charset" );
        update_option( 'six_appointments_db_v', self::DB_VERSION );
    }

    /** Onboarding time windows -> a concrete start hour (24h). */
    private static function window_start_hour( $w ) {
        $map = array( '9am-12pm' => 9, '12pm-3pm' => 12, '3pm-6pm' => 15, '6pm-9pm' => 18 );
        return $map[ $w ] ?? 10;
    }

    /** Assigned advisor for a client (creates the assignment if needed). */
    public static function resolve_advisor( $client_id ) {
        global $wpdb;
        $a = intval( $wpdb->get_var( $wpdb->prepare(
            "SELECT advisor_id FROM {$wpdb->prefix}six_assignments WHERE client_id=%d", $client_id ) ) );
        if ( ! $a && class_exists( 'Six_Odoo' ) && method_exists( 'Six_Odoo', 'assign_advisor' ) ) {
            $a = intval( Six_Odoo::assign_advisor( $client_id ) );
        }
        return $a;
    }

    /**
     * Unified entry point. Creates the appointment, calendar event + Meet link,
     * dual emails, advisor notification and Odoo sync.
     *
     * @param array $args client_id (req), advisor_id, start (datetime) OR
     *                     date + time_window, duration, notes, source, title
     * @return array success, id, meet_link, start, status, advisor_id
     */
    public static function create( $args ) {
        self::maybe_create_table();
        global $wpdb;

        $client_id = intval( $args['client_id'] ?? 0 );
        if ( ! $client_id ) return array( 'success' => false, 'error' => 'No client.' );
        $client = get_userdata( $client_id );
        if ( ! $client ) return array( 'success' => false, 'error' => 'No client user.' );

        $advisor_id = intval( $args['advisor_id'] ?? 0 ) ?: self::resolve_advisor( $client_id );
        $advisor    = $advisor_id ? get_userdata( $advisor_id ) : null;

        $duration    = intval( $args['duration'] ?? 30 ) ?: 30;
        $notes       = sanitize_textarea_field( $args['notes'] ?? '' );
        $source      = sanitize_key( $args['source'] ?? 'booking' );
        $time_window = sanitize_text_field( $args['time_window'] ?? '' );

        // Resolve a concrete start time when we can.
        $start_iso = '';
        if ( ! empty( $args['start'] ) ) {
            $ts = strtotime( $args['start'] );
            if ( $ts ) $start_iso = date( 'c', $ts );
        } elseif ( ! empty( $args['date'] ) ) {
            $ts = strtotime( $args['date'] . ' ' . sprintf( '%02d:00:00', self::window_start_hour( $time_window ) ) );
            if ( $ts ) $start_iso = date( 'c', $ts );
        }

        $title  = sanitize_text_field( $args['title'] ?? '' )
            ?: ( '6ix Developers — Strategy Call with ' . $client->display_name );
        $status = $start_iso ? 'scheduled' : 'requested';

        $wpdb->insert( "{$wpdb->prefix}six_appointments", array(
            'client_id'      => $client_id,
            'advisor_id'     => $advisor_id,
            'title'          => $title,
            'start_datetime' => $start_iso ? date( 'Y-m-d H:i:s', strtotime( $start_iso ) ) : null,
            'time_window'    => $time_window,
            'duration'       => $duration,
            'notes'          => $notes,
            'source'         => $source,
            'status'         => $status,
            'created_at'     => current_time( 'mysql' ),
            'updated_at'     => current_time( 'mysql' ),
        ) );
        $appt_id = intval( $wpdb->insert_id );

        // Google Calendar event + Meet link (only when advisor connected + we
        // have a concrete time).
        $meet_link = '';
        $event_id  = '';
        if ( $advisor_id && $start_iso && class_exists( 'Six_Google_Calendar' ) ) {
            $res = Six_Google_Calendar::book_meeting( array(
                'client_id'       => $client_id,
                'advisor_id'      => $advisor_id,
                'start'           => $start_iso,
                'duration'        => $duration,
                'notes'           => $notes ?: $title,
                'title'           => $title,
                'suppress_notify' => true, // this class handles notify + email + Odoo
            ) );
            if ( is_array( $res ) && ! empty( $res['success'] ) ) {
                $meet_link = $res['meet_link'] ?? '';
                $event_id  = $res['event_id']  ?? '';
            }
        }
        if ( $meet_link || $event_id ) {
            $wpdb->update( "{$wpdb->prefix}six_appointments",
                array( 'meet_link' => $meet_link, 'gcal_event_id' => $event_id, 'updated_at' => current_time( 'mysql' ) ),
                array( 'id' => $appt_id ) );
        }
        // Cache the Meet link on the client's checkout row too (handy for the
        // advisor client profile + customer dashboard).
        if ( $meet_link ) {
            update_user_meta( $client_id, 'six_next_call_meet_link', $meet_link );
        }

        // Emails to BOTH parties.
        self::send_emails( $client, $advisor, $start_iso, $time_window, $duration, $notes, $meet_link, $source );

        // In-portal advisor notification.
        if ( $advisor_id && class_exists( 'Six_Notifications' ) ) {
            $when = $start_iso ? date( 'M j, g:i A', strtotime( $start_iso ) ) : ( $time_window ?: 'time TBD' );
            Six_Notifications::create( array(
                'user_id'    => $advisor_id,
                'type'       => 'call_scheduled',
                'title'      => $status === 'scheduled' ? 'New call scheduled' : 'New call request',
                'message'    => $client->display_name . ' — ' . $when . ( $meet_link ? ' (Meet link ready)' : '' ),
                'action_url' => home_url( '/advisor-portal/?tab=calendar' ),
            ) );
        }

        // Odoo sync — onboarding calls move the lead to Call Requested; existing
        // customers just get a scheduled-call activity on their lead.
        if ( class_exists( 'Six_Odoo' ) ) {
            if ( $source === 'onboarding_call' && method_exists( 'Six_Odoo', 'on_call_requested' ) ) {
                Six_Odoo::on_call_requested( $client_id, array(
                    'call_date'  => $start_iso ? date( 'Y-m-d', strtotime( $start_iso ) ) : ( $args['date'] ?? '' ),
                    'call_time'  => $time_window ?: ( $start_iso ? date( 'g:i A', strtotime( $start_iso ) ) : '' ),
                    'call_notes' => trim( $notes . ( $meet_link ? "\nGoogle Meet: {$meet_link}" : '' ) ),
                    'services'   => sanitize_text_field( $args['services'] ?? '' ),
                    'score'      => intval( $args['score'] ?? 0 ),
                    'step'       => 3,
                ) );
            } else {
                $lead_id = intval( get_user_meta( $client_id, 'six_odoo_lead_id', true ) );
                if ( ! $lead_id && method_exists( 'Six_Odoo', 'sync_lead' ) ) {
                    $lead_id = intval( Six_Odoo::sync_lead( array( 'user_id' => $client_id ) ) );
                }
                if ( $lead_id && method_exists( 'Six_Odoo', 'create_activity' ) ) {
                    $when = $start_iso ? date( 'M j, Y g:i A', strtotime( $start_iso ) ) : ( $time_window ?: 'TBD' );
                    Six_Odoo::create_activity(
                        $lead_id,
                        'Strategy call scheduled — ' . $when,
                        "Client: {$client->display_name}\nWhen: {$when}\n"
                            . ( $meet_link ? "Google Meet: {$meet_link}\n" : '' )
                            . ( $notes ? "Notes: {$notes}\n" : '' ),
                        'Call',
                        $start_iso ? max( 0, (int) ceil( ( strtotime( $start_iso ) - time() ) / DAY_IN_SECONDS ) ) : 0,
                        method_exists( 'Six_Odoo', 'get_advisor_odoo_uid_public' ) ? Six_Odoo::get_advisor_odoo_uid_public( $client_id ) : 0
                    );
                }
            }
        }

        return array(
            'success'    => true,
            'id'         => $appt_id,
            'meet_link'  => $meet_link,
            'start'      => $start_iso,
            'status'     => $status,
            'advisor_id' => $advisor_id,
        );
    }

    /** Upcoming scheduled calls + pending requests for an advisor. */
    public static function get_upcoming_for_advisor( $advisor_id, $days = 30 ) {
        self::maybe_create_table();
        global $wpdb;
        $max = date( 'Y-m-d H:i:s', strtotime( "+{$days} days" ) );
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}six_appointments
             WHERE advisor_id=%d AND status NOT IN ('cancelled','completed')
               AND ( ( start_datetime IS NOT NULL AND start_datetime >= %s AND start_datetime <= %s )
                     OR start_datetime IS NULL )
             ORDER BY (start_datetime IS NULL) ASC, start_datetime ASC
             LIMIT 50",
            $advisor_id, date( 'Y-m-d 00:00:00' ), $max
        ) );
    }

    /** Upcoming appointments for a customer. */
    public static function get_upcoming_for_client( $client_id, $days = 60 ) {
        self::maybe_create_table();
        global $wpdb;
        $max = date( 'Y-m-d H:i:s', strtotime( "+{$days} days" ) );
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}six_appointments
             WHERE client_id=%d AND status NOT IN ('cancelled','completed')
               AND ( ( start_datetime IS NOT NULL AND start_datetime >= %s AND start_datetime <= %s )
                     OR start_datetime IS NULL )
             ORDER BY (start_datetime IS NULL) ASC, start_datetime ASC
             LIMIT 20",
            $client_id, date( 'Y-m-d 00:00:00' ), $max
        ) );
    }

    /** Count of upcoming scheduled + requested for an advisor (overview stat). */
    public static function count_upcoming_for_advisor( $advisor_id, $days = 30 ) {
        return count( self::get_upcoming_for_advisor( $advisor_id, $days ) );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // EMAILS
    // ─────────────────────────────────────────────────────────────────────────

    private static function send_emails( $client, $advisor, $start_iso, $time_window, $duration, $notes, $meet_link, $source ) {
        $when_h = $start_iso
            ? date( 'l, F j, Y \a\t g:i A', strtotime( $start_iso ) )
            : ( $time_window ? 'your requested window (' . $time_window . ')' : 'a time your advisor will confirm' );

        $ics_path = ( $start_iso ) ? self::build_ics( $client, $advisor, $start_iso, $duration, $meet_link, $notes ) : '';
        $attach   = $ics_path ? array( $ics_path ) : array();
        $headers  = array( 'Content-Type: text/html; charset=UTF-8' );

        $meet_row = $meet_link
            ? '<p style="margin:16px 0"><a href="' . esc_url( $meet_link ) . '" style="display:inline-block;background:#FF6699;color:#fff;text-decoration:none;font-weight:700;padding:12px 22px;border-radius:8px">Join Google Meet</a></p>'
              . '<p style="font-size:13px;color:#555">Or open this link at the meeting time:<br><a href="' . esc_url( $meet_link ) . '">' . esc_html( $meet_link ) . '</a></p>'
            : '<p style="font-size:13px;color:#555">Your advisor will send the Google Meet link before the call.</p>';

        $adv_name = $advisor ? $advisor->display_name : 'Your 6ix Developers advisor';

        // ── Customer email ──
        $cust_body =
            '<div style="font-family:Helvetica,Arial,sans-serif;max-width:560px;margin:0 auto;color:#1a1a2e">'
            . '<h2 style="color:#0f1428">Your call is booked</h2>'
            . '<p>Hi ' . esc_html( $client->first_name ?: $client->display_name ) . ',</p>'
            . '<p>Thanks for scheduling a call with 6ix Developers. Here are the details:</p>'
            . '<table style="font-size:14px;line-height:1.9;margin:8px 0">'
            . '<tr><td style="color:#666;padding-right:14px">When</td><td><strong>' . esc_html( $when_h ) . '</strong></td></tr>'
            . '<tr><td style="color:#666;padding-right:14px">Advisor</td><td>' . esc_html( $adv_name ) . '</td></tr>'
            . '<tr><td style="color:#666;padding-right:14px">Duration</td><td>' . intval( $duration ) . ' minutes</td></tr>'
            . ( $notes ? '<tr><td style="color:#666;padding-right:14px;vertical-align:top">Notes</td><td>' . esc_html( $notes ) . '</td></tr>' : '' )
            . '</table>'
            . $meet_row
            . '<p style="font-size:13px;color:#555;margin-top:20px">Need to reschedule? Reply to this email or message your advisor in the portal.</p>'
            . '<p style="font-size:12px;color:#999">— 6ix Developers</p></div>';
        wp_mail( $client->user_email, 'Your call with 6ix Developers — ' . ( $start_iso ? date( 'M j', strtotime( $start_iso ) ) : 'confirmation' ), $cust_body, $headers, $attach );

        // ── Advisor email ──
        if ( $advisor && $advisor->user_email ) {
            $phone = get_user_meta( $client->ID, 'billing_phone', true );
            $adv_body =
                '<div style="font-family:Helvetica,Arial,sans-serif;max-width:560px;margin:0 auto;color:#1a1a2e">'
                . '<h2 style="color:#0f1428">' . ( $start_iso ? 'New call scheduled' : 'New call request' ) . '</h2>'
                . '<p>Hi ' . esc_html( $advisor->first_name ?: $advisor->display_name ) . ',</p>'
                . '<p><strong>' . esc_html( $client->display_name ) . '</strong> ' . ( $source === 'onboarding_call' ? 'requested a consultation call during onboarding.' : 'booked a call with you.' ) . '</p>'
                . '<table style="font-size:14px;line-height:1.9;margin:8px 0">'
                . '<tr><td style="color:#666;padding-right:14px">When</td><td><strong>' . esc_html( $when_h ) . '</strong></td></tr>'
                . '<tr><td style="color:#666;padding-right:14px">Client</td><td>' . esc_html( $client->display_name ) . ' &lt;' . esc_html( $client->user_email ) . '&gt;</td></tr>'
                . ( $phone ? '<tr><td style="color:#666;padding-right:14px">Phone</td><td>' . esc_html( $phone ) . '</td></tr>' : '' )
                . ( $notes ? '<tr><td style="color:#666;padding-right:14px;vertical-align:top">Notes</td><td>' . esc_html( $notes ) . '</td></tr>' : '' )
                . '</table>'
                . $meet_row
                . '<p style="margin-top:16px"><a href="' . esc_url( home_url( '/advisor-portal/?tab=clients&client=' . $client->ID ) ) . '" style="color:#83C5ED">Open client profile →</a></p>'
                . '<p style="font-size:12px;color:#999">— 6ix Developers Portal</p></div>';
            wp_mail( $advisor->user_email, ( $start_iso ? 'Call scheduled: ' : 'Call request: ' ) . $client->display_name, $adv_body, $headers, $attach );
        }

        if ( $ics_path && file_exists( $ics_path ) ) @unlink( $ics_path );
    }

    /** Build a temporary .ics invite; returns the file path (or '' on failure). */
    private static function build_ics( $client, $advisor, $start_iso, $duration, $meet_link, $notes ) {
        $start = strtotime( $start_iso );
        if ( ! $start ) return '';
        $end   = $start + ( intval( $duration ) * 60 );
        $dt    = function ( $ts ) { return gmdate( 'Ymd\THis\Z', $ts ); };
        $uid   = 'six-' . md5( $client->ID . $start_iso . wp_rand() ) . '@6ixdevelopers.com';
        $desc  = trim( ( $notes ? $notes . '\\n' : '' ) . ( $meet_link ? 'Google Meet: ' . $meet_link : '' ) );
        $adv_email = $advisor ? $advisor->user_email : get_option( 'admin_email' );

        $lines = array(
            'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//6ix Developers//Portal//EN',
            'METHOD:REQUEST', 'BEGIN:VEVENT',
            'UID:' . $uid,
            'DTSTAMP:' . $dt( time() ),
            'DTSTART:' . $dt( $start ),
            'DTEND:' . $dt( $end ),
            'SUMMARY:6ix Developers — Strategy Call',
            'DESCRIPTION:' . str_replace( array( "\r\n", "\n" ), '\\n', $desc ),
            $meet_link ? 'LOCATION:' . $meet_link : 'LOCATION:Google Meet',
            'ORGANIZER;CN=6ix Developers:mailto:' . $adv_email,
            'ATTENDEE;CN=' . $client->display_name . ';RSVP=TRUE:mailto:' . $client->user_email,
            'STATUS:CONFIRMED', 'END:VEVENT', 'END:VCALENDAR',
        );
        $content = implode( "\r\n", array_filter( $lines ) );

        $path = trailingslashit( get_temp_dir() ) . 'six-invite-' . $client->ID . '-' . $start . '.ics';
        if ( false === @file_put_contents( $path, $content ) ) return '';
        return $path;
    }
}

// Ensure the table exists on load (cheap once created).
add_action( 'init', array( 'Six_Appointments', 'maybe_create_table' ), 20 );
